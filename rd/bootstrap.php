<?php
if (!defined('IN_SYS')) define('IN_SYS', true);
if (!defined('ROOT')) define('ROOT', dirname(__DIR__) . '/');

// ---- 未安装检测：config.php 不存在时自动跳转到安装向导 /install/ ----
if (!file_exists(ROOT . 'config.php')) {
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $installDir = realpath(ROOT . 'install');
    // 当前脚本位于 install 目录内（如安装向导自身）时不跳转，避免循环
    if ($script === false || $installDir === false || strpos($script, $installDir) !== 0) {
        $prefix = '';
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = realpath($_SERVER['DOCUMENT_ROOT']) ?: '';
            $root = realpath(ROOT) ?: ROOT;
            if ($docRoot !== '' && strpos($root, $docRoot) === 0) {
                $prefix = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($root, strlen($docRoot))), '/');
            }
        }
        header('Location: ' . $prefix . '/install/');
        exit;
    }
}

// 提前引入主配置文件，允许其中的 define() 覆盖下方「可选覆盖」默认值（SITE_URL / DEBUG 等）。
if (file_exists(ROOT . 'config.php')) include ROOT . 'config.php';
if (!defined('RD_ROOT')) define('RD_ROOT', __DIR__ . '/');
if (!defined('DATA_ROOT')) define('DATA_ROOT', ROOT . 'data/');
if (!defined('THEME_ROOT')) define('THEME_ROOT', ROOT . 'theme/');
if (!defined('MAIL_ROOT')) define('MAIL_ROOT', ROOT . 'mail/');

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('PRC');
// 管理员/用户登录 Session 长期有效：Cookie 30 天，服务端 Session 文件 30 天
$sessionLifetime = 30 * 86400;
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();


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


