<?php
/**
 * StarIDC 标准数据库结构定义（唯一权威来源 / Single Source of Truth）
 * --------------------------------------------------------------------------
 * 本文件被以下脚本共用，避免“安装脚本与修复脚本结构不一致”导致字段缺失：
 *   - install/index.php   （新用户安装，保证全新安装即完整结构）
 *   - migrate.php         （老用户升级，补齐表/字段/索引）
 *   - admin/db_check.php  （后台一键检查与自动修复）
 *
 * 原则（只增不改，绝不破坏已有数据）：
 *   1. tables   : 每张表的【完整】CREATE TABLE IF NOT EXISTS（含全部字段/索引/默认值）。
 *                缺少某张表时会用完整结构创建；已存在则跳过。
 *   2. columns  : 针对“老数据库表已存在但缺少新增字段”的情况，逐列 ALTER ADD（幂等）。
 *   3. indexes  : 针对“老数据库缺少索引”的情况，逐索引 ALTER ADD（幂等）。
 *
 * 任何新增表/字段/索引，都应在此集中维护，并保持三处调用一致。
 */

return [
    // ===================== 完整建表语句（缺表时按此创建） =====================
    'tables' => [
        'config' => "CREATE TABLE IF NOT EXISTS config (
            k VARCHAR(50) NOT NULL PRIMARY KEY,
            v TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'admins' => "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nickname VARCHAR(50) NULL,
            role ENUM('super','admin') NOT NULL DEFAULT 'admin',
            status TINYINT(1) NOT NULL DEFAULT 1,
            last_login_ip VARCHAR(45) NULL,
            last_login_time DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role(role),
            INDEX idx_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'users' => "CREATE TABLE IF NOT EXISTS users (
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
            two_factor_secret VARCHAR(64) NULL,
            two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
            sign_streak INT NOT NULL DEFAULT 0 COMMENT '连续签到天数',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'vhost_models' => "CREATE TABLE IF NOT EXISTS vhost_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            web_space INT NOT NULL DEFAULT 0,
            db_space INT NOT NULL DEFAULT 0,
            flow INT NOT NULL DEFAULT 30,
            domain_limit INT NOT NULL DEFAULT 5,
            price INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            server_id INT NULL DEFAULT NULL,
            max_per_user INT NOT NULL DEFAULT 0,
            category_id INT NULL,
            is_elastic TINYINT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'vhost_categories' => "CREATE TABLE IF NOT EXISTS vhost_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            parent_id INT NULL,
            level TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            FOREIGN KEY (parent_id) REFERENCES vhost_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'vhost_model_durations' => "CREATE TABLE IF NOT EXISTS vhost_model_durations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            duration_type VARCHAR(20) NOT NULL,
            discount INT NOT NULL DEFAULT 0,
            enabled TINYINT NOT NULL DEFAULT 0,
            FOREIGN KEY (model_id) REFERENCES vhost_models(id) ON DELETE CASCADE,
            UNIQUE KEY uk_model_dur (model_id, duration_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'vhost_model_elastic' => "CREATE TABLE IF NOT EXISTS vhost_model_elastic (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'vhosts' => "CREATE TABLE IF NOT EXISTS vhosts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            model_id INT NOT NULL,
            account VARCHAR(100) NOT NULL,
            password VARCHAR(100) NOT NULL,
            mnbt_opened TINYINT NOT NULL DEFAULT 0,
            expire_time DATETIME NULL,
            expire_warned TINYINT(1) NOT NULL DEFAULT 0,
            server_id INT NULL DEFAULT NULL,
            web_space INT NULL DEFAULT NULL,
            db_space INT NULL DEFAULT NULL,
            flow INT NULL DEFAULT NULL,
            domain_limit INT NULL DEFAULT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0待开通 1已开通 2已过期 3已暂停',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (model_id) REFERENCES vhost_models(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'cart_items' => "CREATE TABLE IF NOT EXISTS cart_items (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'api_keys' => "CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(50) NOT NULL DEFAULT '',
            status TINYINT NOT NULL DEFAULT 1,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'mnbt_servers' => "CREATE TABLE IF NOT EXISTS mnbt_servers (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'orders' => "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_no VARCHAR(50) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'points',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            points INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 0,
            params TEXT NULL,
            remark VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'visit_logs' => "CREATE TABLE IF NOT EXISTS visit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            visit_date DATE NOT NULL,
            UNIQUE KEY uk_ip_date (ip, visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'referral_logs' => "CREATE TABLE IF NOT EXISTS referral_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referrer_id INT NOT NULL,
            referred_id INT NOT NULL,
            reward_points INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'coupons' => "CREATE TABLE IF NOT EXISTS coupons (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'verify_codes' => "CREATE TABLE IF NOT EXISTS verify_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account VARCHAR(100) NOT NULL,
            code VARCHAR(10) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'register',
            expire_time DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_account_type (account, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'tickets' => "CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            vhost_id INT NULL,
            subject VARCHAR(200) NOT NULL,
            status TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'ticket_replies' => "CREATE TABLE IF NOT EXISTS ticket_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NULL,
            admin_id INT NULL,
            content TEXT NOT NULL,
            attachment VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'recharge_packages' => "CREATE TABLE IF NOT EXISTS recharge_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            points INT NOT NULL DEFAULT 0,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'oauth_bindings' => "CREATE TABLE IF NOT EXISTS oauth_bindings (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ===== 以下为早期安装脚本未包含的表（由 migrate / 本文件补齐） =====
        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NULL,
            title VARCHAR(200) NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'admin_logs' => "CREATE TABLE IF NOT EXISTS admin_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            admin_name VARCHAR(64) NULL,
            action VARCHAR(64) NOT NULL,
            target_type VARCHAR(32) NULL,
            target_id VARCHAR(64) NULL,
            detail TEXT NULL,
            ip VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'admin_login_attempts' => "CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL,
            ip VARCHAR(64) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'risk_rules' => "CREATE TABLE IF NOT EXISTS risk_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(32) NOT NULL,
            scope VARCHAR(32) NOT NULL DEFAULT 'ip',
            limit_count INT NOT NULL DEFAULT 5,
            window_minutes INT NOT NULL DEFAULT 10,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uniq_type_scope (type, scope)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'risk_attempts' => "CREATE TABLE IF NOT EXISTS risk_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(32) NOT NULL,
            scope VARCHAR(32) NOT NULL,
            value VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_scope (type, scope, value),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'cron_jobs' => "CREATE TABLE IF NOT EXISTS cron_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            task VARCHAR(64) NOT NULL,
            schedule VARCHAR(50) NOT NULL DEFAULT 'daily',
            last_run DATETIME NULL,
            last_status TINYINT(1) NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'backups' => "CREATE TABLE IF NOT EXISTS backups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            size INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            admin_id INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'points_log' => "CREATE TABLE IF NOT EXISTS points_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL COMMENT 'sign/recharge/referral/exchange/renew/expire/adjust/other',
            source VARCHAR(64) NULL,
            delta INT NOT NULL,
            balance INT NOT NULL DEFAULT 0,
            remark VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'sso_nonces' => "CREATE TABLE IF NOT EXISTS sso_nonces (
            nonce VARCHAR(32) NOT NULL PRIMARY KEY,
            expire_at DATETIME NOT NULL,
            INDEX expire_at (expire_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // ===== 广告扩展（点击统计 + 新用户弹窗记录） =====
        'ad_clicks' => "CREATE TABLE IF NOT EXISTS ad_clicks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ad_uid VARCHAR(64) NOT NULL DEFAULT '' COMMENT '对应 ad_global_config.custom_links[].uid',
            user_id INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ad_uid (ad_uid),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'user_ad_popup_log' => "CREATE TABLE IF NOT EXISTS user_ad_popup_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            popup_type VARCHAR(32) NOT NULL DEFAULT 'newuser',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_type (user_id, popup_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],

    // ============ 老库增量字段（仅当字段缺失时 ALTER ADD，幂等） ============
    // 全新安装已由上面 tables 建表语句包含，此处用于修复历史数据库。
    'columns' => [
        ['table' => 'users',          'column' => 'two_factor_secret', 'sql' => "ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(64) NULL"],
        ['table' => 'users',          'column' => 'two_factor_enabled', 'sql' => "ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0"],
        ['table' => 'users',          'column' => 'sign_streak',        'sql' => "ALTER TABLE users ADD COLUMN sign_streak INT NOT NULL DEFAULT 0 COMMENT '连续签到天数'"],
        ['table' => 'vhost_models',   'column' => 'category_id',        'sql' => "ALTER TABLE vhost_models ADD COLUMN category_id INT NULL"],
        ['table' => 'vhost_models',   'column' => 'is_elastic',         'sql' => "ALTER TABLE vhost_models ADD COLUMN is_elastic TINYINT NOT NULL DEFAULT 0"],
        ['table' => 'vhosts',         'column' => 'status',             'sql' => "ALTER TABLE vhosts ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0待开通 1已开通 2已过期 3已暂停'"],
        ['table' => 'orders',         'column' => 'remark',             'sql' => "ALTER TABLE orders ADD COLUMN remark VARCHAR(255) NULL"],
        ['table' => 'ticket_replies', 'column' => 'attachment',         'sql' => "ALTER TABLE ticket_replies ADD COLUMN attachment VARCHAR(255) NULL"],
    ],

    // ============ 老库增量索引（仅当索引缺失时 ALTER ADD，幂等） ============
    'indexes' => [
        ['table' => 'admins',              'name' => 'idx_role',      'sql' => "ALTER TABLE admins ADD INDEX idx_role(role)"],
        ['table' => 'admins',              'name' => 'idx_status',    'sql' => "ALTER TABLE admins ADD INDEX idx_status(status)"],
        ['table' => 'users',               'name' => 'email',         'sql' => "ALTER TABLE users ADD UNIQUE KEY email(email)"],
        ['table' => 'vhost_model_durations','name' => 'uk_model_dur',  'sql' => "ALTER TABLE vhost_model_durations ADD UNIQUE KEY uk_model_dur(model_id, duration_type)"],
        ['table' => 'vhost_model_elastic', 'name' => 'uk_model_field','sql' => "ALTER TABLE vhost_model_elastic ADD UNIQUE KEY uk_model_field(model_id, field_name)"],
        ['table' => 'api_keys',            'name' => 'api_key',       'sql' => "ALTER TABLE api_keys ADD UNIQUE KEY api_key(api_key)"],
        ['table' => 'visit_logs',          'name' => 'uk_ip_date',    'sql' => "ALTER TABLE visit_logs ADD UNIQUE KEY uk_ip_date(ip, visit_date)"],
        ['table' => 'verify_codes',        'name' => 'idx_account_type','sql' => "ALTER TABLE verify_codes ADD INDEX idx_account_type(account, type)"],
        ['table' => 'oauth_bindings',      'name' => 'uk_type_uid',   'sql' => "ALTER TABLE oauth_bindings ADD UNIQUE KEY uk_type_uid(oauth_type, social_uid)"],
        ['table' => 'admin_logs',          'name' => 'idx_admin',     'sql' => "ALTER TABLE admin_logs ADD INDEX idx_admin(admin_id)"],
        ['table' => 'admin_logs',          'name' => 'idx_created',   'sql' => "ALTER TABLE admin_logs ADD INDEX idx_created(created_at)"],
        ['table' => 'admin_login_attempts','name' => 'idx_username',  'sql' => "ALTER TABLE admin_login_attempts ADD INDEX idx_username(username)"],
        ['table' => 'admin_login_attempts','name' => 'idx_created',   'sql' => "ALTER TABLE admin_login_attempts ADD INDEX idx_created(created_at)"],
        ['table' => 'risk_rules',          'name' => 'uniq_type_scope','sql' => "ALTER TABLE risk_rules ADD UNIQUE KEY uniq_type_scope(type, scope)"],
        ['table' => 'risk_attempts',       'name' => 'idx_type_scope','sql' => "ALTER TABLE risk_attempts ADD INDEX idx_type_scope(type, scope, value)"],
        ['table' => 'risk_attempts',       'name' => 'idx_created',   'sql' => "ALTER TABLE risk_attempts ADD INDEX idx_created(created_at)"],
        ['table' => 'points_log',          'name' => 'idx_user',      'sql' => "ALTER TABLE points_log ADD INDEX idx_user(user_id)"],
        ['table' => 'points_log',          'name' => 'idx_created',   'sql' => "ALTER TABLE points_log ADD INDEX idx_created(created_at)"],
        ['table' => 'points_log',          'name' => 'idx_type',      'sql' => "ALTER TABLE points_log ADD INDEX idx_type(type)"],
        ['table' => 'sso_nonces',          'name' => 'expire_at',     'sql' => "ALTER TABLE sso_nonces ADD INDEX expire_at(expire_at)"],
        ['table' => 'coupons',             'name' => 'code',          'sql' => "ALTER TABLE coupons ADD UNIQUE KEY code(code)"],
        ['table' => 'orders',              'name' => 'order_no',      'sql' => "ALTER TABLE orders ADD UNIQUE KEY order_no(order_no)"],
    ],
];
