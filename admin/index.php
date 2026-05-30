<?php
define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/MNBT_API.php';

if (file_exists(ROOT . 'config.php')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $DB->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            redirect('index.php');
        } else {
            $loginError = '账号或密码错误';
        }
    }
    if (!isAdmin()) {
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>管理后台 - 登录</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;padding:20px}
.login-box{background:rgba(255,255,255,0.95);backdrop-filter:blur(20px);border-radius:24px;padding:48px 40px;max-width:420px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25)}
.login-box h1{text-align:center;color:#1a1a2e;margin-bottom:8px;font-size:1.8rem;font-weight:700}
.login-box .subtitle{text-align:center;color:#6b7280;margin-bottom:32px;font-size:.9rem}
.input-group{position:relative;margin-bottom:20px}
.input-group label{display:block;margin-bottom:8px;font-weight:600;color:#374151;font-size:.9rem}
.input-group input{width:100%;padding:14px 16px 14px 44px;border:2px solid #e5e7eb;border-radius:12px;font-size:1rem;transition:all .3s;background:#f9fafb}
.input-group input:focus{border-color:#667eea;outline:none;background:#fff;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
.input-group i{position:absolute;left:16px;bottom:14px;color:#9ca3af;font-size:1.1rem}
.btn{width:100%;padding:16px;border:none;border-radius:12px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;font-size:1rem;font-weight:600;cursor:pointer;transition:all .3s;box-shadow:0 4px 15px rgba(102,126,234,0.4)}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(102,126,234,0.5)}
.btn:active{transform:translateY(0)}
.err{background:#fef2f2;color:#dc2626;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.9rem;text-align:center;border:1px solid #fecaca}
.logo{text-align:center;margin-bottom:24px}
.logo i{font-size:3rem;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:4rem}
</style>
</head>
<body>
<div class="login-box">
<div class="logo"><i class="fas fa-server"></i></div>
<h1>管理后台</h1>
<p class="subtitle">云虚拟主机分销平台</p>
<?php if(!empty($loginError)) echo '<div class="err"><i class="fas fa-exclamation-circle"></i> '.$loginError.'</div>';?>
<form method="post">
<input type="hidden" name="admin_login" value="1">
<div class="input-group">
<label>管理员账号</label>
<i class="fas fa-user"></i>
<input type="text" name="username" required placeholder="请输入账号">
</div>
<div class="input-group">
<label>密码</label>
<i class="fas fa-lock"></i>
<input type="password" name="password" required placeholder="请输入密码">
</div>
<button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> 登录</button>
</form>
</div>
</body>
</html>
        <?php
        exit;
    }
} else {
    redirect('../install/');
}

$page = $_GET['page'] ?? 'dashboard';
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'save_config':
            $fields = ['site_name','mnbt_api_url','mnbt_bh','mnbt_key','mnbt_keye','mnbt_vs',
                'pay_api_url','pay_pid','pay_key',
                'mail_host','mail_port','mail_user','mail_pass','mail_name','mail_security','mail_enabled',
                'email_domain_restrict_enabled','email_domain_whitelist',
                'sign_min','sign_max','theme','announcement',
                'register_points_enabled','register_points',
                'points_200_price','points_400_price','points_1000_price','points_3000_price',
                'referral_enabled','referral_reward_points'];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) setConf($f, trim($_POST[$f]));
            }
            loadConfig();
            $msg = '配置保存成功'; $msgType = 'success';
            break;
        case 'test_mnbt':
            $r = MNBT_API::testConnection();
            $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
            break;
        case 'test_mail':
            $testEmail = trim($_POST['test_email'] ?? '');
            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $msg = '请输入有效的测试邮箱地址'; $msgType = 'error';
            } else {
                $code = '';
                for ($i = 0; $i < 6; $i++) $code .= mt_rand(0, 9);
                $result = Mailer::send($testEmail, '邮件测试 - ' . conf('site_name', '云主机'), '这是一封测试邮件，验证码：' . $code . '。如果您收到此邮件，说明邮件配置正确。');
                if ($result) {
                    $msg = '测试邮件已发送到 ' . h($testEmail) . '，请检查收件箱（包括垃圾邮件）'; $msgType = 'success';
                } else {
                    $msg = '邮件发送失败，请检查邮箱配置是否正确'; $msgType = 'error';
                }
            }
            break;
        case 'add_model':
            $stmt = $DB->prepare("INSERT INTO vhost_models(name,web_space,db_space,flow,domain_limit,price,sort_order) VALUES(?,?,?,?,?,?,?)");
            $stmt->execute([$_POST['name'],intval($_POST['web_space']),intval($_POST['db_space']),intval($_POST['flow']),intval($_POST['domain_limit']),intval($_POST['price']),intval($_POST['sort_order'])]);
            $msg = '型号添加成功'; $msgType = 'success';
            break;
        case 'toggle_model':
            $stmt = $DB->prepare("UPDATE vhost_models SET status=? WHERE id=?");
            $stmt->execute([intval($_POST['status']),intval($_POST['id'])]);
            $msg = '操作成功'; $msgType = 'success';
            break;
        case 'del_model':
            $stmt = $DB->prepare("DELETE FROM vhost_models WHERE id=?");
            $stmt->execute([intval($_POST['id'])]);
            $msg = '型号已删除'; $msgType = 'success';
            break;
        case 'del_vhost':
            $vid = intval($_POST['id']);
            $vstmt = $DB->prepare("SELECT * FROM vhosts WHERE id=?");
            $vstmt->execute([$vid]);
            $vh = $vstmt->fetch();
            if ($vh && $vh['mnbt_opened']) {
                MNBT_API::deleteHost($vh['account']);
            }
            $stmt = $DB->prepare("DELETE FROM vhosts WHERE id=?");
            $stmt->execute([$vid]);
            $msg = '虚拟主机已删除'; $msgType = 'success';
            break;
        case 'del_vhost_batch':
            $ids = $_POST['ids'] ?? [];
            if (is_string($ids)) $ids = $ids !== '' ? explode(',', $ids) : [];
            $count = 0;
            foreach ($ids as $vid) {
                $vid = intval($vid);
                $vstmt = $DB->prepare("SELECT * FROM vhosts WHERE id=?");
                $vstmt->execute([$vid]);
                $vh = $vstmt->fetch();
                if ($vh && $vh['mnbt_opened']) {
                    MNBT_API::deleteHost($vh['account']);
                }
                $stmt = $DB->prepare("DELETE FROM vhosts WHERE id=?");
                $stmt->execute([$vid]);
                $count++;
            }
            $msg = '已删除 ' . $count . ' 台虚拟主机'; $msgType = 'success';
            break;
        case 'edit_user':
            $userId = intval($_POST['id']);
            $email = trim($_POST['email'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $points = intval($_POST['points'] ?? 0);
            $password = $_POST['password'] ?? '';
            
            // 验证邮箱格式
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $msg = '邮箱格式不正确';
                $msgType = 'error';
                break;
            }
            
            // 检查邮箱是否已被其他用户使用
            $stmtCheck = $DB->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $userId]);
            if ($stmtCheck->fetch()) {
                $msg = '该邮箱已被其他用户使用';
                $msgType = 'error';
                break;
            }
            
            // 如果填写了新密码，则更新密码
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $msg = '密码至少6位';
                    $msgType = 'error';
                    break;
                }
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $DB->prepare("UPDATE users SET email=?,nickname=?,points=?,password=? WHERE id=?");
                $stmt->execute([$email, $nickname, $points, $hashedPassword, $userId]);
            } else {
                $stmt = $DB->prepare("UPDATE users SET email=?,nickname=?,points=? WHERE id=?");
                $stmt->execute([$email, $nickname, $points, $userId]);
            }
            $msg = '用户信息已更新'; $msgType = 'success';
            break;
        case 'del_user':
            $stmt = $DB->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([intval($_POST['id'])]);
            $msg = '用户已删除'; $msgType = 'success';
            break;
        case 'del_user_batch':
            $ids = $_POST['ids'] ?? [];
            if (is_string($ids)) $ids = $ids !== '' ? explode(',', $ids) : [];
            $count = 0;
            foreach ($ids as $uid) {
                $uid = intval($uid);
                $stmt = $DB->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$uid]);
                $count++;
            }
            $msg = '已删除 ' . $count . ' 个用户'; $msgType = 'success';
            break;
        case 'add_points_batch':
            $ids = $_POST['ids'] ?? [];
            if (is_string($ids)) $ids = $ids !== '' ? explode(',', $ids) : [];
            $points = intval($_POST['points_amount'] ?? 0);
            $count = 0;
            foreach ($ids as $uid) {
                $uid = intval($uid);
                $stmt = $DB->prepare("UPDATE users SET points=points+? WHERE id=?");
                $stmt->execute([$points, $uid]);
                $count++;
            }
            $msg = '已为 ' . $count . ' 个用户添加 ' . $points . ' 积分'; $msgType = 'success';
            break;
        case 'save_announcement':
            setConf('announcement', $_POST['announcement'] ?? '');
            loadConfig();
            $msg = '公告已保存'; $msgType = 'success';
            break;
    }
}

