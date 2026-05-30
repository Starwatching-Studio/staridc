<?php
/**
 * 主机到期告警定时任务
 * 
 * 使用方式：
 * 1. 命令行：php cron_expire_warning.php
 * 2. Web访问：https://yourdomain.com/cron_expire_warning.php?key=你的cron_key
 * 
 * 建议配置系统crontab每天执行一次，例如：
 * 0 9 * * * php /path/to/cron_expire_warning.php
 * 或
 * 0 9 * * * wget -q "https://yourdomain.com/cron_expire_warning.php?key=xxx" -O /dev/null
 */

define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';

// 安全验证：CLI模式直接运行，Web模式需验证cron_key
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    $cronKey = conf('cron_key', '');
    if (empty($cronKey) || $key !== $cronKey) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// 检查到期告警通知是否开启
if (conf('mail_notify_expire') !== '1' || conf('mail_enabled') !== '1') {
    if (php_sapi_name() === 'cli') {
        echo "到期告警通知未启用，跳过执行。\n";
    } else {
        echo 'skipped';
    }
    exit;
}

// 查询5天内即将到期且未发送过告警的主机
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
        // 标记已发送告警
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
