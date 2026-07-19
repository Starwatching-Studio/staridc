<?php
/**
 * 单点登录（SSO）共享库
 * 用于打通：商城 54188.wsalwlgu.xyz  ⇄  虚拟主机面板 www.wsalwlgu.xyz
 *
 * 依赖 rd/bootstrap.php 提供的：$DB、conf()、$_SESSION、redirect()、h()、
 *                       getUser()、isLogin()、requireLogin()、setRememberToken()、getClientIp()
 *
 * 原理（无状态、可跨独立数据库）：
 *   1. 源站点（已登录）调用 sso_login.php，以登录用户的邮箱生成带 HMAC-SHA256 签名的短期令牌；
 *   2. 浏览器被重定向到对方站点的 sso_callback.php?token=...；
 *   3. 对方站点校验签名、时效与重放（一次性 nonce），无误后以邮箱为账号键登录或自动开户；
 *   4. 建立对方站点的会话（写入 $_SESSION['user_id']）。
 */

if (defined('SSO_LIB_LOADED')) return;
define('SSO_LIB_LOADED', true);

// 默认共享密钥：两站必须一致。可通过后台 config 表 sso_secret 覆盖。
if (!defined('SSO_DEFAULT_SECRET')) define('SSO_DEFAULT_SECRET', 'StarIDC-SSO-2026-Default-Secret-ChangeMe');
// 令牌有效期（秒），建议 30~120
if (!defined('SSO_TOKEN_TTL')) define('SSO_TOKEN_TTL', 60);

function ssoEnabled() {
    return conf('sso_enabled', '1') === '1';
}

function ssoSecret() {
    $s = trim(conf('sso_secret', ''));
    return $s !== '' ? $s : SSO_DEFAULT_SECRET;
}

/**
 * 合作伙伴站点基础地址。
 * 优先读取后台配置 sso_partner_url；未配置时按当前域名自动推断（仅支持这两个站点）。
 */
function ssoPartnerUrl() {
    $c = trim(conf('sso_partner_url', ''));
    if ($c !== '') return rtrim($c, '/');
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (strpos($host, '54188') !== false) return 'https://www.wsalwlgu.xyz';
    if (strpos($host, 'www.wsalwlgu.xyz') !== false || $host === 'wsalwlgu.xyz') return 'https://54188.wsalwlgu.xyz';
    return '';
}

/** 当前站点是商城（54188）还是面板（www） */
function ssoIsMall() {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    return strpos($host, '54188') !== false;
}

function ssoB64urlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function ssoB64urlDecode($data) {
    $data = (string)$data;
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * 生成令牌（源站点调用）。返回 base64url(json).hmac
 */
function ssoBuildToken($email) {
    $payload = [
        'e' => $email,
        't' => time(),
        'n' => bin2hex(random_bytes(12)),
    ];
    $b64 = ssoB64urlEncode(json_encode($payload));
    $sig = hash_hmac('sha256', $b64, ssoSecret());
    return $b64 . '.' . $sig;
}

/**
 * 校验令牌。成功返回邮箱，失败返回 false。
 */
function ssoVerifyToken($token) {
    if (!is_string($token) || strpos($token, '.') === false) return false;
    list($b64, $sig) = explode('.', $token, 2);
    $expected = hash_hmac('sha256', $b64, ssoSecret());
    if (!function_exists('hash_equals') || !hash_equals($expected, $sig)) return false;
    $payload = json_decode(ssoB64urlDecode($b64), true);
    if (!is_array($payload) || empty($payload['e']) || empty($payload['t']) || empty($payload['n'])) return false;
    if (abs(time() - intval($payload['t'])) > SSO_TOKEN_TTL) return false;
    if (!ssoNonceIsFresh($payload['n'])) return false;
    ssoNonceConsume($payload['n']);
    return $payload['e'];
}

function ssoNonceTable() {
    global $DB;
    $DB->exec("CREATE TABLE IF NOT EXISTS sso_nonces (
        nonce VARCHAR(32) NOT NULL PRIMARY KEY,
        expire_at DATETIME NOT NULL,
        INDEX (expire_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ssoNonceIsFresh($nonce) {
    global $DB;
    try {
        ssoNonceTable();
        $stmt = $DB->prepare("SELECT nonce FROM sso_nonces WHERE nonce=? AND expire_at>NOW()");
        $stmt->execute([$nonce]);
        if ($stmt->fetch()) return false; // 已使用过，拒绝重放
        return true;
    } catch (Exception $e) {
        return true; // 表不可用时降级放行
    }
}

function ssoNonceConsume($nonce) {
    global $DB;
    try {
        ssoNonceTable();
        $expire = date('Y-m-d H:i:s', time() + SSO_TOKEN_TTL + 120);
        $DB->prepare("INSERT IGNORE INTO sso_nonces(nonce,expire_at) VALUES(?,?)")->execute([$nonce, $expire]);
        $DB->exec("DELETE FROM sso_nonces WHERE expire_at<NOW()");
    } catch (Exception $e) {}
}

/**
 * 以邮箱为账号键查找用户；若不存在则自动开户（SSO 专用户，随机密码，仅可经 SSO 登录）。
 * 成功建立会话（写入 $_SESSION['user_id']）并返回 true。
 */
function ssoLoginByEmail($email, $remember = true) {
    global $DB;
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

    $stmt = $DB->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // 自动开户
        try {
            $hashed = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $nick = 'USER' . strtoupper(substr(md5($email . microtime()), 0, 6));
            $stmt2 = $DB->prepare("INSERT INTO users(email,nickname,password) VALUES(?,?,?)");
            $stmt2->execute([$email, $nick, $hashed]);
            $uid = $DB->lastInsertId();
            $stmt = $DB->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$uid]);
            $user = $stmt->fetch();
        } catch (Exception $e) {
            return false; // 开户失败（如表结构差异），交由调用方引导注册
        }
    }
    if (!$user) return false;

    $_SESSION['user_id'] = $user['id'];
    if ($remember && function_exists('setRememberToken')) {
        setRememberToken($user['id']);
    }
    if (function_exists('getClientIp')) $ip = getClientIp();
    else $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $DB->prepare("UPDATE users SET last_login_time=?, last_login_ip=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $ip, $user['id']]);
    } catch (Exception $e) {}

    // 兼容面板侧可能存在的 admin 会话键（避免误判为管理员）
    unset($_SESSION['admin_id']);
    return true;
}

/** 校验跳转目标，防御开放重定向 */
function ssoSafeRedirect($path) {
    $path = trim($path ?? 'personalpanel.php');
    if (preg_match('#^(https?:)?//#i', $path) || strpos($path, '..') !== false) {
        $path = 'personalpanel.php';
    }
    return $path;
}
