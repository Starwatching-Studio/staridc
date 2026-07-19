<?php
/**
 * StarIDC 数据库迁移脚本（幂等，可重复执行）
 * --------------------------------------------------------------------------
 * 用于为新增功能补齐表 / 字段 / 索引，并初始化默认数据。
 *
 * 标准结构统一由 rd/db_schema.php 定义，被 install/index.php 与 admin/db_check.php 共用，
 * 避免“安装脚本与修复脚本结构不一致”导致字段缺失。
 *
 * 使用方法：浏览器或命令行访问一次本文件即可，执行完可删除。
 */
define('ROOT', __DIR__ . '/');
if (!file_exists(ROOT . 'config.php')) {
    die('请先完成安装（缺少 config.php）。');
}
include ROOT . 'config.php';
if (!isset($dbconfig)) {
    die('config.php 中未找到 $dbconfig。');
}
$dsn = 'mysql:host=' . $dbconfig['host'] . ';port=' . ($dbconfig['port'] ?? 3306) . ';dbname=' . $dbconfig['dbname'] . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, $dbconfig['user'], $dbconfig['pwd'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

$log = [];

// 1) 应用标准数据库结构（表 / 字段 / 索引，只增不改，幂等）
require_once ROOT . 'rd/db_fix.php';
applyDatabaseSchema($pdo, $log);

// 2) 初始化默认数据（仅插入缺失项，INSERT IGNORE，不覆盖人工调整）
try {
    $pdo->exec("INSERT IGNORE INTO risk_rules(type,scope,limit_count,window_minutes,enabled) VALUES
        ('register','ip',10,60,1),
        ('register','account',3,60,1),
        ('order','ip',20,10,1),
        ('login','ip',10,15,1)");
    $log[] = 'OK  - 初始化默认风控规则';
} catch (PDOException $e) { $log[] = 'ERR - 初始化风控规则: ' . $e->getMessage(); }

try {
    $pdo->exec("INSERT IGNORE INTO cron_jobs(name,task,schedule,sort_order) VALUES
        ('到期提醒','expire_warning','daily',1),
        ('数据库备份','db_backup','daily',2),
        ('访问统计清理','visit_cleanup','daily',3)");
    $log[] = 'OK  - 初始化默认 Cron 任务';
} catch (PDOException $e) { $log[] = 'ERR - 初始化 Cron 任务: ' . $e->getMessage(); }

// 3) 签到风控规则（补默认，不覆盖人工调整）
try {
    $pdo->exec("INSERT IGNORE INTO risk_rules(type,scope,limit_count,window_minutes,enabled) VALUES
        ('sign','ip',30,1440,1),
        ('sign','account',5,1440,1)");
    $log[] = 'OK  - 补充签到风控规则(sign)';
} catch (PDOException $e) { $log[] = 'ERR - 签到风控规则: ' . $e->getMessage(); }

// 4) 确保 uploads 目录可写
$uploadDir = ROOT . 'uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
if (is_dir($uploadDir) && !is_writable($uploadDir)) {
    @chmod($uploadDir, 0755);
}

header('Content-Type: text/html; charset=utf-8');
echo "<h2>StarIDC 数据库迁移完成</h2><pre>\n";
foreach ($log as $line) echo h($line) . "\n";
echo "</pre><p>若无 ERROR，可删除本文件 migrate.php。</p>";
function h($s){return htmlspecialchars($s, ENT_QUOTES);}
