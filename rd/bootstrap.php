<?php
if (!defined('IN_SYS')) define('IN_SYS', true);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__) . '/');
if (!defined('RD_ROOT')) define('RD_ROOT', __DIR__ . '/');
if (!defined('DATA_ROOT')) define('DATA_ROOT', ROOT . 'data/');
if (!defined('THEME_ROOT')) define('THEME_ROOT', ROOT . 'theme/');
if (!defined('MAIL_ROOT')) define('MAIL_ROOT', ROOT . 'mail/');

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('PRC');
session_start();

// ========== CSRF 防护 ==========
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfToken() {
    return generateCsrfToken();
}

function csrfField() {
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['_csrf'] ?? '';
    if (empty($token)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// 全局错误处理：捕获致命错误，显示友好提示而不是白屏
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        if ($isAjax || (defined('API_CALL') && API_CALL)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['code' => 500, 'message' => 'Server internal error. Please try again later or contact the administrator.'], JSON_UNESCAPED_UNICODE);
        } else {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
                http_response_code(500);
            }
            $siteName = defined('SITE_NAME') ? SITE_NAME : 'StarIDC';
            echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>系统错误 - ' . $siteName . '</title>';
            echo '<style>body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box}.card{background:#fff;border-radius:16px;padding:40px;max-width:540px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center}.icon{font-size:3rem;margin-bottom:16px}.card h2{font-size:1.4rem;color:#e74c3c;margin:0 0 12px}.card p{color:#64748b;font-size:.92rem;line-height:1.7;margin:0 0 24px}.card .hint{background:#fef3c7;border-radius:8px;padding:12px 16px;font-size:.85rem;color:#92400e;text-align:left;line-height:1.6;word-break:break-all}.btn{display:inline-block;margin-top:16px;padding:10px 24px;background:#6366f1;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem}</style>';
            echo '</head><body><div class="card"><div class="icon">⚠</div><h2>系统暂时出现错误</h2><p>很抱歉，服务器遇到了一个意外错误，我们正在努力修复中。<br>请稍后再试，或联系管理员。</p>';

            // 调试模式：显示错误详情
            $debug = defined('DEBUG') ? DEBUG : false;
            if ($debug) {
                echo '<div class="hint"><strong>错误详情（调试模式）</strong><br>类型: ' . $error['type'] . '<br>文件: ' . $error['file'] . ' (第 ' . $error['line'] . ' 行)<br>信息: ' . htmlspecialchars($error['message']) . '</div>';
            }
            echo '<a href="javascript:location.reload()" class="btn">刷新页面</a></div></body></html>';
        }
        exit;
    }
});