$totalUsers = $DB->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$totalVhosts = $DB->query("SELECT COUNT(*) as c FROM vhosts")->fetch()['c'];
$todayVisits = $DB->query("SELECT COUNT(*) as c FROM visit_logs WHERE visit_date=CURDATE()")->fetch()['c'];
$totalOrders = $DB->query("SELECT COUNT(*) as c FROM orders WHERE status=1")->fetch()['c'];
$pages = ['dashboard','config','vhost_models','vhosts','users','prices','announcement','statistics'];
if (!in_array($page, $pages)) $page = 'dashboard';

$pageTitles = [
    'dashboard' => ['icon' => 'fa-chart-pie', 'title' => '仪表盘', 'desc' => '系统数据总览'],
    'config' => ['icon' => 'fa-cog', 'title' => '系统配置', 'desc' => '网站参数设置'],
    'vhost_models' => ['icon' => 'fa-cube', 'title' => '主机型号', 'desc' => '产品套餐管理'],
    'vhosts' => ['icon' => 'fa-server', 'title' => '虚拟主机', 'desc' => '用户主机列表'],
    'users' => ['icon' => 'fa-users', 'title' => '用户管理', 'desc' => '会员信息管理'],
    'prices' => ['icon' => 'fa-tags', 'title' => '价格设置', 'desc' => '积分与定价'],
    'announcement' => ['icon' => 'fa-bullhorn', 'title' => '公告管理', 'desc' => '网站公告发布'],
    'statistics' => ['icon' => 'fa-chart-line', 'title' => '消费统计', 'desc' => '运营数据分析']
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>管理后台 - <?php echo $pageTitles[$page]['title']; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
--primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
--primary-solid: #667eea;
--success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
--warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
--danger: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
--info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
--dark: #1a1a2e;
--gray-100: #f7fafc;
--gray-200: #edf2f7;
--gray-300: #e2e8f0;
--gray-500: #718096;
--gray-700: #4a5568;
--gray-900: #1a202c;
--shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
--shadow: 0 4px 6px rgba(0,0,0,0.07);
--shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;color:var(--gray-700);font-size:.95rem;background:var(--gray-100);line-height:1.6}
a{text-decoration:none;color:inherit}
.clearfix::after{content:'';display:table;clear:both}

/* 顶部导航 */
.topbar{background:#fff;box-shadow:var(--shadow);padding:0 24px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar-left{display:flex;align-items:center;gap:16px}
.topbar-logo{font-size:1.4rem;font-weight:700;background:var(--primary);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.topbar-page{color:var(--gray-500);font-size:.9rem}
.topbar-right{display:flex;align-items:center;gap:20px}
.topbar-user{display:flex;align-items:center;gap:10px;cursor:pointer;padding:6px 12px;border-radius:10px;transition:all .2s}
.topbar-user:hover{background:var(--gray-100)}
.topbar-user i{color:var(--gray-500)}
.user-avatar{width:36px;height:36px;border-radius:10px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600}

/* 主布局 */
.layout{display:flex;min-height:calc(100vh - 64px)}
.sidebar{width:240px;background:#fff;box-shadow:var(--shadow-sm);padding:20px 0;flex-shrink:0}
.sidebar-title{padding:0 20px 16px;font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--gray-500);font-weight:600;border-bottom:1px solid var(--gray-200);margin-bottom:12px}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--gray-700);transition:all .2s;font-weight:500}
.sidebar-nav a:hover{background:var(--gray-100);color:var(--primary-solid)}
.sidebar-nav a.active{background:linear-gradient(90deg,rgba(102,126,234,0.1) 0%,rgba(118,75,162,0.1) 100%);color:var(--primary-solid);border-right:3px solid var(--primary-solid);font-weight:600}
.sidebar-nav a i{width:20px;text-align:center;color:var(--gray-500)}
.sidebar-nav a.active i{color:var(--primary-solid)}
.sidebar-footer{padding:20px;margin-top:auto;border-top:1px solid var(--gray-200)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:var(--gray-500);font-size:.85rem;transition:all .2s}
.sidebar-footer a:hover{color:var(--primary-solid)}

/* 主内容区 */
.main{flex:1;padding:24px;overflow-x:hidden}
.page-header{margin-bottom:24px}
.page-title{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.page-title h1{font-size:1.5rem;font-weight:700;color:var(--gray-900)}
.page-title .icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;background:var(--primary)}
.page-desc{color:var(--gray-500);font-size:.9rem}

/* 消息提示 */
.alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:.9rem;display:flex;align-items:center;gap:10px;animation:slideIn .3s ease}
.alert-success{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0}
.alert-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.alert i{font-size:1.1rem}
@keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}

