<?php
define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
define('RD_ROOT', ROOT . 'rd/');

if (file_exists(ROOT . 'config.php')) {
    header('Location: ../index.php');
    exit;
}

$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = intval($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPwd  = trim($_POST['db_pwd'] ?? '');
        $siteName = trim($_POST['site_name'] ?? '云主机');
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminPwd  = trim($_POST['admin_pwd'] ?? '');

        if (empty($dbName) || empty($dbUser) || empty($adminUser) || empty($adminPwd)) {
            $error = '请填写所有必填项';
            $step = 1;
        } else {
            try {
                $dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';charset=utf8mb4';
                $pdo = new PDO($dsn, $dbUser, $dbPwd, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $pdo->exec("USE `{$dbName}`");

                $pdo->exec("CREATE TABLE IF NOT EXISTS config (
                    k VARCHAR(50) NOT NULL PRIMARY KEY,
                    v TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    nickname VARCHAR(50) NOT NULL DEFAULT '',
                    points INT NOT NULL DEFAULT 0,
                    last_sign_date DATE NULL,
                    last_login_time DATETIME NULL,
                    last_login_ip VARCHAR(45) NULL,
                    login_attempts INT DEFAULT 0,
                    locked_until DATETIME NULL,
                    invite_code VARCHAR(20) NULL,
                    invited_by INT NULL,
                    referral_count INT DEFAULT 0,
                    remember_token VARCHAR(64) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS vhost_models (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    web_space INT NOT NULL DEFAULT 0,
                    db_space INT NOT NULL DEFAULT 0,
                    flow INT NOT NULL DEFAULT 30,
                    domain_limit INT NOT NULL DEFAULT 5,
                    price INT NOT NULL DEFAULT 0,
                    status TINYINT NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    server_id INT NULL DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS vhosts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    model_id INT NOT NULL,
                    account VARCHAR(100) NOT NULL,
                    password VARCHAR(100) NOT NULL,
                    mnbt_opened TINYINT NOT NULL DEFAULT 0,
                    expire_time DATETIME NULL,
                    expire_warned TINYINT(1) NOT NULL DEFAULT 0,
                    server_id INT NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (model_id) REFERENCES vhost_models(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS mnbt_servers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    api_url VARCHAR(255) NOT NULL,
                    mn_bh VARCHAR(50) NOT NULL DEFAULT '',
                    mn_key VARCHAR(255) NOT NULL DEFAULT '',
                    mn_keye VARCHAR(255) NOT NULL DEFAULT '',
                    mn_vs VARCHAR(20) NOT NULL DEFAULT '16',
                    status TINYINT NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_no VARCHAR(50) NOT NULL UNIQUE,
                    user_id INT NOT NULL,
                    type VARCHAR(20) NOT NULL DEFAULT 'points',
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    points INT NOT NULL DEFAULT 0,
                    status TINYINT NOT NULL DEFAULT 0,
                    params TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    paid_at DATETIME NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS visit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip VARCHAR(45) NOT NULL,
                    visit_date DATE NOT NULL,
                    UNIQUE KEY uk_ip_date (ip, visit_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS referral_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    referrer_id INT NOT NULL,
                    referred_id INT NOT NULL,
                    reward_points INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(32) NOT NULL UNIQUE,
                    discount INT NOT NULL DEFAULT 0,
                    max_uses INT NOT NULL DEFAULT 1,
                    used_count INT NOT NULL DEFAULT 0,
                    expire_at DATETIME NULL,
                    model_id INT NULL,
                    status TINYINT NOT NULL DEFAULT 0,
                    used_by INT NULL,
                    used_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS verify_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account VARCHAR(100) NOT NULL,
                    code VARCHAR(10) NOT NULL,
                    type VARCHAR(20) NOT NULL DEFAULT 'register',
                    expire_time DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_account_type (account, type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    vhost_id INT NULL,
                    subject VARCHAR(200) NOT NULL,
                    status TINYINT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    user_id INT NULL,
                    admin_id INT NULL,
                    content TEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS recharge_packages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    points INT NOT NULL DEFAULT 0,
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    sort_order INT NOT NULL DEFAULT 0,
                    status TINYINT NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $defaults = [
                    'site_name' => $siteName,
                    'mnbt_api_url' => '', 'mnbt_bh' => '', 'mnbt_key' => '', 'mnbt_keye' => '', 'mnbt_vs' => '16',
                    'pay_api_url' => '', 'pay_pid' => '', 'pay_key' => '',
                    'mail_host' => '', 'mail_port' => '465', 'mail_user' => '', 'mail_pass' => '',
                    'mail_name' => $siteName, 'mail_security' => 'ssl', 'mail_enabled' => '0',
                    'mail_whitelist' => '',
                    'mail_notify_host' => '1', 'mail_notify_points' => '1', 'mail_notify_expire' => '1', 'mail_notify_ticket' => '1', 'cron_key' => '',
                    'sign_min' => '50', 'sign_max' => '100',
                    'theme' => '清新薄荷主题',
                    'announcement' => '',
                    'referral_enabled' => '1', 'referral_reward_points' => '30',
                    'register_points_enabled' => '1', 'register_points' => '100',
                    'points_200_price' => '10', 'points_400_price' => '18',
                    'points_1000_price' => '40', 'points_3000_price' => '100',
                ];
                $stmt = $pdo->prepare("INSERT INTO config(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
                foreach ($defaults as $k => $v) {
                    $stmt->execute([$k, $v]);
                }

                $models = [
                    ['入门型', 500, 50, 10, 3, 100, 1, 1],
                    ['标准型', 1024, 100, 30, 5, 200, 1, 2],
                    ['专业型', 2048, 200, 50, 10, 400, 1, 3],
                    ['旗舰型', 5120, 500, 100, 20, 800, 1, 4],
                ];
                $stmt2 = $pdo->prepare("INSERT INTO vhost_models(name,web_space,db_space,flow,domain_limit,price,status,sort_order) VALUES(?,?,?,?,?,?,?,?)");
                foreach ($models as $m) {
                    $stmt2->execute($m);
                }

                $hashedPwd = password_hash($adminPwd, PASSWORD_DEFAULT);
                $stmt3 = $pdo->prepare("INSERT INTO admins(username,password) VALUES(?,?)");
                $stmt3->execute([$adminUser, $hashedPwd]);

                $configContent = "<?php\n\$dbconfig=array(\n\t'host' => '".addslashes($dbHost)."',\n\t'port' => {$dbPort},\n\t'user' => '".addslashes($dbUser)."',\n\t'pwd' => '".addslashes($dbPwd)."',\n\t'dbname' => '".addslashes($dbName)."',\n);\n?>";
                $written = @file_put_contents(ROOT . 'config.php', $configContent);
                if ($written === false) {
                    throw new Exception('无法写入配置文件 config.php，请确保项目根目录有写入权限（chmod 755 或在宝塔面板设置目录权限为 755、所有者为 www）。配置内容如下，您也可手动创建 config.php 文件并粘贴：<br><br><pre style="background:#f5f5f5;padding:10px;border-radius:5px;white-space:pre-wrap;word-break:break-all">'.htmlspecialchars($configContent).'</pre>');
                }

                $success = '安装成功！正在跳转到首页...';
                $step = 3;
            } catch (Exception $e) {
                $error = '安装失败：' . $e->getMessage();
                $step = 1;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>系统安装</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#e0e5ec;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:#2d3436}
.install-card{background:#e0e5ec;border-radius:24px;padding:40px;max-width:520px;width:90%;box-shadow:8px 8px 16px #a3b1c6,-8px -8px 16px #ffffff}
.install-card h1{text-align:center;margin-bottom:8px;font-size:1.6rem;color:#6c5ce7}
.install-card .subtitle{text-align:center;color:#636e72;margin-bottom:30px;font-size:.9rem}
.form-group{margin-bottom:18px}
.form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:.9rem;color:#2d3436}
.form-group input{width:100%;padding:12px 16px;border:none;border-radius:12px;background:#e0e5ec;box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff;font-size:1rem;outline:none;color:#2d3436;transition:box-shadow .2s}
.form-group input:focus{box-shadow:inset 6px 6px 12px #a3b1c6,inset -6px -6px 12px #ffffff}
.neo-btn{width:100%;padding:14px;border:none;border-radius:12px;background:#e0e5ec;box-shadow:6px 6px 12px #a3b1c6,-6px -6px 12px #ffffff;font-size:1rem;font-weight:700;color:#6c5ce7;cursor:pointer;transition:all .2s;margin-top:10px}
.neo-btn:hover{color:#a29bfe}
.neo-btn:active{box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff}
.error{background:#ffeaa7;color:#d63031;padding:12px;border-radius:12px;margin-bottom:18px;font-size:.9rem;box-shadow:inset 2px 2px 4px rgba(214,48,49,.1)}
.success{background:#55efc4;color:#00b894;padding:12px;border-radius:12px;margin-bottom:18px;font-size:.9rem;box-shadow:inset 2px 2px 4px rgba(0,184,148,.1)}
.divider{height:1px;background:linear-gradient(90deg,transparent,#a3b1c6,transparent);margin:24px 0}
.step-indicator{text-align:center;margin-bottom:20px;color:#636e72;font-size:.85rem}
.row{display:flex;gap:12px}
.row .form-group{flex:1}
@media(max-width:500px){.row{flex-direction:column}.install-card{padding:24px}}
</style>
</head>
<body>
<div class="install-card">
<h1>🚀 系统安装</h1>
<p class="subtitle">欢迎使用虚拟主机分发平台，请完成以下配置</p>
<?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<?php if ($step === 1): ?>
<div class="step-indicator">步骤 1/2 — 数据库与管理员配置</div>
<form method="post">
<input type="hidden" name="step" value="2">
<div class="form-group"><label>数据库主机</label><input type="text" name="db_host" value="localhost" required></div>
<div class="row">
<div class="form-group"><label>端口</label><input type="number" name="db_port" value="3306" required></div>
<div class="form-group"><label>数据库名</label><input type="text" name="db_name" placeholder="如: yunhost" required></div>
</div>
<div class="row">
<div class="form-group"><label>数据库用户名</label><input type="text" name="db_user" placeholder="root" required></div>
<div class="form-group"><label>数据库密码</label><input type="password" name="db_pwd"></div>
</div>
<div class="divider"></div>
<div class="form-group"><label>网站名称</label><input type="text" name="site_name" value="云主机" required></div>
<div class="row">
<div class="form-group"><label>管理员账号</label><input type="text" name="admin_user" placeholder="admin" required></div>
<div class="form-group"><label>管理员密码</label><input type="password" name="admin_pwd" placeholder="至少6位" required></div>
</div>
<button type="submit" class="neo-btn">开始安装</button>
</form>
<?php elseif ($step === 3): ?>
<div class="success">✅ 安装完成！系统已成功初始化。</div>
<p style="text-align:center;margin-top:20px;"><a href="../index.php" style="color:#6c5ce7;font-weight:700;text-decoration:none;font-size:1.1rem;">→ 进入首页</a></p>
<script>setTimeout(function(){window.location.href='../index.php';},3000);</script>
<?php endif; ?>
</div>
</body>
</html>
