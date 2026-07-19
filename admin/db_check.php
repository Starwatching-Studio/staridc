<?php
/**
 * 数据库结构一键检查与自动修复（后台运维工具）
 * --------------------------------------------------------------------------
 * 访问：/admin/db_check.php
 * 权限：已登录的超级管理员，或输入管理员密码（与后台登录密码一致）验证通过。
 * 行为：只增不改 —— 仅创建缺失的表、添加缺失的字段与索引，绝不删除/修改已有结构。
 *       幂等，可反复执行，无任何副作用。
 */
define('IN_SYS', true);
require_once __DIR__ . '/../rd/bootstrap.php';

// 未安装或数据库不可用
if (empty($DB)) {
    die('数据库连接失败，请先完成安装并确认 config.php 配置正确。');
}

$error = '';
$success = '';
$logs = [];
$stat = null;
$authorized = false;

// 已登录的超级管理员可直接执行
if (isAdmin() && isSuperAdmin()) {
    $authorized = true;
}

// 密码验证（与后台管理员登录密码一致，使用 password_verify 比对哈希）
if (!$authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if (!verifyCsrf()) {
        $error = '安全校验失败（CSRF），请刷新页面后重试。';
    } else {
        $pw = $_POST['admin_password'];
        $stmt = $DB->prepare("SELECT password FROM admins LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && password_verify($pw, $row['password'])) {
            $authorized = true;
        } else {
            $error = '管理员密码错误。';
        }
    }
}

// 执行检查与修复
if ($authorized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check') {
    if (!verifyCsrf()) {
        $error = '安全校验失败（CSRF），请刷新页面后重试。';
    } else {
        require_once RD_ROOT . 'db_fix.php';
        try {
            $stat = applyDatabaseSchema($DB, $logs);
            $total = $stat['tables_created'] + $stat['columns_added'] + $stat['indexes_added'];
            if ($total === 0) {
                $success = '数据库结构检查完成，一切正常，无需修复。';
            } else {
                $success = "数据库结构检查完成！本次共修复 {$total} 项"
                    . "（新建表 {$stat['tables_created']} 张、补字段 {$stat['columns_added']} 个、补索引 {$stat['indexes_added']} 个）。";
            }
        } catch (Exception $e) {
            $error = '执行失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>数据库结构检查与修复 - StarIDC</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#e0e5ec;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:#2d3436}
.card{background:#e0e5ec;border-radius:24px;padding:40px;max-width:640px;width:92%;box-shadow:8px 8px 16px #a3b1c6,-8px -8px 16px #ffffff}
.card h1{text-align:center;margin-bottom:8px;font-size:1.5rem;color:#6c5ce7}
.card .desc{text-align:center;color:#636e72;margin-bottom:24px;font-size:.9rem;line-height:1.6}
.form-group{margin-bottom:16px}
.form-group label{display:block;margin-bottom:6px;font-weight:600;font-size:.9rem;color:#2d3436}
.form-group input{width:100%;padding:12px 16px;border:none;border-radius:12px;background:#e0e5ec;box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff;font-size:1rem;outline:none;color:#2d3436}
.form-group input:focus{box-shadow:inset 4px 4px 8px #a3b1c6,inset -4px -4px 8px #ffffff,0 0 0 2px #6c5ce744}
.btn{width:100%;padding:12px;border:none;border-radius:12px;background:#6c5ce7;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;box-shadow:4px 4px 8px #a3b1c6,-4px -4px 8px #ffffff;transition:all .2s}
.btn:hover{transform:translateY(-2px);box-shadow:6px 6px 12px #a3b1c6,-6px -6px 12px #ffffff}
.error{background:#ffeaa7;color:#d63031;padding:12px;border-radius:12px;margin-bottom:16px;font-size:.9rem}
.success{background:#dfe6e9;color:#00b894;padding:12px;border-radius:12px;margin-bottom:16px;font-size:.9rem;font-weight:600}
.log-list{background:#dfe6e9;border-radius:12px;padding:16px;margin-top:16px;max-height:340px;overflow-y:auto;font-family:ui-monospace,Menlo,Consolas,monospace}
.log-list li{padding:3px 0;font-size:.82rem;color:#2d3436;list-style:none;white-space:pre-wrap;word-break:break-all}
.actions{text-align:center;margin-top:20px}
.actions a{color:#6c5ce7;text-decoration:none;font-weight:600}
.actions a:hover{text-decoration:underline}
.badge{display:inline-block;background:#6c5ce7;color:#fff;border-radius:20px;padding:2px 12px;font-size:.8rem;margin:0 4px}
.note{font-size:.8rem;color:#b2bec3;margin-top:14px;text-align:center;line-height:1.5}
</style>
</head>
<body>
<div class="card">
<h1>🛠️ 数据库结构检查与修复</h1>
<p class="desc">自动对比当前数据库与标准结构，<b>仅新增</b>缺失的表 / 字段 / 索引，<br>绝不删除或修改已有结构（保护数据）。可反复执行，安全幂等。</p>

<?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<?php if (!empty($logs)): ?>
<ol class="log-list">
<?php foreach ($logs as $line): ?>
<li><?php echo htmlspecialchars($line, ENT_QUOTES); ?></li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ($authorized): ?>
<form method="post">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="check">
<button type="submit" class="btn">🔍 开始检查并自动修复</button>
</form>
<p class="note">您已以管理员身份登录，点击上方按钮立即执行。</p>
<?php else: ?>
<form method="post">
<?php echo csrfField(); ?>
<div class="form-group">
<label>管理员密码</label>
<input type="password" name="admin_password" placeholder="请输入后台管理员密码" required autofocus>
</div>
<button type="submit" class="btn">验证并执行修复</button>
</form>
<p class="note">输入的管理员密码将与数据库中的哈希进行比对（password_verify）。</p>
<?php endif; ?>

<div class="actions">
<a href="index.php">← 返回管理后台</a>
</div>
</div>
</body>
</html>
