<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';

$mode = $_GET['mode'] ?? 'login';
$error = '';
$success = '';
$mailEnabled = conf('mail_enabled') === '1';
$domainRestrict = conf('email_domain_restrict_enabled') === '1';
$domainWhitelist = trim(conf('email_domain_whitelist', ''));
$inviteCode = trim($_GET['invite'] ?? $_SESSION['invite_code'] ?? '');
$oauthError = trim($_GET['oauth_error'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $account = trim($_POST['account'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');
        $remember = isset($_POST['remember']) ? 1 : 0;

        if (!Captcha::checkImage($captcha)) {
            $error = L('login_error_captcha');
        } elseif (empty($account) || empty($password)) {
            $error = L('login_error_empty');
        } else {
            $isEmail = filter_var($account, FILTER_VALIDATE_EMAIL) !== false;
            if ($isEmail) {
                $stmt = $DB->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$account]);
            } else {
                $stmt = $DB->prepare("SELECT * FROM users WHERE nickname = ?");
                $stmt->execute([$account]);
            }
            $user = $stmt->fetch();

            if (!$user) {
                $error = L('login_error_invalid');
            } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remainMin = ceil((strtotime($user['locked_until']) - time()) / 60);
                $error = L('login_error_locked') . $remainMin . L('login_error_locked_suffix');
            } elseif (password_verify($password, $user['password'])) {
                $stmtReset = $DB->prepare("UPDATE users SET login_attempts=0, locked_until=NULL, last_login_time=?, last_login_ip=? WHERE id=?");
                $stmtReset->execute([date('Y-m-d H:i:s'), getClientIp(), $user['id']]);
                Captcha::clearImage();
                $_SESSION['user_id'] = $user['id'];
                if ($remember) setRememberToken($user['id']);
                redirect('personalpanel.php');
            } else {
                $newAttempts = ($user['login_attempts'] ?? 0) + 1;
                if ($newAttempts >= 5) {
                    $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $stmtLock = $DB->prepare("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?");
                    $stmtLock->execute([5, $lockUntil, $user['id']]);
                    $error = L('login_error_too_many');
                } else {
                    $stmtFail = $DB->prepare("UPDATE users SET login_attempts=? WHERE id=?");
                    $stmtFail->execute([$newAttempts, $user['id']]);
                    $remain = 5 - $newAttempts;
                    $error = L('login_error_attempts') . $remain . L('login_error_attempts_suffix');
                }
            }
        }
        Captcha::clearImage();
    }

    if ($action === 'register') {
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');
        $emailCode = trim($_POST['email_code'] ?? '');
        $inviteCode = trim($_POST['invite_code'] ?? '');

        $domainInvalid = false;
        if ($domainRestrict && !empty($domainWhitelist)) {
            $allowed = array_map('trim', explode(',', $domainWhitelist));
            $domainInvalid = true;
            foreach ($allowed as $suffix) {
                if (substr($email, -strlen($suffix)) === $suffix) {
                    $domainInvalid = false;
                    break;
                }
            }
        }

        if (strlen($password) < 6) {
            $error = L('register_error_password_short');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = L('register_error_email_invalid');
        } elseif ($domainInvalid) {
            $error = L('register_error_domain_restrict') . h($domainWhitelist);
        } elseif (!empty($nickname) && (mb_strlen($nickname) < 2 || mb_strlen($nickname) > 20)) {
            $error = L('register_error_nickname_length');
        } elseif (!empty($nickname) && !preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $nickname)) {
            $error = L('register_error_nickname_chars');
        } elseif (!Captcha::checkImage($captcha)) {
            $error = L('login_error_captcha');
        } else {
            if ($mailEnabled) {
                if (empty($emailCode)) { $error = L('register_error_email_code'); }
                elseif (!Captcha::verify($email, $emailCode, 'register')) { $error = L('register_error_email_code_expired'); }
            }
            if (empty($error)) {
                $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) { $error = L('register_error_email_exists'); }
                elseif (!empty($nickname)) {
                    $stmtU = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
                    $stmtU->execute([$nickname]);
                    if ($stmtU->fetch()) { $error = L('register_error_nickname_exists'); }
                }
                if (empty($error)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $finalNickname = !empty($nickname) ? $nickname : ('USER' . strtoupper(substr(md5($email . time()), 0, 6)));
                    $regPoints = intval(conf('register_points', '0'));
                    $regEnabled = conf('register_points_enabled', '0') === '1';

                    $newInviteCode = 'INV' . strtoupper(substr(md5($email . time()), 0, 6));

                    $referrerId = null;
                    $referralReward = 0;
                    if (!empty($inviteCode)) {
                        $stmtRef = $DB->prepare("SELECT id FROM users WHERE invite_code = ?");
                        $stmtRef->execute([$inviteCode]);
                        $referrer = $stmtRef->fetch();
                        if ($referrer) {
                            $referrerId = $referrer['id'];
                            if (conf('referral_enabled', '1') === '1') {
                                $referralReward = intval(conf('referral_reward_points', '30'));
                            }
                        }
                    }

                    $initialPoints = ($regEnabled ? $regPoints : 0) + $referralReward;

                    $stmt2 = $DB->prepare("INSERT INTO users(email,nickname,password,points,invite_code,invited_by) VALUES(?,?,?,?,?,?)");
                    $stmt2->execute([$email, $finalNickname, $hashed, $initialPoints, $newInviteCode, $referrerId]);
                    $newUserId = $DB->lastInsertId();

                    if ($referrerId && $referralReward > 0) {
                        $stmtUpdate = $DB->prepare("UPDATE users SET points = points + ?, referral_count = referral_count + 1 WHERE id = ?");
                        $stmtUpdate->execute([$referralReward, $referrerId]);
                        $stmtLog = $DB->prepare("INSERT INTO referral_logs(referrer_id, referred_id, reward_points) VALUES(?, ?, ?)");
                        $stmtLog->execute([$referrerId, $newUserId, $referralReward]);
                    }

                    $_SESSION['user_id'] = $newUserId;
                    Captcha::clearImage();

                    if (!empty($inviteCode)) { $_SESSION['invite_code'] = $inviteCode; }

                    redirect('personalpanel.php');
                }
            }
        }
        Captcha::clearImage();
    }

    if ($action === 'send_email_code') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonExit(400, L('register_error_email_invalid')); }
        if ($domainRestrict && !empty($domainWhitelist)) {
            $allowed = array_map('trim', explode(',', $domainWhitelist));
            $matched = false;
            foreach ($allowed as $suffix) {
                if (substr($email, -strlen($suffix)) === $suffix) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) { jsonExit(400, L('register_error_domain_restrict') . $domainWhitelist); }
        }
        if (!$mailEnabled) { jsonExit(400, L('register_error_email_not_enabled')); }
        $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { jsonExit(400, L('register_error_email_exists')); }
        if (!Captcha::canSend($email, 'register', 60)) { jsonExit(400, L('register_error_send_frequent')); }
        $code = Captcha::generate($email, 'register', 300);
        $result = Mailer::send($email, L('email_code_subject') . conf('site_name', '云主机'), '【' . $code . '】' . L('email_code_body'));
        if ($result) { jsonExit(200, L('register_code_sent')); }
        else { jsonExit(500, L('register_error_email_code_send_fail')); }
    }

    if ($action === 'check_nickname') {
        $nickname = trim($_POST['nickname'] ?? '');
        header('Content-Type: application/json');
        if (mb_strlen($nickname) < 2 || mb_strlen($nickname) > 20) { echo json_encode(['valid'=>false,'message'=>L('register_nickname_invalid_length')]); exit; }
        if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $nickname)) { echo json_encode(['valid'=>false,'message'=>L('register_nickname_invalid_chars')]); exit; }
        $stmt = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
        $stmt->execute([$nickname]);
        if ($stmt->fetch()) { echo json_encode(['valid'=>false,'message'=>L('register_nickname_taken')]); exit; }
        echo json_encode(['valid'=>true,'message'=>L('register_nickname_available')]); exit;
    }
}

