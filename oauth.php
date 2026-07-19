<?php
if (!defined('IN_SYS')) define('IN_SYS', true);
if (!defined('ROOT')) define('ROOT', __DIR__ . '/');
if (!function_exists('conf')) include ROOT . 'rd/bootstrap.php';





$OAUTH_TYPES = [
    'qq'        => ['name' => 'QQ',       'icon_type' => 'fa',   'icon' => 'fab fa-qq',          'color' => '#12B7F5'],
    'wx'        => ['name' => '微信',     'icon_type' => 'fa',   'icon' => 'fab fa-weixin',      'color' => '#07C160'],
    'alipay'    => ['name' => '支付宝',   'icon_type' => 'fa',   'icon' => 'fab fa-alipay',      'color' => '#1677FF'],
    'sina'      => ['name' => '微博',     'icon_type' => 'fa',   'icon' => 'fab fa-weibo',       'color' => '#E6162D'],
    'baidu'     => ['name' => '百度',     'icon_type' => 'svg',  'svg' => 'M9.154 0C7.71 0 6.54 1.658 6.54 3.707c0 2.051 1.171 3.71 2.615 3.71 1.446 0 2.614-1.659 2.614-3.71C11.768 1.658 10.6 0 9.154 0zm7.025.594C14.86.58 13.347 2.589 13.2 3.927c-.187 1.745.25 3.487 2.179 3.735 1.933.25 3.175-1.806 3.422-3.364.252-1.555-.995-3.364-2.362-3.674a1.218 1.218 0 0 0-.261-.03zM3.582 5.535a2.811 2.811 0 0 0-.156.008c-2.118.19-2.428 3.24-2.428 3.24-.287 1.41.686 4.425 3.297 3.864 2.617-.561 2.262-3.68 2.183-4.362-.125-1.018-1.292-2.773-2.896-2.75zm16.534 1.753c-2.308 0-2.617 2.119-2.617 3.616 0 1.43.121 3.425 2.988 3.362 2.867-.063 2.553-3.238 2.553-3.988 0-.745-.62-2.99-2.924-2.99zm-8.264 2.478c-1.424.014-2.708.925-3.323 1.947-1.118 1.868-2.863 3.05-3.112 3.363-.25.309-3.61 2.116-2.864 5.42.746 3.301 3.365 3.237 3.365 3.237s1.93.19 4.171-.31c2.24-.495 4.17.123 4.17.123s5.233 1.748 6.665-1.616c1.43-3.364-.808-5.109-.808-5.109s-2.99-2.306-4.736-4.798c-1.072-1.665-2.348-2.268-3.528-2.257z', 'color' => '#2932E1'],
    'douyin'    => ['name' => '抖音',     'icon_type' => 'fa',   'icon' => 'fab fa-tiktok',      'color' => '#000000'],
    'huawei'    => ['name' => '华为',     'icon_type' => 'svg',  'svg' => 'M3.67 6.14S1.82 7.91 1.72 9.78v.35c.08 1.51 1.22 2.4 1.22 2.4 1.83 1.79 6.26 4.04 7.3 4.55 0 0 .06.03.1-.01l.02-.04v-.04C7.52 10.8 3.67 6.14 3.67 6.14zM9.65 18.6c-.02-.08-.1-.08-.1-.08l-7.38.26c.8 1.43 2.15 2.53 3.56 2.2.96-.25 3.16-1.78 3.88-2.3.06-.05.04-.09.04-.09zm.08-.78C6.49 15.63.21 12.28.21 12.28c-.15.46-.2.9-.21 1.3v.07c0 1.07.4 1.82.4 1.82.8 1.69 2.34 2.2 2.34 2.2.7.3 1.4.31 1.4.31.12.02 4.4 0 5.54 0 .05 0 .08-.05.08-.05v-.06c0-.03-.03-.05-.03-.05zM9.06 3.19a3.42 3.42 0 0 0-2.57 3.15v.41c.03.6.16 1.05.16 1.05.66 2.9 3.86 7.65 4.55 8.65.05.05.1.03.1.03a.1.1 0 0 0 .06-.1c1.06-10.6-1.11-13.42-1.11-13.42-.32.02-1.19.23-1.19.23zm8.299 2.27s-.49-1.8-2.44-2.28c0 0-.57-.14-1.17-.22 0 0-2.18 2.81-1.12 13.43.01.07.06.08.06.08.07.03.1-.03.1-.03.72-1.03 3.9-5.76 4.55-8.64 0 0 .36-1.4.02-2.34zm-2.92 13.07s-.07 0-.09.05c0 0-.01.07.03.1.7.51 2.85 2 3.88 2.3 0 0 .16.05.43.06h.14c.69-.02 1.9-.37 3-2.26l-7.4-.25zm7.83-8.41c.14-2.06-1.94-3.97-1.94-3.98 0 0-3.85 4.66-6.67 10.8 0 0-.03.08.02.13l.04.01h.06c1.06-.53 5.46-2.77 7.28-4.54 0 0 1.15-.93 1.21-2.42zm1.52 2.14s-6.28 3.37-9.52 5.55c0 0-.05.04-.03.11 0 0 .03.06.07.06 1.16 0 5.56 0 5.67-.02 0 0 .57-.02 1.27-.29 0 0 1.56-.5 2.37-2.27 0 0 .73-1.45.17-3.14z', 'color' => '#CF0A2C'],
    'xiaomi'    => ['name' => '小米',     'icon_type' => 'svg',  'svg' => 'M12 0C8.016 0 4.756.255 2.493 2.516.23 4.776 0 8.033 0 12.012c0 3.98.23 7.235 2.494 9.497C4.757 23.77 8.017 24 12 24c3.983 0 7.243-.23 9.506-2.491C23.77 19.247 24 15.99 24 12.012c0-3.984-.233-7.243-2.502-9.504C19.234.252 15.978 0 12 0zM4.906 7.405h5.624c1.47 0 3.007.068 3.764.827.746.746.827 2.233.83 3.676v4.54a.15.15 0 0 1-.152.147h-1.947a.15.15 0 0 1-.152-.148V11.83c-.002-.806-.048-1.634-.464-2.051-.358-.36-1.026-.441-1.72-.458H7.158a.15.15 0 0 0-.151.147v6.98a.15.15 0 0 1-.152.148H4.906a.15.15 0 0 1-.15-.148V7.554a.15.15 0 0 1 .15-.149zm12.131 0h1.949a.15.15 0 0 1 .15.15v8.892a.15.15 0 0 1-.15.148h-1.949a.15.15 0 0 1-.151-.148V7.554a.15.15 0 0 1 .151-.149zM8.92 10.948h2.046c.083 0 .15.066.15.147v5.352a.15.15 0 0 1-.15.148H8.92a.15.15 0 0 1-.152-.148v-5.352a.15.15 0 0 1 .152-.147Z', 'color' => '#FF6900'],
    'google'    => ['name' => '谷歌',     'icon_type' => 'fa',   'icon' => 'fab fa-google',      'color' => '#EA4335'],
    'microsoft' => ['name' => '微软',     'icon_type' => 'svg',  'svg' => 'M0 0v11.408h11.408V0zm12.594 0v11.408H24V0zM0 12.594V24h11.408V12.594zm12.594 0V24H24V12.594z', 'color' => '#00A4EF'],
    'dingtalk'  => ['name' => '钉钉',     'icon_type' => 'custom', 'color' => '#1677FF'],
    'feishu'    => ['name' => '飞书',     'icon_type' => 'custom', 'color' => '#00D6B9'],
    'gitee'     => ['name' => 'Gitee',    'icon_type' => 'fa',   'icon' => 'fab fa-git-alt',     'color' => '#C71D23'],
    'github'    => ['name' => 'GitHub',   'icon_type' => 'fa',   'icon' => 'fab fa-github',      'color' => '#181717'],
];


