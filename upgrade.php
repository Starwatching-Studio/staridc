<?php
/**
 * StarIDC 数据库升级脚本
 * 
 * 用途：为现有安装补充缺失的数据库字段和表
 * 使用：上传至网站根目录，浏览器访问 upgrade.php 即可
 * 安全：仅管理员可执行（需提供管理员密码验证）
 */

define('IN_SYS', true);
define('ROOT', __DIR__ . '/');

// 检查是否已安装
if (!file_exists(ROOT . 'config.php')) {
    die('未检测到系统安装，请先运行 <a href="install/">安装向导</a>');
}

require ROOT . 'config.php';

$error = '';
$success = '';
$logs = [];
$authorized = false;

// 验证管理员身份（简单密码验证，避免被滥用）
session_start();
if (!empty($_POST['admin_password'])) {
    try {
        $dsn = 'mysql:host=' . $dbconfig['host'] . ';port=' . $dbconfig['port'] . ';dbname=' . $dbconfig['dbname'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $dbconfig['user'], $dbconfig['pwd'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $stmt = $pdo->prepare("SELECT password FROM admins WHERE username = ?");
        $stmt->execute(['admin']);
        $admin = $stmt->fetch();

        if ($admin && password_verify($_POST['admin_password'], $admin['password'])) {
            $authorized = true;
        } else {
            $error = '管理员密码错误';
        }
    } catch (Exception $e) {
        $error = '数据库连接失败：' . $e->getMessage();
    }
}

if ($authorized) {
    try {
        // === 1. 修复 users 表：补充缺失字段 ===
        $columns = [
            'login_attempts' => 'INT DEFAULT 0',
            'locked_until'   => 'DATETIME NULL',
            'invite_code'    => 'VARCHAR(20) NULL',
            'invited_by'     => 'INT NULL',
            'referral_count' => 'INT DEFAULT 0',
            'remember_token' => 'VARCHAR(64) NULL',
        ];

        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        $existing = [];
        while ($row = $stmt->fetch()) {
            $existing[] = $row['Field'];
        }

        foreach ($columns as $col => $def) {
            if (!in_array($col, $existing)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
                $logs[] = "✅ users 表已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ users 表字段已存在：{$col}";
            }
        }

        // === 2. 创建缺失的表 ===
        $stmt = $pdo->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if (!in_array('referral_logs', $tables)) {
            $pdo->exec("CREATE TABLE referral_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referrer_id INT NOT NULL,
                referred_id INT NOT NULL,
                reward_points INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：referral_logs";
        } else {
            $logs[] = "⏭️ 表已存在：referral_logs";
        }

        if (!in_array('coupons', $tables)) {
            $pdo->exec("CREATE TABLE coupons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL UNIQUE,
                discount INT NOT NULL DEFAULT 0,
                status TINYINT NOT NULL DEFAULT 0,
                used_by INT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：coupons";
        } else {
            $logs[] = "⏭️ 表已存在：coupons";
        }

        // === 3. 补充缺失的 config 默认值 ===
        $missingConfigs = [
            'mail_whitelist'          => '',
            'referral_enabled'        => '1',
            'referral_reward_points'  => '30',
            'register_points_enabled' => '1',
            'register_points'         => '100',
        ];

        $stmt = $pdo->query("SELECT k FROM config");
        $configKeys = [];
        while ($row = $stmt->fetch()) {
            $configKeys[] = $row['k'];
        }

        $insertStmt = $pdo->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($missingConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        // === 4. 多MNBT服务器支持：创建 mnbt_servers 表 ===
        if (!in_array('mnbt_servers', $tables)) {
            $pdo->exec("CREATE TABLE mnbt_servers (
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
            $logs[] = "✅ 已创建表：mnbt_servers";
        } else {
            $logs[] = "⏭️ 表已存在：mnbt_servers";
        }

        // === 5. vhost_models 表添加 server_id 字段 ===
        $stmt = $pdo->query("SHOW COLUMNS FROM vhost_models");
        $vmCols = [];
        while ($row = $stmt->fetch()) {
            $vmCols[] = $row['Field'];
        }
        if (!in_array('server_id', $vmCols)) {
            $pdo->exec("ALTER TABLE vhost_models ADD COLUMN server_id INT NULL DEFAULT NULL");
            $logs[] = "✅ vhost_models 表已添加字段：server_id";
        } else {
            $logs[] = "⏭️ vhost_models 表字段已存在：server_id";
        }

        // === 6. vhosts 表添加 server_id 字段 ===
        $stmt = $pdo->query("SHOW COLUMNS FROM vhosts");
        $vhCols = [];
        while ($row = $stmt->fetch()) {
            $vhCols[] = $row['Field'];
        }
        if (!in_array('server_id', $vhCols)) {
            $pdo->exec("ALTER TABLE vhosts ADD COLUMN server_id INT NULL DEFAULT NULL");
            $logs[] = "✅ vhosts 表已添加字段：server_id";
        } else {
            $logs[] = "⏭️ vhosts 表字段已存在：server_id";
        }

        // === 7. coupons 表添加新字段（有效期、使用次数、适用型号） ===
        $stmt = $pdo->query("SHOW COLUMNS FROM coupons");
        $cpCols = [];
        while ($row = $stmt->fetch()) {
            $cpCols[] = $row['Field'];
        }
        $couponNewCols = [
            'max_uses'   => 'INT NOT NULL DEFAULT 1',
            'used_count' => 'INT NOT NULL DEFAULT 0',
            'expire_at'  => 'DATETIME NULL',
            'model_id'   => 'INT NULL',
        ];
        foreach ($couponNewCols as $col => $def) {
            if (!in_array($col, $cpCols)) {
                $pdo->exec("ALTER TABLE coupons ADD COLUMN {$col} {$def}");
                $logs[] = "✅ coupons 表已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ coupons 表字段已存在：{$col}";
            }
        }

        $success = '数据库升级完成！共执行 ' . count($logs) . ' 项操作。';

        // === 8. 创建工单表 ===
        if (!in_array('tickets', $tables)) {
            $pdo->exec("CREATE TABLE tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                vhost_id INT NULL,
                subject VARCHAR(200) NOT NULL,
                status TINYINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：tickets";
        } else {
            $logs[] = "⏭️ 表已存在：tickets";
        }

        if (!in_array('ticket_replies', $tables)) {
            $pdo->exec("CREATE TABLE ticket_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                user_id INT NULL,
                admin_id INT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：ticket_replies";
        } else {
            $logs[] = "⏭️ 表已存在：ticket_replies";
        }

        // === 9. 补充工单通知配置项 ===
        $ticketConfigs = [
            'mail_notify_ticket' => '1',
        ];
        $insertTicketStmt = $pdo->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($ticketConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertTicketStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        // === 10. 创建充值套餐表 ===
        if (!in_array('recharge_packages', $tables)) {
            $pdo->exec("CREATE TABLE recharge_packages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                points INT NOT NULL DEFAULT 0,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                status TINYINT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：recharge_packages";
        } else {
            $logs[] = "⏭️ 表已存在：recharge_packages";
        }

        $success = '数据库升级完成！共执行 ' . count($logs) . ' 项操作。';
    } catch (Exception $e) {
        $error = '升级失败：' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>数据库升级 - StarIDC</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#e0e5ec;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:#2d3436}
.card{background:#e0e5ec;border-radius:24px;padding:40px;max-width:560px;width:90%;box-shadow:8px 8px 16px #a3b1c6,-8px -8px 16px #ffffff}
.card h1{text-align:center;margin-bottom:8px;font-size:1.5rem;color:#6c5ce7}
.card .desc{text-align:center;color:#636e72;margin-bottom:24px;font-size:.9rem}
.form-group{margin-bottom:16px}
.form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:.9rem;color:#2d3436}
.form-group input{width:100%;padding:12px 16px;border:none;border-radius:12px;background:#e0e5ec;box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff;font-size:1rem;outline:none;color:#2d3436}
.form-group input:focus{box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff,0 0 0 2px #6c5ce744}
.btn{width:100%;padding:12px;border:none;border-radius:12px;background:#6c5ce7;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;box-shadow:4px 4px 8px #a3b1c6,-4px -4px 8px #ffffff;transition:all .2s}
.btn:hover{transform:translateY(-2px);box-shadow:6px 6px 12px #a3b1c6,-6px -6px 12px #ffffff}
.error{background:#ffeaa7;color:#d63031;padding:12px;border-radius:12px;margin-bottom:16px;font-size:.9rem}
.success{background:#dfe6e9;color:#00b894;padding:12px;border-radius:12px;margin-bottom:16px;font-size:.9rem;font-weight:600}
.log-list{background:#dfe6e9;border-radius:12px;padding:16px;margin-top:16px;max-height:300px;overflow-y:auto}
.log-list li{padding:4px 0;font-size:.85rem;color:#636e72;list-style:none}
.log-list li:before{content:'';margin-right:4px}
.actions{text-align:center;margin-top:20px}
.actions a{color:#6c5ce7;text-decoration:none;font-weight:600}
.actions a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="card">
<h1>🔧 StarIDC 数据库升级</h1>
<p class="desc">修复缺少的数据库字段和表，解决注册报错等问题</p>

<?php if (!empty($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <ol class="log-list">
        <?php foreach ($logs as $log): ?>
            <li><?php echo $log; ?></li>
        <?php endforeach; ?>
    </ol>
    <div class="actions">
        <a href="admin/index.php">→ 进入管理后台</a>
    </div>
<?php elseif (!$authorized): ?>
    <form method="post">
        <div class="form-group">
            <label>管理员密码</label>
            <input type="password" name="admin_password" placeholder="请输入管理员密码验证身份" required>
        </div>
        <p style="font-size:.8rem;color:#b2bec3;margin-bottom:16px;text-align:center">
            验证通过后将自动执行数据库修复，无需再次操作
        </p>
        <button type="submit" class="btn">验证并开始升级</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>