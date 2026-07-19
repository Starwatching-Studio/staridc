<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/PayAPI.php';


$data = $_GET;
if (PayAPI::verifyNotify($data)) {
    if (isset($data['trade_status']) && ($data['trade_status'] === 'TRADE_SUCCESS' || $data['trade_status'] === 'trade_success')) {
        $result = processPayment($data);
        if ($result) {
            echo 'success';
        } else {
            echo 'fail';
        }
    } else {
        echo 'success'; 
    }
} else {
    echo 'fail';
}
exit;

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

        if (conf('mail_notify_points') === '1') {
            $stmt3 = $DB->prepare("SELECT email, points FROM users WHERE id=?");
            $stmt3->execute([$order['user_id']]);
            $u = $stmt3->fetch();
            if ($u) {
                $notifySubject = '积分充值成功 - ' . conf('site_name', '云主机');
                $notifyBody = "您已成功充值 " . $order['points'] . " 积分！\n\n"
                    . "订单号：" . $orderNo . "\n"
                    . "支付金额：¥" . $order['amount'] . "\n"
                    . "当前积分余额：" . $u['points'] . "\n\n"
                    . "感谢您的支持！";
                Mailer::sendNotify($u['email'], $notifySubject, $notifyBody);
            }
        }
    }
    return true;
}
