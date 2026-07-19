<?php
/**
 * SSO 回调入口（合作伙伴站点调用）。
 * 由源站点的 sso_login.php 重定向至此，携带签名令牌 token。
 * 校验通过后，以令牌中的邮箱登录或自动开户，并建立本站会话。
 */
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'sso_lib.php';

if (!ssoEnabled()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '单点登录（SSO）未启用。';
    exit;
}

$token = $_GET['token'] ?? '';
$redirect = ssoSafeRedirect($_GET['redirect'] ?? 'personalpanel.php');

$email = ssoVerifyToken($token);

if ($email === false) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>单点登录失败</title>';
    echo '<style>body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.card{background:#fff;border-radius:16px;padding:40px;max-width:460px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center}.card h2{color:#e74c3c;margin:0 0 12px}.card p{color:#64748b;font-size:.92rem;line-height:1.7}.btn{display:inline-block;margin-top:16px;padding:10px 24px;background:#6366f1;color:#fff;border-radius:8px;text-decoration:none;font-weight:600}</style>';
    echo '</head><body><div class="card"><h2>单点登录失败</h2><p>登录令牌无效或已过期。请返回原站点重新点击跳转，或直接在本站登录。</p><a class="btn" href="login.php">前往登录</a></div></body></html>';
    exit;
}

$ok = ssoLoginByEmail($email, true);
if (!$ok) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>需要注册</title>';
    echo '<style>body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.card{background:#fff;border-radius:16px;padding:40px;max-width:480px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center}.card h2{color:#f59e0b;margin:0 0 12px}.card p{color:#64748b;font-size:.92rem;line-height:1.7}.btn{display:inline-block;margin-top:16px;padding:10px 24px;background:#6366f1;color:#fff;border-radius:8px;text-decoration:none;font-weight:600}</style>';
    echo '</head><body><div class="card"><h2>请先注册</h2><p>邮箱 <strong>' . h($email) . '</strong> 在本站尚未注册账号，请先完成注册，即可与商城/面板账号打通。</p><a class="btn" href="login.php?mode=register">前往注册</a></div></body></html>';
    exit;
}

redirect($redirect);
