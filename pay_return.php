<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/PayAPI.php';

// 同步跳转回调（用户浏览器跳转）
$data = $_GET;
$verified = PayAPI::verifyNotify($data);
if ($verified) {
    processPayment($data);
} else {
    $orderNo = $data['out_trade_no'] ?? '';
    if (!empty($orderNo)) {
        $result = PayAPI::queryOrder($orderNo);
        if (isset($result['code']) && $result['code'] == 0 && ($result['trade_status'] ?? '') === 'TRADE_SUCCESS') {
            processPayment($data);
        }
    }
}
redirect('personalpanel.php');

function processPayment($data) {
    global $DB;
    $orderNo = $data['out_trade_no'] ?? '';
    // 原子更新：仅当 status=0 时才更新，防止并发重复处理
    $stmt = $DB->prepare("UPDATE orders SET status=1,paid_at=NOW() WHERE order_no=? AND status=0");
    $stmt->execute([$orderNo]);
    if ($stmt->rowCount() === 0) return false;
    // 更新成功后，校验金额
    $stmt2 = $DB->prepare("SELECT * FROM orders WHERE order_no=? AND status=1");
    $stmt2->execute([$orderNo]);
    $order = $stmt2->fetch();
    if (!$order) return false;
    $payMoney = floatval($data['money'] ?? 0);
    if (abs($payMoney - floatval($order['amount'])) > 0.01) {
        $DB->prepare("UPDATE orders SET status=0 WHERE order_no=?")->execute([$orderNo]);
        return false;
    }
    if ($order['type'] === 'points' && $order['points'] > 0) {
        $DB->prepare("UPDATE users SET points=points+? WHERE id=?")->execute([$order['points'], $order['user_id']]);
    }
    return true;
}
