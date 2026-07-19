<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/PayAPI.php';

$act = $_GET['act'] ?? '';

function processPayment($data) {
    global $DB;
    $orderNo = $data['out_trade_no'] ?? '';
    $stmt = $DB->prepare("SELECT * FROM orders WHERE order_no=? AND status=0");
    $stmt->execute([$orderNo]);
    $order = $stmt->fetch();
    if (!$order) return false;
    $payMoney = floatval($data['money'] ?? 0);
    if (abs($payMoney - floatval($order['amount'])) > 0.01) return false;
    $DB->prepare("UPDATE orders SET status=1,paid_at=NOW() WHERE order_no=?")->execute([$orderNo]);
    if ($order['type'] === 'points' && $order['points'] > 0) {
        addPoints($order['user_id'], $order['points'], 'recharge', 'order:' . $order['order_no'], '积分充值（¥' . $order['amount'] . '）');
    }
    return true;
}


function getNotifyData() {
    $data = $_GET;
    unset($data['act']);
    return $data;
}

if ($act === 'notify') {
    $notifyData = getNotifyData();
    if (PayAPI::verifyNotify($notifyData)) {
        if (isset($notifyData['trade_status']) && ($notifyData['trade_status'] === 'TRADE_SUCCESS' || $notifyData['trade_status'] === 'trade_success')) {
            processPayment($notifyData);
        }
        echo 'success';
    } else {
        echo 'fail';
    }
    exit;
}

if ($act === 'return') {
    $notifyData = getNotifyData();
    $verified = PayAPI::verifyNotify($notifyData);
    if ($verified) {
        processPayment($notifyData);
    } else {
        $orderNo = $notifyData['out_trade_no'] ?? '';
        if (!empty($orderNo)) {
            $result = PayAPI::queryOrder($orderNo);
            if (isset($result['code']) && $result['code'] == 0 && ($result['trade_status'] ?? '') === 'TRADE_SUCCESS') {
                processPayment($notifyData);
                $verified = true;
            }
        }
    }
    redirect('personalpanel.php');
}

redirect('index.php');