renderHeader(L('login_title') . ' / ' . L('register_title'), '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">');
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-tabs">
            <a href="?mode=login" class="auth-tab <?php echo $mode==='login'?'active':''; ?>"><?php echo L('login_tab_login'); ?></a>
            <a href="?mode=register" class="auth-tab <?php echo $mode==='register'?'active':''; ?>"><?php echo L('login_tab_register'); ?></a>
        </div>

        <?php if ($error): ?>
        <div class="msg msg-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="msg msg-success"><?php echo h($success); ?></div>
        <?php endif; ?>
        <?php if ($oauthError): ?>
        <div class="msg msg-error"><?php echo h($oauthError); ?></div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
        <form method="post" class="auth-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label><?php echo L('login_email_or_nickname'); ?></label>
                <input type="text" name="account" placeholder="<?php echo L('login_account_placeholder'); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label><?php echo L('login_password'); ?></label>
                <input type="password" name="password" placeholder="<?php echo L('login_password_placeholder'); ?>" required>
            </div>
            <div class="form-group captcha-group">
                <label><?php echo L('login_captcha'); ?></label>
                <div class="captcha-row">
                    <input type="text" name="captcha" placeholder="<?php echo L('login_captcha_placeholder'); ?>" maxlength="5" required>
                    <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="<?php echo L('login_captcha_alt'); ?>" title="<?php echo L('login_captcha_refresh'); ?>">
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;font-size:.9rem;color:#666">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="checkbox" name="remember" value="1"> <?php echo L('login_remember_me'); ?>
                </label>
                <a href="forgot.php" style="color:#6c5ce7;text-decoration:none"><?php echo L('login_forgot'); ?></a>
            </div>
            <button type="submit" class="btn-primary" style="width:100%"><?php echo L('login_btn'); ?></button>
        </form>
        <p class="auth-switch"><?php echo L('login_switch_register'); ?><a href="?mode=register"><?php echo L('login_register_link'); ?></a></p>

        <?php else: ?>
        <form method="post" class="auth-form" id="registerForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label><?php echo L('register_email_label'); ?> <span style="color:#e74c3c">*</span></label>
                <input type="email" name="email" id="reg_email" placeholder="<?php echo L('register_email_placeholder'); ?>" required>
                <?php if ($domainRestrict && !empty($domainWhitelist)): ?>
                <small style="color:#888;display:block;margin-top:4px;"><?php echo L('register_email_domain_hint'); ?><?php echo h($domainWhitelist); ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label><?php echo L('register_nickname_label'); ?> <span style="color:#999;font-weight:normal">(<?php echo L('register_nickname_optional'); ?>)</span></label>
                <div style="position:relative">
                    <input type="text" name="nickname" id="reg_nickname" placeholder="<?php echo L('register_nickname_placeholder'); ?>" onblur="checkNickname()" autocomplete="off">
                    <span id="nicknameCheckResult" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.82rem"></span>
                </div>
            </div>
            <?php if (!empty($inviteCode)): ?>
            <div class="form-group" style="background:#e8f5e9;padding:12px;border-radius:8px;margin-bottom:16px">
                <label style="color:#2e7d32"><i class="fas fa-gift"></i> <?php echo L('register_invite_title'); ?></label>
                <div style="font-size:1.1rem;font-weight:600;color:#2e7d32"><?php echo h($inviteCode); ?></div>
                <small style="color:#666"><?php echo L('register_invite_hint'); ?></small>
            </div>
            <input type="hidden" name="invite_code" value="<?php echo h($inviteCode); ?>">
            <?php endif; ?>
            <?php if ($mailEnabled): ?>
            <div class="form-group">
                <label><?php echo L('register_email_code_label'); ?></label>
                <div class="code-row">
                    <input type="text" name="email_code" placeholder="<?php echo L('register_email_code_placeholder'); ?>" maxlength="5">
                    <button type="button" class="btn-send-code" id="sendCodeBtn" onclick="sendEmailCode()"><?php echo L('register_send_code'); ?></button>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label><?php echo L('register_password_label'); ?></label>
                <input type="password" name="password" placeholder="<?php echo L('register_password_placeholder'); ?>" required>
            </div>
            <?php if (empty($inviteCode)): ?>
            <div class="form-group">
                <label><?php echo L('register_invite_code_label'); ?> <span style="color:#999;font-weight:normal">(<?php echo L('optional'); ?>)</span></label>
                <input type="text" name="invite_code" id="invite_code" placeholder="<?php echo L('register_invite_code_placeholder'); ?>" style="text-transform:uppercase">
            </div>
            <?php endif; ?>
            <div class="form-group captcha-group">
                <label><?php echo L('register_human_captcha'); ?></label>
                <div class="captcha-row">
                    <input type="text" name="captcha" placeholder="<?php echo L('login_captcha_placeholder'); ?>" maxlength="5" required>
                    <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="<?php echo L('login_captcha_alt'); ?>" title="<?php echo L('login_captcha_refresh'); ?>">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width:100%"><?php echo L('register_btn_text'); ?></button>
        </form>
        <p class="auth-switch"><?php echo L('register_switch_login'); ?><a href="?mode=login"><?php echo L('register_switch_login_link'); ?></a></p>
        <?php endif; ?>

        <?php
        $oauthEnabled = conf('oauth_enabled') === '1';
        if ($oauthEnabled) {
            require_once ROOT . 'oauth.php';
            $enabledTypes = getEnabledOauthTypes();
            if (!empty($enabledTypes)):
        ?>
        <div class="oauth-login" style="margin-top:24px;padding-top:20px;border-top:1px solid #eee">
            <p style="text-align:center;color:#888;font-size:.85rem;margin-bottom:14px"><?php echo L('login_oauth_title'); ?></p>
            <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center">
                <?php foreach ($enabledTypes as $typeKey => $typeInfo): 
                    $bgStyle = oauthNeedBackground($typeKey, $typeInfo) ? 'background:' . h($typeInfo['color']) . ';color:#fff;' : 'background:none;color:#333;';
                ?>
                <a href="oauth.php?act=login&type=<?php echo h($typeKey); ?>"
                   style="display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;<?php echo $bgStyle; ?>text-decoration:none;transition:all .2s;box-shadow:0 2px 8px rgba(0,0,0,0.15)"
                   onmouseover="this.style.transform='scale(1.1)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'"
                   onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.15)'"
                   title="<?php echo h($typeInfo['name']); ?>">
                    <?php echo renderOauthIconByKey($typeKey, $typeInfo); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
            endif;
        }
        ?>
    </div>
