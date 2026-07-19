<?php
/**
 * 广告点击记录端点（仅统计自定义广告，无 uid 的广告不会被统计）。
 * 前端通过 navigator.sendBeacon 异步上报，不阻塞跳转。
 */
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$uid = trim($_POST['uid'] ?? '');
if ($uid === '') {
    http_response_code(400);
    exit;
}
$uid = mb_substr($uid, 0, 64);
$userId = intval($_SESSION['user_id'] ?? 0);
try {
    $DB->prepare("INSERT INTO ad_clicks(ad_uid, user_id, created_at) VALUES(?, ?, NOW())")
        ->execute([$uid, $userId]);
} catch (Exception $e) {
    // 记录失败不影响用户跳转
}
http_response_code(204);
exit;
