<?php
/**
 * SSO 登录入口（源站点调用）。
 * 用法：<a href="https://本站/sso_login.php?redirect=personalpanel.php">进入合作伙伴站点</a>
 * 前提：当前站点已登录（requireLogin）。将以登录邮箱生成签名令牌，跳转至合作伙伴的 sso_callback.php。
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

requireLogin();
$user = getUser();
if (!$user || empty($user['email'])) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '当前账号未绑定邮箱，无法使用单点登录。请先在个人中心补全邮箱。';
    exit;
}

$partner = ssoPartnerUrl();
if (empty($partner)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '未配置 SSO 合作伙伴地址（sso_partner_url）。';
    exit;
}

$redirect = ssoSafeRedirect($_GET['redirect'] ?? 'personalpanel.php');
$token = ssoBuildToken($user['email']);
$target = $partner . '/sso_callback.php?token=' . rawurlencode($token) . '&redirect=' . rawurlencode($redirect);
redirect($target);