$LANG = [];
function loadLanguage($lang = null) {
    global $LANG;
    if ($lang === null) {
        
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

function get_ad_config() {
    static $adCache = null;
    if ($adCache !== null) return $adCache;
    $file = ROOT . 'cache/ad_config.cache.php';
    $ad = null;
    if (is_file($file)) {
        $data = @include $file;
        if (is_array($data)) $ad = $data;
    }
    if (!is_array($ad)) $ad = json_decode(conf('ad_global_config', '{}'), true);
    if (!is_array($ad)) $ad = [];
    if (!isset($ad['enabled'])) $ad['enabled'] = conf('ad_enable', '0') === '1' ? 1 : 0;
    if (!isset($ad['custom_links']) || !is_array($ad['custom_links'])) {
        $ad['custom_links'] = json_decode(conf('ad_config', '[]'), true);
    }
    if (!isset($ad['checkin_ad']) || !is_array($ad['checkin_ad'])) {
        $ad['checkin_ad'] = ['images' => [], 'link' => '', 'wait' => 6, 'interval' => 4];
    }
    // 兼容旧结构：单 image 转为 images 数组
    if (isset($ad['checkin_ad']['image']) && !isset($ad['checkin_ad']['images'])) {
        $ad['checkin_ad']['images'] = [['image' => $ad['checkin_ad']['image'], 'weight' => 5, 'link' => '']];
        unset($ad['checkin_ad']['image']);
    }
    if (!isset($ad['checkin_ad']['images']) || !is_array($ad['checkin_ad']['images'])) {
        $ad['checkin_ad']['images'] = [];
    }
    // 兼容旧版：为每张图片补齐 link 字段
    foreach ($ad['checkin_ad']['images'] as &$img) {
        if (!isset($img['link'])) $img['link'] = '';
    }
    unset($img);
    if (!isset($ad['checkin_ad']['interval']) || intval($ad['checkin_ad']['interval']) < 2) {
        $ad['checkin_ad']['interval'] = 4;
    }
    if (!is_array($ad['custom_links'])) $ad['custom_links'] = [];
    // 自定义广告补齐默认权重与稳定 uid（uid 用于点击统计，避免重排后统计错位）
    foreach ($ad['custom_links'] as &$ci) {
        $w = isset($ci['weight']) ? intval($ci['weight']) : 0;
        $ci['weight'] = $w >= 1 ? $w : 5;
        if (empty($ci['uid'])) {
            $ci['uid'] = 'c' . substr(md5(($ci['name'] ?? '') . '|' . ($ci['url'] ?? '')), 0, 12);
        }
    }
    unset($ci);
    // 新增广告位默认值（登录页广告 / 新用户专享弹窗）
    if (!isset($ad['login_ad']) || !is_array($ad['login_ad'])) {
        $ad['login_ad'] = ['enabled' => 0, 'interval' => 5];
    }
    if (!isset($ad['newuser_ad']) || !is_array($ad['newuser_ad'])) {
        $ad['newuser_ad'] = ['enabled' => 0, 'image' => '', 'link' => ''];
    }
    $adCache = $ad;
    if (!is_dir(ROOT . 'cache')) @mkdir(ROOT . 'cache', 0755, true);
    @file_put_contents($file, '<?php return ' . var_export($ad, true) . ';', LOCK_EX);
    return $adCache;
}

function clearAdConfigCache() {
    $file = ROOT . 'cache/ad_config.cache.php';
    if (is_file($file)) @unlink($file);
}

function getAdGlobal() {
    return get_ad_config();
}

/**
 * 按权重随机挑选一个元素（权重键名由 $weightKey 指定，默认 'weight'）。
 * 权重小于 1 视为 1；空数组返回 null。
 */
function pickWeighted(array $items, $weightKey = 'weight') {
    if (empty($items)) return null;
    $total = 0;
    foreach ($items as $it) {
        $w = isset($it[$weightKey]) ? intval($it[$weightKey]) : 1;
        if ($w < 1) $w = 1;
        $total += $w;
    }
    if ($total <= 0) return $items[array_rand($items)];
    $r = function_exists('random_int') ? random_int(1, $total) : mt_rand(1, $total);
    $acc = 0;
    foreach ($items as $it) {
        $w = isset($it[$weightKey]) ? intval($it[$weightKey]) : 1;
        if ($w < 1) $w = 1;
        $acc += $w;
        if ($r <= $acc) return $it;
    }
    return $items[count($items) - 1];
}

/**
 * 渲染指定广告位的广告块：
 *  - 自定义广告按权重展示，多条时轮播；
 *  - 自定义广告点击时通过 trackAdClick 记录。
 * 仅当全局「广告总开关」开启时输出；login 位还需 login_ad.enabled=1。
 */
function renderLocationAds($location) {
    $ad = get_ad_config();
    if (empty($ad['enabled'])) return;
    $interval = 5;
    if ($location === 'login') {
        $la = $ad['login_ad'] ?? ['enabled' => 0, 'interval' => 5];
        if (empty($la['enabled'])) return;
        $interval = max(2, intval($la['interval'] ?? 5));
    }

    $custom = $ad['custom_links'] ?? [];
    if (!is_array($custom)) $custom = [];
    $items = [];
    foreach ($custom as $c) {
        $url = trim($c['url'] ?? '');
        if ($url === '') continue;
        $uid = $c['uid'] ?? '';
        $name = $c['name'] ?? '广告';
        $attr = $uid !== '' ? ' data-ad-uid="' . h($uid) . '" onclick="trackAdClick(this)"' : '';
        $items[] = '<a class="ad-custom ad-car-item" href="' . h($url) . '" target="_blank" rel="noopener"' . $attr . '>' . h($name) . '</a>';
    }

    if (empty($items)) return;

    // 广告标签样式（只需输出一次）
    static $adLabelCssOnce = false;
    if (!$adLabelCssOnce) {
        $adLabelCssOnce = true;
        echo '<style>.user-ad-block{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;padding:12px;border-radius:12px;background:linear-gradient(135deg,rgba(245,158,11,0.12),rgba(245,158,11,0.04));border:1px solid rgba(245,158,11,0.25);position:relative}.ad-label-tag{display:inline-block;font-size:.68rem;color:#fff;background:rgba(0,0,0,.45);padding:1px 6px;border-radius:3px;line-height:1.5;letter-spacing:.5px}.user-ad-block a{display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.92rem;transition:all .2s}.ad-custom{background:#fff;color:#d97706;border:1px solid rgba(245,158,11,0.3)}.ad-custom:hover{background:#fffbeb;color:#b45309}</style>';
    }

    echo '<div class="user-ad-block"><span class="ad-label-tag">广告</span>';
    if (count($items) === 1) {
        echo $items[0] . '</div>';
    } else {
        echo '<div class="ad-carousel" id="adCar_' . h($location) . '">';
        foreach ($items as $i => $html) {
            echo '<div class="ad-car-item-wrap" style="display:' . ($i === 0 ? 'block' : 'none') . '">' . $html . '</div>';
        }
        echo '</div></div>';
    }
    // 共享 JS（函数定义带重复守卫，多次调用也只定义一次）——必须在调用前输出
    echo '<script>' . adSharedJs() . '</script>';
    if (count($items) > 1) {
        echo '<script>startAdCarousel(\'adCar_' . h($location) . '\',' . intval($interval) . ');</script>';
    }
}

/**
 * 广告相关共享前端 JS（trackAdClick / startAdCarousel），含重复定义守卫。
 */
function adSharedJs() {
    static $once = false;
    if ($once) return '';
    $once = true;
    return <<<JS
if (typeof window.__starAdJs === 'undefined') {
  window.__starAdJs = 1;
  function trackAdClick(el){
    var uid = el.getAttribute('data-ad-uid');
    if (!uid) return;
    try {
      var fd = new FormData(); fd.append('uid', uid);
      if (navigator.sendBeacon) { navigator.sendBeacon('ad_click.php', fd); }
      else { var x = new XMLHttpRequest(); x.open('POST','ad_click.php',true); x.send(fd); }
    } catch(e) {}
  }
  function startAdCarousel(id, interval){
    var box = document.getElementById(id);
    if (!box) return;
    var items = box.querySelectorAll('.ad-car-item-wrap');
    if (items.length < 2) return;
    var idx = 0;
    setInterval(function(){
      items[idx].style.display = 'none';
      idx = (idx + 1) % items.length;
      items[idx].style.display = 'block';
    }, Math.max(2, interval) * 1000);
  }
}
JS;
}

/**
 * 自定义广告点击统计：返回 [ad_uid => ['total','today','d7']]。
 * 不统计无 uid 的广告。
 */
function getAdClickStats() {
    global $DB;
    $out = [];
    try {
        $rs = $DB->query("SELECT ad_uid,
            COUNT(*) AS total,
            SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) AS today,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS d7
            FROM ad_clicks GROUP BY ad_uid");
        foreach ($rs as $r) {
            $out[$r['ad_uid']] = ['total' => (int)$r['total'], 'today' => (int)$r['today'], 'd7' => (int)$r['d7']];
        }
    } catch (Exception $e) {}
    return $out;
}

/**
 * 全局点击趋势（默认近 7 天），返回 [日期 => 次数]。
 */
function getAdClickTrend($days = 7) {
    global $DB;
    $rows = [];
    try {
        $rs = $DB->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ad_clicks"
            . " WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL " . intval($days) . " DAY)"
            . " GROUP BY DATE(created_at)");
        foreach ($rs as $r) $rows[$r['d']] = (int)$r['c'];
    } catch (Exception $e) {}
    return $rows;
}

function signLockAcquire($userId) {
    $dir = DATA_ROOT . 'locks';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = $dir . '/sign_' . intval($userId) . '_' . date('Y-m-d') . '.lock';
    $h = @fopen($file, 'c');
    if ($h === false) return null;
    if (!flock($h, LOCK_EX)) { fclose($h); return null; }
    return $h;
}

function signLockRelease($h) {
    if ($h) { flock($h, LOCK_UN); fclose($h); }
}

function renderCheckinAdModal() {
    $ad = getAdGlobal();
    if (empty($ad['enabled'])) return;
    $ca = $ad['checkin_ad'];
    $imgs = isset($ca['images']) && is_array($ca['images']) ? $ca['images'] : [];
    $link = $ca['link'] ?? '';
    if (empty($imgs) && empty($link)) return;
    $base = rtrim(siteUrl(), '/');
    $placeholder = $base . '/uploads/checkin-ad-placeholder.png';
    $slides = [];
    foreach ($imgs as $it) {
        $img = $it['image'] ?? '';
        if (!$img) continue;
        $src = ($img && strpos($img, 'http') === 0) ? $img : $base . '/' . ltrim($img, '/');
        $w = isset($it['weight']) ? intval($it['weight']) : 5;
        // 单图独立链接：image_link 优先，否则 fallback 到全局 link（兼容旧版）
        $il = isset($it['link']) ? trim($it['link']) : '';
        $slides[] = ['src' => $src, 'weight' => $w >= 1 ? $w : 5, 'link' => $il !== '' ? $il : $link];
    }
    $wait = max(0, intval($ca['wait'] ?? 6));
    $interval = max(2, intval($ca['interval'] ?? 4));
    // 多图时按权重随机选起始图
    $startIdx = 0;
    if (count($slides) > 1) {
        $total = 0;
        foreach ($slides as $s) { $total += $s['weight']; }
        $r = function_exists('random_int') ? random_int(1, $total) : mt_rand(1, $total);
        $acc = 0;
        foreach ($slides as $i => $s) {
            $acc += $s['weight'];
            if ($r <= $acc) { $startIdx = $i; break; }
        }
    }
    $showCarousel = count($slides) > 1;
    ?>
    <style>.ad-label-tag{display:inline-block;font-size:.68rem;color:#fff;background:rgba(0,0,0,.45);padding:1px 6px;border-radius:3px;line-height:1.5;letter-spacing:.5px}</style>
    <div id="checkinAdOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9998" onclick="if(event.target===this)closeCheckinAd()"></div>
    <div id="checkinAdModal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:9999;width:min(90vw,420px);background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)">
        <a id="checkinAdLink" href="<?php echo h($slides ? $slides[$startIdx]['link'] : $link); ?>" target="_blank" rel="noopener" style="display:block;position:relative">
            <span class="ad-label-tag" style="position:absolute;top:8px;left:8px;z-index:2">广告</span>
            <div id="checkinAdSlides" style="position:relative;background:#f3f4f6">
                <?php if (empty($slides)): ?>
                <img id="checkinAdImg" src="<?php echo h($placeholder); ?>" alt="checkin ad" style="width:100%;display:block;max-height:60vh;object-fit:cover">
                <?php else: foreach ($slides as $i => $s): ?>
                <img class="checkin-ad-slide" src="<?php echo h($s['src']); ?>" data-href="<?php echo h($s['link']); ?>" alt="checkin ad" style="width:100%;display:<?php echo $i === $startIdx ? 'block' : 'none'; ?>;max-height:60vh;object-fit:cover;background:#f3f4f6" onerror="this.onerror=null;this.src='<?php echo h($placeholder); ?>'">
                <?php endforeach; endif; ?>
            </div>
        </a>
        <?php if ($showCarousel): ?>
        <div id="checkinAdDots" style="display:flex;gap:6px;justify-content:center;padding:8px 0 0"></div>
        <?php endif; ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-top:1px solid #eee">
            <span id="checkinAdCount" style="font-size:.9rem;color:#555"></span>
            <button id="checkinAdClose" onclick="closeCheckinAd()" disabled style="padding:8px 18px;border:none;border-radius:8px;background:#e5e7eb;color:#9ca3af;cursor:not-allowed;font-weight:600">关闭</button>
        </div>
    </div>
    <script>
    (function(){
        var wait = <?php echo $wait; ?>;
        var interval = <?php echo $interval; ?>;
        var startIdx = <?php echo $startIdx; ?>;
        var validUntil = <?php echo (time() + $wait); ?> * 1000;
        var overlay = document.getElementById('checkinAdOverlay');
        var modal = document.getElementById('checkinAdModal');
        var countEl = document.getElementById('checkinAdCount');
        var closeBtn = document.getElementById('checkinAdClose');
        var slides = Array.prototype.slice.call(document.querySelectorAll('#checkinAdSlides .checkin-ad-slide'));
        var linkEl = document.getElementById('checkinAdLink');
        var idx = startIdx;
        overlay.style.display = 'block';
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        if (slides.length > 1) {
            var dotsWrap = document.getElementById('checkinAdDots');
            function render(idx){
                slides.forEach(function(s,k){ s.style.display = (k===idx)?'block':'none'; });
                if (linkEl && slides[idx]) linkEl.href = slides[idx].getAttribute('data-href') || '';
                if (dotsWrap) {
                    dotsWrap.querySelectorAll('.checkin-ad-dot').forEach(function(d,k){ d.classList.toggle('active', k===idx); });
                }
            }
            slides.forEach(function(s,k){
                var d = document.createElement('span');
                d.className = 'checkin-ad-dot' + (k===idx?' active':'');
                d.style.cssText = 'width:8px;height:8px;border-radius:50%;background:#d1d5db;cursor:pointer;transition:background .2s';
                d.onclick = function(){ idx=k; render(idx); resetAuto(); };
                if (dotsWrap) dotsWrap.appendChild(d);
            });
            var autoTimer = null;
            function next(){ idx=(idx+1)%slides.length; render(idx); }
            function resetAuto(){ if(autoTimer) clearInterval(autoTimer); autoTimer=setInterval(function(){ if(modal.style.display!=='none') next(); }, interval*1000); }
            resetAuto();
        }
        function enableClose(){
            countEl.textContent = '广告';
            closeBtn.disabled = false;
            closeBtn.style.background = '#f59e0b';
            closeBtn.style.color = '#fff';
            closeBtn.style.cursor = 'pointer';
        }
        function tick(){
            var remain = Math.ceil((validUntil - Date.now()) / 1000);
            if (remain <= 0) { enableClose(); return; }
            countEl.textContent = '广告 · ' + remain + ' 秒后可关闭';
            setTimeout(tick, 250);
        }
        tick();
    })();
    function closeCheckinAd(){
        document.getElementById('checkinAdOverlay').style.display = 'none';
        document.getElementById('checkinAdModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    </script>
    <?php
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


function isSuperAdmin() {
    $admin = getAdmin();
    if (!$admin) return false;
    
    if (!array_key_exists('role', $admin)) return true;
    return $admin['role'] === 'super';
}


function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        redirect($base . '/admin/index.php');
    }
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

/* ===================== 新功能共享函数 ===================== */

/**
 * 管理员操作审计日志
 */
function adminLog($action, $targetType = '', $targetId = '', $detail = '') {
    global $DB;
    if (!$DB) return;
    $adminId = $_SESSION['admin_id'] ?? null;
    $adminName = $_SESSION['admin_user'] ?? null;
    $ip = getClientIp();
    try {
        $stmt = $DB->prepare("INSERT INTO admin_logs(admin_id,admin_name,action,target_type,target_id,detail,ip,created_at) VALUES(?,?,?,?,?,?,?,NOW())");
        $stmt->execute([$adminId, $adminName, $action, $targetType, (string)$targetId, $detail, $ip]);
    } catch (Exception $e) {}
}

/**
 * 管理员登录失败锁定状态
 * 返回 ['locked'=>bool,'attempts'=>int,'window_minutes'=>int]
 */
function adminLoginState($username, $max = 5, $windowMin = 15) {
    global $DB;
    if (!$DB) return ['locked' => false, 'attempts' => 0, 'window_minutes' => $windowMin];
    $since = date('Y-m-d H:i:s', time() - $windowMin * 60);
    try {
        $stmt = $DB->prepare("SELECT COUNT(*) AS c FROM admin_login_attempts WHERE username=? AND success=0 AND created_at>?");
        $stmt->execute([$username, $since]);
        $attempts = intval($stmt->fetchColumn());
    } catch (Exception $e) {
        // 审计表不存在时安全降级：不锁定、计 0 次失败，保证登录可用
        $attempts = 0;
    }
    return ['locked' => $attempts >= $max, 'attempts' => $attempts, 'window_minutes' => $windowMin];
}

function recordAdminLoginAttempt($username, $success) {
    global $DB;
    if (!$DB) return;
    try {
        $stmt = $DB->prepare("INSERT INTO admin_login_attempts(username,ip,success,created_at) VALUES(?,?,?,NOW())");
        $stmt->execute([$username, getClientIp(), $success ? 1 : 0]);
    } catch (Exception $e) {}
}

function clearAdminLoginAttempts($username) {
    global $DB;
    if (!$DB) return;
    try { $DB->prepare("DELETE FROM admin_login_attempts WHERE username=?")->execute([$username]); } catch (Exception $e) {}
}

/**
 * 上传文件真实 MIME 校验（防图片马 / 类型伪装）
 * $allowedMimes 例如 ['image/png','image/jpeg','image/gif','image/webp','application/pdf']
 */
function verifyUploadMime($tmpFile, $allowedMimes) {
    if (!is_uploaded_file($tmpFile) && !is_file($tmpFile)) return false;
    if (!function_exists('finfo_open') && !class_exists('finfo')) return true; // 无 fileinfo 扩展时放行（交由扩展名白名单兜底）
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) return true;
    $mime = @finfo_file($finfo, $tmpFile);
    @finfo_close($finfo);
    return in_array($mime, $allowedMimes, true);
}

/**
 * 风控频率限制：基于 risk_rules + risk_attempts 表
 * 返回 true 表示应被限流（拒绝）
 */
function riskIsBlocked($type, $scope, $value) {
    global $DB;
    if (!$DB) return false;
    $stmt = $DB->prepare("SELECT * FROM risk_rules WHERE type=? AND scope=? AND enabled=1");
    $stmt->execute([$type, $scope]);
    $rule = $stmt->fetch();
    if (!$rule) return false;
    $since = date('Y-m-d H:i:s', time() - intval($rule['window_minutes']) * 60);
    $t = $DB->prepare("SELECT COUNT(*) AS c FROM risk_attempts WHERE type=? AND scope=? AND value=? AND created_at>?");
    $t->execute([$type, $scope, $value, $since]);
    return intval($t->fetchColumn()) >= intval($rule['limit_count']);
}

function riskRecord($type, $scope, $value) {
    global $DB;
    if (!$DB) return;
    try {
        $stmt = $DB->prepare("INSERT INTO risk_attempts(type,scope,value,created_at) VALUES(?,?,?,NOW())");
        $stmt->execute([$type, $scope, $value]);
    } catch (Exception $e) {}
}

/**
 * 积分变动统一入口：更新余额并写 points_log 流水
 * $type: sign/recharge/referral/exchange/renew/expire/adjust/other
 * 返回变动后余额(int)或 false
 */
function addPoints($userId, $delta, $type, $source = '', $remark = '') {
    global $DB;
    if (!$DB) return false;
    $delta = (int)$delta;
    try {
        $DB->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$delta, $userId]);
        $bal = $DB->prepare("SELECT points FROM users WHERE id = ?")->fetchColumn();
        $DB->prepare("INSERT INTO points_log(user_id,type,source,delta,balance,remark,created_at) VALUES(?,?,?,?,?,?,NOW())")
            ->execute([$userId, $type, $source, $delta, (int)$bal, $remark]);
        return (int)$bal;
    } catch (Exception $e) { return false; }
}

/**
 * 计算签到可得积分（含连续签到阶梯奖励），不落库
 */
function calcSignPoints($streak) {
    $min = intval(conf('sign_min', 50));
    $max = intval(conf('sign_max', 100));
    $base = mt_rand($min, $max);
    $streakDays = intval(conf('sign_streak_days', 7));
    $bonus = intval(conf('sign_streak_bonus', 50));
    if ($streakDays > 0 && $bonus > 0 && $streak > 0 && $streak % $streakDays === 0) {
        $base += $bonus;
    }
    return $base;
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
    for ($i = 0; $i < 3; $i++) $letters .= chr(mt_rand(97, 122)); 
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

/**
 * 处理工单附件上传，成功返回相对路径（如 uploads/xxx.png），失败返回 null
 */
function uploadAttachment($inputName, $allowedExt = ['png','jpg','jpeg','gif','webp','pdf']) {
    if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $f = $_FILES[$inputName];
    $name = basename($f['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) return null;
    if ($f['size'] <= 0 || $f['size'] > 5 * 1024 * 1024) return null;
    // 真实 MIME 二次校验（防图片马 / 类型伪装）
    $mimeMap = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'
    ];
    $allowedMimes = array_values(array_intersect_key($mimeMap, array_flip($allowedExt)));
    if (!verifyUploadMime($f['tmp_name'], $allowedMimes)) return null;
    $dir = ROOT . 'uploads/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_writable($dir)) return null;
    $newName = 'tk_' . bin2hex(random_bytes(10)) . '.' . $ext;
    if (move_uploaded_file($f['tmp_name'], $dir . $newName)) {
        return 'uploads/' . $newName;
    }
    return null;
}

/* ===================== 2FA TOTP（RFC6238，纯 PHP 实现） ===================== */
function base32Decode($s) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s));
    $buf = '';
    $bits = 0;
    $val = 0;
    for ($i = 0; $i < strlen($s); $i++) {
        $val = ($val << 5) | strpos($alphabet, $s[$i]);
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $buf .= chr(($val >> $bits) & 0xFF);
        }
    }
    return $buf;
}

