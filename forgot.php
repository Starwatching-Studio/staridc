<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';

$mode = $_GET['mode'] ?? 'request';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    
    if ($action === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif (!conf('mail_enabled')) {
            $error = '邮件服务未启用，请联系管理员';
        } else {
            
            $stmt = $DB->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                $error = '该邮箱未注册';
            } elseif (!Captcha::canSend($email, 'forgot', 60)) {
                $error = '发送太频繁，请60秒后再试';
            } else {
                $code = Captcha::generate($email, 'forgot', 300);
                $result = Mailer::send($email, '密码重置验证码 - ' . conf('site_name', '云主机'), '【' . $code . '】您正在申请重置密码，5分钟内有效，请勿泄露给他人。');
                if ($result) {
                    $success = '验证码已发送到您的邮箱';
                    $_SESSION['forgot_email'] = $email;
                } else {
                    $error = '邮件发送失败，请检查邮箱配置';
                }
            }
        }
    }

    
    if ($action === 'reset_password') {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($_SESSION['forgot_email']) || $_SESSION['forgot_email'] !== $email) {
            $error = '请先获取验证码';
        } elseif (empty($code)) {
            $error = '请输入验证码';
        } elseif (strlen($newPassword) < 6) {
            $error = '新密码至少6位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次密码输入不一致';
        } elseif (!Captcha::verify($email, $code, 'forgot')) {
            $error = '验证码错误或已过期';
        } else {
            
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $DB->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed, $email]);
            Captcha::delete($email, 'forgot');
            unset($_SESSION['forgot_email']);
            $success = '密码重置成功！';
            $mode = 'success';
        }
    }
}


if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    if (!empty($error)) {
        jsonExit(400, $error);
    } elseif (!empty($success)) {
        jsonExit(200, $success);
    }
}

renderHeader('找回密码');
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-tabs">
            <span class="auth-tab active">找回密码</span>
        </div>

        <?php if ($error): ?>
        <div class="msg msg-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success && $mode !== 'success'): ?>
        <div class="msg msg-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <?php if ($mode === 'request'): ?>
        <form method="post" class="auth-form" id="forgotForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="send_code">
            <div class="form-group">
                <label>注册邮箱</label>
                <input type="email" name="email" id="forgot_email" placeholder="请输入您注册的邮箱" required value="<?php echo h($_POST['email'] ?? ''); ?>">
            </div>
            <?php if (conf('mail_enabled')): ?>
            <div class="form-group">
                <label>验证码</label>
                <div class="code-row">
                    <input type="text" name="captcha" placeholder="人机验证" maxlength="5" required>
                    <img src="captcha.php?_=<?php echo time(); ?>" onclick="this.src='captcha.php?_='+Date.now()" class="captcha-img" alt="验证码" title="点击刷新">
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn-primary" style="width:100%">发送验证码</button>
        </form>

        <form method="post" class="auth-form" id="resetForm" style="margin-top:20px;display:none">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="email" id="reset_email">
            <div class="form-group">
                <label>邮箱验证码</label>
                <div class="code-row">
                    <input type="text" name="code" id="reset_code" placeholder="5位验证码" maxlength="5" required>
                    <button type="button" class="btn-send-code" id="resendBtn">重新发送</button>
                </div>
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" name="new_password" placeholder="至少6位" required>
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="confirm_password" placeholder="再次输入新密码" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%">重置密码</button>
        </form>

        <?php elseif ($mode === 'success'): ?>
        <div class="auth-form" style="text-align:center;padding:20px 0">
            <div style="font-size:48px;margin-bottom:16px">✅</div>
            <h3 style="color:#00b894;margin-bottom:12px"><?php echo h($success); ?></h3>
            <p style="color:#636e72;margin-bottom:20px">请使用新密码登录</p>
            <a href="login.php" class="btn-primary" style="display:inline-block;text-align:center;padding:12px 40px">返回登录</a>
        </div>
        <?php endif; ?>

        <p class="auth-switch"><a href="login.php">想起密码了？返回登录</a></p>
    </div>
</div>

<?php if (conf('mail_enabled')): ?>
<script>
var codeSent = false;
document.getElementById('forgotForm').addEventListener('submit', function(e) {
    var email = document.getElementById('forgot_email').value;
    if (!email) return;
    document.getElementById('reset_email').value = email;
    
    var formData = new FormData();
    formData.append('action', 'send_code');
    formData.append('email', email);
    
    fetch('forgot.php', {method:'POST', body:formData})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.code === 200) {
            codeSent = true;
            document.getElementById('forgotForm').style.display = 'none';
            document.getElementById('resetForm').style.display = 'block';
            alert(d.message);
        } else {
            alert(d.message);
        }
    })
    .catch(function(){alert('请求失败');});
    e.preventDefault();
});

document.getElementById('resendBtn').addEventListener('click', function() {
    var email = document.getElementById('forgot_email').value;
    var btn = this;
    btn.disabled = true;
    btn.textContent = '发送中...';
    
    var formData = new FormData();
    formData.append('action', 'send_code');
    formData.append('email', email);
    
    fetch('forgot.php', {method:'POST', body:formData})
    .then(function(r){return r.json()})
    .then(function(d){
        if(d.code === 200) {
            alert(d.message);
            var c = 60;
            btn.textContent = c + 's';
            var t = setInterval(function() {
                c--;
                btn.textContent = c + 's';
                if(c <= 0) {
                    clearInterval(t);
                    btn.disabled = false;
                    btn.textContent = '重新发送';
                }
            }, 1000);
        } else {
            alert(d.message);
            btn.disabled = false;
            btn.textContent = '重新发送';
        }
    })
    .catch(function(){alert('请求失败');btn.disabled = false;btn.textContent = '重新发送';});
});
</script>
<?php endif; ?>

<?php renderFooter(); ?>
