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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $account = trim($_POST['account'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = trim($_POST['captcha'] ?? '');
        $remember = isset($_POST['remember']) ? 1 : 0;

        if (!Captcha::checkImage($captcha)) {
            $error = '人机验证码错误';
        } elseif (empty($account) || empty($password)) {
            $error = '请输入账号和密码';
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
                $error = '账号或密码错误';
            } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remainMin = ceil((strtotime($user['locked_until']) - time()) / 60);
                $error = "账号已锁定，请 {$remainMin} 分钟后再试";
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
                    $error = '登录失败次数过多，账号已锁定15分钟';
                } else {
                    $stmtFail = $DB->prepare("UPDATE users SET login_attempts=? WHERE id=?");
                    $stmtFail->execute([$newAttempts, $user['id']]);
                    $remain = 5 - $newAttempts;
                    $error = "账号或密码错误（剩余 {$remain} 次机会）";
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

        // 提前检查邮箱后缀是否在允许列表中
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
            $error = '密码至少6位';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif ($domainInvalid) {
            $error = '仅允许指定邮箱后缀注册：' . h($domainWhitelist);
        } elseif (!empty($nickname) && (strlen($nickname) < 2 || strlen($nickname) > 20)) {
            $error = '昵称2-20位';
        } elseif (!empty($nickname) && !preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $nickname)) {
            $error = '昵称只能包含中文、字母、数字、下划线';
        } elseif (!Captcha::checkImage($captcha)) {
            $error = '人机验证码错误';
        } else {
            if ($mailEnabled) {
                if (empty($emailCode)) { $error = '请输入邮箱验证码'; }
                elseif (!Captcha::verify($email, $emailCode, 'register')) { $error = '邮箱验证码错误或已过期'; }
            }
            if (empty($error)) {
                $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) { $error = '该邮箱已注册'; }
                elseif (!empty($nickname)) {
                    $stmtU = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
                    $stmtU->execute([$nickname]);
                    if ($stmtU->fetch()) { $error = '该昵称已被使用'; }
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
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonExit(400, '邮箱格式不正确'); }
        if ($domainRestrict && !empty($domainWhitelist)) {
            $allowed = array_map('trim', explode(',', $domainWhitelist));
            $matched = false;
            foreach ($allowed as $suffix) {
                if (substr($email, -strlen($suffix)) === $suffix) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) { jsonExit(400, '仅允许指定邮箱后缀注册：' . $domainWhitelist); }
        }
        if (!$mailEnabled) { jsonExit(400, '邮箱验证未启用'); }
        $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { jsonExit(400, '该邮箱已注册'); }
        if (!Captcha::canSend($email, 'register', 60)) { jsonExit(400, '发送太频繁，请60秒后再试'); }
        $code = Captcha::generate($email, 'register', 300);
        $result = Mailer::send($email, '验证码 - ' . conf('site_name', '云主机'), '【' . $code . '】5分钟内有效，请勿泄露给他人。');
        if ($result) { jsonExit(200, '验证码已发送'); }
        else { jsonExit(500, '邮件发送失败，请检查邮箱配置'); }
    }

    if ($action === 'check_nickname') {
        $nickname = trim($_POST['nickname'] ?? '');
        header('Content-Type: application/json');
        if (strlen($nickname) < 2 || strlen($nickname) > 20) { echo json_encode(['valid'=>false,'message'=>'昵称2-20位']); exit; }
        if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $nickname)) { echo json_encode(['valid'=>false,'message'=>'只能中文/字母/数字/下划线']); exit; }
        $stmt = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
        $stmt->execute([$nickname]);
        if ($stmt->fetch()) { echo json_encode(['valid'=>false,'message'=>'该昵称已被占用']); exit; }
        echo json_encode(['valid'=>true,'message'=>'昵称可用']); exit;
    }
}