/* 统计卡片 */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:24px}
.stat-card{background:#fff;border-radius:16px;padding:24px;box-shadow:var(--shadow);transition:all .3s;cursor:default}
.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.stat-card .icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:16px}
.stat-card .icon.users{background:linear-gradient(135deg,#667eea22,#764ba222);color:#667eea}
.stat-card .icon.vhosts{background:linear-gradient(135deg,#11998e22,#38ef7d22);color:#11998e}
.stat-card .icon.visits{background:linear-gradient(135deg,#4facfe22,#00f2fe22);color:#4facfe}
.stat-card .icon.orders{background:linear-gradient(135deg,#f093fb22,#f5576c22);color:#f5576c}
.stat-card .num{font-size:2rem;font-weight:700;color:var(--gray-900);margin-bottom:4px}
.stat-card .label{color:var(--gray-500);font-size:.85rem}
.stat-card .trend{font-size:.8rem;margin-top:8px;display:flex;align-items:center;gap:4px}
.stat-card .trend.up{color:#059669}
.stat-card .trend.down{color:#dc2626}

/* 卡片 */
.card{background:#fff;border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:20px}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--gray-200)}
.card-title{font-size:1.1rem;font-weight:600;color:var(--gray-900);display:flex;align-items:center;gap:10px}
.card-title i{color:var(--primary-solid)}
.card-actions{display:flex;gap:8px}

/* 表格 */
.table-wrapper{overflow-x:auto}
table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:12px;overflow:hidden}
th{background:var(--gray-100);color:var(--gray-700);padding:14px 16px;text-align:left;font-weight:600;font-size:.85rem;text-transform:uppercase;letter-spacing:.5px}
td{padding:14px 16px;border-bottom:1px solid var(--gray-200);font-size:.9rem}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--gray-100)}
tr:last-child:hover td{background:transparent}

/* 按钮 */
.btn{padding:10px 18px;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(102,126,234,0.3)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(102,126,234,0.4)}
.btn-outline{background:transparent;border:2px solid var(--gray-300);color:var(--gray-700)}
.btn-outline:hover{background:var(--gray-100);border-color:var(--gray-400)}
.btn-sm{padding:6px 12px;font-size:.8rem;border-radius:8px}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,65,108,0.3)}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(17,153,142,0.3)}

/* 表单 */
.form-group{margin-bottom:20px}
.form-label{display:block;margin-bottom:8px;font-weight:600;color:var(--gray-700);font-size:.9rem}
.form-control{width:100%;padding:12px 16px;border:2px solid var(--gray-200);border-radius:10px;font-size:.95rem;transition:all .3s;background:#fff}
.form-control:focus{border-color:var(--primary-solid);outline:none;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
.form-control::placeholder{color:var(--gray-500)}
textarea.form-control{min-height:120px;resize:vertical}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 16px center;padding-right:44px}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.form-hint{font-size:.8rem;color:var(--gray-500);margin-top:6px}

/* 徽章 */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600}
.badge-success{background:#ecfdf5;color:#059669}
.badge-danger{background:#fef2f2;color:#dc2626}
.badge-warning{background:#fffbeb;color:#d97706}
.badge-info{background:#eff6ff;color:#2563eb}
.badge-purple{background:#f5f3ff;color:#7c3aed}

/* 标签页 */
.tabs{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;background:var(--gray-100);padding:6px;border-radius:12px}
.tab{padding:10px 20px;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;color:var(--gray-600);transition:all .2s;background:transparent}
.tab:hover{color:var(--primary-solid)}
.tab.active{background:#fff;color:var(--primary-solid);box-shadow:var(--shadow-sm)}
.tab-content{display:none}
.tab-content.active{display:block}

/* 搜索框 */
.search-box{display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.search-input{flex:1;min-width:200px;position:relative}
.search-input input{padding-left:44px}
.search-input i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--gray-500)}

/* 批量操作 */
.batch-actions{display:flex;gap:12px;align-items:center;padding:16px;background:var(--gray-100);border-radius:12px;margin-bottom:16px;flex-wrap:wrap}
.batch-actions label{font-weight:600;font-size:.9rem;color:var(--gray-700)}

/* 系统状态 */
.status-list{list-style:none}
.status-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--gray-200)}
.status-item:last-child{border-bottom:none}
.status-item .label{color:var(--gray-600);font-size:.9rem}
.status-item .value{font-weight:600;color:var(--gray-900)}
.status-item .value.success{color:#059669}
.status-item .value.error{color:#dc2626}
.status-item .value.warning{color:#d97706}

/* 主题选择器 */
.theme-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:16px}
.theme-card{background:var(--gray-100);border-radius:14px;padding:16px;cursor:pointer;transition:all .3s;border:3px solid transparent}
.theme-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.theme-card.active{border-color:var(--primary-solid);background:#fff}
.theme-preview{height:100px;border-radius:10px;overflow:hidden;position:relative;margin-bottom:12px}
.theme-check{font-size:.85rem;color:var(--primary-solid);font-weight:600;display:flex;align-items:center;gap:6px}
.theme-check i{width:20px;height:20px;border-radius:50%;background:var(--primary-solid);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.7rem}

/* 分页 */
.pagination{display:flex;gap:8px;align-items:center;justify-content:center;margin-top:20px}
.pagination a,.pagination span{padding:8px 14px;border-radius:8px;font-size:.9rem;transition:all .2s}
.pagination a{background:var(--gray-100);color:var(--gray-700)}
.pagination a:hover{background:var(--primary-solid);color:#fff}
.pagination .active{background:var(--primary-solid);color:#fff}

/* 响应式 */
@media(max-width:1024px){
.sidebar{width:200px}
}
@media(max-width:768px){
.layout{flex-direction:column}
.sidebar{width:100%;padding:12px 0}
.sidebar-nav{display:flex;flex-wrap:wrap;gap:4px;padding:0 12px}
.sidebar-nav a{padding:10px 14px;border-radius:10px;flex:1;justify-content:center;min-width:120px}
.sidebar-nav a.active{border-right:none;border-bottom:3px solid var(--primary-solid)}
.sidebar-title{display:none}
.sidebar-footer{display:none}
.main{padding:16px}
.stats-grid{grid-template-columns:repeat(2,1fr)}
.topbar{padding:0 16px}
.page-title h1{font-size:1.3rem}
}

/* 动画 */
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.fade-in{animation:fadeIn .3s ease}
</style>
</head>
<body>
<!-- 顶部导航 -->
<nav class="topbar">
<div class="topbar-left">
<span class="topbar-logo"><i class="fas fa-cloud"></i> 管理后台</span>
<span class="topbar-page">/ <?php echo $pageTitles[$page]['title']; ?></span>
</div>
<div class="topbar-right">
<div class="topbar-user">
<div class="user-avatar"><?php echo mb_substr($_SESSION['admin_user'] ?? 'A', 0, 1); ?></div>
<span><?php echo h($_SESSION['admin_user'] ?? '管理员'); ?></span>
</div>
<a href="../index.php" class="btn btn-outline btn-sm"><i class="fas fa-home"></i> 返回前台</a>
</div>
</nav>

<div class="layout">
<!-- 侧边栏 -->
<aside class="sidebar">
<div class="sidebar-title">导航菜单</div>
<nav class="sidebar-nav">
<?php foreach($pages as $p): ?>
<a href="?page=<?php echo $p; ?>" class="<?php echo $page===$p?'active':''; ?>">
<i class="fas <?php echo $pageTitles[$p]['icon']; ?>"></i>
<span><?php echo $pageTitles[$p]['title']; ?></span>
</a>
<?php endforeach; ?>
</nav>
<div class="sidebar-footer">
<a href="../index.php"><i class="fas fa-home"></i> 返回前台首页</a>
</div>
</aside>

<!-- 主内容 -->
<main class="main">
<?php if($msg): ?>
<div class="alert alert-<?php echo $msgType; ?>">
<i class="fas fa-<?php echo $msgType==='success'?'check-circle':'exclamation-circle'; ?>"></i>
<?php echo h($msg); ?>
</div>
<?php endif; ?>

<!-- 页面标题 -->
<div class="page-header">
<div class="page-title">
<div class="icon"><i class="fas <?php echo $pageTitles[$page]['icon']; ?>"></i></div>
<div>
<h1><?php echo $pageTitles[$page]['title']; ?></h1>
<p class="page-desc"><?php echo $pageTitles[$page]['desc']; ?></p>
</div>
</div>
</div>

<!-- 仪表盘 -->
<?php if($page==='dashboard'): ?>
<div class="stats-grid">
<div class="stat-card fade-in">
<div class="icon users"><i class="fas fa-users"></i></div>
<div class="num"><?php echo number_format($totalUsers); ?></div>
<div class="label">注册用户</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.1s">
<div class="icon vhosts"><i class="fas fa-server"></i></div>
<div class="num"><?php echo number_format($totalVhosts); ?></div>
<div class="label">虚拟主机</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.2s">
<div class="icon visits"><i class="fas fa-eye"></i></div>
<div class="num"><?php echo number_format($todayVisits); ?></div>
<div class="label">今日访问</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.3s">
<div class="icon orders"><i class="fas fa-shopping-cart"></i></div>
<div class="num"><?php echo number_format($totalOrders); ?></div>
<div class="label">成功订单</div>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-info-circle"></i> 系统状态</h3>
</div>
<ul class="status-list">
<li class="status-item">
<span class="label"><i class="fas fa-code"></i> PHP版本</span>
<span class="value"><?php echo PHP_VERSION; ?></span>
</li>
<li class="status-item">
<span class="label"><i class="fas fa-database"></i> 数据库</span>
<span class="value success">MySQL 已连接</span>
</li>
<li class="status-item">
<span class="label"><i class="fas fa-plug"></i> MNBT对接</span>
<span class="value <?php echo conf('mnbt_api_url')?'success':'warning'; ?>"><?php echo conf('mnbt_api_url')?'已配置':'未配置'; ?></span>
</li>
<li class="status-item">
<span class="label"><i class="fas fa-credit-card"></i> 支付接口</span>
<span class="value <?php echo conf('pay_api_url')?'success':'warning'; ?>"><?php echo conf('pay_api_url')?'已配置':'未配置'; ?></span>
</li>
<li class="status-item">
<span class="label"><i class="fas fa-envelope"></i> 邮件服务</span>
<span class="value <?php echo conf('mail_enabled')?'success':'warning'; ?>"><?php echo conf('mail_enabled')?'已启用':'未启用'; ?></span>
</li>
<li class="status-item">
<span class="label"><i class="fas fa-clock"></i> 服务器时间</span>
<span class="value"><?php echo date('Y-m-d H:i:s'); ?></span>
</li>
</ul>
</div>
<?php endif; ?>

<!-- 系统配置 -->
<?php if($page==='config'): ?>
<div class="tabs">
<a class="tab active" onclick="showTab('tab-mnbt')"><i class="fas fa-server"></i> MNBT对接</a>
<a class="tab" onclick="showTab('tab-pay')"><i class="fas fa-credit-card"></i> 支付接口</a>
<a class="tab" onclick="showTab('tab-mail')"><i class="fas fa-envelope"></i> 邮件服务</a>
<a class="tab" onclick="showTab('tab-site')"><i class="fas fa-cog"></i> 网站设置</a>
</div>

<form method="post"><input type="hidden" name="action" value="save_config">

<div id="tab-mnbt" class="tab-content active">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-server"></i> MNBT 对接配置</h3>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">API地址</label>
<input type="text" name="mnbt_api_url" value="<?php echo h(conf('mnbt_api_url')); ?>" class="form-control" placeholder="http://xxx/api/api.php">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">宝塔编号 (mn_bh)</label>
<input type="text" name="mnbt_bh" value="<?php echo h(conf('mnbt_bh')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">API秘钥 (mn_key)</label>
<input type="text" name="mnbt_key" value="<?php echo h(conf('mnbt_key')); ?>" class="form-control">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">宝塔调用秘钥 (mn_keye)</label>
<input type="text" name="mnbt_keye" value="<?php echo h(conf('mnbt_keye')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">插件版本 (mn_vs)</label>
<input type="text" name="mnbt_vs" value="<?php echo h(conf('mnbt_vs','16')); ?>" class="form-control">
</div>
</div>
<div style="display:flex;gap:12px;margin-top:24px">
<button type="button" class="btn btn-outline" onclick="this.form.action.value='test_mnbt';this.form.submit()"><i class="fas fa-plug"></i> 测试连接</button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存配置</button>
</div>
</div>
</div>

<div id="tab-pay" class="tab-content">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-credit-card"></i> 易支付配置</h3>
</div>
<div class="form-group">
<label class="form-label">支付接口地址</label>
<input type="text" name="pay_api_url" value="<?php echo h(conf('pay_api_url')); ?>" class="form-control" placeholder="https://pay.xxx.com/">
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">商户ID (pid)</label>
<input type="text" name="pay_pid" value="<?php echo h(conf('pay_pid')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">商户密钥 (key)</label>
<input type="text" name="pay_key" value="<?php echo h(conf('pay_key')); ?>" class="form-control">
</div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:24px"><i class="fas fa-save"></i> 保存配置</button>
</div>
</div>

<div id="tab-mail" class="tab-content">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-envelope"></i> 邮件服务配置</h3>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">启用邮箱验证</label>
<select name="mail_enabled" class="form-control">
<option value="0" <?php echo conf('mail_enabled')!='1'?'selected':''; ?>>关闭</option>
<option value="1" <?php echo conf('mail_enabled')=='1'?'selected':''; ?>>开启</option>
</select>
</div>
<div class="form-group">
<label class="form-label">SMTP服务器</label>
<input type="text" name="mail_host" value="<?php echo h(conf('mail_host','smtp.qq.com')); ?>" class="form-control">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">端口</label>
<input type="number" name="mail_port" value="<?php echo h(conf('mail_port','465')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">发件邮箱</label>
<input type="text" name="mail_user" value="<?php echo h(conf('mail_user')); ?>" class="form-control">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">邮箱密码/授权码</label>
<input type="password" name="mail_pass" value="<?php echo h(conf('mail_pass')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">发件人名称</label>
<input type="text" name="mail_name" value="<?php echo h(conf('mail_name')); ?>" class="form-control">
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">加密方式</label>
<select name="mail_security" class="form-control">
<option value="ssl" <?php echo conf('mail_security','ssl')==='ssl'?'selected':''; ?>>SSL</option>
<option value="tls" <?php echo conf('mail_security')==='tls'?'selected':''; ?>>TLS</option>
</select>
</div>
</div>

<div class="card" style="margin-top:20px;background:var(--gray-100)">
<h4 style="margin-bottom:12px;color:var(--gray-700)"><i class="fas fa-shield-alt"></i> 邮箱后缀限制</h4>
<div class="form-row">
    <div class="form-group">
        <label class="form-label">启用邮箱后缀限制</label>
        <select name="email_domain_restrict_enabled" class="form-control">
            <option value="0" <?php echo conf('email_domain_restrict_enabled')==='1'?'':'selected'; ?>>关闭</option>
            <option value="1" <?php echo conf('email_domain_restrict_enabled')==='1'?'selected':''; ?>>开启</option>
        </select>
        <div class="form-tip">开启后，仅允许指定邮箱后缀的用户注册</div>
    </div>
    <div class="form-group">
        <label class="form-label">允许的邮箱后缀</label>
        <input type="text" name="email_domain_whitelist" value="<?php echo h(conf('email_domain_whitelist','')); ?>" class="form-control" placeholder="@qq.com,@gmail.com,@outlook.com">
        <div class="form-tip">多个后缀用英文逗号隔开，例如：@qq.com,@gmail.com（需先开启上方开关）</div>
    </div>
</div>
</div>

<div class="card" style="margin-top:20px;background:var(--gray-100)">
<h4 style="margin-bottom:12px;color:var(--gray-700)"><i class="fas fa-paper-plane"></i> 测试发件</h4>
<div class="form-row">
<div class="form-group">
<label class="form-label">收件邮箱</label>
<input type="email" name="test_email" class="form-control" placeholder="输入测试邮箱地址">
</div>
<div class="form-group" style="display:flex;align-items:flex-end">
<button type="button" class="btn btn-outline" onclick="this.form.action.value='test_mail';this.form.submit()"><i class="fas fa-paper-plane"></i> 发送测试邮件</button>
</div>
</div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:24px"><i class="fas fa-save"></i> 保存配置</button>
</div>
</div>

<div id="tab-site" class="tab-content">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-cog"></i> 网站设置</h3>
</div>
<div class="form-group">
<label class="form-label">网站名称</label>
<input type="text" name="site_name" value="<?php echo h(conf('site_name','云主机')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">选择主题</label>
<input type="hidden" name="theme" id="themeInput" value="<?php echo h(conf('theme','nomorphism')); ?>">
<div class="theme-grid">
<?php 
$currentTheme = conf('theme','nomorphism');
$themes = [
    'nomorphism' => ['name' => '新拟态风格', 'preview' => '#e8ecf1', 'accent' => '#6366f1', 'desc' => '柔和的阴影与渐变'],
    'modern-gradient' => ['name' => '现代渐变', 'preview' => '#0f0f23', 'accent' => '#00d9ff', 'desc' => '深色沉浸式体验']
];
$dirs = array_filter(glob(ROOT.'theme/*'), 'is_dir');
foreach($dirs as $d){
    $n = basename($d);
    if(!isset($themes[$n])){
        $themes[$n] = ['name' => $n, 'preview' => '#e0e5ec', 'accent' => '#6c5ce7', 'desc' => '自定义主题'];
    }
}
foreach($themes as $key => $t): ?>
<div class="theme-card <?php echo $currentTheme===$key?'active':''; ?>" onclick="selectTheme('<?php echo h($key); ?>')" data-theme="<?php echo h($key); ?>">
<div class="theme-preview" style="background:<?php echo $t['preview']; ?>"></div>
<div>
<div style="font-weight:600;margin-bottom:4px"><?php echo h($t['name']); ?></div>
<div style="font-size:.8rem;color:var(--gray-500)"><?php echo h($t['desc']); ?></div>
<?php if($currentTheme===$key): ?>
<div class="theme-check"><i class="fas fa-check"></i> 已启用</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:24px"><i class="fas fa-save"></i> 保存配置</button>
</div>
</div>

</form>

<script>
function showTab(id){
document.querySelectorAll('.tab-content').forEach(function(el){el.classList.remove('active')});
document.querySelectorAll('.tab').forEach(function(el){el.classList.remove('active')});
document.getElementById(id).classList.add('active');
event.target.classList.add('active');
}
function selectTheme(name){
document.querySelectorAll('.theme-card').forEach(function(el){el.classList.remove('active')});
var card=document.querySelector('.theme-card[data-theme="'+name+'"]');
if(card){card.classList.add('active')}
document.getElementById('themeInput').value=name;
}
</script>
<?php endif; ?>

<!-- 主机型号 -->
<?php if($page==='vhost_models'): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-plus-circle"></i> 添加型号</h3>
</div>
<form method="post" class="form-row">
<input type="hidden" name="action" value="add_model">
<div class="form-group" style="flex:2">
<label class="form-label">名称</label>
<input type="text" name="name" required class="form-control" placeholder="如：入门型">
</div>
<div class="form-group">
<label class="form-label">网页空间 (MB)</label>
<input type="number" name="web_space" required class="form-control">
</div>
<div class="form-group">
<label class="form-label">数据库 (MB)</label>
<input type="number" name="db_space" required class="form-control">
</div>
<div class="form-group">
<label class="form-label">流量 (GB/月)</label>
<input type="number" name="flow" value="30" class="form-control">
</div>
<div class="form-group">
<label class="form-label">域名数</label>
<input type="number" name="domain_limit" value="5" class="form-control">
</div>
<div class="form-group">
<label class="form-label">价格 (积分)</label>
<input type="number" name="price" required class="form-control">
</div>
<div class="form-group">
<label class="form-label">排序</label>
<input type="number" name="sort_order" value="0" class="form-control">
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> 添加</button>
</div>
</form>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-list"></i> 型号列表</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>ID</th>
<th>名称</th>
<th>网页空间</th>
<th>数据库</th>
<th>流量</th>
<th>域名数</th>
<th>积分</th>
<th>状态</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php $models=$DB->query("SELECT * FROM vhost_models ORDER BY sort_order,id")->fetchAll(); foreach($models as $m): ?>
<tr>
<td><?php echo $m['id']; ?></td>
<td><strong><?php echo h($m['name']); ?></strong></td>
<td><?php echo $m['web_space']; ?> MB</td>
<td><?php echo $m['db_space']; ?> MB</td>
<td><?php echo $m['flow']; ?> GB</td>
<td><?php echo $m['domain_limit']; ?></td>
<td><span class="badge badge-purple"><?php echo $m['price']; ?> 积分</span></td>
<td>
<?php if($m['status']): ?>
<span class="badge badge-success"><i class="fas fa-check"></i> 上架</span>
<?php else: ?>
<span class="badge badge-danger"><i class="fas fa-times"></i> 下架</span>
<?php endif; ?>
</td>
<td>
<form method="post" style="display:inline">
<input type="hidden" name="action" value="toggle_model">
<input type="hidden" name="id" value="<?php echo $m['id']; ?>">
<input type="hidden" name="status" value="<?php echo $m['status']?0:1; ?>">
<button type="submit" class="btn btn-sm <?php echo $m['status']?'btn-outline':'btn-success'; ?>">
<?php echo $m['status']?'下架':'上架'; ?>
</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
<input type="hidden" name="action" value="del_model">
<input type="hidden" name="id" value="<?php echo $m['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($models)): ?>
<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无型号数据
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- 虚拟主机 -->
<?php if($page==='vhosts'): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-search"></i> 搜索筛选</h3>
</div>
<form method="get" class="search-box">
<input type="hidden" name="page" value="vhosts">
<div class="search-input">
<i class="fas fa-search"></i>
<input type="text" name="search" value="<?php echo h($_GET['search'] ?? ''); ?>" class="form-control" placeholder="搜索账号、邮箱或型号...">
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
<a href="?page=vhosts" class="btn btn-outline"><i class="fas fa-redo"></i> 重置</a>
</form>
</div>

<div class="batch-actions">
<label><i class="fas fa-tasks"></i> 批量操作：</label>
<button type="button" class="btn btn-sm btn-outline" onclick="vhostSelectAll()"><i class="fas fa-check-square"></i> 全选</button>
<button type="button" class="btn btn-sm btn-outline" onclick="vhostSelectNone()"><i class="fas fa-square"></i> 取消</button>
<button type="button" class="btn btn-sm btn-danger" onclick="submitVhostBatch('del_vhost_batch')"><i class="fas fa-trash"></i> 删除选中</button>
</div>

<form method="post" id="vhostBatchForm" onsubmit="return confirm('确定执行批量操作？')">
<input type="hidden" name="action" value="del_vhost_batch" id="vhostBatchAction">
<input type="hidden" name="ids" id="vhostBatchIds">
</form>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-server"></i> 虚拟主机列表</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th style="width:50px"><input type="checkbox" id="vhostSelectAll" onchange="vhostToggleAll(this)"></th>
<th>ID</th>
<th>用户</th>
<th>型号</th>
<th>账号</th>
<th>密码</th>
<th>MNBT</th>
<th>到期时间</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php 
$search = trim($_GET['search'] ?? '');
if ($search) {
    $searchParam = '%' . $search . '%';
    $stmt = $DB->prepare("SELECT v.*,u.email,vm.name as model_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.account LIKE ? OR u.email LIKE ? OR vm.name LIKE ? ORDER BY v.id DESC");
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
} else {
    $stmt = $DB->query("SELECT v.*,u.email,vm.name as model_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id ORDER BY v.id DESC");
}
$vhosts = $stmt->fetchAll(); 
foreach($vhosts as $v): ?>
<tr>
<td><input type="checkbox" class="vhost-check" value="<?php echo $v['id']; ?>"></td>
<td><?php echo $v['id']; ?></td>
<td><?php echo h($v['email'] ?? '<span style="color:var(--gray-500)">已删除</span>'); ?></td>
<td><?php echo h($v['model_name'] ?? '<span style="color:var(--gray-500)">已删除</span>'); ?></td>
<td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?php echo h($v['account']); ?></code></td>
<td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?php echo h($v['password']); ?></code></td>
<td>
<?php if($v['mnbt_opened']): ?>
<span class="badge badge-success"><i class="fas fa-check"></i> 已开通</span>
<?php else: ?>
<span class="badge badge-danger"><i class="fas fa-times"></i> 未开通</span>
<?php endif; ?>
</td>
<td><?php echo $v['expire_time']?date('Y-m-d',strtotime($v['expire_time'])):'永久'; ?></td>
<td>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除此主机？将同时从MNBT删除')">
<input type="hidden" name="action" value="del_vhost">
<input type="hidden" name="id" value="<?php echo $v['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($vhosts)): ?>
<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无数据<?php echo $search?'（无匹配结果）':''; ?>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<script>
function vhostToggleAll(el){document.querySelectorAll('.vhost-check').forEach(function(c){c.checked=el.checked})}
function vhostSelectAll(){document.querySelectorAll('.vhost-check').forEach(function(c){c.checked=true})}
function vhostSelectNone(){document.querySelectorAll('.vhost-check').forEach(function(c){c.checked=false})}
function submitVhostBatch(action){
var ids=[];
document.querySelectorAll('.vhost-check:checked').forEach(function(c){ids.push(c.value)});
if(ids.length===0){alert('请先选择虚拟主机');return false}
if(!confirm('确定删除选中的 '+ids.length+' 台虚拟主机？将同时从MNBT删除！'))return false;
document.getElementById('vhostBatchIds').value=ids.join(',');
document.getElementById('vhostBatchForm').submit();
}
</script>
<?php endif; ?>

<!-- 用户管理 -->
<?php if($page==='users'): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-search"></i> 搜索筛选</h3>
</div>
<form method="get" class="search-box">
<input type="hidden" name="page" value="users">
<div class="search-input">
<i class="fas fa-search"></i>
<input type="text" name="search" value="<?php echo h($_GET['search'] ?? ''); ?>" class="form-control" placeholder="搜索邮箱或昵称...">
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
<a href="?page=users" class="btn btn-outline"><i class="fas fa-redo"></i> 重置</a>
</form>
</div>

<div class="batch-actions">
<label><i class="fas fa-tasks"></i> 批量操作：</label>
<button type="button" class="btn btn-sm btn-outline" onclick="selectAll()"><i class="fas fa-check-square"></i> 全选</button>
<button type="button" class="btn btn-sm btn-outline" onclick="selectNone()"><i class="fas fa-square"></i> 取消</button>
<button type="button" class="btn btn-sm btn-danger" onclick="submitBatch('del_user_batch')"><i class="fas fa-trash"></i> 删除选中</button>
<div style="display:flex;gap:8px;align-items:center;margin-left:auto">
<span style="font-size:.85rem">批量加积分：</span>
<input type="number" id="pointsAmount" class="form-control" style="width:100px;padding:8px 12px" placeholder="积分">
<button type="button" class="btn btn-sm btn-success" onclick="submitBatchAddPoints()"><i class="fas fa-plus"></i> 确认</button>
</div>
</div>

<form method="post" id="batchForm" onsubmit="return confirm('确定执行批量操作？')">
<input type="hidden" name="action" value="del_user_batch" id="batchAction">
<input type="hidden" name="ids" id="batchIds">
</form>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-users"></i> 用户列表</h3>
<span class="badge badge-info">共 <?php echo $totalUsers; ?> 人</span>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th style="width:50px"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
<th>ID</th>
<th>邮箱</th>
<th>昵称</th>
<th>积分</th>
<th>注册时间</th>
<th>最后登录</th>
<th>登录IP</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php 
$search = trim($_GET['search'] ?? '');
if ($search) {
    $searchParam = '%' . $search . '%';
    $stmt = $DB->prepare("SELECT * FROM users WHERE email LIKE ? OR nickname LIKE ? ORDER BY id DESC");
    $stmt->execute([$searchParam, $searchParam]);
} else {
    $stmt = $DB->query("SELECT * FROM users ORDER BY id DESC");
}
$users = $stmt->fetchAll(); 
foreach($users as $u): 
    $loginTime = !empty($u['last_login_time']) ? date('Y-m-d H:i', strtotime($u['last_login_time'])) : '从未登录';
    $loginIp = !empty($u['last_login_ip']) ? h($u['last_login_ip']) : '-';
?>
<tr>
<td><input type="checkbox" class="user-check" value="<?php echo $u['id']; ?>"></td>
<td><?php echo $u['id']; ?></td>
<td><?php echo h($u['email']); ?></td>
<td>
<input type="text" name="nickname" value="<?php echo h($u['nickname']); ?>" class="form-control" style="width:100px;padding:6px 10px;font-size:.85rem">
</td>
<td>
<span class="badge badge-purple"><?php echo number_format($u['points']); ?></span>
</td>
<td><?php echo date('Y-m-d H:i',strtotime($u['created_at'])); ?></td>
<td>
<?php if($loginTime !== '从未登录'): ?>
<span style="color:#059669"><?php echo $loginTime; ?></span>
<?php else: ?>
<span style="color:var(--gray-500)"><?php echo $loginTime; ?></span>
<?php endif; ?>
</td>
<td><code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;font-size:.8rem"><?php echo $loginIp; ?></code></td>
<td>
<button type="button" class="btn btn-sm btn-primary" onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode(['id'=>$u['id'],'email'=>$u['email'],'nickname'=>$u['nickname'],'points'=>$u['points']])); ?>)"><i class="fas fa-edit"></i></button>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除此用户？')">
<input type="hidden" name="action" value="del_user">
<input type="hidden" name="id" value="<?php echo $u['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($users)): ?>
<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无数据<?php echo $search?'（无匹配结果）':''; ?>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<script>
function toggleAll(el){document.querySelectorAll('.user-check').forEach(function(c){c.checked=el.checked})}
function selectAll(){document.querySelectorAll('.user-check').forEach(function(c){c.checked=true})}
function selectNone(){document.querySelectorAll('.user-check').forEach(function(c){c.checked=false})}
function submitBatch(action){
var ids=[];
document.querySelectorAll('.user-check:checked').forEach(function(c){ids.push(c.value)});
if(ids.length===0){alert('请先选择用户');return false}
document.getElementById('batchIds').value=ids.join(',');
document.getElementById('batchForm').submit();
}
function submitBatchAddPoints(){
var ids=[];
document.querySelectorAll('.user-check:checked').forEach(function(c){ids.push(c.value)});
if(ids.length===0){alert('请先选择用户');return false}
var points=document.getElementById('pointsAmount').value;
if(!points||parseInt(points)===0){alert('请输入要添加的积分');return false}
document.getElementById('batchIds').value=ids.join(',');
document.getElementById('batchAction').value='add_points_batch';
var input=document.createElement('input');
input.type='hidden';
input.name='points_amount';
input.value=points;
document.getElementById('batchForm').appendChild(input);
document.getElementById('batchForm').submit();
}

// 编辑用户弹窗
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_user_email').value = user.email;
    document.getElementById('edit_user_nickname').value = user.nickname;
    document.getElementById('edit_user_points').value = user.points;
    document.getElementById('edit_user_password').value = '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 点击弹窗背景关闭
document.addEventListener('click', function(e) {
    if (e.target.id === 'editModal') {
        closeEditModal();
    }
});
</script>

<!-- 编辑用户弹窗 -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;justify-content:center;align-items:center">
<div style="background:#fff;border-radius:16px;width:90%;max-width:480px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.15)">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #eee">
<h3 style="margin:0;font-size:1.2rem"><i class="fas fa-user-edit" style="color:#667eea"></i> 编辑用户</h3>
<button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999">&times;</button>
</div>
<form method="post" id="editUserForm">
<input type="hidden" name="action" value="edit_user">
<input type="hidden" name="id" id="edit_user_id">

<div style="margin-bottom:16px">
<label style="display:block;margin-bottom:6px;font-weight:500;color:#333">邮箱</label>
<input type="email" name="email" id="edit_user_email" class="form-control" required>
</div>

<div style="margin-bottom:16px">
<label style="display:block;margin-bottom:6px;font-weight:500;color:#333">昵称</label>
<input type="text" name="nickname" id="edit_user_nickname" class="form-control" required>
</div>

<div style="margin-bottom:16px">
<label style="display:block;margin-bottom:6px;font-weight:500;color:#333">积分</label>
<input type="number" name="points" id="edit_user_points" class="form-control" required>
</div>

<div style="margin-bottom:20px">
<label style="display:block;margin-bottom:6px;font-weight:500;color:#333">新密码 <span style="color:#999;font-weight:normal;font-size:.85rem">(留空则不修改)</span></label>
<input type="password" name="password" id="edit_user_password" class="form-control" placeholder="输入新密码留空则不修改">
</div>

<div style="display:flex;gap:12px;justify-content:flex-end">
<button type="button" onclick="closeEditModal()" class="btn btn-outline" style="padding:10px 24px">取消</button>
<button type="submit" class="btn btn-primary" style="padding:10px 24px"><i class="fas fa-save"></i> 保存修改</button>
</div>
</form>
</div>
</div>
<?php endif; ?>

<!-- 价格设置 -->
<?php if($page==='prices'): ?>
<form method="post"><input type="hidden" name="action" value="save_config">

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-calendar-check"></i> 签到积分</h3>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">最少积分</label>
<input type="number" name="sign_min" value="<?php echo h(conf('sign_min','50')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">最多积分</label>
<input type="number" name="sign_max" value="<?php echo h(conf('sign_max','100')); ?>" class="form-control">
</div>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-gift"></i> 注册送积分</h3>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">开启注册送积分</label>
<select name="register_points_enabled" class="form-control">
<option value="0" <?php echo conf('register_points_enabled')==='1'?'':'selected'; ?>>关闭</option>
<option value="1" <?php echo conf('register_points_enabled')==='1'?'selected':''; ?>>开启</option>
</select>
</div>
<div class="form-group">
<label class="form-label">注册赠送积分</label>
<input type="number" name="register_points" value="<?php echo h(conf('register_points','100')); ?>" class="form-control">
<p class="form-hint">新用户注册时自动赠送的积分数</p>
</div>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-users-referral"></i> 推荐奖励</h3>
<span class="badge badge-success">邀请好友赚积分</span>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">启用推荐奖励</label>
<select name="referral_enabled" class="form-control">
<option value="0" <?php echo conf('referral_enabled')!=='1'?'selected':''; ?>>关闭</option>
<option value="1" <?php echo conf('referral_enabled')==='1'?'selected':''; ?>>开启</option>
</select>
<p class="form-hint">开启后，用户可通过推荐码邀请好友注册</p>
</div>
<div class="form-group">
<label class="form-label">推荐奖励积分</label>
<input type="number" name="referral_reward_points" value="<?php echo h(conf('referral_reward_points','30')); ?>" class="form-control">
<p class="form-hint">推荐人成功邀请1位好友注册获得的积分奖励</p>
</div>
</div>
<div style="background:linear-gradient(135deg,#667eea22,#764ba222);padding:16px;border-radius:12px;margin-top:12px">
<p style="color:var(--gray-700);font-size:.9rem;margin-bottom:8px"><i class="fas fa-info-circle"></i> 推荐奖励规则：</p>
<ul style="color:var(--gray-600);font-size:.85rem;padding-left:20px;line-height:1.8">
<li>被推荐人注册时输入推荐码，双方都可获得奖励积分</li>
<li>推荐码格式：系统自动生成，如 <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px">INV8A3F2C</code></li>
<li>推荐人可在个人中心查看自己的推荐码和推荐记录</li>
</ul>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-coins"></i> 积分套餐价格</h3>
<span class="badge badge-purple">单位：元</span>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">200 积分</label>
<div style="display:flex;align-items:center;gap:8px">
<span style="font-size:1.5rem;color:var(--primary-solid)">¥</span>
<input type="number" step="0.01" name="points_200_price" value="<?php echo h(conf('points_200_price','10')); ?>" class="form-control">
</div>
</div>
<div class="form-group">
<label class="form-label">400 积分</label>
<div style="display:flex;align-items:center;gap:8px">
<span style="font-size:1.5rem;color:var(--primary-solid)">¥</span>
<input type="number" step="0.01" name="points_400_price" value="<?php echo h(conf('points_400_price','18')); ?>" class="form-control">
</div>
</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">1000 积分</label>
<div style="display:flex;align-items:center;gap:8px">
<span style="font-size:1.5rem;color:var(--primary-solid)">¥</span>
<input type="number" step="0.01" name="points_1000_price" value="<?php echo h(conf('points_1000_price','40')); ?>" class="form-control">
</div>
</div>
<div class="form-group">
<label class="form-label">3000 积分</label>
<div style="display:flex;align-items:center;gap:8px">
<span style="font-size:1.5rem;color:var(--primary-solid)">¥</span>
<input type="number" step="0.01" name="points_3000_price" value="<?php echo h(conf('points_3000_price','100')); ?>" class="form-control">
</div>
</div>
</div>
</div>

<button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-save"></i> 保存价格设置</button>
</form>
<?php endif; ?>

<!-- 公告管理 -->
<?php if($page==='announcement'): ?>
<form method="post"><input type="hidden" name="action" value="save_announcement">

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-bullhorn"></i> 公告内容</h3>
<span class="badge badge-info">支持 Markdown 格式</span>
</div>
<div class="form-group">
<textarea name="announcement" class="form-control" placeholder="在此输入公告内容..." style="min-height:200px;font-family:monospace"><?php echo h(conf('announcement','')); ?></textarea>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px">
<p class="form-hint"><i class="fas fa-lightbulb"></i> 提示：公告将显示在用户前台首页顶部</p>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存公告</button>
</div>
</div>

</form>
<?php endif; ?>

<!-- 消费统计 -->
<?php if($page==='statistics'): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-calendar"></i> 时间范围</h3>
</div>
<form method="get" class="form-row" style="align-items:flex-end">
<input type="hidden" name="page" value="statistics">
<div class="form-group">
<label class="form-label">开始日期</label>
<input type="date" name="start_date" value="<?php echo h($_GET['start_date'] ?? date('Y-m-01')); ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">结束日期</label>
<input type="date" name="end_date" value="<?php echo h($_GET['end_date'] ?? date('Y-m-d')); ?>" class="form-control">
</div>
<div>
<button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> 筛选</button>
</div>
</form>
</div>

<?php
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$orderStats = $DB->prepare("SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM orders WHERE status=1 AND created_at >= ? AND created_at <= ?");
$orderStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$orderData = $orderStats->fetch();

$userConsume = $DB->prepare("SELECT u.id, u.email, u.nickname, COUNT(o.id) as order_count, SUM(o.amount) as total_spent FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status = 1 AND o.created_at >= ? AND o.created_at <= ? GROUP BY u.id, u.email, u.nickname ORDER BY total_spent DESC LIMIT 20");
$userConsume->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topUsers = $userConsume->fetchAll();

$modelSales = $DB->prepare("SELECT vm.name, vm.price, COUNT(v.id) as sell_count, SUM(vm.price) as total_revenue FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.created_at >= ? AND v.created_at <= ? GROUP BY vm.name, vm.price ORDER BY sell_count DESC LIMIT 10");
$modelSales->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topModels = $modelSales->fetchAll();

$pointsConsume = $DB->prepare("SELECT SUM(points) as total_points FROM orders WHERE status = 1 AND created_at >= ? AND created_at <= ?");
$pointsConsume->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$pointsData = $pointsConsume->fetch();
?>

<div class="stats-grid">
<div class="stat-card fade-in">
<div class="icon users"><i class="fas fa-receipt"></i></div>
<div class="num"><?php echo intval($orderData['total_count'] ?? 0); ?></div>
<div class="label">订单总数</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.1s">
<div class="icon orders"><i class="fas fa-yen-sign"></i></div>
<div class="num">¥<?php echo number_format($orderData['total_amount'] ?? 0, 2); ?></div>
<div class="label">订单总金额</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.2s">
<div class="icon visits"><i class="fas fa-chart-bar"></i></div>
<div class="num">¥<?php echo number_format($orderData['total_amount'] / max($orderData['total_count'], 1), 2); ?></div>
<div class="label">平均客单价</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.3s">
<div class="icon vhosts"><i class="fas fa-coins"></i></div>
<div class="num"><?php echo number_format($pointsData['total_points'] ?? 0); ?></div>
<div class="label">积分消耗</div>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-trophy"></i> 用户消费排行</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>排名</th>
<th>用户</th>
<th>订单数</th>
<th>消费金额</th>
</tr>
</thead>
<tbody>
<?php $rank = 1; foreach($topUsers as $u): ?>
<tr>
<td>
<?php if($rank == 1): ?><span style="color:#f59e0b;font-size:1.2rem">🥇</span>
<?php elseif($rank == 2): ?><span style="font-size:1.1rem">🥈</span>
<?php elseif($rank == 3): ?><span style="font-size:1rem">🥉</span>
<?php else: ?><span style="color:var(--gray-500)"><?php echo $rank; ?></span><?php endif; ?>
</td>
<td><?php echo h($u['email'] ?? '已删除'); ?></td>
<td><span class="badge badge-info"><?php echo $u['order_count']; ?> 单</span></td>
<td><strong style="color:#059669;font-size:1.1rem">¥<?php echo number_format($u['total_spent'] ?? 0, 2); ?></strong></td>
</tr>
<?php $rank++; endforeach; ?>
<?php if(empty($topUsers)): ?>
<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无数据
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-cube"></i> 主机销售排行</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>型号</th>
<th>单价</th>
<th>销量</th>
<th>销售额</th>
</tr>
</thead>
<tbody>
<?php foreach($topModels as $m): ?>
<tr>
<td><strong><?php echo h($m['name'] ?? '已删除'); ?></strong></td>
<td><span class="badge badge-purple"><?php echo $m['price']; ?> 积分</span></td>
<td><?php echo $m['sell_count']; ?></td>
<td><strong style="color:#059669"><?php echo number_format($m['total_revenue']); ?> 积分</strong></td>
</tr>
<?php endforeach; ?>
<?php if(empty($topModels)): ?>
<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无数据
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

</main>
</div>
</body>
</html>
