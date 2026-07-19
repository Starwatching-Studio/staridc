<?php


define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';


if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    $cronKey = conf('cron_key', '');
    if (empty($cronKey) || $key !== $cronKey) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}


if (conf('mail_notify_expire') !== '1' || conf('mail_enabled') !== '1') {
    if (php_sapi_name() === 'cli') {
        echo "到期告警通知未启用，跳过执行。\n";
    } else {
        echo 'skipped';
    }
    exit;
}


$stmt = $DB->prepare("
    SELECT v.id, v.account, v.expire_time, v.expire_warned, vm.name as model_name, u.email, u.nickname
    FROM vhosts v
    LEFT JOIN vhost_models vm ON v.model_id = vm.id
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.expire_time IS NOT NULL
      AND v.expire_time > NOW()
      AND v.expire_time < DATE_ADD(NOW(), INTERVAL 5 DAY)
      AND v.expire_warned = 0
");
$stmt->execute();
$vhosts = $stmt->fetchAll();

if (empty($vhosts)) {
    if (php_sapi_name() === 'cli') {
        echo "没有即将到期需要告警的主机。\n";
    } else {
        echo 'no_warnings';
    }
    exit;
}

$successCount = 0;
$failCount = 0;

foreach ($vhosts as $v) {
    $daysLeft = max(1, ceil((strtotime($v['expire_time']) - time()) / 86400));
    $subject = '主机即将到期提醒 - ' . conf('site_name', '云主机');
    $body = $v['nickname'] . "，您好！\n\n"
          . "您的虚拟主机即将到期！\n\n"
          . "型号：" . $v['model_name'] . "\n"
          . "账号：" . $v['account'] . "\n"
          . "到期时间：" . date('Y-m-d', strtotime($v['expire_time'])) . "\n"
          . "剩余天数：" . $daysLeft . "天\n\n"
          . "请及时续费，以免主机被停用。\n\n"
          . "如已续费，请忽略此邮件。";

    $result = Mailer::sendNotify($v['email'], $subject, $body);
    if ($result) {
        
        $DB->prepare("UPDATE vhosts SET expire_warned = 1 WHERE id = ?")->execute([$v['id']]);
        $successCount++;
    } else {
        $failCount++;
    }
}

$msg = "到期告警执行完毕：成功 {$successCount} 封，失败 {$failCount} 封。\n";
if (php_sapi_name() === 'cli') {
    echo $msg;
} else {
    echo "done:success={$successCount},fail={$failCount}";
}
