<?php


define('IN_SYS', true);
define('ROOT', __DIR__ . '/');


if (!file_exists(ROOT . 'config.php')) {
    die('未检测到系统安装，请先运行 <a href="install/">安装向导</a>');
}

require ROOT . 'rd/bootstrap.php';

$error = '';
$success = '';
$logs = [];
$authorized = false;


if (!empty($_POST['admin_password'])) {
    $password = $_POST['admin_password'];
    $stmt = $DB->prepare("SELECT password FROM admins LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $authorized = true;
    } else {
        $error = '管理员密码错误';
    }
}

if ($authorized) {
    try {
        
        $columns = [
            'login_attempts' => 'INT DEFAULT 0',
            'locked_until'   => 'DATETIME NULL',
            'invite_code'    => 'VARCHAR(20) NULL',
            'invited_by'     => 'INT NULL',
            'referral_count' => 'INT DEFAULT 0',
            'remember_token' => 'VARCHAR(64) NULL',
        ];

        $stmt = $DB->query("SHOW COLUMNS FROM users");
        $existing = [];
        while ($row = $stmt->fetch()) {
            $existing[] = $row['Field'];
        }

        foreach ($columns as $col => $def) {
            if (!in_array($col, $existing)) {
                $DB->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
                $logs[] = "✅ users 表已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ users 表字段已存在：{$col}";
            }
        }

        
        $stmt = $DB->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if (!in_array('referral_logs', $tables)) {
            $DB->exec("CREATE TABLE referral_logs (
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
            $DB->exec("CREATE TABLE coupons (
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

        
        $missingConfigs = [
            'mail_whitelist'          => '',
            'referral_enabled'        => '1',
            'referral_reward_points'  => '30',
            'register_points_enabled' => '1',
            'register_points'         => '100',
        ];

        $stmt = $DB->query("SELECT k FROM config");
        $configKeys = [];
        while ($row = $stmt->fetch()) {
            $configKeys[] = $row['k'];
        }

        $insertStmt = $DB->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($missingConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        
        if (!in_array('mnbt_servers', $tables)) {
            $DB->exec("CREATE TABLE mnbt_servers (
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

        
        $stmt = $DB->query("SHOW COLUMNS FROM vhost_models");
        $vmCols = [];
        while ($row = $stmt->fetch()) {
            $vmCols[] = $row['Field'];
        }
        if (!in_array('server_id', $vmCols)) {
            $DB->exec("ALTER TABLE vhost_models ADD COLUMN server_id INT NULL DEFAULT NULL");
            $logs[] = "✅ vhost_models 表已添加字段：server_id";
        } else {
            $logs[] = "⏭️ vhost_models 表字段已存在：server_id";
        }

        
        $stmt = $DB->query("SHOW COLUMNS FROM vhosts");
        $vhCols = [];
        while ($row = $stmt->fetch()) {
            $vhCols[] = $row['Field'];
        }
        if (!in_array('server_id', $vhCols)) {
            $DB->exec("ALTER TABLE vhosts ADD COLUMN server_id INT NULL DEFAULT NULL");
            $logs[] = "✅ vhosts 表已添加字段：server_id";
        } else {
            $logs[] = "⏭️ vhosts 表字段已存在：server_id";
        }

        
        $stmt = $DB->query("SHOW COLUMNS FROM coupons");
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
                $DB->exec("ALTER TABLE coupons ADD COLUMN {$col} {$def}");
                $logs[] = "✅ coupons 表已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ coupons 表字段已存在：{$col}";
            }
        }

        $success = '数据库升级完成！共执行 ' . count($logs) . ' 项操作。';

        
        if (!in_array('tickets', $tables)) {
            $DB->exec("CREATE TABLE tickets (
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
            
            $missingCols = [
                'vhost_id' => 'INT NULL AFTER user_id',
                'subject'  => 'VARCHAR(200) NOT NULL DEFAULT "" AFTER vhost_id',
                'status'   => 'TINYINT NOT NULL DEFAULT 0 AFTER subject',
            ];
            foreach ($missingCols as $col => $def) {
                try {
                    $cols = $DB->query("SHOW COLUMNS FROM tickets LIKE '{$col}'")->fetchAll();
                    if (empty($cols)) {
                        $DB->exec("ALTER TABLE tickets ADD COLUMN {$col} {$def}");
                        $logs[] = "✅ 已添加列：tickets.{$col}";
                    }
                } catch (Exception $e) {
                    $logs[] = "⚠️ 添加 tickets.{$col} 失败：" . $e->getMessage();
                }
            }
        }

        if (!in_array('ticket_replies', $tables)) {
            $DB->exec("CREATE TABLE ticket_replies (
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
            
            $replyMissingCols = [
                'admin_id' => 'INT NULL AFTER user_id',
            ];
            foreach ($replyMissingCols as $col => $def) {
                try {
                    $cols = $DB->query("SHOW COLUMNS FROM ticket_replies LIKE '{$col}'")->fetchAll();
                    if (empty($cols)) {
                        $DB->exec("ALTER TABLE ticket_replies ADD COLUMN {$col} {$def}");
                        $logs[] = "✅ 已添加列：ticket_replies.{$col}";
                    }
                } catch (Exception $e) {
                    $logs[] = "⚠️ 添加 ticket_replies.{$col} 失败：" . $e->getMessage();
                }
            }
        }

        
        $ticketConfigs = [
            'mail_notify_ticket' => '1',
        ];
        $insertTicketStmt = $DB->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($ticketConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertTicketStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        
        $updateConfigs = [
            'current_version' => '1.6.0',
            'update_api_url' => 'https://staridc.fangqihang.cn/api.php',
        ];
        $upsertStmt = $DB->prepare("INSERT INTO config(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
        foreach ($updateConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $upsertStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $upsertStmt->execute([$key, $val]);
                $logs[] = "✅ 已更新配置项：{$key}";
            }
        }

        
        if (!in_array('recharge_packages', $tables)) {
            $DB->exec("CREATE TABLE recharge_packages (
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

        
        $vmCols = $DB->query("SHOW COLUMNS FROM vhost_models")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('max_per_user', $vmCols)) {
            $DB->exec("ALTER TABLE vhost_models ADD COLUMN max_per_user INT NOT NULL DEFAULT 0");
            $logs[] = "✅ 已添加字段：vhost_models.max_per_user";
        } else {
            $logs[] = "⏭️ 字段已存在：vhost_models.max_per_user";
        }

        
        $limitConfigs = [
            'max_hosts_per_user' => '5',
        ];
        $insertLimitStmt = $DB->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($limitConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertLimitStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        
        if (!in_array('oauth_bindings', $tables)) {
            $DB->exec("CREATE TABLE oauth_bindings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                oauth_type VARCHAR(20) NOT NULL,
                social_uid VARCHAR(100) NOT NULL,
                nickname VARCHAR(100) NULL,
                faceimg VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_type_uid (oauth_type, social_uid),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：oauth_bindings";
        } else {
            $logs[] = "⏭️ 表已存在：oauth_bindings";
        }

        
        $oauthConfigs = [
            'oauth_enabled' => '0',
            'oauth_api_url' => 'https://login.az0.cn/connect.php',
            'oauth_appid' => '',
            'oauth_appkey' => '',
            'oauth_types' => 'qq,wx,alipay',
            'oauth_icon_img_dingtalk' => '',
            'oauth_icon_text_dingtalk' => '钉',
            'oauth_icon_img_feishu' => '',
            'oauth_icon_text_feishu' => '飞',
        ];
        $insertOauthStmt = $DB->prepare("INSERT IGNORE INTO config(k,v) VALUES(?,?)");
        foreach ($oauthConfigs as $key => $val) {
            if (!in_array($key, $configKeys)) {
                $insertOauthStmt->execute([$key, $val]);
                $logs[] = "✅ 已添加配置项：{$key}";
            } else {
                $logs[] = "⏭️ 配置项已存在：{$key}";
            }
        }

        
        $modelNewCols = [
            'category_id' => 'INT NULL AFTER max_per_user',
            'is_elastic'  => 'TINYINT NOT NULL DEFAULT 0 AFTER category_id',
        ];
        $modelCols = array_column($DB->query("SHOW COLUMNS FROM vhost_models")->fetchAll(), 'Field');
        foreach ($modelNewCols as $col => $def) {
            if (!in_array($col, $modelCols)) {
                $DB->exec("ALTER TABLE vhost_models ADD COLUMN {$col} {$def}");
                $logs[] = "✅ vhost_models 已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ vhost_models 字段已存在：{$col}";
            }
        }

        
        if (!in_array('vhost_categories', $tables)) {
            $DB->exec("CREATE TABLE vhost_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                parent_id INT NULL,
                level TINYINT NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                FOREIGN KEY (parent_id) REFERENCES vhost_categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：vhost_categories";
            
        } else {
            $logs[] = "⏭️ 表已存在：vhost_categories";
            
            try {
                $cols = $DB->query("SHOW COLUMNS FROM vhost_categories LIKE 'level'")->fetchAll();
                if (empty($cols)) {
                    $DB->exec("ALTER TABLE vhost_categories ADD COLUMN level TINYINT NOT NULL DEFAULT 1 AFTER parent_id");
                    $logs[] = "✅ 已添加列：vhost_categories.level";
                    
                    $allCats = $DB->query("SELECT id, parent_id FROM vhost_categories")->fetchAll();
                    foreach ($allCats as $c) {
                        if (empty($c['parent_id'])) {
                            $DB->exec("UPDATE vhost_categories SET level=1 WHERE id={$c['id']}");
                        }
                    }
                    foreach ($allCats as $c) {
                        if (!empty($c['parent_id'])) {
                            $pStmt = $DB->prepare("SELECT level FROM vhost_categories WHERE id=?");
                            $pStmt->execute([$c['parent_id']]);
                            $p = $pStmt->fetch();
                            if ($p) {
                                $DB->exec("UPDATE vhost_categories SET level=".($p['level']+1)." WHERE id={$c['id']}");
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $logs[] = "⚠️ 添加 vhost_categories.level 失败：" . $e->getMessage();
            }
        }

        
        if (!in_array('vhost_model_durations', $tables)) {
            $DB->exec("CREATE TABLE vhost_model_durations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                model_id INT NOT NULL,
                duration_type VARCHAR(20) NOT NULL,
                discount INT NOT NULL DEFAULT 0,
                enabled TINYINT NOT NULL DEFAULT 0,
                FOREIGN KEY (model_id) REFERENCES vhost_models(id) ON DELETE CASCADE,
                UNIQUE KEY uk_model_dur (model_id, duration_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：vhost_model_durations";
            $models = $DB->query("SELECT id FROM vhost_models")->fetchAll();
            $durStmt = $DB->prepare("INSERT IGNORE INTO vhost_model_durations(model_id,duration_type,discount,enabled) VALUES(?,'month',0,1)");
            foreach ($models as $m) { $durStmt->execute([$m['id']]); }
            $logs[] = "✅ 旧型号已设置默认月付";
        } else {
            $logs[] = "⏭️ 表已存在：vhost_model_durations";
        }

        
        if (!in_array('vhost_model_elastic', $tables)) {
            $DB->exec("CREATE TABLE vhost_model_elastic (
                id INT AUTO_INCREMENT PRIMARY KEY,
                model_id INT NOT NULL,
                field_name VARCHAR(20) NOT NULL,
                min_value INT NOT NULL,
                max_value INT NOT NULL,
                step INT NOT NULL DEFAULT 1,
                unit_price INT NOT NULL DEFAULT 0,
                enabled TINYINT NOT NULL DEFAULT 0,
                FOREIGN KEY (model_id) REFERENCES vhost_models(id) ON DELETE CASCADE,
                UNIQUE KEY uk_model_field (model_id, field_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $logs[] = "✅ 已创建表：vhost_model_elastic";
        } else {
            $logs[] = "⏭️ 表已存在：vhost_model_elastic";
        }

        
        $vhostsNewCols = [
            'web_space'    => 'INT NULL DEFAULT NULL',
            'db_space'     => 'INT NULL DEFAULT NULL',
            'flow'         => 'INT NULL DEFAULT NULL',
            'domain_limit' => 'INT NULL DEFAULT NULL',
        ];
        $vhostsCols = array_column($DB->query("SHOW COLUMNS FROM vhosts")->fetchAll(), 'Field');
        foreach ($vhostsNewCols as $col => $def) {
            if (!in_array($col, $vhostsCols)) {
                $DB->exec("ALTER TABLE vhosts ADD COLUMN {$col} {$def}");
                $logs[] = "✅ vhosts 已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ vhosts 字段已存在：{$col}";
            }
        }

        
        $DB->exec("CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            model_id INT NOT NULL,
            duration_type VARCHAR(20) NOT NULL DEFAULT 'month',
            elastic_values TEXT NULL,
            coupon_code VARCHAR(50) NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (model_id) REFERENCES vhost_models(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $logs[] = "✅ 购物车表 cart_items 已创建";

        
        $DB->exec("CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(50) NOT NULL DEFAULT '',
            status TINYINT NOT NULL DEFAULT 1,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $logs[] = "✅ API Key 表 api_keys 已创建";

        
        $adminNewCols = [
            'nickname'        => 'VARCHAR(50) NULL AFTER password',
            'role'            => "ENUM('super','admin') NOT NULL DEFAULT 'admin' AFTER nickname",
            'status'          => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER role',
            'last_login_ip'   => 'VARCHAR(45) NULL AFTER status',
            'last_login_time' => 'DATETIME NULL AFTER last_login_ip',
            'created_at'      => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_login_time',
            'updated_at'      => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        $adminCols = array_column($DB->query("SHOW COLUMNS FROM admins")->fetchAll(), 'Field');
        $roleExisted = in_array('role', $adminCols); 
        foreach ($adminNewCols as $col => $def) {
            if (!in_array($col, $adminCols)) {
                $DB->exec("ALTER TABLE admins ADD COLUMN {$col} {$def}");
                $logs[] = "✅ admins 表已添加字段：{$col}";
            } else {
                $logs[] = "⏭️ admins 表字段已存在：{$col}";
            }
        }
        
        if (!$roleExisted) {
            $DB->exec("UPDATE admins SET role='super', status=1, nickname=username WHERE 1");
            $logs[] = "✅ 已将现有管理员升级为超级管理员";
        }

        
        try { $DB->exec("ALTER TABLE admins ADD INDEX idx_role(role)"); } catch(Exception $e) {}
        try { $DB->exec("ALTER TABLE admins ADD INDEX idx_status(status)"); } catch(Exception $e) {}
        $logs[] = "✅ admins 表索引已添加";

        // ===== 使用标准 Schema 补齐所有缺失的表/字段/索引 =====
        // upgrade.php 的手动增量逻辑仅覆盖了早期版本差异，
        // 调用 applyDatabaseSchema() 可补齐所有标准表（如 messages、admin_logs、
        // risk_rules、cron_jobs、points_log、ad_clicks 等）以及缺失字段和索引。
        require_once ROOT . 'rd/db_fix.php';
        $schemaLog = [];
        $schemaStat = applyDatabaseSchema($DB, $schemaLog);
        foreach ($schemaLog as $sLog) {
            $logs[] = $sLog;
        }
        if ($schemaStat['tables_created'] > 0 || $schemaStat['columns_added'] > 0 || $schemaStat['indexes_added'] > 0) {
            $logs[] = "✅ Schema 补齐完成：新建 {$schemaStat['tables_created']} 张表，添加 {$schemaStat['columns_added']} 个字段，添加 {$schemaStat['indexes_added']} 个索引";
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
        <?php echo csrfField(); ?>
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