</div>

<?php if ($mailEnabled): ?>
<script>
var _L = Object.assign(_L || {}, {
    codeNeedEmail: <?php echo json_encode(L('register_code_need_email')); ?>,
    codeSending: <?php echo json_encode(L('register_code_sending')); ?>,
    codeSent: <?php echo json_encode(L('register_code_sent')); ?>,
    codeRetry: <?php echo json_encode(L('register_code_retry')); ?>,
    codeRequestFail: <?php echo json_encode(L('register_code_request_fail')); ?>,
    nicknameChecking: <?php echo json_encode(L('register_nickname_checking')); ?>
});
function sendEmailCode() {
    var email = document.getElementById('reg_email').value;
    var btn = document.getElementById('sendCodeBtn');
    if (!email) { alert(_L.codeNeedEmail); return; }
    btn.disabled = true;
    btn.textContent = _L.codeSending;
    var fd = new FormData();
    fd.append('action', 'send_email_code');
    fd.append('email', email);
    fetch('login.php', {method:'POST', body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.code===200){alert(d.message);var c=60;btn.textContent=c+'s';var t=setInterval(function(){c--;btn.textContent=c+'s';if(c<=0){clearInterval(t);btn.disabled=false;btn.textContent=_L.codeRetry;}},1000);}
        else{alert(d.message);btn.disabled=false;btn.textContent=_L.codeRetry;}
    })
    .catch(function(){alert(_L.codeRequestFail);btn.disabled=false;btn.textContent=_L.codeRetry;});
}
</script>
<?php endif; ?>

<script>
function checkNickname() {
    var val = document.getElementById('reg_nickname').value.trim();
    var el = document.getElementById('nicknameCheckResult');
    if (!val) { el.innerHTML = ''; return; }
    el.style.color = '#999'; el.innerHTML = <?php echo json_encode(L('register_nickname_checking')); ?>;
    var fd = new FormData();
    fd.append('action', 'check_nickname');
    fd.append('nickname', val);
    fetch('login.php', {method:'POST', body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.valid){el.style.color='#10b981';el.innerHTML='<i class="fas fa-check"></i>';}
        else{el.style.color='#e74c3c';el.innerHTML=d.message||'<?php echo addslashes(L('register_nickname_invalid_chars')); ?>';}
    })
    .catch(function(){el.style.color='#999';el.innerHTML='';});
}
</script>
<?php renderFooter(); ?>