function renderOauthIconByKey($typeKey, $typeInfo) {
    $iconType = $typeInfo['icon_type'] ?? 'fa';
    if ($iconType === 'fa') {
        return '<i class="' . h($typeInfo['icon']) . '"></i>';
    }
    if ($iconType === 'svg') {
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="display:block"><path d="' . h($typeInfo['svg']) . '"/></svg>';
    }
    if ($iconType === 'custom') {
        $imgUrl = trim(conf('oauth_icon_img_' . $typeKey, ''));
        $text = trim(conf('oauth_icon_text_' . $typeKey, ''));
        if ($imgUrl !== '') {
            return '<img src="' . h($imgUrl) . '" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%" alt="' . h($typeInfo['name']) . '">';
        }
        if ($text !== '') {
            return '<span style="font-size:0.9rem;font-weight:700">' . h($text) . '</span>';
        }
        return '<span style="font-size:0.9rem;font-weight:700">' . h(mb_substr($typeInfo['name'], 0, 1)) . '</span>';
    }
    return '';
}


function oauthNeedBackground($typeKey, $typeInfo) {
    $iconType = $typeInfo['icon_type'] ?? 'fa';
    if ($iconType === 'custom') {
        $imgUrl = trim(conf('oauth_icon_img_' . $typeKey, ''));
        return $imgUrl === '';
    }
    return true;
}


