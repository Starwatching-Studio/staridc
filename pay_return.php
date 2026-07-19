<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/PayAPI.php';


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
    
    $stmt = $DB->prepare("UPDATE orders SET status=1,paid_at=NOW() WHERE order_no=? AND status=0");
    $stmt->execute([$orderNo]);
    if ($stmt->rowCount() === 0) return false;
    
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
        addPoints($order['user_id'], $order['points'], 'recharge', 'order:' . $order['order_no'], '积分充值（¥' . $order['amount'] . '）');
    }
    return true;
}
