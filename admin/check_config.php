<?php
/**
 * 配置体检页：管理员访问后检查配置是否完整、有无遗漏项。
 * 访问：/admin/check_config.php
 */
define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
include ROOT . 'rd/bootstrap.php';
if (!isAdmin()) {
    redirect('index.php');
    exit;
}

$checks = [];
function addCheck(&$arr, $name, $status, $msg) {
    $arr[] = ['name' => $name, 'status' => $status, 'msg' => $msg];
}

// 1) config.php 是否存在
if (file_exists(ROOT . 'config.php')) {
    addCheck($checks, '主配置文件 config.php', 'ok', '存在');
} else {
    addCheck($checks, '主配置文件 config.php', 'fail', '缺失，请运行安装向导或手动创建');
}

// 2) $dbconfig 完整性
$dbNeed = ['host', 'port', 'user', 'pwd', 'dbname'];
$dbMissing = [];
if (isset($dbconfig) && is_array($dbconfig)) {
    foreach ($dbNeed as $k) {
        if (!array_key_exists($k, $dbconfig) || $dbconfig[$k] === '') $dbMissing[] = $k;
    }
    if (empty($dbMissing)) {
        addCheck($checks, '数据库连接信息 $dbconfig', 'ok', '字段完整（host/user/pwd/dbname）');
    } else {
        addCheck($checks, '数据库连接信息 $dbconfig', 'fail', '缺少字段：' . implode(', ', $dbMissing));
    }
} else {
    addCheck($checks, '数据库连接信息 $dbconfig', 'fail', '未定义 $dbconfig');
}

// 3) 数据库连通性
if ($DB instanceof PDO) {
    addCheck($checks, '数据库连接', 'ok', 'PDO 连接成功');
} else {
    addCheck($checks, '数据库连接', 'fail', '无法连接（请核对 $dbconfig）');
}

// 4) 关键运行时配置键（config 表）
$runtimeKeys = [
    'site_name'     => '站点名称',
    'mnbt_api_url'  => 'MNBT 接口地址',
    'mnbt_bh'       => 'MNBT 户头',
    'mnbt_key'      => 'MNBT 密钥',
    'mnbt_keye'     => 'MNBT 调用密钥',
    'pay_api_url'   => '支付接口地址',
    'pay_pid'       => '支付商户号',
    'pay_key'       => '支付密钥',
    'mail_host'     => '邮件 SMTP 主机',
    'mail_user'     => '邮件账号',
    'mail_pass'     => '邮件密码',
];
if ($DB instanceof PDO) {
    try {
        $have = $DB->query("SELECT k FROM config")->fetchAll(PDO::FETCH_COLUMN);
        $haveMap = array_flip($have);
        $miss = [];
        foreach ($runtimeKeys as $k => $label) {
            if (!isset($haveMap[$k]) || (string)conf($k) === '') $miss[] = $label;
        }
        if (empty($miss)) {
            addCheck($checks, '运行时配置（config 表）', 'ok', '关键项已填写');
        } else {
            addCheck($checks, '运行时配置（config 表）', 'warn', '以下建议项为空：' . implode('、', $miss) . '（可在后台「系统配置」填写）');
        }
    } catch (Exception $e) {
        addCheck($checks, '运行时配置（config 表）', 'warn', '无法读取 config 表：' . $e->getMessage());
    }
}

// 5) 可写目录
foreach (['cache', 'uploads', 'data'] as $d) {
    $p = ROOT . $d;
    if (!is_dir($p)) @mkdir($p, 0755, true);
    if (is_dir($p) && is_writable($p)) {
        addCheck($checks, '目录可写：' . $d . '/', 'ok', '可写');
    } else {
        addCheck($checks, '目录可写：' . $d . '/', 'fail', '不可写，请设置权限为 755（所有者 www）');
    }
}

// 7) 宝塔面板配置（域名检查功能依赖）
$btPanels = json_decode(conf('bt_panels_config', '[]'), true);
if (is_array($btPanels) && count($btPanels) > 0) {
    addCheck($checks, '宝塔面板配置', 'ok', '已配置 ' . count($btPanels) . ' 个面板（域名检查功能可用）');
} else {
    addCheck($checks, '宝塔面板配置', 'warn', '未配置宝塔面板（域名检查功能不可用，请在「系统配置 → 宝塔面板」中添加）');
}

$ok = $warn = $fail = 0;
foreach ($checks as $c) { ${$c['status']}++; }
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>配置体检 - 管理后台</title>
<style>
body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#f0f2f5;margin:0;color:#1f2937}
.topbar{background:#1e293b;color:#fff;padding:14px 22px;display:flex;align-items:center;gap:12px}
.topbar h1{font-size:1.05rem;margin:0;font-weight:600}
.topbar .back{margin-left:auto;color:#cbd5e1;text-decoration:none;font-size:.85rem}
.wrap{padding:22px;max-width:960px;margin:0 auto}
.summary{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.chip{padding:8px 16px;border-radius:10px;font-weight:600;font-size:.9rem}
.chip.ok{background:#dcfce7;color:#166534}.chip.warn{background:#fef3c7;color:#92400e}.chip.fail{background:#fee2e2;color:#b91c1c}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06)}
th,td{padding:12px 14px;text-align:left;font-size:.88rem;border-bottom:1px solid #f1f5f9;vertical-align:top}
th{background:#f8fafc;color:#475569;font-weight:600}
.badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:600}
.badge.ok{background:#dcfce7;color:#166534}.badge.warn{background:#fef3c7;color:#92400e}.badge.fail{background:#fee2e2;color:#b91c1c}
.msg{color:#475569;font-size:.85rem;word-break:break-all}
.foot{margin-top:14px;color:#94a3b8;font-size:.78rem;text-align:center}
</style>
</head>
<body>
<div class="topbar">
  <h1>配置体检</h1>
  <a class="back" href="index.php">← 返回后台</a>
</div>
<div class="wrap">
  <div class="summary">
    <span class="chip ok">正常 <?php echo $ok; ?></span>
    <span class="chip warn">警告 <?php echo $warn; ?></span>
    <span class="chip fail">错误 <?php echo $fail; ?></span>
  </div>
  <table>
    <thead><tr><th>检查项</th><th>状态</th><th>说明</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><?php echo h($c['name']); ?></td>
        <td><span class="badge <?php echo $c['status']; ?>"><?php echo $c['status'] === 'ok' ? '通过' : ($c['status'] === 'warn' ? '警告' : '错误'); ?></span></td>
        <td class="msg"><?php echo h($c['msg']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="foot">绿色=通过，黄色=建议处理，红色=必须修复。详细说明见根目录 CONFIG.md</div>
</div>
</body>
</html>
