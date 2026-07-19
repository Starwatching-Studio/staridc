<?php
/**
 * StarIDC 数据库结构应用器（只增不改，幂等）
 * --------------------------------------------------------------------------
 * 被 install/index.php、migrate.php、admin/db_check.php 共用。
 * 仅接收“已连接的 PDO 对象”，不自行读取配置、不主动连接数据库。
 *
 * 行为：
 *   - 缺表    -> 按标准结构 CREATE TABLE IF NOT EXISTS（完整字段/索引/默认值）
 *   - 缺字段  -> ALTER TABLE ... ADD COLUMN（保留已有数据，使用相同默认值）
 *   - 缺索引  -> ALTER TABLE ... ADD INDEX / ADD UNIQUE KEY
 *   - 绝不    -> DROP 表 / DROP 字段 / 修改字段类型或默认值（避免数据丢失）
 */

if (!function_exists('db_schema_def')) {
    function db_schema_def() {
        static $def = null;
        if ($def === null) {
            $def = require __DIR__ . '/db_schema.php';
        }
        return $def;
    }
}

function dbHasTable($pdo, $table) {
    try {
        $rows = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchAll();
        return count($rows) > 0;
    } catch (Exception $e) {
        return false;
    }
}

function dbHasColumn($pdo, $table, $col) {
    try {
        $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
    foreach ($rows as $r) {
        if (strcasecmp($r['Field'], $col) === 0) return true;
    }
    return false;
}

function dbHasIndex($pdo, $table, $idx) {
    try {
        $rows = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
    foreach ($rows as $r) {
        if (strcasecmp($r['Key_name'], $idx) === 0) return true;
    }
    return false;
}

function dbExecLog($pdo, $sql, &$log, $label) {
    try {
        $pdo->exec($sql);
        $log[] = "✅ {$label}";
        return true;
    } catch (PDOException $e) {
        $log[] = "❌ {$label} 失败: " . $e->getMessage();
        return false;
    }
}

/**
 * 对比当前数据库与标准结构，自动补齐缺失的表/字段/索引。
 * @param PDO  $pdo   已连接的 PDO
 * @param array &$log 操作日志（引用，函数内追加）
 * @return array 统计 ['tables_created'=>int,'columns_added'=>int,'indexes_added'=>int,'skipped'=>int]
 */
function applyDatabaseSchema($pdo, &$log) {
    $schema = db_schema_def();
    $stat = ['tables_created' => 0, 'columns_added' => 0, 'indexes_added' => 0, 'skipped' => 0];

    // 1) 缺失的表 -> 按完整结构创建
    foreach ($schema['tables'] as $table => $createSql) {
        if (!dbHasTable($pdo, $table)) {
            if (dbExecLog($pdo, $createSql, $log, "创建缺失的数据表：{$table}")) {
                $stat['tables_created']++;
            }
        } else {
            $log[] = "⏭️ 数据表已存在：{$table}";
            $stat['skipped']++;
        }
    }

    // 2) 缺失的字段 -> 添加（不触碰已有字段）
    foreach ($schema['columns'] as $c) {
        if (!dbHasColumn($pdo, $c['table'], $c['column'])) {
            if (dbExecLog($pdo, $c['sql'], $log, "表 {$c['table']} 添加缺失字段：{$c['column']}")) {
                $stat['columns_added']++;
            }
        } else {
            $log[] = "⏭️ 字段已存在：{$c['table']}.{$c['column']}";
            $stat['skipped']++;
        }
    }

    // 3) 缺失的索引 -> 添加（不删除、不重建已有索引）
    foreach ($schema['indexes'] as $idx) {
        if (!dbHasIndex($pdo, $idx['table'], $idx['name'])) {
            if (dbExecLog($pdo, $idx['sql'], $log, "表 {$idx['table']} 添加缺失索引：{$idx['name']}")) {
                $stat['indexes_added']++;
            }
        } else {
            $log[] = "⏭️ 索引已存在：{$idx['table']}.{$idx['name']}";
            $stat['skipped']++;
        }
    }

    return $stat;
}