renderHeader('登录 / 注册');
?>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-tabs">
            <a href="?mode=login" class="auth-tab <?php echo $mode==='login'?'active':''; ?>">登录</a>
            <a href="?mode=register" class="auth-tab <?php echo $mode==='register'?'active':''; ?>">注册</a>
        </div>

        <?php if ($error): ?>
        <div class="msg msg-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="msg msg-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <?php if ($mode === 'login'): ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>邮箱 / 昵称</label>
                <input type="text" name="account" placeholder="输入邮箱或昵称" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="请输入密码" required>
            </div>
            <div class="form-group captcha-group">
                <label>验证码</label>
                <div class="captcha-row">
                    <input type="text" name="captcha" placeholder="请输入" maxlength="5" required>
                    <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="验证码" title="点击刷新">
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;font-size:.9rem;color:#666">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="checkbox" name="remember" value="1"> 记住我（7天）
                </label>
                <a href="forgot.php" style="color:#6c5ce7;text-decoration:none">忘记密码？</a>
            </div>
            <button type="submit" class="btn-primary" style="width:100%">登 录</button>
        </form>
        <p class="auth-switch">还没有账号？<a href="?mode=register">立即注册</a></p>

        <?php else: ?>
        <form method="post" class="auth-form" id="registerForm">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label>邮箱 <span style="color:#e74c3c">*</span></label>
                <input type="email" name="email" id="reg_email" placeholder="请输入邮箱" required>
                <?php if ($domainRestrict && !empty($domainWhitelist)): ?>
                <small style="color:#888;display:block;margin-top:4px;">仅支持以下邮箱：<?php echo h($domainWhitelist); ?></small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>昵称 <span style="color:#999;font-weight:normal">(选填，可用于登录)</span></label>
                <div style="position:relative">
                    <input type="text" name="nickname" id="reg_nickname" placeholder="2-20位，支持中文、字母、数字、下划线" onblur="checkNickname()" autocomplete="off">
                    <span id="nicknameCheckResult" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.82rem"></span>
                </div>
            </div>
            <?php if (!empty($inviteCode)): ?>
            <div class="form-group" style="background:#e8f5e9;padding:12px;border-radius:8px;margin-bottom:16px">
                <label style="color:#2e7d32"><i class="fas fa-gift"></i> 推荐码</label>
                <div style="font-size:1.1rem;font-weight:600;color:#2e7d32"><?php echo h($inviteCode); ?></div>
                <small style="color:#666">使用推荐码注册，双方都将获得奖励！</small>
            </div>
            <input type="hidden" name="invite_code" value="<?php echo h($inviteCode); ?>">
            <?php endif; ?>
            <?php if ($mailEnabled): ?>
            <div class="form-group">
                <label>邮箱验证码</label>
                <div class="code-row">
                    <input type="text" name="email_code" placeholder="5位验证码" maxlength="5">
                    <button type="button" class="btn-send-code" id="sendCodeBtn" onclick="sendEmailCode()">发送验证码</button>
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="至少6位" required>
            </div>
            <?php if (empty($inviteCode)): ?>
            <div class="form-group">
                <label>推荐码 <span style="color:#999;font-weight:normal">(选填)</span></label>
                <input type="text" name="invite_code" id="invite_code" placeholder="输入推荐码，双方获得奖励" style="text-transform:uppercase">
            </div>
            <?php endif; ?>
            <div class="form-group captcha-group">
                <label>人机验证码</label>
                <div class="captcha-row">
                    <input type="text" name="captcha" placeholder="请输入" maxlength="5" required>
                    <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="验证码" title="点击刷新">
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width:100%">注 册</button>
        </form>
        <p class="auth-switch">已有账号？<a href="?mode=login">去登录</a></p>
        <?php endif; ?>
    </div>
</div>

<?php if ($mailEnabled): ?>
<script>
function sendEmailCode() {
    var email = document.getElementById('reg_email').value;
    var btn = document.getElementById('sendCodeBtn');
    if (!email) { alert('请先输入邮箱'); return; }
    btn.disabled = true;
    btn.textContent = '发送中...';
    var fd = new FormData();
    fd.append('action', 'send_email_code');
    fd.append('email', email);
    fetch('login.php', {method:'POST', body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.code===200){alert(d.msg);var c=60;btn.textContent=c+'s';var t=setInterval(function(){c--;btn.textContent=c+'s';if(c<=0){clearInterval(t);btn.disabled=false;btn.textContent='发送验证码';}},1000);}
        else{alert(d.msg);btn.disabled=false;btn.textContent='发送验证码';}
    })
    .catch(function(){alert('请求失败');btn.disabled=false;btn.textContent='发送验证码';});
}
</script>
<?php endif; ?>

<script>
function checkNickname() {
    var val = document.getElementById('reg_nickname').value.trim();
    var el = document.getElementById('nicknameCheckResult');
    if (!val) { el.innerHTML = ''; return; }
    el.style.color = '#999'; el.innerHTML = '检测中...';
    var fd = new FormData();
    fd.append('action', 'check_nickname');
    fd.append('nickname', val);
    fetch('login.php', {method:'POST', body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.valid){el.style.color='#10b981';el.innerHTML='<i class="fas fa-check"></i>';}
        else{el.style.color='#e74c3c';el.innerHTML=d.message||'不可用';}
    })
    .catch(function(){el.style.color='#999';el.innerHTML='';});
}
</script>
<?php renderFooter(); ?>