function oauthApiRequest($params) {
    $apiUrl = trim(conf('oauth_api_url', 'https://login.az0.cn/connect.php'));
    $appid = trim(conf('oauth_appid', ''));
    $appkey = trim(conf('oauth_appkey', ''));
    if ($apiUrl === '' || $appid === '' || $appkey === '') {
        return ['code' => -1, 'msg' => '聚合登录未配置'];
    }
    $params['appid'] = $appid;
    $params['appkey'] = $appkey;
    $url = $apiUrl . (strpos($apiUrl, '?') === false ? '?' : '&') . http_build_query($params);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'StarIDC-OAuth/1.3',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['code' => -1, 'msg' => '请求聚合登录接口失败'];
    }
    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['code' => -1, 'msg' => '解析响应失败'];
    }
    return $json;
}


function oauthEnabled() {
    return conf('oauth_enabled') === '1';
}


function getEnabledOauthTypes() {
    global $OAUTH_TYPES;
    $enabled = trim(conf('oauth_types', 'qq,wx,alipay'));
    if ($enabled === '') return [];
    $types = array_map('trim', explode(',', $enabled));
    $result = [];
    foreach ($types as $t) {
        if (isset($OAUTH_TYPES[$t])) {
            $result[$t] = $OAUTH_TYPES[$t];
        }
    }
    return $result;
}


if (basename($_SERVER['SCRIPT_NAME']) !== 'oauth.php') {
    return;
}

$act = $_GET['act'] ?? '';


if (!oauthEnabled() && $act !== '') {
    die('聚合登录未启用');
}

if ($act === 'login') {
    
    $type = trim($_GET['type'] ?? '');
    if (!isset($OAUTH_TYPES[$type])) {
        die('不支持的登录方式');
    }
    $redirectUri = siteUrl() . 'oauth.php?act=callback';
    $result = oauthApiRequest([
        'act' => 'login',
        'type' => $type,
        'redirect_uri' => $redirectUri,
    ]);
    if ($result['code'] !== 0) {
        die('获取登录地址失败：' . ($result['msg'] ?? '未知错误'));
    }
    
    $_SESSION['oauth_type'] = $type;
    header('Location: ' . $result['url']);
    exit;
}