function totpGenerate($secret, $time = null, $digits = 6, $period = 30) {
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $bin = pack('J', $counter);
    $hash = hash_hmac('sha1', $bin, base32Decode($secret), true);
    $offset = ord($hash[19]) & 0xF;
    $code = (ord($hash[$offset]) & 0x7F) << 24
          | (ord($hash[$offset + 1]) & 0xFF) << 16
          | (ord($hash[$offset + 2]) & 0xFF) << 8
          | (ord($hash[$offset + 3]) & 0xFF);
    $code = $code % (10 ** $digits);
    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

function totpVerify($secret, $code, $window = 1) {
    $code = preg_replace('/\D/', '', (string)$code);
    if (strlen($code) !== 6) return false;
    $t = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (totpGenerate($secret, $t + $i * 30) === $code) return true;
    }
    return false;
}

function totpSecret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    for ($i = 0; $i < 16; $i++) $s .= $chars[random_int(0, 31)];
    return $s;
}

function totpUri($secret, $label, $issuer) {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&period=30&digits=6';
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
        $btnCfg = json_decode(conf('announcement_btn', '{}'), true);
        $btnHtml = '';
        if (is_array($btnCfg) && !empty($btnCfg['enabled']) && !empty($btnCfg['name']) && !empty($btnCfg['url'])) {
            $btnName = h($btnCfg['name']);
            $btnUrl = h($btnCfg['url']);
            $btnHtml = '<a class="btn-primary btn-sm" href="' . $btnUrl . '" target="_blank" rel="noopener" style="margin-left:8px;text-decoration:none">' . $btnName . '</a>';
        }
        echo '<div id="announceModal" class="modal" style="display:flex">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>📢 公告</h3>
                        <span class="modal-close" onclick="document.getElementById(\'announceModal\').classList.add(\'hidden\');sessionStorage.setItem(\'announceClosed\',\'1\')">&times;</span>
                    </div>
                    <div class="modal-body">' . parseMd($GLOBALS['_announcement']) . '</div>
                    <div class="modal-footer">
                        <button class="btn-primary btn-sm" onclick="document.getElementById(\'announceModal\').classList.add(\'hidden\');sessionStorage.setItem(\'announceClosed\',\'1\')">我知道了</button>' . $btnHtml . '
                    </div>
                </div>
            </div>';
        echo '<script>(function(){var m=document.getElementById("announceModal");if(sessionStorage.getItem("announceClosed")){m.style.display="none"}})();</script>';
    }
    
    echo '<script>(function(){var t=document.querySelector("meta[name=csrf-token]");if(!t)return;var tk=t.getAttribute("content");if(!tk)return;var of=window.fetch;window.fetch=function(u,o){o=o||{};var m=(o.method||"GET").toUpperCase();if(m!=="POST")return of.call(this,u,o);var url=(typeof u==="string")?u:((u&&u.url)||"");var so=(!/^https?:\\/\\//i.test(url)&&(url.charAt(0)!=="/"||url.charAt(1)!=="/"))||url.indexOf(window.location.origin)===0;if(!so)return of.call(this,u,o);o.headers=o.headers||{};if(o.headers instanceof Headers){if(!o.headers.has("X-CSRF-TOKEN"))o.headers.set("X-CSRF-TOKEN",tk)}else{if(!o.headers["X-CSRF-TOKEN"])o.headers["X-CSRF-TOKEN"]=tk}return of.call(this,u,o)}})();</script>';
    echo '</div></body></html>';
}

logVisit();
