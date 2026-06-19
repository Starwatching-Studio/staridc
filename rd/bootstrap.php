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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
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
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>' . h($title) . '</title><link rel="stylesheet" href="' . h($cssUrl) . '">' . $extra . '</head><body><div class="app"><header class="header"><div class="header-inner"><a class="logo" href="' . h($base) . '/index.php">' . h($siteName) . '</a><nav class="nav">';
    if ($loggedIn) {
        echo '<a href="' . h($base) . '/cart.php">选购主机</a><a href="' . h($base) . '/personalpanel.php">个人中心</a><a href="' . h($base) . '/rd/logout.php">退出</a>';
    } else {
        echo '<a href="' . h($base) . '/cart.php">选购主机</a><a href="' . h($base) . '/login.php">登录/注册</a>';
    }
    echo '</nav></div></header><main class="main">';
    $GLOBALS['_show_announce'] = $showAnnounce;
    $GLOBALS['_announcement'] = $announcement;
}

function renderFooter() {
    $siteName = h(conf('site_name', '云主机'));
    echo '</main><footer class="footer"><div class="footer-inner"><p>&copy; ' . date('Y') . ' ' . $siteName . '</p></div></footer>';
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
    echo '</div></body></html>';
}

logVisit();