if ($act === 'callback') {
    
    $type = trim($_GET['type'] ?? $_SESSION['oauth_type'] ?? '');
    $code = trim($_GET['code'] ?? '');
    if ($type === '' || $code === '') {
        redirect('login.php?mode=login&oauth_error=' . urlencode('登录回调参数缺失'));
    }
    if (!isset($OAUTH_TYPES[$type])) {
        redirect('login.php?mode=login&oauth_error=' . urlencode('不支持的登录方式'));
    }

    $result = oauthApiRequest([
        'act' => 'callback',
        'type' => $type,
        'code' => $code,
    ]);

    if ($result['code'] !== 0) {
        $errorMsg = $result['code'] === 2 ? '请先在第三方平台完成登录' : ($result['msg'] ?? '获取用户信息失败');
        redirect('login.php?mode=login&oauth_error=' . urlencode($errorMsg));
    }

    $socialUid = $result['social_uid'] ?? '';
    $nickname = $result['nickname'] ?? '';
    $faceimg = $result['faceimg'] ?? '';
    if ($socialUid === '') {
        redirect('login.php?mode=login&oauth_error=' . urlencode('获取第三方UID失败'));
    }

    
    $stmt = $DB->prepare("SELECT b.*, u.id as user_id FROM oauth_bindings b JOIN users u ON b.user_id=u.id WHERE b.oauth_type=? AND b.social_uid=?");
    $stmt->execute([$type, $socialUid]);
    $binding = $stmt->fetch();

    if ($binding) {
        
        $_SESSION['user_id'] = $binding['user_id'];
        
        $stmtUpd = $DB->prepare("UPDATE users SET last_login_time=?, last_login_ip=? WHERE id=?");
        $stmtUpd->execute([date('Y-m-d H:i:s'), getClientIp(), $binding['user_id']]);
        
        $stmtBind = $DB->prepare("UPDATE oauth_bindings SET nickname=?, faceimg=?, updated_at=NOW() WHERE id=?");
        $stmtBind->execute([$nickname, $faceimg, $binding['id']]);
        redirect('personalpanel.php');
    }

    
    if (isLogin()) {
        
        $userId = $_SESSION['user_id'];
        $stmtBind = $DB->prepare("INSERT INTO oauth_bindings(user_id, oauth_type, social_uid, nickname, faceimg) VALUES(?,?,?,?,?)");
        $stmtBind->execute([$userId, $type, $socialUid, $nickname, $faceimg]);
        redirect('personalpanel.php?oauth_bound=1');
    }

    
    
    $mailEnabled = conf('mail_enabled') === '1';

    
    if (!$mailEnabled && !($domainRestrict && !empty($domainWhitelist))) {
        
        $fakeEmail = $type . '_' . substr(md5($socialUid), 0, 10) . '@oauth.local';
        $finalNickname = !empty($nickname) ? $nickname : ($OAUTH_TYPES[$type]['name'] . '用户' . substr(md5($socialUid), 0, 6));
        
        $baseNickname = $finalNickname;
        $suffix = 1;
        while (true) {
            $stmtChk = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
            $stmtChk->execute([$finalNickname]);
            if (!$stmtChk->fetch()) break;
            $finalNickname = $baseNickname . $suffix;
            $suffix++;
        }
        $randomPwd = bin2hex(random_bytes(8));
        $hashed = password_hash($randomPwd, PASSWORD_DEFAULT);
        $inviteCode = 'INV' . strtoupper(substr(md5($fakeEmail . time()), 0, 6));
        $regPoints = intval(conf('register_points', '0'));
        $regEnabled = conf('register_points_enabled', '0') === '1';
        $initialPoints = $regEnabled ? $regPoints : 0;

        $stmtUser = $DB->prepare("INSERT INTO users(email, nickname, password, points, invite_code) VALUES(?,?,?,?,?)");
        $stmtUser->execute([$fakeEmail, $finalNickname, $hashed, $initialPoints, $inviteCode]);
        $newUserId = $DB->lastInsertId();

        
        $stmtBind = $DB->prepare("INSERT INTO oauth_bindings(user_id, oauth_type, social_uid, nickname, faceimg) VALUES(?,?,?,?,?)");
        $stmtBind->execute([$newUserId, $type, $socialUid, $nickname, $faceimg]);

        $_SESSION['user_id'] = $newUserId;
        redirect('personalpanel.php?oauth_register=1');
    } else {
        
        
        $_SESSION['oauth_pending'] = [
            'type' => $type,
            'social_uid' => $socialUid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
        ];
        redirect('oauth.php?act=bind');
    }
}