set_exception_handler(function($e) {
    while (ob_get_level()) ob_end_clean();
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
           || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    if ($isAjax || (defined('API_CALL') && API_CALL)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['code' => 500, 'message' => 'Server error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
        }
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'StarIDC';
        echo '<!DOCTYPE html><html lang="zh"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>系统错误 - ' . $siteName . '</title>';
        echo '<style>body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box}.card{background:#fff;border-radius:16px;padding:40px;max-width:540px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.08);text-align:center}.icon{font-size:3rem;margin-bottom:16px}.card h2{font-size:1.4rem;color:#e74c3c;margin:0 0 12px}.card p{color:#64748b;font-size:.92rem;line-height:1.7;margin:0 0 24px}.card .hint{background:#fef3c7;border-radius:8px;padding:12px 16px;font-size:.85rem;color:#92400e;text-align:left;line-height:1.6;word-break:break-all}.btn{display:inline-block;margin-top:16px;padding:10px 24px;background:#6366f1;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem}</style>';
        echo '</head><body><div class="card"><div class="icon">⚠</div><h2>系统暂时出现错误</h2><p>很抱歉，服务器遇到了一个意外错误，我们正在努力修复中。<br>请稍后再试，或联系管理员。</p>';
        $debug = defined('DEBUG') ? DEBUG : false;
        if ($debug) {
            echo '<div class="hint"><strong>错误详情（调试模式）</strong><br>类型: ' . get_class($e) . '<br>文件: ' . $e->getFile() . ' (第 ' . $e->getLine() . ' 行)<br>信息: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        echo '<a href="javascript:location.reload()" class="btn">刷新页面</a></div></body></html>';
    }
    exit;
});

function checkInstall() {
    if (!file_exists(ROOT . 'config.php')) {
        $script = $_SERVER['SCRIPT_NAME'];
        if (strpos($script, '/install/') === false) {
            header('Location: ' . rtrim(dirname($script), '/\\') . '/install/');
            exit;
        }
    }
}
checkInstall();

$DB = null;
$CONF = [];

if (file_exists(ROOT . 'config.php')) {
    include ROOT . 'config.php';
    if (isset($dbconfig)) {
        try {
            $dsn = 'mysql:host=' . $dbconfig['host'] . ';port=' . $dbconfig['port'] . ';dbname=' . $dbconfig['dbname'] . ';charset=utf8mb4';
            $DB = new PDO($dsn, $dbconfig['user'], $dbconfig['pwd'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
        loadConfig();
    }
}

// CSRF 全局校验（豁免外部回调和 API）
$csrfExempt = ['pay_notify.php', 'pay_return.php'];
$_scriptBase = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_isApi = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($_scriptBase, $csrfExempt) && !$_isApi) {
    if (!verifyCsrf()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['code' => 403, 'message' => 'CSRF token validation failed'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(403);
            die('<h1>403</h1><p>安全验证失败，请返回上一页刷新后重试。</p>');
        }
        exit;
    }
}

function loadConfig() {
    global $DB, $CONF;
    $CONF = [];
    try {
        $rows = $DB->query("SELECT k,v FROM config")->fetchAll();
        foreach ($rows as $row) {
            $CONF[$row['k']] = $row['v'];
        }
    } catch (Exception $e) {}
}

function conf($key, $default = '') {
    global $CONF;
    return isset($CONF[$key]) ? $CONF[$key] : $default;
}

// ========== 多语言 ==========
$LANG = [];
function loadLanguage($lang = null) {
    global $LANG;
    if ($lang === null) {
        // 优先级：GET > session > cookie > 浏览器 > 默认中文
        if (!empty($_GET['lang'])) {
            $lang = $_GET['lang'];
        } elseif (!empty($_SESSION['lang'])) {
            $lang = $_SESSION['lang'];
        } elseif (!empty($_COOKIE['lang'])) {
            $lang = $_COOKIE['lang'];
        } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
            $lang = ($lang === 'zh') ? 'zh_CN' : 'en_US';
        } else {
            $lang = 'zh_CN';
        }
    }
    $allowed = ['zh_CN', 'en_US'];
    if (!in_array($lang, $allowed)) $lang = 'zh_CN';
    
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + 86400 * 365, '/', '', false, false);
    
    $file = ROOT . 'lang/' . $lang . '.php';
    if (file_exists($file)) {
        $LANG = include $file;
    } else {
        $LANG = [];
    }
    return $lang;
}
function L($key, $default = '') {
    global $LANG;
    return isset($LANG[$key]) ? $LANG[$key] : ($default ?: $key);
}
loadLanguage();

function setConf($key, $value) {
    global $DB;
    $stmt = $DB->prepare("INSERT INTO config(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=?");
    $stmt->execute([$key, $value, $value]);
}

// 根据 server_id 获取 MNBT 服务器配置，返回 null 则回退到旧版 config
function getServer($serverId) {
    if (empty($serverId)) return null;
    global $DB;
    $stmt = $DB->prepare("SELECT * FROM mnbt_servers WHERE id=? AND status=1");
    $stmt->execute([$serverId]);
    $server = $stmt->fetch();
    return $server ?: null;
}

function getTheme() {
    $t = conf('theme', 'nomorphism');
    if (!is_dir(THEME_ROOT . $t)) $t = 'nomorphism';
    return $t;
}

function themeUrl() {
    return 'theme/' . getTheme() . '/';
}

function siteUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $script = $_SERVER['SCRIPT_NAME'];
    $base = rtrim(dirname($script), '/\\');
    if ($base === '\\' || $base === '/') $base = '';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $base . '/';
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function jsonExit($code, $msg = '', $data = null) {
    http_response_code($code >= 100 && $code < 600 ? $code : 200);
    header('Content-Type: application/json; charset=utf-8');
    $result = ['code' => $code, 'message' => $msg];
    if ($data !== null) $result['data'] = $data;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function isLogin() {
    return !empty($_SESSION['user_id']);
}

function isAdmin() {
    return !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLogin()) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        redirect($base . '/login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        redirect($base . '/admin/');
    }
}

function getUser() {
    global $DB;
    if (isLogin()) {
        $stmt = $DB->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    if (!empty($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $DB->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            return $user;
        }
    }
    return null;
}

function setRememberToken($userId) {
    global $DB;
    $token = bin2hex(random_bytes(32));
    $stmt = $DB->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
    $stmt->execute([$token, $userId]);
    setcookie('remember_token', $token, time() + 86400 * 7, '/', '', false, true);
}

function clearRememberToken($userId) {
    global $DB;
    $stmt = $DB->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    setcookie('remember_token', '', time() - 86400, '/', '', false, true);
}

function getAdmin() {
    global $DB;
    if (!isAdmin()) return null;
    $stmt = $DB->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

function getClientIp() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logVisit() {
    global $DB;
    if (!$DB) return;
    $ip = getClientIp();
    $today = date('Y-m-d');
    try {
        $stmt = $DB->prepare("INSERT IGNORE INTO visit_logs(ip,visit_date) VALUES(?,?)");
        $stmt->execute([$ip, $today]);
    } catch (Exception $e) {}
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function parseMd($text) {
    $text = h($text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    $text = preg_replace('/^### (.+)$/m', '<strong>$1</strong>', $text);
    $text = preg_replace('/^## (.+)$/m', '<strong style="font-size:1.1em">$1</strong>', $text);
    $text = preg_replace('/^# (.+)$/m', '<strong style="font-size:1.2em">$1</strong>', $text);
    $text = preg_replace('/^- (.+)$/m', '• $1', $text);
    $text = preg_replace('/\n/', '<br>', $text);
    return $text;
}

function genOrderNo() {
    return date('YmdHis') . mt_rand(100, 999);
}

function genVhostAccount($userId, $modelId) {
    $digits = mt_rand(1000, 9999);
    $letters = '';
    for ($i = 0; $i < 3; $i++) $letters .= chr(mt_rand(97, 122)); // a-z
    return $digits . $letters;
}

function genVhostPassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $pwd = '';
    for ($i = 0; $i < 12; $i++) $pwd .= $chars[mt_rand(0, strlen($chars) - 1)];
    return $pwd;
}

function getCartCount($userId) {
    global $DB;
    $stmt = $DB->prepare("SELECT SUM(quantity) FROM cart_items WHERE user_id=?");
    $stmt->execute([$userId]);
    return intval($stmt->fetchColumn()) ?: 0;
}

function getCartItems($userId) {
    global $DB;
    $stmt = $DB->prepare("SELECT ci.*, vm.name as model_name, vm.price, vm.is_elastic, vm.web_space, vm.db_space, vm.flow, vm.domain_limit FROM cart_items ci LEFT JOIN vhost_models vm ON ci.model_id=vm.id WHERE ci.user_id=? ORDER BY ci.created_at");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll();
    $total = 0;
    foreach ($items as &$it) {
        $months = ['month'=>1,'quarter'=>3,'half_year'=>6,'year'=>12,'2year'=>24,'3year'=>36,'5year'=>60,'10year'=>120][$it['duration_type']] ?? 1;
        $durDiscount = 0;
        $dStmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id=? AND duration_type=? AND enabled=1");
        $dStmt->execute([$it['model_id'], $it['duration_type']]);
        $durRow = $dStmt->fetch();
        if ($durRow) $durDiscount = intval($durRow['discount']);
        $basePrice = intval(ceil($it['price'] * $months * (100 - $durDiscount) / 100));
        $elasticSurcharge = 0;
        if ($it['is_elastic'] && !empty($it['elastic_values'])) {
            $ev = json_decode($it['elastic_values'], true) ?: [];
            $eStmt = $DB->prepare("SELECT field_name, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
            $eStmt->execute([$it['model_id']]);
            foreach ($eStmt->fetchAll() as $ec) {
                $fn = $ec['field_name'];
                $val = isset($ev[$fn]) ? intval($ev[$fn]) : intval($it[$fn]);
                if ($val > intval($it[$fn]) && intval($ec['step']) > 0) {
                    $elasticSurcharge += intval(($val - intval($it[$fn])) / intval($ec['step']) * intval($ec['unit_price']));
                }
            }
        }
        $it['unit_price'] = $basePrice + $elasticSurcharge;
        $it['total_price'] = $it['unit_price'] * intval($it['quantity']);
        $total += $it['total_price'];
    }
    return ['items' => $items, 'total' => $total, 'count' => count($items)];
}

class Captcha {
    public static function generate($account, $type = 'register', $expire = 300) {
        global $DB;
        $code = '';
        for ($i = 0; $i < 5; $i++) $code .= mt_rand(0, 9);
        $expireTime = date('Y-m-d H:i:s', time() + $expire);
        $stmt = $DB->prepare("INSERT INTO verify_codes(account,code,type,expire_time) VALUES(?,?,?,?)");
        $stmt->execute([$account, $code, $type, $expireTime]);
        return $code;
    }

    public static function verify($account, $code, $type = 'register') {
        global $DB;
        $now = date('Y-m-d H:i:s');
        $stmt = $DB->prepare("SELECT * FROM verify_codes WHERE account=? AND code=? AND type=? AND expire_time>? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$account, $code, $type, $now]);
        $row = $stmt->fetch();
        if ($row) {
            self::delete($account, $type);
            return true;
        }
        return false;
    }

    public static function delete($account, $type = 'register') {
        global $DB;
        $stmt = $DB->prepare("DELETE FROM verify_codes WHERE account=? AND type=?");
        $stmt->execute([$account, $type]);
    }

    public static function canSend($account, $type = 'register', $interval = 60) {
        global $DB;
        $after = date('Y-m-d H:i:s', time() - $interval);
        $stmt = $DB->prepare("SELECT COUNT(*) as cnt FROM verify_codes WHERE account=? AND type=? AND created_at>?");
        $stmt->execute([$account, $type, $after]);
        $row = $stmt->fetch();
        return $row['cnt'] == 0;
    }

    public static function image($code = '') {
        if (empty($code)) {
            $code = '';
            for ($i = 0; $i < 5; $i++) $code .= mt_rand(0, 9);
        }
        $_SESSION['captcha_code'] = $code;
        $_SESSION['captcha_time'] = time();
        $w = 130;
        $h = 44;
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 224, 229, 236);
        imagefill($img, 0, 0, $bg);
        for ($i = 0; $i < 6; $i++) {
            $lc = imagecolorallocate($img, mt_rand(160, 200), mt_rand(160, 200), mt_rand(160, 200));
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $lc);
        }
        for ($i = 0; $i < 80; $i++) {
            $pc = imagecolorallocate($img, mt_rand(140, 200), mt_rand(140, 200), mt_rand(140, 200));
            imagesetpixel($img, mt_rand(0, $w), mt_rand(0, $h), $pc);
        }
        $tc = imagecolorallocate($img, mt_rand(40, 100), mt_rand(40, 100), mt_rand(80, 140));
        $x = 12;
        for ($i = 0; $i < strlen($code); $i++) {
            imagestring($img, 5, $x, mt_rand(8, 16), $code[$i], $tc);
            $x += 22;
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store');
        imagepng($img);
        imagedestroy($img);
        exit;
    }

    public static function checkImage($input) {
        if (empty($_SESSION['captcha_code'])) return false;
        if (time() - $_SESSION['captcha_time'] > 300) return false;
        return strtoupper($input) === strtoupper($_SESSION['captcha_code']);
    }

    public static function clearImage() {
        unset($_SESSION['captcha_code'], $_SESSION['captcha_time']);
    }
}

class Mailer {
    public static function send($to, $subject, $body) {
        if (!file_exists(MAIL_ROOT . 'vendor/autoload.php')) return false;
        require_once MAIL_ROOT . 'vendor/autoload.php';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = conf('mail_host', '');
            $mail->SMTPAuth = true;
            $mail->Username = conf('mail_user', '');
            $mail->Password = conf('mail_pass', '');
            $mail->SMTPSecure = conf('mail_security', 'ssl');
            $mail->Port = intval(conf('mail_port', 465));
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(conf('mail_user', ''), conf('mail_name', '云主机系统'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function sendVerifyCode($email, $code) {
        if (!self::canSend($email)) {
            return ['status' => false, 'msg' => '发送太频繁，请稍后再试'];
        }
        $subject = '验证码 - ' . conf('site_name', '云主机');
        $body = '【' . $code . '】5分钟内有效，请勿泄露给他人。';
        $result = self::send($email, $subject, $body);
        if ($result) {
            return ['status' => true, 'msg' => '验证码已发送'];
        }
        return ['status' => false, 'msg' => '邮件发送失败，请检查邮箱配置'];
    }

    private static function canSend($email, $interval = 60) {
        return Captcha::canSend($email, 'register', $interval);
    }

    /**
     * 发送业务通知邮件（不受验证码频率限制，邮件开关关闭时静默跳过）
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件正文
     * @return bool
     */
    public static function sendNotify($to, $subject, $body) {
        if (conf('mail_enabled') !== '1') return false;
        return self::send($to, $subject, $body);
    }
}

function renderHeader($title = '', $extra = '') {
    $siteName = h(conf('site_name', '云主机'));
    $theme = getTheme();
    $cssUrl = themeUrl() . 'style.css';
    $announcement = conf('announcement', '');
    $showAnnounce = !empty($announcement) && isLogin();
    if (empty($title)) $title = $siteName;
    else $title = $title . ' - ' . $siteName;
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $user = getUser();
    $loggedIn = isLogin();
    $cartCSS = '';
    if ($loggedIn) {
        $cartCSS = '<style>
.cart-sidebar { position:fixed;top:0;right:-400px;width:380px;max-width:90vw;height:100vh;background:#fff;z-index:10001;box-shadow:-4px 0 20px rgba(0,0,0,.15);transition:right .3s ease;display:flex;flex-direction:column }
.cart-sidebar.open { right:0 }
.cart-sidebar-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #eee }
.cart-sidebar-header h3 { margin:0;font-size:1.1rem }
.cart-close { background:none;border:none;font-size:1.4rem;cursor:pointer;color:#999;padding:0 4px }
.cart-close:hover { color:#333 }
.cart-sidebar-body { flex:1;overflow-y:auto;padding:16px 20px }
.cart-empty { text-align:center;color:#999;padding:40px 0;font-size:.95rem }
.cart-item { display:flex;align-items:center;padding:12px;margin-bottom:10px;background:#f8f9fa;border-radius:10px;gap:10px }
.cart-item-info { flex:1 }
.cart-item-name { font-weight:600;font-size:.9rem;margin-bottom:4px }
.cart-item-detail { font-size:.8rem;color:#888 }
.cart-item-price { font-weight:600;color:var(--primary, #6366f1);white-space:nowrap;font-size:.9rem }
.cart-item-del { background:none;border:none;color:#ccc;cursor:pointer;font-size:1.1rem;padding:4px;transition:color .2s }
.cart-item-del:hover { color:#e74c3c }
.cart-sidebar-footer { padding:16px 20px;border-top:1px solid #eee;display:flex;align-items:center;justify-content:space-between;background:#fff }
.cart-total { font-size:1rem }
.cart-total strong { color:var(--primary, #6366f1);font-size:1.1rem }
.btn-checkout { padding:10px 24px;background:var(--primary, #6366f1);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600;font-size:.9rem;transition:opacity .2s }
.btn-checkout:hover { opacity:.88 }
.btn-checkout:disabled { opacity:.5;cursor:not-allowed }
.cart-overlay { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.3);z-index:10000;display:none }
.cart-overlay.show { display:block }
.nav-cart { position:relative;cursor:pointer;display:inline-flex;align-items:center;padding:6px 10px;border-radius:8px;transition:background .2s }
.nav-cart:hover { background:rgba(255,255,255,.1) }
.nav-cart .cart-badge { position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:.7rem;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-weight:700;padding:0 4px }
.nav-cart .cart-badge:empty { display:none }
.api-loading-overlay { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10002;display:none;align-items:center;justify-content:center;flex-direction:column }
.api-loading-overlay.show { display:flex }
.api-loading-spinner { width:48px;height:48px;border:4px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:16px }
@keyframes spin { to{transform:rotate(360deg)} }
.api-loading-text { color:#fff;font-size:1rem;font-weight:500 }
.lang-switch { display:inline-flex;align-items:center;gap:4px;font-size:.85rem }
.lang-switch a { color:inherit;text-decoration:none;padding:2px 6px;border-radius:4px;opacity:.6;transition:opacity .2s }
.lang-switch a:hover,.lang-switch a.active { opacity:1 }
.lang-switch .sep { opacity:.3 }
</style>';
    }
    echo '<!DOCTYPE html><html lang="' . (isset($_SESSION['lang']) && $_SESSION['lang'] === 'en_US' ? 'en' : 'zh-CN') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><meta name="csrf-token" content="' . h(csrfToken()) . '"><title>' . h($title) . '</title><link rel="stylesheet" href="' . h($cssUrl) . '">' . $extra . $cartCSS . '</head><body><div class="app"><header class="header"><div class="header-inner"><a class="logo" href="' . h($base) . '/index.php">' . h($siteName) . '</a><nav class="nav">';
    $currentLang = $_SESSION['lang'] ?? 'zh_CN';
    $langSwitch = '<span class="lang-switch"><a href="?lang=zh_CN"' . ($currentLang === 'zh_CN' ? ' class="active"' : '') . '>中</a><span class="sep">|</span><a href="?lang=en_US"' . ($currentLang === 'en_US' ? ' class="active"' : '') . '>EN</a></span>';
    if ($loggedIn) {
        echo '<a href="' . h($base) . '/cart.php">' . L('nav_buy') . '</a><a href="' . h($base) . '/personalpanel.php">' . L('nav_panel') . '</a><span class="nav-cart" onclick="toggleCart()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg><span class="cart-badge" id="cartNavBadge"></span></span>' . $langSwitch . '<a href="' . h($base) . '/rd/logout.php">' . L('logout') . '</a>';
    } else {
        echo '<a href="' . h($base) . '/cart.php">' . L('nav_buy') . '</a><a href="' . h($base) . '/login.php">' . L('nav_login_register') . '</a>' . $langSwitch;
    }
    echo '</nav></div></header><main class="main">';
    $GLOBALS['_show_announce'] = $showAnnounce;
    $GLOBALS['_announcement'] = $announcement;
}

function renderFooter() {
    $siteName = h(conf('site_name', '云主机'));
    $loggedIn = isLogin();
    echo '</main><footer class="footer"><div class="footer-inner"><p>&copy; ' . date('Y') . ' ' . $siteName . '</p></div></footer>';
    echo '<div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>';
    if ($loggedIn) {
        echo '<!-- 购物车侧边栏 -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-sidebar-header">
        <h3>🛒 ' . L('cart_title') . '</h3>
        <button type="button" class="cart-close" onclick="toggleCart()">✕</button>
    </div>
    <div class="cart-sidebar-body" id="cartSidebarBody">
        <div class="cart-empty">' . L('cart_empty') . '</div>
    </div>
    <div class="cart-sidebar-footer" id="cartSidebarFooter" style="display:none">
        <div class="cart-total">' . L('cart_total') . '：<strong id="cartTotal">0</strong> ' . L('buy_points') . '</div>
        <button type="button" class="btn-checkout" onclick="cartCheckout()">' . L('cart_checkout') . '</button>
    </div>
</div>
<div class="api-loading-overlay" id="apiLoadingOverlay">
    <div class="api-loading-spinner"></div>
    <div class="api-loading-text">' . L('api_loading') . '</div>
</div>
<script>
var _L = Object.assign(_L || {}, {
    cartEmpty: ' . json_encode(L('cart_empty'), JSON_UNESCAPED_UNICODE) . ',
    cartTotal: ' . json_encode(L('cart_total'), JSON_UNESCAPED_UNICODE) . ',
    cartCheckout: ' . json_encode(L('cart_checkout'), JSON_UNESCAPED_UNICODE) . ',
    cartChecking: ' . json_encode(L('cart_checking'), JSON_UNESCAPED_UNICODE) . ',
    cartConfirm: ' . json_encode(L('cart_checkout_confirm'), JSON_UNESCAPED_UNICODE) . ',
    cartSuccess: ' . json_encode(L('cart_checkout_success'), JSON_UNESCAPED_UNICODE) . ',
    cartFail: ' . json_encode(L('cart_checkout_fail'), JSON_UNESCAPED_UNICODE) . ',
    buyUnit: ' . json_encode(L('buy_unit'), JSON_UNESCAPED_UNICODE) . ',
    buyPoints: ' . json_encode(L('buy_points'), JSON_UNESCAPED_UNICODE) . ',
    buySuccess: ' . json_encode(L('buy_success'), JSON_UNESCAPED_UNICODE) . ',
    durMonth: ' . json_encode(L('buy_month'), JSON_UNESCAPED_UNICODE) . ',
    durQuarter: ' . json_encode(L('buy_quarter'), JSON_UNESCAPED_UNICODE) . ',
    durHalfYear: ' . json_encode(L('buy_half_year'), JSON_UNESCAPED_UNICODE) . ',
    durYear: ' . json_encode(L('buy_year'), JSON_UNESCAPED_UNICODE) . ',
    dur2year: ' . json_encode(L('buy_2year'), JSON_UNESCAPED_UNICODE) . ',
    dur3year: ' . json_encode(L('buy_3year'), JSON_UNESCAPED_UNICODE) . ',
    dur5year: ' . json_encode(L('buy_5year'), JSON_UNESCAPED_UNICODE) . ',
    dur10year: ' . json_encode(L('buy_10year'), JSON_UNESCAPED_UNICODE) . '
});
function toggleCart() {
    var sidebar = document.getElementById("cartSidebar");
    var overlay = document.getElementById("cartOverlay");
    if (!sidebar || !overlay) return;
    sidebar.classList.toggle("open");
    overlay.classList.toggle("show");
    if (sidebar.classList.contains("open")) loadCart();
}
function showLoading() {
    var el = document.getElementById("apiLoadingOverlay");
    if (el) el.classList.add("show");
}
function hideLoading() {
    var el = document.getElementById("apiLoadingOverlay");
    if (el) el.classList.remove("show");
}
function loadCart() {
    var body = document.getElementById("cartSidebarBody");
    var footer = document.getElementById("cartSidebarFooter");
    if (!body) return;
    var fd = new FormData();
    fd.append("action", "get_cart");
    fetch("cart.php", { method: "POST", body: fd })
        .then(function(r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(function(data) {
            if (!data.items || data.items.length === 0) {
                body.innerHTML = \'<div class="cart-empty">\' + _L.cartEmpty + \'</div>\';
                if (footer) footer.style.display = "none";
            } else {
                var html = "";
                var durLabels = { month: _L.durMonth, quarter: _L.durQuarter, half_year: _L.durHalfYear, year: _L.durYear, "2year": _L.dur2year, "3year": _L.dur3year, "5year": _L.dur5year, "10year": _L.dur10year };
                for (var i = 0; i < data.items.length; i++) {
                    var it = data.items[i];
                    html += \'<div class="cart-item"><div class="cart-item-info"><div class="cart-item-name">\' + it.model_name + \'</div><div class="cart-item-detail">\' + (durLabels[it.duration_type] || it.duration_type) + " × " + it.quantity + _L.buyUnit + \'</div></div><div class="cart-item-price">\' + it.total_price + _L.buyPoints + \'</div><button class="cart-item-del" onclick="removeCartItem(\' + it.id + \')" title="' . L('cart_remove') . '">✕</button></div>\';
                }
                body.innerHTML = html;
                if (footer) footer.style.display = "flex";
                document.getElementById("cartTotal").textContent = data.total;
            }
            updateCartBadge(data.count);
        }).catch(function(e) { console.error("loadCart error:", e); });
}
function removeCartItem(itemId) {
    var fd = new FormData();
    fd.append("action", "remove_from_cart");
    fd.append("item_id", itemId);
    fetch("cart.php", { method: "POST", body: fd })
        .then(function(r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(function(res) {
            if (res.ok) { loadCart(); updateCartBadge(res.cart_count); }
        }).catch(function(e) { console.error("removeCartItem error:", e); });
}
function cartCheckout() {
    if (!confirm(_L.cartConfirm)) return;
    var btn = document.querySelector(".btn-checkout");
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = _L.cartChecking;
    showLoading();
    var fd = new FormData();
    fd.append("action", "cart_checkout");
    fetch("cart.php", { method: "POST", body: fd })
        .then(function(r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(function(res) {
            hideLoading();
            if (res.ok) {
                var msgs = [];
                for (var i = 0; i < res.results.length; i++) {
                    msgs.push(res.results[i].name + ": " + (res.results[i].ok ? _L.buySuccess : res.results[i].message));
                }
                alert(_L.cartSuccess + "\\n" + msgs.join("\\n"));
                loadCart();
                updateCartBadge(res.cart_count);
            } else {
                alert(_L.cartFail);
            }
            btn.disabled = false;
            btn.textContent = _L.cartCheckout;
        }).catch(function(e) { hideLoading(); console.error("cartCheckout error:", e); btn.disabled = false; btn.textContent = _L.cartCheckout; });
}
function updateCartBadge(count) {
    var badge = document.getElementById("cartNavBadge");
    if (badge) { badge.textContent = count > 0 ? count : ""; }
}
// 页面加载时更新购物车徽标
(function(){
    var fd = new FormData();
    fd.append("action", "get_cart");
    fetch("cart.php", { method: "POST", body: fd })
        .then(function(r) { if (!r.ok) throw new Error("HTTP " + r.status); return r.json(); })
        .then(function(data) { updateCartBadge(data.count); })
        .catch(function(e) { console.error("cart badge init error:", e); });
})();
</script>';
    }
    if (!empty($GLOBALS['_show_announce']) && !empty($GLOBALS['_announcement'])) {
        echo '<div id="announceModal" class="modal" style="display:flex">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>📢 公告</h3>
                        <span class="modal-close" onclick="document.getElementById(\'announceModal\').classList.add(\'hidden\');sessionStorage.setItem(\'announceClosed\',\'1\')">&times;</span>
                    </div>
                    <div class="modal-body">' . parseMd($GLOBALS['_announcement']) . '</div>
                    <div class="modal-footer">
                        <button class="btn-primary btn-sm" onclick="document.getElementById(\'announceModal\').classList.add(\'hidden\');sessionStorage.setItem(\'announceClosed\',\'1\')">我知道了</button>
                    </div>
                </div>
            </div>';
        echo '<script>(function(){var m=document.getElementById("announceModal");if(sessionStorage.getItem("announceClosed")){m.style.display="none"}})();</script>';
    }
    // CSRF Token 全局注入 fetch 拦截器
    echo '<script>(function(){var t=document.querySelector("meta[name=csrf-token]");if(!t)return;var tk=t.getAttribute("content");if(!tk)return;var of=window.fetch;window.fetch=function(u,o){o=o||{};var m=(o.method||"GET").toUpperCase();if(m!=="POST")return of.call(this,u,o);var url=(typeof u==="string")?u:((u&&u.url)||"");var so=(!/^https?:\\/\\//i.test(url)&&(url.charAt(0)!=="/"||url.charAt(1)!=="/"))||url.indexOf(window.location.origin)===0;if(!so)return of.call(this,u,o);o.headers=o.headers||{};if(o.headers instanceof Headers){if(!o.headers.has("X-CSRF-TOKEN"))o.headers.set("X-CSRF-TOKEN",tk)}else{if(!o.headers["X-CSRF-TOKEN"])o.headers["X-CSRF-TOKEN"]=tk}return of.call(this,u,o)}})();</script>';
    echo '</div></body></html>';
}

logVisit();
