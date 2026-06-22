<?php
if (!defined('IN_SYS')) exit;

class MNBT_API {
    // 获取服务器配置：传入 $server 数组则使用多服务器配置，否则回退到旧版 config（向后兼容）
    private static function getConfig($server = null) {
        if ($server) {
            return [
                'api_url' => $server['api_url'],
                'mn_bh'   => $server['mn_bh'],
                'mn_key'  => $server['mn_key'],
                'mn_keye' => $server['mn_keye'],
                'mn_vs'   => $server['mn_vs'] ?? '16',
            ];
        }
        return [
            'api_url' => conf('mnbt_api_url', ''),
            'mn_bh'   => conf('mnbt_bh', ''),
            'mn_key'  => conf('mnbt_key', ''),
            'mn_keye' => conf('mnbt_keye', ''),
            'mn_vs'   => conf('mnbt_vs', '16'),
        ];
    }

    private static function getCommonParams($server = null) {
        $c = self::getConfig($server);
        return [
            'mn_bh'   => $c['mn_bh'],
            'mn_key'  => $c['mn_key'],
            'mn_keye' => $c['mn_keye'],
            'mn_vs'   => $c['mn_vs'],
        ];
    }

    private static function sendRequest($gn, $params = [], $server = null) {
        $c = self::getConfig($server);
        if (empty($c['api_url'])) return ['code' => 100, 'msg' => 'MNBT未配置'];
        $url = $c['api_url'] . '?gn=' . $gn;
        $data = array_merge(self::getCommonParams($server), $params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || !empty($error)) return ['code' => 100, 'msg' => 'API请求失败：' . $error];
        if (empty($response)) return ['code' => 100, 'msg' => 'API返回空响应'];
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) return ['code' => 100, 'msg' => 'API返回数据格式错误：' . mb_substr($response, 0, 500)];
        return $result;
    }

    public static function testConnection($server = null) {
        $result = self::sendRequest('cfif', ['username' => 'Link'], $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => 'MNBT连接正常'];
        }
        return ['success' => false, 'message' => 'MNBT连接失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function openHost($username, $password, $webSpace, $dbSpace, $flow = 30, $domainLimit = 10, $expireDate = null, $server = null) {
        if ($expireDate === null) $expireDate = date('Y-m-d', strtotime('+30 days'));
        $params = [
            'username' => $username,
            'password' => $password,
            'webdx'    => $webSpace,
            'sqldx'     => $dbSpace,
            'sizemax'   => $flow,
            'type'      => 2,
            'ymbds'     => $domainLimit,
            'dqtime'    => $expireDate,
        ];
        $result = self::sendRequest('kt', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '主机开通成功', 'username' => $username];
        }
        return ['success' => false, 'message' => '主机开通失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function renewHost($username, $expireDate, $server = null) {
        $params = ['username' => $username, 'setdate' => $expireDate];
        $result = self::sendRequest('xf', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '主机续费成功'];
        }
        return ['success' => false, 'message' => '主机续费失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function deleteHost($username, $server = null) {
        $params = ['username' => $username];
        $result = self::sendRequest('tz', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '主机删除成功'];
        }
        return ['success' => false, 'message' => '主机删除失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function suspendHost($username, $server = null) {
        $params = ['username' => $username];
        $result = self::sendRequest('zt', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '主机暂停成功'];
        }
        return ['success' => false, 'message' => '主机暂停失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function unsuspendHost($username, $server = null) {
        $params = ['username' => $username];
        $result = self::sendRequest('jc', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '主机解除暂停成功'];
        }
        return ['success' => false, 'message' => '主机解除暂停失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function resetPassword($username, $password, $server = null) {
        $params = ['username' => $username, 'password' => $password];
        $result = self::sendRequest('czmm', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'message' => '密码重置成功'];
        }
        return ['success' => false, 'message' => '密码重置失败：' . ($result['msg'] ?? '未知错误')];
    }

    public static function queryHost($username, $server = null) {
        $params = ['username' => $username];
        $result = self::sendRequest('cfif', $params, $server);
        if (isset($result['code']) && $result['code'] == 200) {
            return ['success' => true, 'data' => $result];
        }
        return ['success' => false, 'message' => '查询失败：' . ($result['msg'] ?? '未知错误')];
    }
}