if ($act === 'bind') {
    
    $pending = $_SESSION['oauth_pending'] ?? null;
    if (!$pending) {
        redirect('login.php');
    }
    $type = $pending['type'];
    $oauthName = $OAUTH_TYPES[$type]['name'] ?? '第三方';
    $error = $_GET['error'] ?? '';

    
    if (($_GET['send_code'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonExit(400, '邮箱格式不正确'); }
        $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) { jsonExit(400, '该邮箱已注册'); }
        if (!Captcha::canSend($email, 'register', 60)) { jsonExit(400, '发送太频繁，请60秒后再试'); }
        $code = Captcha::generate($email, 'register', 300);
        $result = Mailer::send($email, '验证码 - ' . conf('site_name', '云主机'), '【' . $code . '】5分钟内有效，请勿泄露给他人。');
        if ($result) { jsonExit(200, '验证码已发送'); }
        else { jsonExit(500, '邮件发送失败'); }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $nickname = trim($_POST['nickname'] ?? $pending['nickname']);
        $password = $_POST['password'] ?? '';
        $emailCode = trim($_POST['email_code'] ?? '');
        $captcha = trim($_POST['captcha'] ?? '');
        $mode = $_POST['bind_mode'] ?? 'register'; 

        if ($mode === 'login') {
            
            $loginAccount = trim($_POST['login_account'] ?? '');
            $loginPwd = $_POST['login_password'] ?? '';
            if (empty($loginAccount) || empty($loginPwd)) {
                redirect('oauth.php?act=bind&error=' . urlencode('请输入账号和密码'));
            }
            $isEmail = filter_var($loginAccount, FILTER_VALIDATE_EMAIL) !== false;
            if ($isEmail) {
                $stmt = $DB->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$loginAccount]);
            } else {
                $stmt = $DB->prepare("SELECT * FROM users WHERE nickname = ?");
                $stmt->execute([$loginAccount]);
            }
            $user = $stmt->fetch();
            if (!$user || !password_verify($loginPwd, $user['password'])) {
                redirect('oauth.php?act=bind&error=' . urlencode('账号或密码错误'));
            }
            
            $stmtChk = $DB->prepare("SELECT id FROM oauth_bindings WHERE user_id=? AND oauth_type=?");
            $stmtChk->execute([$user['id'], $type]);
            if ($stmtChk->fetch()) {
                redirect('oauth.php?act=bind&error=' . urlencode('该账号已绑定此' . $oauthName . '，无需重复绑定'));
            }
            
            $stmtBind = $DB->prepare("INSERT INTO oauth_bindings(user_id, oauth_type, social_uid, nickname, faceimg) VALUES(?,?,?,?,?)");
            $stmtBind->execute([$user['id'], $type, $pending['social_uid'], $pending['nickname'], $pending['faceimg']]);
            $_SESSION['user_id'] = $user['id'];
            unset($_SESSION['oauth_pending']);
            redirect('personalpanel.php?oauth_bound=1');
        } else {
            
            if (strlen($password) < 6) {
                redirect('oauth.php?act=bind&error=' . urlencode('密码至少6位'));
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirect('oauth.php?act=bind&error=' . urlencode('邮箱格式不正确'));
            } elseif (!empty($nickname) && (strlen($nickname) < 2 || strlen($nickname) > 20)) {
                redirect('oauth.php?act=bind&error=' . urlencode('昵称2-20位'));
            } elseif (!Captcha::checkImage($captcha)) {
                redirect('oauth.php?act=bind&error=' . urlencode('人机验证码错误'));
            } else {
                
                if (conf('mail_enabled') === '1') {
                    if (empty($emailCode) || !Captcha::verify($email, $emailCode, 'register')) {
                        redirect('oauth.php?act=bind&error=' . urlencode('邮箱验证码错误或已过期'));
                    }
                }
                
                $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    redirect('oauth.php?act=bind&error=' . urlencode('该邮箱已注册，请使用登录绑定模式'));
                }
                
                $finalNickname = !empty($nickname) ? $nickname : $pending['nickname'];
                if (empty($finalNickname)) {
                    $finalNickname = $oauthName . '用户' . substr(md5($pending['social_uid']), 0, 6);
                }
                $baseNickname = $finalNickname;
                $suffix = 1;
                while (true) {
                    $stmtChk = $DB->prepare("SELECT id FROM users WHERE nickname = ?");
                    $stmtChk->execute([$finalNickname]);
                    if (!$stmtChk->fetch()) break;
                    $finalNickname = $baseNickname . $suffix;
                    $suffix++;
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $inviteCode = 'INV' . strtoupper(substr(md5($email . time()), 0, 6));
                $regPoints = intval(conf('register_points', '0'));
                $regEnabled = conf('register_points_enabled', '0') === '1';
                $initialPoints = $regEnabled ? $regPoints : 0;

                $stmtUser = $DB->prepare("INSERT INTO users(email, nickname, password, points, invite_code) VALUES(?,?,?,?,?)");
                $stmtUser->execute([$email, $finalNickname, $hashed, $initialPoints, $inviteCode]);
                $newUserId = $DB->lastInsertId();

                $stmtBind = $DB->prepare("INSERT INTO oauth_bindings(user_id, oauth_type, social_uid, nickname, faceimg) VALUES(?,?,?,?,?)");
                $stmtBind->execute([$newUserId, $type, $pending['social_uid'], $pending['nickname'], $pending['faceimg']]);

                $_SESSION['user_id'] = $newUserId;
                unset($_SESSION['oauth_pending']);
                Captcha::clearImage();
                redirect('personalpanel.php?oauth_register=1');
            }
        }
    }

    renderHeader('绑定' . $oauthName . '账号');
    ?>
    <div class="auth-container">
        <div class="auth-card">
            <div style="text-align:center;margin-bottom:20px">
                <div style="font-size:2.5rem;color:<?php echo $OAUTH_TYPES[$type]['color']; ?>;margin-bottom:8px">
                    <?php echo renderOauthIconByKey($type, $OAUTH_TYPES[$type]); ?>
                </div>
                <h3>绑定<?php echo h($oauthName); ?>账号</h3>
                <?php if (!empty($pending['nickname'])): ?>
                <p style="color:#666;margin-top:4px"><?php echo h($pending['nickname']); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
            <div class="msg msg-error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <div class="auth-tabs" id="bindTabs">
                <a class="auth-tab active" onclick="setBindMode('register')">注册新账号</a>
                <a class="auth-tab" onclick="setBindMode('login')">绑定已有账号</a>
            </div>

            
            <form method="post" class="auth-form" id="registerForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="bind_mode" value="register">
                <div class="form-group">
                    <label>邮箱 <span style="color:#e74c3c">*</span></label>
                    <input type="email" name="email" placeholder="请输入邮箱" required>
                </div>
                <div class="form-group">
                    <label>昵称 <span style="color:#999">(选填)</span></label>
                    <input type="text" name="nickname" value="<?php echo h($pending['nickname']); ?>" placeholder="2-20位">
                </div>
                <?php if (conf('mail_enabled') === '1'): ?>
                <div class="form-group">
                    <label>邮箱验证码</label>
                    <div class="code-row">
                        <input type="text" name="email_code" placeholder="5位验证码" maxlength="5">
                        <button type="button" class="btn-send-code" id="sendCodeBtn" onclick="sendBindCode()">发送验证码</button>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>密码 <span style="color:#e74c3c">*</span></label>
                    <input type="password" name="password" placeholder="至少6位" required>
                </div>
                <div class="form-group captcha-group">
                    <label>人机验证码</label>
                    <div class="captcha-row">
                        <input type="text" name="captcha" placeholder="请输入" maxlength="5" required>
                        <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="验证码" title="点击刷新">
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width:100%">注册并绑定</button>
            </form>

            
            <form method="post" class="auth-form" id="loginForm" style="display:none">
                <?php echo csrfField(); ?>
                <input type="hidden" name="bind_mode" value="login">
                <div class="form-group">
                    <label>邮箱 / 昵称</label>
                    <input type="text" name="login_account" placeholder="输入邮箱或昵称" required>
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="login_password" placeholder="请输入密码" required>
                </div>
                <button type="submit" class="btn-primary" style="width:100%">登录并绑定</button>
            </form>

            <p style="text-align:center;margin-top:16px"><a href="login.php" style="color:#6c5ce7;text-decoration:none">取消</a></p>
        </div>
    </div>

    <script>
    function setBindMode(mode) {
        document.querySelectorAll('#bindTabs .auth-tab').forEach(function(el){el.classList.remove('active')});
        event.target.classList.add('active');
        document.getElementById('registerForm').style.display = mode === 'register' ? 'block' : 'none';
        document.getElementById('loginForm').style.display = mode === 'login' ? 'block' : 'none';
    }
    function sendBindCode() {
        var email = document.querySelector('#registerForm input[name="email"]').value;
        var btn = document.getElementById('sendCodeBtn');
        if (!email) { alert('请先输入邮箱'); return; }
        btn.disabled = true;
        btn.textContent = '发送中...';
        var fd = new FormData();
        fd.append('email', email);
        fetch('oauth.php?act=bind&send_code=1', {method:'POST', body:fd})
        .then(function(r){return r.json()})
        .then(function(d){
            if(d.code===200){alert(d.message);var c=60;btn.textContent=c+'s';var t=setInterval(function(){c--;btn.textContent=c+'s';if(c<=0){clearInterval(t);btn.disabled=false;btn.textContent='发送验证码';}},1000);}
            else{alert(d.message);btn.disabled=false;btn.textContent='发送验证码';}
        })
        .catch(function(){alert('请求失败');btn.disabled=false;btn.textContent='发送验证码';});
    }
    </script>
    <?php renderFooter(); ?>
    <?php
    exit;
}


redirect('login.php');
