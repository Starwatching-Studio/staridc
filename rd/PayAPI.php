<?php
if (!defined('IN_SYS')) exit;

class PayAPI {

    private static function getConfig() {
        return [
            'apiurl'  => conf('pay_api_url', ''),
            'pid'     => conf('pay_pid', ''),
            'key'     => conf('pay_key', ''),
        ];
    }

    private static function http($url, $data = '', $method = 'POST') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        $result = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        return $err ? false : $result;
    }

    private static function sign(array $params, $key) {
        ksort($params);
        reset($params);
        $arr = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null) continue;
            if ($k === 'sign' || $k === 'sign_type') continue;
            $arr[$k] = stripslashes((string)$v);
        }
        $str = '';
        $i = 0;
        foreach ($arr as $k => $v) {
            $str .= "$k=$v";
            if (++$i < count($arr)) $str .= '&';
        }
        return strtolower(md5($str . $key));
    }

    private static function checkConfig() {
        $c = self::getConfig();
        return !empty($c['apiurl']) && !empty($c['pid']) && !empty($c['key']);
    }

    
    public static function createPayment($orderNo, $name, $money, $type = 'alipay', $notifyUrl = '', $returnUrl = '') {
        if (!self::checkConfig()) {
            return '<p style="color:red;text-align:center;padding:40px;">支付接口未配置，请联系管理员</p>';
        }
        $c = self::getConfig();
        $params = [
            'pid'          => $c['pid'],
            'type'         => $type,
            'notify_url'   => $notifyUrl,
            'return_url'   => $returnUrl,
            'out_trade_no' => $orderNo,
            'name'         => $name,
            'money'        => $money,
            'sign_type'    => 'MD5',
        ];
        $params['sign'] = self::sign($params, $c['key']);
        $url = rtrim($c['apiurl'], '/') . '/submit.php?' . http_build_query($params);
        header("Location: " . $url);
        exit;
    }

    
    public static function apiPayment($orderNo, $name, $money, $type = 'alipay', $notifyUrl = '', $returnUrl = '') {
        if (!self::checkConfig()) return ['code' => -1, 'msg' => '支付接口未配置'];
        $c = self::getConfig();
        $params = [
            'pid'          => $c['pid'],
            'type'         => $type,
            'notify_url'   => $notifyUrl,
            'return_url'   => $returnUrl,
            'out_trade_no' => $orderNo,
            'name'         => $name,
            'money'        => $money,
            'sign_type'    => 'MD5',
        ];
        $params['sign'] = self::sign($params, $c['key']);
        $resp = self::http(rtrim($c['apiurl'], '/') . '/mapi.php', http_build_query($params), 'POST');
        if (!$resp) return ['code' => -1, 'msg' => '请求失败'];
        $json = json_decode($resp, true);
        return $json ?: ['code' => -1, 'msg' => '解析失败: ' . substr($resp, 0, 200)];
    }

    
    public static function verifyNotify($data) {
        $c = self::getConfig();
        if (empty($c['key']) || empty($data['sign'])) return false;
        $calcSign = self::sign($data, $c['key']);
        return $data['sign'] === $calcSign;
    }

    
    public static function queryOrder($orderNo) {
        if (!self::checkConfig()) return ['code' => -1, 'msg' => '支付接口未配置'];
        $c = self::getConfig();
        $url = rtrim($c['apiurl'], '/') . '/api.php?act=order&pid=' . $c['pid'] . '&key=' . $c['key'] . '&out_trade_no=' . $orderNo;
        $resp = self::http($url, '', 'GET');
        if (!$resp) return ['code' => -1, 'msg' => '查询请求失败'];
        $json = json_decode($resp, true);
        return $json ?: ['code' => -1, 'msg' => '解析失败: ' . substr($resp, 0, 200)];
    }

    
    public static function buildRequestForm($params) {
        $c = self::getConfig();
        if (!self::checkConfig()) {
            return '<p style="color:red;text-align:center;padding:40px;">支付接口未配置，请联系管理员</p>';
        }
        $params['pid'] = $c['pid'];
        $params['sign_type'] = 'MD5';
        $params['sign'] = self::sign($params, $c['key']);
        $gateway = rtrim($c['apiurl'], '/') . '/submit.php';
        $html = "<form id='payform' action='" . h($gateway) . "' method='POST'>";
        foreach ($params as $k => $v) {
            $html .= "<input type='hidden' name='" . h($k) . "' value='" . h($v) . "'/>";
        }
        $html .= "<input type='submit' value='正在跳转到支付页面...'></form>";
        $html .= "<script>document.forms['payform'].submit();</script>";
        return $html;
    }
}
