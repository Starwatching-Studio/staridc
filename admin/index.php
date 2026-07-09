<?php
define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/MNBT_API.php';

/**
 * 检测官方版本更新
 * 返回数组表示有新版本，返回 null 表示无更新或检测失败
 */
function checkUpdate() {
    $apiUrl = trim(conf('update_api_url', ''));
    $currentVersion = trim(conf('current_version', '1.4.9'));
    if ($apiUrl === '' || $currentVersion === '') {
        return null;
    }
    // 使用 Session 缓存 1 小时，避免频繁请求
    $cacheKey = 'staridc_update_check';
    $cached = $_SESSION[$cacheKey] ?? null;
    if ($cached && ($cached['time'] ?? 0) > time() - 3600) {
        return $cached['hasUpdate'] ? $cached['data'] : null;
    }

    $latest = null;
    $url = $apiUrl . (strpos($apiUrl, '?') === false ? '?' : '&') . 'action=latest';
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'StarIDC-UpdateCheck/1.0',
            'follow_location' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response !== false) {
        $json = json_decode($response, true);
        if (is_array($json) && !empty($json['success']) && !empty($json['version'])) {
            $latest = $json;
        }
    }

    $hasUpdate = false;
    if ($latest && !empty($latest['version'])) {
        $hasUpdate = version_compare($latest['version'], $currentVersion, '>');
    }

    $_SESSION[$cacheKey] = [
        'time' => time(),
        'hasUpdate' => $hasUpdate,
        'data' => $hasUpdate ? $latest : null,
    ];
    return $hasUpdate ? $latest : null;
}

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
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#10b981 0%,#059669 100%);font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;padding:20px}
.login-box{background:rgba(255,255,255,0.95);backdrop-filter:blur(20px);border-radius:24px;padding:48px 40px;max-width:420px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25)}
.login-box h1{text-align:center;color:#1a1a2e;margin-bottom:8px;font-size:1.8rem;font-weight:700}
.login-box .subtitle{text-align:center;color:#6b7280;margin-bottom:32px;font-size:.9rem}
.input-group{position:relative;margin-bottom:20px}
.input-group label{display:block;margin-bottom:8px;font-weight:600;color:#374151;font-size:.9rem}
.input-group input{width:100%;padding:14px 16px 14px 44px;border:2px solid #e5e7eb;border-radius:12px;font-size:1rem;transition:all .3s;background:#f9fafb}
.input-group input:focus{border-color:#10b981;outline:none;background:#fff;box-shadow:0 0 0 4px rgba(16,185,129,0.1)}
.input-group i{position:absolute;left:16px;bottom:14px;color:#9ca3af;font-size:1.1rem}
.btn{width:100%;padding:16px;border:none;border-radius:12px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#fff;font-size:1rem;font-weight:600;cursor:pointer;transition:all .3s;box-shadow:0 4px 15px rgba(16,185,129,0.4)}
.btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,0.5)}
.btn:active{transform:translateY(0)}
.err{background:#fef2f2;color:#dc2626;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.9rem;text-align:center;border:1px solid #fecaca}
.logo{text-align:center;margin-bottom:24px}
.logo i{font-size:3rem;background:linear-gradient(135deg,#10b981,#059669);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:4rem}
</style>
</head>
<body>
<div class="login-box">
<div class="logo"><i class="fas fa-server"></i></div>
<h1>管理后台</h1>
<p class="subtitle">云虚拟主机分销平台</p>
<?php if(!empty($loginError)) echo '<div class="err"><i class="fas fa-exclamation-circle"></i> '.$loginError.'</div>';?>
<form method="post">
<?php echo csrfField(); ?>
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

// 旧版URL兼容：旧页面名自动重定向到新页面
$legacyPages = [
    'servers' => 'config',
    'vhost_categories' => 'product',
    'vhost_models' => 'product',
    'statistics' => 'dashboard',
    'recharge_packages' => 'pricing',
    'coupons' => 'promotions',
    'prices' => 'promotions'
];
if (isset($legacyPages[$page])) {
    $redirectPage = $legacyPages[$page];
    if ($redirectPage === 'promotions') {
        header('Location: ?page=pricing&sub=promotions');
    } else {
        header('Location: ?page=' . $redirectPage);
    }
    exit;
}

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
                'admin_email','admin_email_notify',
                'sign_min','sign_max','theme','announcement',
                'register_points_enabled','register_points',
                'referral_enabled','referral_reward_points',
                'mail_notify_ticket','mail_notify_host','mail_notify_points','mail_notify_expire',
                'cron_key','current_version','update_api_url','max_hosts_per_user',
                'oauth_enabled','oauth_api_url','oauth_appid','oauth_appkey','oauth_types',
                'oauth_icon_img_dingtalk','oauth_icon_text_dingtalk','oauth_icon_img_feishu','oauth_icon_text_feishu'];
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
            $serverId = !empty($_POST['server_id']) ? intval($_POST['server_id']) : null;
            $maxPerUser = intval($_POST['max_per_user'] ?? 0);
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $isElastic = isset($_POST['is_elastic']) ? 1 : 0;
            $stmt = $DB->prepare("INSERT INTO vhost_models(name,web_space,db_space,flow,domain_limit,price,sort_order,server_id,max_per_user,category_id,is_elastic) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$_POST['name'],intval($_POST['web_space']),intval($_POST['db_space']),intval($_POST['flow']),intval($_POST['domain_limit']),intval($_POST['price']),intval($_POST['sort_order']),$serverId,$maxPerUser,$categoryId,$isElastic]);
            $modelId = $DB->lastInsertId();
            // 保存时长折扣（若用户未勾选任何时长，默认启用月付以避免无法购买）
            $DB->prepare("DELETE FROM vhost_model_durations WHERE model_id=?")->execute([$modelId]);
            $durs = $_POST['dur'] ?? [];
            $hasEnabled = false;
            foreach(['month','quarter','half_year','year','2year','3year','5year','10year'] as $dk) {
                $enabled = isset($durs[$dk]['enabled']) ? 1 : 0;
                if ($enabled) $hasEnabled = true;
                $discount = intval($durs[$dk]['discount'] ?? 0);
                $DB->prepare("INSERT INTO vhost_model_durations(model_id,duration_type,enabled,discount) VALUES(?,?,?,?)")->execute([$modelId, $dk, $enabled, $discount]);
            }
            if (!$hasEnabled) {
                $DB->prepare("UPDATE vhost_model_durations SET enabled=1, discount=0 WHERE model_id=? AND duration_type='month'")->execute([$modelId]);
            }
            // 保存弹性配置
            $DB->prepare("DELETE FROM vhost_model_elastic WHERE model_id=?")->execute([$modelId]);
            $elasticFields = ['web_space','db_space','flow','domain_limit'];
            foreach($elasticFields as $ef) {
                $e = $_POST['elastic'][$ef] ?? [];
                $enabled = isset($e['enabled']) ? 1 : 0;
                $min = intval($e['min'] ?? 0);
                $max = intval($e['max'] ?? 0);
                $step = intval($e['step'] ?? 1);
                $price = intval($e['price'] ?? 0);
                $DB->prepare("INSERT INTO vhost_model_elastic(model_id,field_name,min_value,max_value,step,unit_price,enabled) VALUES(?,?,?,?,?,?,?)")->execute([$modelId, $ef, $min, $max, $step, $price, $enabled]);
            }
            $msg = '型号添加成功'; $msgType = 'success';
            break;
        case 'edit_model':
            $mid = intval($_POST['id']);
            $serverId = !empty($_POST['server_id']) ? intval($_POST['server_id']) : null;
            $maxPerUser = intval($_POST['max_per_user'] ?? 0);
            $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
            $isElastic = isset($_POST['is_elastic']) ? 1 : 0;
            $stmt = $DB->prepare("UPDATE vhost_models SET name=?,web_space=?,db_space=?,flow=?,domain_limit=?,price=?,sort_order=?,server_id=?,max_per_user=?,category_id=?,is_elastic=? WHERE id=?");
            $stmt->execute([$_POST['name'],intval($_POST['web_space']),intval($_POST['db_space']),intval($_POST['flow']),intval($_POST['domain_limit']),intval($_POST['price']),intval($_POST['sort_order']),$serverId,$maxPerUser,$categoryId,$isElastic,$mid]);
            // 保存时长折扣
            $DB->prepare("DELETE FROM vhost_model_durations WHERE model_id=?")->execute([$mid]);
            $durs = $_POST['dur'] ?? [];
            foreach(['month','quarter','half_year','year','2year','3year','5year','10year'] as $dk) {
                $enabled = isset($durs[$dk]['enabled']) ? 1 : 0;
                $discount = intval($durs[$dk]['discount'] ?? 0);
                $DB->prepare("INSERT INTO vhost_model_durations(model_id,duration_type,enabled,discount) VALUES(?,?,?,?)")->execute([$mid, $dk, $enabled, $discount]);
            }
            // 保存弹性配置
            $DB->prepare("DELETE FROM vhost_model_elastic WHERE model_id=?")->execute([$mid]);
            $elasticFields = ['web_space','db_space','flow','domain_limit'];
            foreach($elasticFields as $ef) {
                $e = $_POST['elastic'][$ef] ?? [];
                $enabled = isset($e['enabled']) ? 1 : 0;
                $min = intval($e['min'] ?? 0);
                $max = intval($e['max'] ?? 0);
                $step = intval($e['step'] ?? 1);
                $price = intval($e['price'] ?? 0);
                $DB->prepare("INSERT INTO vhost_model_elastic(model_id,field_name,min_value,max_value,step,unit_price,enabled) VALUES(?,?,?,?,?,?,?)")->execute([$mid, $ef, $min, $max, $step, $price, $enabled]);
            }
            $msg = '型号已更新'; $msgType = 'success';
            break;
        case 'toggle_model':
            $stmt = $DB->prepare("UPDATE vhost_models SET status=? WHERE id=?");
            $stmt->execute([intval($_POST['status']),intval($_POST['id'])]);
            $msg = '操作成功'; $msgType = 'success';
            break;
        case 'del_model':
            $mid = intval($_POST['id']);
            $chk = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE model_id=?");
            $chk->execute([$mid]);
            if ($chk->fetch()['c'] > 0) {
                $msg = '该型号下还有主机，无法删除，请先删除相关主机'; $msgType = 'error';
            } else {
                try {
                    // 先清理购物车中关联的该型号记录，避免外键约束
                    $DB->prepare("DELETE FROM cart_items WHERE model_id=?")->execute([$mid]);
                    $stmt = $DB->prepare("DELETE FROM vhost_models WHERE id=?");
                    $stmt->execute([$mid]);
                    $msg = '型号已删除'; $msgType = 'success';
                } catch (Exception $e) {
                    $msg = '删除失败：' . $e->getMessage(); $msgType = 'error';
                }
            }
            break;
        case 'del_vhost':
            $vid = intval($_POST['id']);
            $vstmt = $DB->prepare("SELECT * FROM vhosts WHERE id=?");
            $vstmt->execute([$vid]);
            $vh = $vstmt->fetch();
            if ($vh && $vh['mnbt_opened']) {
                MNBT_API::deleteHost($vh['account'], getServer($vh['server_id']));
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
                    MNBT_API::deleteHost($vh['account'], getServer($vh['server_id']));
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
            if ($points === 0) { $msg = '积分变动值不能为0'; $msgType = 'error'; break; }
            if ($points < 0) {
                // 扣减积分时，需确保不会扣成负数
                $ids = is_array($ids) ? $ids : ($ids !== '' ? explode(',', $ids) : []);
                $blocked = false;
                foreach ($ids as $uid) {
                    $uid = intval($uid);
                    $chkStmt = $DB->prepare("SELECT points FROM users WHERE id=?");
                    $chkStmt->execute([$uid]);
                    $uRow = $chkStmt->fetch();
                    if ($uRow && ($uRow['points'] + $points) < 0) {
                        $msg = '用户ID ' . $uid . ' 积分不足，无法扣减'; $msgType = 'error'; $blocked = true; break;
                    }
                }
                if ($blocked) break;
            }
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
        case 'add_server':
            $stmt = $DB->prepare("INSERT INTO mnbt_servers(name,api_url,mn_bh,mn_key,mn_keye,mn_vs,status,sort_order) VALUES(?,?,?,?,?,?,1,0)");
            $stmt->execute([trim($_POST['name']),trim($_POST['api_url']),trim($_POST['mn_bh']),trim($_POST['mn_key']),trim($_POST['mn_keye']),trim($_POST['mn_vs'] ?? '16')]);
            $msg = '服务器添加成功'; $msgType = 'success';
            break;
        case 'del_server':
            $stmt = $DB->prepare("DELETE FROM mnbt_servers WHERE id=?");
            $stmt->execute([intval($_POST['id'])]);
            $msg = '服务器已删除'; $msgType = 'success';
            break;
        case 'toggle_server':
            $stmt = $DB->prepare("UPDATE mnbt_servers SET status=? WHERE id=?");
            $stmt->execute([intval($_POST['status']),intval($_POST['id'])]);
            $msg = '操作成功'; $msgType = 'success';
            break;
        case 'edit_server':
            $sid = intval($_POST['id']);
            $stmt = $DB->prepare("UPDATE mnbt_servers SET name=?,api_url=?,mn_bh=?,mn_key=?,mn_keye=?,mn_vs=? WHERE id=?");
            $stmt->execute([trim($_POST['name']),trim($_POST['api_url']),trim($_POST['mn_bh']),trim($_POST['mn_key']),trim($_POST['mn_keye']),trim($_POST['mn_vs'] ?? '16'),$sid]);
            $msg = '服务器已更新'; $msgType = 'success';
            break;
        case 'test_server':
            $sid = intval($_POST['id']);
            $sstmt = $DB->prepare("SELECT * FROM mnbt_servers WHERE id=?");
            $sstmt->execute([$sid]);
            $sv = $sstmt->fetch();
            if ($sv) {
                $r = MNBT_API::testConnection($sv);
                $msg = $r['message']; $msgType = $r['success'] ? 'success' : 'error';
            } else {
                $msg = '服务器不存在'; $msgType = 'error';
            }
            break;
        case 'add_coupon':
            $code = trim($_POST['code'] ?? '');
            $discount = intval($_POST['discount'] ?? 0);
            $maxUses = intval($_POST['max_uses'] ?? 1);
            $expireAt = !empty($_POST['expire_at']) ? $_POST['expire_at'] : null;
            $modelId = !empty($_POST['model_id']) ? intval($_POST['model_id']) : null;
            if (empty($code)) {
                $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            }
            if ($discount <= 0 || $discount >= 100) {
                $msg = '折扣必须在1-99之间'; $msgType = 'error'; break;
            }
            try {
                $stmt = $DB->prepare("INSERT INTO coupons(code,discount,max_uses,expire_at,model_id) VALUES(?,?,?,?,?)");
                $stmt->execute([$code, $discount, $maxUses, $expireAt, $modelId]);
                $msg = '优惠码添加成功：' . h($code); $msgType = 'success';
            } catch (Exception $e) {
                $msg = '添加失败：优惠码可能已存在'; $msgType = 'error';
            }
            break;
        case 'batch_add_coupon':
            $prefix = trim($_POST['prefix'] ?? 'CP');
            $count = intval($_POST['count'] ?? 5);
            $discount = intval($_POST['discount'] ?? 0);
            $maxUses = intval($_POST['max_uses'] ?? 1);
            $expireAt = !empty($_POST['expire_at']) ? $_POST['expire_at'] : null;
            $modelId = !empty($_POST['model_id']) ? intval($_POST['model_id']) : null;
            if ($discount <= 0 || $discount >= 100) {
                $msg = '折扣必须在1-99之间'; $msgType = 'error'; break;
            }
            if ($count <= 0 || $count > 100) {
                $msg = '生成数量需在1-100之间'; $msgType = 'error'; break;
            }
            $generated = 0;
            $stmt = $DB->prepare("INSERT IGNORE INTO coupons(code,discount,max_uses,expire_at,model_id) VALUES(?,?,?,?,?)");
            for ($i = 0; $i < $count; $i++) {
                $code = $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                $stmt->execute([$code, $discount, $maxUses, $expireAt, $modelId]);
                if ($stmt->rowCount() > 0) $generated++;
            }
            $msg = "已生成 {$generated} 个优惠码"; $msgType = 'success';
            break;
        case 'del_coupon':
            $stmt = $DB->prepare("DELETE FROM coupons WHERE id=?");
            $stmt->execute([intval($_POST['id'])]);
            $msg = '优惠码已删除'; $msgType = 'success';
            break;
        case 'toggle_coupon':
            $stmt = $DB->prepare("UPDATE coupons SET status=? WHERE id=?");
            $stmt->execute([intval($_POST['status']), intval($_POST['id'])]);
            $msg = '操作成功'; $msgType = 'success';
            break;
        case 'admin_reply_ticket':
            $ticketId = intval($_POST['ticket_id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            $stmt = $DB->prepare("SELECT t.*, u.email as user_email, u.nickname as user_nickname FROM tickets t LEFT JOIN users u ON t.user_id=u.id WHERE t.id=?");
            $stmt->execute([$ticketId]);
            $tk = $stmt->fetch();
            if (!$tk) { $msg = '工单不存在'; $msgType = 'error'; break; }
            if (empty($content)) { $msg = '请输入回复内容'; $msgType = 'error'; break; }
            $admin = getAdmin();
            $stmt2 = $DB->prepare("INSERT INTO ticket_replies(ticket_id,admin_id,content) VALUES(?,?,?)");
            $stmt2->execute([$ticketId, $admin['id'], $content]);
            $stmt3 = $DB->prepare("UPDATE tickets SET status=1, updated_at=NOW() WHERE id=?");
            $stmt3->execute([$ticketId]);
            // 邮件通知用户
            if (conf('mail_notify_ticket', '1') === '1' && !empty($tk['user_email'])) {
                $siteName = conf('site_name', '云主机');
                $mailBody = "您提交的工单 #{$ticketId}「{$tk['subject']}」已收到管理员回复，请登录查看。\n\n站点：{$siteName}";
                Mailer::sendNotify($tk['user_email'], "[{$siteName}] 工单 #{$ticketId} 已回复", $mailBody);
            }
            $msg = '回复成功'; $msgType = 'success';
            break;
        case 'close_ticket':
            $ticketId = intval($_POST['ticket_id'] ?? 0);
            $stmt = $DB->prepare("SELECT t.*, u.email as user_email FROM tickets t LEFT JOIN users u ON t.user_id=u.id WHERE t.id=?");
            $stmt->execute([$ticketId]);
            $tk = $stmt->fetch();
            if (!$tk) { $msg = '工单不存在'; $msgType = 'error'; break; }
            $stmt2 = $DB->prepare("UPDATE tickets SET status=2, updated_at=NOW() WHERE id=?");
            $stmt2->execute([$ticketId]);
            if (conf('mail_notify_ticket', '1') === '1' && !empty($tk['user_email'])) {
                $siteName = conf('site_name', '云主机');
                $mailBody = "您提交的工单 #{$ticketId}「{$tk['subject']}」已被管理员关闭。\n\n站点：{$siteName}";
                Mailer::sendNotify($tk['user_email'], "[{$siteName}] 工单 #{$ticketId} 已关闭", $mailBody);
            }
            $msg = '工单已关闭'; $msgType = 'success';
            break;
        case 'del_ticket':
            $ticketId = intval($_POST['id'] ?? 0);
            $stmt = $DB->prepare("DELETE FROM tickets WHERE id=?");
            $stmt->execute([$ticketId]);
            $msg = '工单已删除'; $msgType = 'success';
            break;
        case 'add_recharge_package':
            $points = intval($_POST['points'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            if ($points <= 0 || $price <= 0) {
                $msg = '积分和价格必须大于0'; $msgType = 'error';
            } else {
                $stmt = $DB->prepare("INSERT INTO recharge_packages(points,price,sort_order) VALUES(?,?,?)");
                $stmt->execute([$points, $price, $sortOrder]);
                $msg = '充值套餐添加成功'; $msgType = 'success';
            }
            break;
        case 'edit_recharge_package':
            $pkgId = intval($_POST['id'] ?? 0);
            $points = intval($_POST['points'] ?? 0);
            $price = floatval($_POST['price'] ?? 0);
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            if ($points <= 0 || $price <= 0) {
                $msg = '积分和价格必须大于0'; $msgType = 'error';
            } else {
                $stmt = $DB->prepare("UPDATE recharge_packages SET points=?,price=?,sort_order=? WHERE id=?");
                $stmt->execute([$points, $price, $sortOrder, $pkgId]);
                $msg = '充值套餐更新成功'; $msgType = 'success';
            }
            break;
        case 'toggle_recharge_package':
            $stmt = $DB->prepare("UPDATE recharge_packages SET status=? WHERE id=?");
            $stmt->execute([intval($_POST['status']), intval($_POST['id'])]);
            $msg = '操作成功'; $msgType = 'success';
            break;
        case 'del_recharge_package':
            $stmt = $DB->prepare("DELETE FROM recharge_packages WHERE id=?");
            $stmt->execute([intval($_POST['id'])]);
            $msg = '充值套餐已删除'; $msgType = 'success';
            break;
        case 'add_category':
            $name = trim($_POST['name'] ?? '');
            $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            if (empty($name)) { $msg = '分类名称不能为空'; $msgType = 'error'; break; }
            $level = 1;
            if ($parentId) {
                $stmtP = $DB->prepare("SELECT level FROM vhost_categories WHERE id=?");
                $stmtP->execute([$parentId]);
                $parent = $stmtP->fetch();
                if ($parent) {
                    $level = $parent['level'] + 1;
                    if ($level > 3) { $msg = '最多支持三级分类'; $msgType = 'error'; break; }
                }
            }
            $stmt = $DB->prepare("INSERT INTO vhost_categories(name,parent_id,level,sort_order) VALUES(?,?,?,?)");
            $stmt->execute([$name, $parentId, $level, $sortOrder]);
            $msg = '分类添加成功'; $msgType = 'success';
            break;
        case 'edit_category':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            if (empty($name)) { $msg = '分类名称不能为空'; $msgType = 'error'; break; }
            $stmt = $DB->prepare("UPDATE vhost_categories SET name=?,sort_order=? WHERE id=?");
            $stmt->execute([$name, $sortOrder, $id]);
            $msg = '分类已更新'; $msgType = 'success';
            break;
        case 'del_category':
            $id = intval($_POST['id'] ?? 0);
            // 检查是否有子分类
            $chk = $DB->prepare("SELECT COUNT(*) as c FROM vhost_categories WHERE parent_id=?");
            $chk->execute([$id]);
            if ($chk->fetch()['c'] > 0) {
                $msg = '该分类下还有子分类，无法删除'; $msgType = 'error';
            } else {
                // 检查是否有型号引用
                $chk2 = $DB->prepare("SELECT COUNT(*) as c FROM vhost_models WHERE category_id=?");
                $chk2->execute([$id]);
                if ($chk2->fetch()['c'] > 0) {
                    $msg = '该分类下还有主机型号，无法删除'; $msgType = 'error';
                } else {
                    $stmt = $DB->prepare("DELETE FROM vhost_categories WHERE id=?");
                    $stmt->execute([$id]);
                    $msg = '分类已删除'; $msgType = 'success';
                }
            }
            break;
    }
}

$totalUsers = $DB->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$totalVhosts = $DB->query("SELECT COUNT(*) as c FROM vhosts")->fetch()['c'];
$todayVisits = $DB->query("SELECT COUNT(*) as c FROM visit_logs WHERE visit_date=CURDATE()")->fetch()['c'];
$totalOrders = $DB->query("SELECT COUNT(*) as c FROM orders WHERE status=1")->fetch()['c'];
$pages = ['dashboard','product','users_instances','pricing','tickets','announcement','config','about'];
if (!in_array($page, $pages)) $page = 'dashboard';

$pageTitles = [
    'dashboard' => ['icon' => 'fa-chart-pie', 'title' => '仪表盘', 'desc' => '系统数据总览'],
    'product' => ['icon' => 'fa-cube', 'title' => '商品设置', 'desc' => '分类与型号管理'],
    'users_instances' => ['icon' => 'fa-users', 'title' => '用户及实例', 'desc' => '用户与主机管理'],
    'pricing' => ['icon' => 'fa-tags', 'title' => '定价与优惠', 'desc' => '定价与促销管理'],
    'tickets' => ['icon' => 'fa-headset', 'title' => '工单管理', 'desc' => '用户工单处理'],
    'announcement' => ['icon' => 'fa-bullhorn', 'title' => '公告管理', 'desc' => '网站公告发布'],
    'config' => ['icon' => 'fa-cog', 'title' => '系统配置', 'desc' => '网站参数设置'],
    'about' => ['icon' => 'fa-info-circle', 'title' => '关于项目', 'desc' => '关于 StarIDC']
];

// 二级菜单定义
$subMenus = [
    'users_instances' => [
        'vhosts' => ['icon' => 'fa-server', 'title' => '虚拟主机'],
        'users' => ['icon' => 'fa-user', 'title' => '用户管理']
    ],
    'pricing' => [
        'pricing_main' => ['icon' => 'fa-coins', 'title' => '定价'],
        'promotions' => ['icon' => 'fa-gift', 'title' => '优惠']
    ]
];

// 二级页面路由
$subPage = $_GET['sub'] ?? '';
$validSubs = [
    'users_instances' => ['vhosts', 'users'],
    'pricing' => ['pricing_main', 'promotions']
];
if (!isset($validSubs[$page]) || ($subPage && !in_array($subPage, $validSubs[$page]))) {
    $subPage = '';
}
// 为二级菜单页面设置默认子页面
if (isset($validSubs[$page]) && !$subPage) {
    $subPage = $validSubs[$page][0];
}
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
--primary: linear-gradient(135deg, #10b981 0%, #059669 100%);
--primary-solid: #10b981;
--success: linear-gradient(135deg, #10b981 0%, #059669 100%);
--warning: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
--danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
--info: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
--dark: #1e293b;
--gray-100: #f8fafc;
--gray-200: #f1f5f9;
--gray-300: #e2e8f0;
--gray-500: #64748b;
--gray-700: #475569;
--gray-900: #0f172a;
--shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
--shadow: 0 4px 6px rgba(0,0,0,0.05);
--shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
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
.user-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600}

/* 主布局 */
.layout{display:flex;min-height:calc(100vh - 64px)}
.sidebar{width:240px;background:#fff;box-shadow:var(--shadow-sm);padding:20px 0;flex-shrink:0}
.sidebar-title{padding:0 20px 16px;font-size:.75rem;text-transform:uppercase;letter-spacing:1px;color:var(--gray-500);font-weight:600;border-bottom:1px solid var(--gray-200);margin-bottom:12px}
.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--gray-700);transition:all .2s;font-weight:500}
.sidebar-nav a:hover{background:var(--gray-100);color:var(--primary-solid)}
.sidebar-nav a.active{background:linear-gradient(90deg,rgba(16,185,129,0.08) 0%,rgba(5,150,105,0.08) 100%);color:var(--primary-solid);border-right:3px solid var(--primary-solid);font-weight:600}
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
.stat-card .icon.users{background:linear-gradient(135deg,#10b98122,#05966922);color:#10b981}
.stat-card .icon.vhosts{background:linear-gradient(135deg,#0ea5e922,#0284c722);color:#0ea5e9}
.stat-card .icon.visits{background:linear-gradient(135deg,#8b5cf622,#7c3aed22);color:#8b5cf6}
.stat-card .icon.orders{background:linear-gradient(135deg,#f59e0b22,#f9731622);color:#f59e0b}
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
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,0.25)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(16,185,129,0.35)}
.btn-outline{background:transparent;border:2px solid var(--gray-300);color:var(--gray-700)}
.btn-outline:hover{background:var(--gray-100);border-color:var(--gray-400)}
.btn-sm{padding:6px 12px;font-size:.8rem;border-radius:8px}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(239,68,68,0.3)}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(16,185,129,0.3)}

/* 表单 */
.form-group{margin-bottom:20px}
.form-label{display:block;margin-bottom:8px;font-weight:600;color:var(--gray-700);font-size:.9rem}
.form-control{width:100%;padding:12px 16px;border:2px solid var(--gray-200);border-radius:10px;font-size:.95rem;transition:all .3s;background:#fff}
.form-control:focus{border-color:var(--primary-solid);outline:none;box-shadow:0 0 0 4px rgba(16,185,129,0.1)}
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
.badge-gray{background:#f3f4f6;color:#6b7280}

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
.sidebar-nav>a{padding:10px 14px;border-radius:10px;flex:1;justify-content:center;min-width:120px}
.sidebar-nav>a.active{border-right:none;border-bottom:3px solid var(--primary-solid)}
.menu-group{width:100%;min-width:100%}
.menu-group-header{padding:10px 14px;border-radius:10px}
.menu-group-items{max-height:none!important;display:none}
.menu-group.open .menu-group-items{display:block}
.menu-group-items a{padding:8px 14px 8px 36px}
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
.menu-group{border-radius:8px;overflow:hidden;margin:2px 0}
.menu-group-header{display:flex;align-items:center;gap:12px;padding:12px 20px;color:var(--gray-700);transition:all .2s;font-weight:500;cursor:pointer;width:100%;text-decoration:none}
.menu-group-header:hover{background:var(--gray-100);color:var(--primary-solid)}
.menu-group-header.active{background:linear-gradient(90deg,rgba(16,185,129,0.08) 0%,rgba(5,150,105,0.08) 100%);color:var(--primary-solid);font-weight:600}
.menu-group-header.active i:first-child{color:var(--primary-solid)}
.menu-arrow{margin-left:auto;font-size:.7rem;transition:transform .2s;color:var(--gray-500)}
.menu-group.open .menu-arrow{transform:rotate(180deg)}
.menu-group-items{max-height:0;overflow:hidden;transition:max-height .25s ease}
.menu-group.open .menu-group-items{max-height:200px}
.menu-group-items a{display:flex;align-items:center;gap:10px;padding:10px 20px 10px 48px;color:var(--gray-500);font-size:.9rem;transition:all .2s;font-weight:500;text-decoration:none}
.menu-group-items a:hover{color:var(--primary-solid);background:var(--gray-100)}
.menu-group-items a.active{color:var(--primary-solid);font-weight:600;background:linear-gradient(90deg,rgba(16,185,129,0.05) 0%,rgba(5,150,105,0.05) 100%);border-right:3px solid var(--primary-solid)}
.ticket-badge{display:inline-block;width:8px;height:8px;background:#ef4444;border-radius:50%;margin-left:6px;animation:tkPulse 1s ease-in-out infinite;vertical-align:middle}
@keyframes tkPulse{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(239,68,68,0.5)}50%{opacity:.6;box-shadow:0 0 0 4px rgba(239,68,68,0)}}
</style>
</head>
<body>
<?php
// 工单待处理数量（用于侧边栏红点提醒）
try {
    $pendingTicketCount = intval($DB->query("SELECT COUNT(*) as c FROM tickets WHERE status=0")->fetch()['c']);
} catch (Exception $e) {
    $pendingTicketCount = 0;
}
?>
<!-- 顶部导航 -->
<nav class="topbar">
<div class="topbar-left">
<span class="topbar-logo"><i class="fas fa-cloud"></i> 管理后台</span>
<span class="topbar-page">/ <?php echo $pageTitles[$page]['title']; ?><?php if($subPage && isset($subMenus[$page][$subPage])): ?> / <?php echo $subMenus[$page][$subPage]['title']; ?><?php endif; ?></span>
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
<?php if (isset($subMenus[$p])): ?>
<div class="menu-group<?php echo $page===$p?' open':''; ?>">
<a href="javascript:void(0)" class="menu-group-header<?php echo $page===$p?' active':''; ?>" onclick="toggleMenuGroup(this)">
<i class="fas <?php echo $pageTitles[$p]['icon']; ?>"></i>
<span><?php echo $pageTitles[$p]['title']; ?></span>
<i class="fas fa-chevron-down menu-arrow"></i>
</a>
<div class="menu-group-items">
<?php foreach($subMenus[$p] as $subKey => $subInfo): ?>
<a href="?page=<?php echo $p; ?>&sub=<?php echo $subKey; ?>" class="<?php echo ($page===$p && $subPage===$subKey)?'active':''; ?>">
<i class="fas <?php echo $subInfo['icon']; ?>"></i>
<span><?php echo $subInfo['title']; ?></span>
</a>
<?php endforeach; ?>
</div>
</div>
<?php else: ?>
<a href="?page=<?php echo $p; ?>" class="<?php echo $page===$p?'active':''; ?>">
<i class="fas <?php echo $pageTitles[$p]['icon']; ?>"></i>
<span><?php echo $pageTitles[$p]['title']; ?><?php if($p==='tickets' && $pendingTicketCount>0): ?><span class="ticket-badge" title="<?php echo $pendingTicketCount; ?>个工单待处理"></span><?php endif; ?></span>
</a>
<?php endif; ?>
<?php endforeach; ?>
</nav>
<div class="sidebar-footer">
<a href="../index.php"><i class="fas fa-home"></i> 返回前台首页</a>
<a href="?page=about" class="<?php echo $page==='about'?'active':''; ?>" style="margin-top:10px"><i class="fas fa-info-circle"></i> 关于项目</a>
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
<h1><?php echo $pageTitles[$page]['title']; ?><?php if($subPage && isset($subMenus[$page][$subPage])): ?> - <?php echo $subMenus[$page][$subPage]['title']; ?><?php endif; ?></h1>
<p class="page-desc"><?php echo $pageTitles[$page]['desc']; ?></p>
</div>
</div>
</div>

<!-- 仪表盘 -->
<?php if($page==='dashboard'):
$latestUpdate = checkUpdate();
?>
<?php if($latestUpdate): ?>
<div class="card" style="border-left:4px solid var(--warning);background:linear-gradient(135deg,#fffbeb,#fef3c7);margin-bottom:20px">
<div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
<div style="font-size:2rem;color:var(--warning)"><i class="fas fa-bell"></i></div>
<div style="flex:1;min-width:260px">
<h3 style="margin:0 0 8px;font-size:1.1rem;color:#92400e">发现新版本</h3>
<p style="margin:0 0 12px;color:#78350f;line-height:1.6">
当前版本 <strong><?php echo h(conf('current_version','1.4.0')); ?></strong>，官方最新版本为 <strong><?php echo h($latestUpdate['version']); ?></strong>。
<?php if(!empty($latestUpdate['release_note'])): ?><br><span style="color:#92400e"><?php echo nl2br(h($latestUpdate['release_note'])); ?></span><?php endif; ?>
</p>
<a href="<?php echo h($latestUpdate['download_url']); ?>" target="_blank" class="btn btn-primary" style="background:#f59e0b;border-color:#f59e0b"><i class="fas fa-download"></i> 立即下载更新</a>
<a href="?page=config" class="btn btn-outline" style="margin-left:8px">修改版本号</a>
</div>
</div>
</div>
<?php endif; ?>

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

<!-- 消费统计 -->
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-chart-line"></i> 消费统计</h3>
<span class="badge badge-info" style="margin-left:8px">最近30天</span>
</div>
<?php
$statStartDate = date('Y-m-d', strtotime('-30 days'));
$statEndDate = date('Y-m-d');
$orderStats = $DB->prepare("SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM orders WHERE status=1 AND created_at >= ? AND created_at <= ?");
$orderStats->execute([$statStartDate . ' 00:00:00', $statEndDate . ' 23:59:59']);
$orderData = $orderStats->fetch();
$pointsConsume = $DB->prepare("SELECT SUM(points) as total_points FROM orders WHERE status = 1 AND created_at >= ? AND created_at <= ?");
$pointsConsume->execute([$statStartDate . ' 00:00:00', $statEndDate . ' 23:59:59']);
$pointsData = $pointsConsume->fetch();
$userConsume = $DB->prepare("SELECT u.id, u.email, u.nickname, COUNT(o.id) as order_count, SUM(o.amount) as total_spent FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status = 1 AND o.created_at >= ? AND o.created_at <= ? GROUP BY u.id, u.email, u.nickname ORDER BY total_spent DESC LIMIT 5");
$userConsume->execute([$statStartDate . ' 00:00:00', $statEndDate . ' 23:59:59']);
$topUsers = $userConsume->fetchAll();
$modelSales = $DB->prepare("SELECT vm.name, vm.price, COUNT(v.id) as sell_count, SUM(vm.price) as total_revenue FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.created_at >= ? AND v.created_at <= ? GROUP BY vm.name, vm.price ORDER BY sell_count DESC LIMIT 5");
$modelSales->execute([$statStartDate . ' 00:00:00', $statEndDate . ' 23:59:59']);
$topModels = $modelSales->fetchAll();
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;padding:0 24px 16px">
<div style="text-align:center;padding:12px;background:var(--gray-100);border-radius:10px">
<div style="font-size:.8rem;color:var(--gray-500);margin-bottom:4px">订单数</div>
<div style="font-size:1.4rem;font-weight:700;color:var(--dark)"><?php echo intval($orderData['total_count'] ?? 0); ?></div>
</div>
<div style="text-align:center;padding:12px;background:var(--gray-100);border-radius:10px">
<div style="font-size:.8rem;color:var(--gray-500);margin-bottom:4px">总金额</div>
<div style="font-size:1.4rem;font-weight:700;color:#059669">¥<?php echo number_format($orderData['total_amount'] ?? 0, 2); ?></div>
</div>
<div style="text-align:center;padding:12px;background:var(--gray-100);border-radius:10px">
<div style="font-size:.8rem;color:var(--gray-500);margin-bottom:4px">积分消耗</div>
<div style="font-size:1.4rem;font-weight:700;color:#f59e0b"><?php echo number_format($pointsData['total_points'] ?? 0); ?></div>
</div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:0 24px 20px">
<div>
<h4 style="font-size:.9rem;color:var(--gray-500);margin:0 0 8px">用户消费排行</h4>
<?php if(empty($topUsers)): ?>
<p style="color:var(--gray-400);font-size:.85rem">暂无数据</p>
<?php else: foreach($topUsers as $i => $u): ?>
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-200);font-size:.85rem">
<span><?php echo $i+1; ?>. <?php echo h($u['email'] ?? '已删除'); ?></span>
<span style="color:#059669;font-weight:600">¥<?php echo number_format($u['total_spent'] ?? 0, 2); ?></span>
</div>
<?php endforeach; endif; ?>
</div>
<div>
<h4 style="font-size:.9rem;color:var(--gray-500);margin:0 0 8px">主机销售排行</h4>
<?php if(empty($topModels)): ?>
<p style="color:var(--gray-400);font-size:.85rem">暂无数据</p>
<?php else: foreach($topModels as $i => $m): ?>
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--gray-200);font-size:.85rem">
<span><?php echo $i+1; ?>. <?php echo h($m['name'] ?? '已删除'); ?></span>
<span style="font-weight:600"><?php echo $m['sell_count']; ?> 台</span>
</div>
<?php endforeach; endif; ?>
</div>
</div>
<div style="padding:0 24px 20px">
<a href="?page=dashboard&detail_stats=1" class="btn btn-sm btn-outline"><i class="fas fa-chart-line"></i> 详细统计</a>
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
<?php try { $serverCount=$DB->query("SELECT COUNT(*) as c FROM mnbt_servers")->fetch()['c']; } catch(Exception $e) { $serverCount=0; } ?>
<span class="value <?php echo (conf('mnbt_api_url')||$serverCount>0)?'success':'warning'; ?>"><?php echo $serverCount>0?$serverCount.'台服务器':(conf('mnbt_api_url')?'默认配置':'未配置'); ?></span>
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

<?php if(isset($_GET['detail_stats'])): ?>
<div class="card" style="margin-top:20px">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-calendar"></i> 详细消费统计</h3>
</div>
<form method="get" class="form-row" style="align-items:flex-end">
<input type="hidden" name="page" value="dashboard">
<input type="hidden" name="detail_stats" value="1">
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
$dOrderStats = $DB->prepare("SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM orders WHERE status=1 AND created_at >= ? AND created_at <= ?");
$dOrderStats->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dOrderData = $dOrderStats->fetch();
$dUserConsume = $DB->prepare("SELECT u.id, u.email, u.nickname, COUNT(o.id) as order_count, SUM(o.amount) as total_spent FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status = 1 AND o.created_at >= ? AND o.created_at <= ? GROUP BY u.id, u.email, u.nickname ORDER BY total_spent DESC LIMIT 20");
$dUserConsume->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dTopUsers = $dUserConsume->fetchAll();
$dModelSales = $DB->prepare("SELECT vm.name, vm.price, COUNT(v.id) as sell_count, SUM(vm.price) as total_revenue FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.created_at >= ? AND v.created_at <= ? GROUP BY vm.name, vm.price ORDER BY sell_count DESC LIMIT 10");
$dModelSales->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dTopModels = $dModelSales->fetchAll();
$dPointsConsume = $DB->prepare("SELECT SUM(points) as total_points FROM orders WHERE status = 1 AND created_at >= ? AND created_at <= ?");
$dPointsConsume->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dPointsData = $dPointsConsume->fetch();
?>
<div class="stats-grid" style="margin-top:20px">
<div class="stat-card fade-in">
<div class="icon users"><i class="fas fa-receipt"></i></div>
<div class="num"><?php echo intval($dOrderData['total_count'] ?? 0); ?></div>
<div class="label">订单总数</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.1s">
<div class="icon orders"><i class="fas fa-yen-sign"></i></div>
<div class="num">¥<?php echo number_format($dOrderData['total_amount'] ?? 0, 2); ?></div>
<div class="label">订单总金额</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.2s">
<div class="icon visits"><i class="fas fa-chart-bar"></i></div>
<div class="num">¥<?php echo number_format($dOrderData['total_amount'] / max($dOrderData['total_count'], 1), 2); ?></div>
<div class="label">平均客单价</div>
</div>
<div class="stat-card fade-in" style="animation-delay:.3s">
<div class="icon vhosts"><i class="fas fa-coins"></i></div>
<div class="num"><?php echo number_format($dPointsData['total_points'] ?? 0); ?></div>
<div class="label">积分消耗</div>
</div>
</div>
<div class="card">
<div class="card-header"><h3 class="card-title"><i class="fas fa-trophy"></i> 用户消费排行</h3></div>
<div class="table-wrapper"><table><thead><tr><th>排名</th><th>用户</th><th>订单数</th><th>消费金额</th></tr></thead><tbody>
<?php $rank=1; foreach($dTopUsers as $u): ?>
<tr><td><?php echo $rank++; ?></td><td><?php echo h($u['email'] ?? '已删除'); ?></td><td><span class="badge badge-info"><?php echo $u['order_count']; ?> 单</span></td><td><strong style="color:#059669">¥<?php echo number_format($u['total_spent'] ?? 0, 2); ?></strong></td></tr>
<?php endforeach; ?>
<?php if(empty($dTopUsers)): ?><tr><td colspan="4" style="text-align:center;padding:40px;color:var(--gray-500)">暂无数据</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="card">
<div class="card-header"><h3 class="card-title"><i class="fas fa-cube"></i> 主机销售排行</h3></div>
<div class="table-wrapper"><table><thead><tr><th>型号</th><th>单价</th><th>销量</th><th>销售额</th></tr></thead><tbody>
<?php foreach($dTopModels as $m): ?>
<tr><td><strong><?php echo h($m['name'] ?? '已删除'); ?></strong></td><td><span class="badge badge-purple"><?php echo $m['price']; ?> 积分</span></td><td><?php echo $m['sell_count']; ?></td><td><strong style="color:#059669"><?php echo number_format($m['total_revenue']); ?> 积分</strong></td></tr>
<?php endforeach; ?>
<?php if(empty($dTopModels)): ?><tr><td colspan="4" style="text-align:center;padding:40px;color:var(--gray-500)">暂无数据</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php endif; ?>

<!-- 系统配置 -->
<?php if($page==='config'): ?>
<div class="tabs">
<a class="tab active" onclick="showTab('tab-mnbt')"><i class="fas fa-server"></i> MNBT对接</a>
<a class="tab" onclick="showTab('tab-pay')"><i class="fas fa-credit-card"></i> 支付接口</a>
<a class="tab" onclick="showTab('tab-mail')"><i class="fas fa-envelope"></i> 邮件服务</a>
<a class="tab" onclick="showTab('tab-oauth')"><i class="fas fa-users-cog"></i> 聚合登录</a>
<a class="tab" onclick="showTab('tab-site')"><i class="fas fa-cog"></i> 网站设置</a>
</div>

<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="save_config">

<div id="tab-mnbt" class="tab-content active">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-server"></i> MNBT 对接配置（默认服务器）</h3>
</div>
<p style="padding:0 20px;color:var(--gray-500);font-size:.85rem;margin-bottom:12px">
<i class="fas fa-info-circle"></i> 此处为默认 MNBT 配置，未指定服务器的型号将使用此配置。
</p>
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

<div class="card" style="margin-top:20px">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-plus-circle"></i> 添加MNBT服务器</h3>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="add_server">
<div class="form-group" style="flex:1.5">
<label class="form-label">服务器名称</label>
<input type="text" name="name" required class="form-control" placeholder="如：香港节点">
</div>
<div class="form-group" style="flex:2">
<label class="form-label">API地址</label>
<input type="text" name="api_url" required class="form-control" placeholder="http://xxx/api/api.php">
</div>
<div class="form-group">
<label class="form-label">宝塔编号 (mn_bh)</label>
<input type="text" name="mn_bh" class="form-control">
</div>
<div class="form-group">
<label class="form-label">API秘钥 (mn_key)</label>
<input type="text" name="mn_key" class="form-control">
</div>
<div class="form-group">
<label class="form-label">宝塔调用秘钥 (mn_keye)</label>
<input type="text" name="mn_keye" class="form-control">
</div>
<div class="form-group" style="flex:0.5">
<label class="form-label">插件版本 (mn_vs)</label>
<input type="text" name="mn_vs" value="16" class="form-control">
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> 添加</button>
</div>
</form>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-server"></i> 服务器列表</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>ID</th>
<th>名称</th>
<th>API地址</th>
<th>宝塔编号</th>
<th>VS</th>
<th>状态</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php try { $servers=$DB->query("SELECT * FROM mnbt_servers ORDER BY sort_order,id")->fetchAll(); } catch(Exception $e) { $servers=[]; } foreach($servers as $srv): ?>
<tr>
<td><?php echo $srv['id']; ?></td>
<td><strong><?php echo h($srv['name']); ?></strong></td>
<td style="font-size:.85rem;color:var(--gray-500)"><?php echo h($srv['api_url']); ?></td>
<td><?php echo h($srv['mn_bh']); ?></td>
<td><?php echo h($srv['mn_vs']); ?></td>
<td>
<?php if($srv['status']): ?>
<span class="badge badge-success"><i class="fas fa-check"></i> 启用</span>
<?php else: ?>
<span class="badge badge-danger"><i class="fas fa-times"></i> 禁用</span>
<?php endif; ?>
</td>
<td>
<button type="button" class="btn btn-sm btn-outline" onclick="editServer(<?php echo htmlspecialchars(json_encode($srv), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit"></i> 编辑</button>
<form method="post" style="display:inline">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="test_server">
<input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
<button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-plug"></i> 测试</button>
</form>
<form method="post" style="display:inline">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="toggle_server">
<input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
<input type="hidden" name="status" value="<?php echo $srv['status']?0:1; ?>">
<button type="submit" class="btn btn-sm <?php echo $srv['status']?'btn-outline':'btn-success'; ?>">
<?php echo $srv['status']?'禁用':'启用'; ?>
</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除此服务器？已绑定此服务器的型号将回退到默认配置')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_server">
<input type="hidden" name="id" value="<?php echo $srv['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($servers)): ?>
<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无服务器，未配置服务器的型号将使用「系统配置」中的默认MNBT配置
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- 编辑服务器弹窗 -->
<div id="editServerOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;backdrop-filter:blur(4px)" onclick="closeEditServer()"></div>
<div id="editServerModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10000;width:500px;max-width:92vw;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden">
<div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--gray-200)">
<h3 style="margin:0;font-size:1.15rem"><i class="fas fa-edit" style="color:var(--primary-solid);margin-right:8px"></i>编辑服务器</h3>
<button type="button" onclick="closeEditServer()" style="background:none;border:none;font-size:1.6rem;cursor:pointer;color:var(--gray-500);line-height:1">&times;</button>
</div>
<form method="post" style="padding:20px 24px">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="edit_server">
<input type="hidden" name="id" id="editSrvId">
<div class="form-group" style="margin-bottom:16px">
<label class="form-label">服务器名称</label>
<input type="text" name="name" id="editSrvName" required class="form-control">
</div>
<div class="form-group" style="margin-bottom:16px">
<label class="form-label">API地址</label>
<input type="text" name="api_url" id="editSrvApiUrl" required class="form-control" placeholder="http://xxx/api/api.php">
</div>
<div class="form-row" style="margin-bottom:16px">
<div class="form-group">
<label class="form-label">宝塔编号 (mn_bh)</label>
<input type="text" name="mn_bh" id="editSrvBh" class="form-control">
</div>
<div class="form-group">
<label class="form-label">API秘钥 (mn_key)</label>
<input type="text" name="mn_key" id="editSrvKey" class="form-control">
</div>
</div>
<div class="form-row" style="margin-bottom:16px">
<div class="form-group">
<label class="form-label">宝塔调用秘钥 (mn_keye)</label>
<input type="text" name="mn_keye" id="editSrvKeye" class="form-control">
</div>
<div class="form-group" style="flex:0.5">
<label class="form-label">插件版本 (mn_vs)</label>
<input type="text" name="mn_vs" id="editSrvVs" value="16" class="form-control">
</div>
</div>
<div style="display:flex;gap:10px;justify-content:flex-end">
<button type="button" class="btn btn-outline" onclick="closeEditServer()">取消</button>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存修改</button>
</div>
</form>
</div>
</div>
<script>
function editServer(srv){
    document.getElementById('editSrvId').value = srv.id;
    document.getElementById('editSrvName').value = srv.name;
    document.getElementById('editSrvApiUrl').value = srv.api_url;
    document.getElementById('editSrvBh').value = srv.mn_bh;
    document.getElementById('editSrvKey').value = srv.mn_key;
    document.getElementById('editSrvKeye').value = srv.mn_keye;
    document.getElementById('editSrvVs').value = srv.mn_vs;
    document.getElementById('editServerOverlay').style.display = 'block';
    document.getElementById('editServerModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeEditServer(){
    document.getElementById('editServerOverlay').style.display = 'none';
    document.getElementById('editServerModal').style.display = 'none';
    document.body.style.overflow = '';
}
</script>

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
<h4 style="margin-bottom:12px;color:var(--gray-700)"><i class="fas fa-headset"></i> 工单通知</h4>
<div class="form-row">
    <div class="form-group">
        <label class="form-label">工单状态变更邮件通知</label>
        <select name="mail_notify_ticket" class="form-control">
            <option value="1" <?php echo conf('mail_notify_ticket','1')==='1'?'selected':''; ?>>开启</option>
            <option value="0" <?php echo conf('mail_notify_ticket','1')!=='1'?'selected':''; ?>>关闭</option>
        </select>
        <div class="form-tip">管理员回复或关闭工单时，自动发送邮件提醒用户</div>
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

<div id="tab-oauth" class="tab-content">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-users-cog"></i> 彩虹聚合登录配置</h3>
</div>
<p style="padding:0 20px;color:var(--gray-500);font-size:.85rem;margin-bottom:12px">
<i class="fas fa-info-circle"></i> 配置彩虹聚合登录后，用户可通过QQ、微信、支付宝等第三方账号快速登录/注册。<a href="https://login.az0.cn" target="_blank" style="color:var(--primary-solid)">前往申请 →</a>
</p>
<div class="form-group">
<label class="form-label">启用聚合登录</label>
<select name="oauth_enabled" class="form-control">
<option value="0" <?php echo conf('oauth_enabled')==='1'?'':'selected'; ?>>关闭</option>
<option value="1" <?php echo conf('oauth_enabled')==='1'?'selected':''; ?>>开启</option>
</select>
<div class="form-tip">开启后将在登录/注册页面显示第三方登录入口</div>
</div>
<div class="form-group">
<label class="form-label">聚合登录接口地址</label>
<input type="url" name="oauth_api_url" value="<?php echo h(conf('oauth_api_url','https://login.az0.cn/connect.php')); ?>" class="form-control" placeholder="https://login.az0.cn/connect.php">
<div class="form-tip">彩虹聚合登录API地址，一般无需修改</div>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">AppID</label>
<input type="text" name="oauth_appid" value="<?php echo h(conf('oauth_appid')); ?>" class="form-control" placeholder="在彩虹聚合登录平台获取">
</div>
<div class="form-group">
<label class="form-label">AppKey</label>
<input type="text" name="oauth_appkey" value="<?php echo h(conf('oauth_appkey')); ?>" class="form-control" placeholder="在彩虹聚合登录平台获取">
</div>
</div>
<div class="form-group">
<label class="form-label">启用的登录方式</label>
<input type="text" name="oauth_types" value="<?php echo h(conf('oauth_types','qq,wx,alipay')); ?>" class="form-control" placeholder="qq,wx,alipay">
<div class="form-tip">用英文逗号分隔，可选值：qq, wx, alipay, sina, baidu, douyin, huawei, xiaomi, google, microsoft, dingtalk, feishu, gitee, github</div>
</div>
<div style="margin-top:16px;padding:16px;background:var(--gray-100);border-radius:12px">
<h4 style="margin-bottom:12px;font-size:.9rem;color:var(--gray-700)"><i class="fas fa-list"></i> 可选登录方式</h4>
<div style="display:flex;flex-wrap:wrap;gap:8px;font-size:.85rem">
<?php
$allOauthTypes = [
    'qq'=>'QQ','wx'=>'微信','alipay'=>'支付宝','sina'=>'微博','baidu'=>'百度','douyin'=>'抖音',
    'huawei'=>'华为','xiaomi'=>'小米','google'=>'谷歌','microsoft'=>'微软','dingtalk'=>'钉钉',
    'feishu'=>'飞书','gitee'=>'Gitee','github'=>'GitHub'
];
foreach ($allOauthTypes as $k => $v) {
    echo '<span style="padding:4px 10px;border-radius:6px;background:#fff;border:1px solid var(--gray-300)">'.$v.' ('.$k.')</span>';
}
?>
</div>
</div>

<!-- 自定义图标配置（钉钉、飞书等无内置图标的平台） -->
<div style="margin-top:16px;padding:16px;background:var(--gray-100);border-radius:12px">
<h4 style="margin-bottom:12px;font-size:.9rem;color:var(--gray-700)"><i class="fas fa-image"></i> 自定义图标（钉钉、飞书）</h4>
<p style="font-size:.85rem;color:var(--gray-500);margin-bottom:12px">以下平台无内置图标，可设置图片URL或显示文字（二选一，图片URL优先）。图片建议使用正方形透明背景PNG/SVG。</p>
<?php
$customIconTypes = ['dingtalk' => '钉钉', 'feishu' => '飞书'];
foreach ($customIconTypes as $ck => $cv):
    $imgVal = h(conf('oauth_icon_img_' . $ck, ''));
    $textVal = h(conf('oauth_icon_text_' . $ck, ''));
?>
<div style="margin-bottom:16px;padding:12px;background:#fff;border-radius:8px;border:1px solid var(--gray-300)">
<div style="font-weight:600;margin-bottom:8px"><?php echo $cv; ?></div>
<div class="form-row">
<div class="form-group">
<label class="form-label">图片URL</label>
<input type="text" name="oauth_icon_img_<?php echo $ck; ?>" value="<?php echo $imgVal; ?>" class="form-control" placeholder="https://example.com/dingtalk.png">
</div>
<div class="form-group">
<label class="form-label">显示文字（图片URL为空时生效）</label>
<input type="text" name="oauth_icon_text_<?php echo $ck; ?>" value="<?php echo $textVal; ?>" class="form-control" placeholder="如：钉">
</div>
</div>
</div>
<?php endforeach; ?>
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
<div class="form-row">
<div class="form-group">
<label class="form-label">当前版本号</label>
<input type="text" name="current_version" value="<?php echo h(conf('current_version','1.4.0')); ?>" class="form-control" placeholder="例如：1.2.0">
<div class="form-tip">用于与更新服务器比对，检测到新版本时会在后台提示</div>
</div>
<div class="form-group">
<label class="form-label">版本更新 API 地址</label>
<input type="url" name="update_api_url" value="<?php echo h(conf('update_api_url','https://staridc.fangqihang.cn/api.php')); ?>" class="form-control" placeholder="https://staridc.fangqihang.cn/api.php">
<div class="form-tip">官方更新接口，留空则关闭自动检测</div>
</div>
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

<!-- 商品设置 -->
<?php if($page==='product'):
$cats = $DB->query("SELECT * FROM vhost_categories ORDER BY level, sort_order, id")->fetchAll();
$catTree = [];
foreach($cats as $c) {
    $catTree[$c['id']] = $c;
    $catTree[$c['id']]['children'] = [];
}
foreach($cats as $c) {
    if($c['parent_id'] && isset($catTree[$c['parent_id']])) {
        $catTree[$c['parent_id']]['children'][] = &$catTree[$c['id']];
    }
}
$level1 = array_filter($catTree, function($c){ return $c['level'] == 1; });
?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-plus-circle"></i> 添加一级分类</h3>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="add_category">
<input type="hidden" name="parent_id" value="">
<div class="form-group" style="flex:2">
<label class="form-label">分类名称</label>
<input type="text" name="name" required class="form-control" placeholder="如：云服务器">
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
<h3 class="card-title"><i class="fas fa-list"></i> 分类列表</h3>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr><th>名称</th><th>级别</th><th>排序</th><th>编辑</th><th>操作</th></tr>
</thead>
<tbody>
<?php
function renderCatRow($cat, $depth = 0) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
    $prefix = $depth > 0 ? '↳ ' : '';
    $levelLabels = [1=>'一级',2=>'二级',3=>'三级'];
    ?>
    <tr id="cat-row-<?php echo $cat['id']; ?>">
    <td><strong><?php echo $indent.$prefix.h($cat['name']); ?></strong></td>
    <td><span class="badge badge-info"><?php echo $levelLabels[$cat['level']] ?? $cat['level']; ?></span></td>
    <td><?php echo $cat['sort_order']; ?></td>
    <td style="max-width:300px">
    <form method="post" class="form-row" style="margin:0;gap:6px">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="edit_category">
    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
    <input type="text" name="name" value="<?php echo h($cat['name']); ?>" class="form-control" style="flex:1;padding:6px 10px;font-size:.85rem">
    <input type="number" name="sort_order" value="<?php echo $cat['sort_order']; ?>" class="form-control" style="width:60px;padding:6px 8px;font-size:.85rem">
    <button type="submit" class="btn btn-sm btn-primary" style="padding:4px 8px"><i class="fas fa-save"></i></button>
    </form>
    </td>
    <td style="white-space:nowrap">
    <?php if($cat['level'] < 3): ?>
    <button type="button" class="btn btn-sm btn-outline" onclick="showAddChild(<?php echo $cat['id']; ?>,'<?php echo h(addslashes($cat['name'])); ?>',<?php echo $cat['level']+1; ?>)"><i class="fas fa-plus"></i> 子分类</button>
    <?php endif; ?>
    <form method="post" style="display:inline" onsubmit="return confirm('确定删除此分类？')">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="del_category">
    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
    </form>
    </td>
    </tr>
    <?php
    if(!empty($cat['children'])) {
        foreach($cat['children'] as $child) {
            renderCatRow($child, $depth + 1);
        }
    }
}
foreach($level1 as $c) {
    renderCatRow($c);
}
if(empty($level1)): ?>
<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无分类数据，请在上方添加一级分类
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<!-- 添加子分类弹窗 -->
<div id="addChildOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;backdrop-filter:blur(4px)" onclick="closeAddChild()"></div>
<div id="addChildModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10000;width:450px;max-width:92vw;background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden">
<div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--gray-200)">
<h3 style="margin:0;font-size:1.15rem"><i class="fas fa-plus-circle" style="color:var(--primary-solid);margin-right:8px"></i>添加子分类</h3>
<button type="button" onclick="closeAddChild()" style="background:none;border:none;font-size:1.6rem;cursor:pointer;color:var(--gray-500);line-height:1">&times;</button>
</div>
<form method="post" style="padding:20px 24px">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="add_category">
<input type="hidden" name="parent_id" id="addChildParentId">
<div class="form-group" style="margin-bottom:16px">
<label class="form-label">父级分类</label>
<input type="text" id="addChildParentName" class="form-control" readonly style="background:var(--gray-100)">
</div>
<div class="form-group" style="margin-bottom:16px">
<label class="form-label">分类名称</label>
<input type="text" name="name" required class="form-control" placeholder="请输入子分类名称">
</div>
<div class="form-group" style="margin-bottom:16px">
<label class="form-label">排序</label>
<input type="number" name="sort_order" value="0" class="form-control">
</div>
<div style="display:flex;gap:10px;justify-content:flex-end">
<button type="button" class="btn btn-outline" onclick="closeAddChild()">取消</button>
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> 添加</button>
</div>
</form>
</div>
<script>
function showAddChild(id, name, level) {
    document.getElementById('addChildParentId').value = id;
    document.getElementById('addChildParentName').value = name + ' (当前' + (level===2?'二级':'三级') + '分类)';
    document.getElementById('addChildOverlay').style.display = 'block';
    document.getElementById('addChildModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeAddChild() {
    document.getElementById('addChildOverlay').style.display = 'none';
    document.getElementById('addChildModal').style.display = 'none';
    document.body.style.overflow = '';
}
</script>

<!-- 主机型号 -->
<?php
// 编辑模式：加载已有型号数据
$editingModel = null;
if (isset($_GET['edit_model'])) {
    $editModelId = intval($_GET['edit_model']);
    $stmtEm = $DB->prepare("SELECT * FROM vhost_models WHERE id=?");
    $stmtEm->execute([$editModelId]);
    $editingModel = $stmtEm->fetch();
    // 加载时长折扣
    $editingDurations = [];
    if ($editingModel) {
        $stmtDur = $DB->prepare("SELECT * FROM vhost_model_durations WHERE model_id=?");
        $stmtDur->execute([$editModelId]);
        $editingDurations = [];
        foreach($stmtDur->fetchAll() as $d) {
            $editingDurations[$d['duration_type']] = $d;
        }
        // 加载弹性配置
        $stmtElastic = $DB->prepare("SELECT * FROM vhost_model_elastic WHERE model_id=?");
        $stmtElastic->execute([$editModelId]);
        $editingElastic = [];
        foreach($stmtElastic->fetchAll() as $el) {
            $editingElastic[$el['field_name']] = $el;
        }
    }
}
?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas <?php echo $editingModel?'fa-edit':'fa-plus-circle'; ?>"></i> <?php echo $editingModel?'编辑型号':'添加型号'; ?></h3>
<?php if ($editingModel): ?>
<a href="?page=product" class="btn btn-sm btn-outline"><i class="fas fa-times"></i> 取消编辑</a>
<?php endif; ?>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="<?php echo $editingModel?'edit_model':'add_model'; ?>">
<?php if ($editingModel): ?><input type="hidden" name="id" value="<?php echo $editingModel['id']; ?>"><?php endif; ?>
<?php
// 构建三级分类树用于下拉
$allCats = $DB->query("SELECT * FROM vhost_categories ORDER BY level, sort_order, id")->fetchAll();
$catMap = [];
foreach($allCats as $c) { $catMap[$c['id']] = $c; }
function buildCatOptions($cats, $map, $selectedId, $depth = 0) {
    $html = '';
    foreach($cats as $c) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        $prefix = $depth > 0 ? '↳ ' : '';
        $sel = ($c['id'] == $selectedId) ? ' selected' : '';
        $html .= '<option value="'.$c['id'].'"'.$sel.'>'.$indent.$prefix.h($c['name']).'</option>';
        $children = array_filter($map, function($x) use ($c) { return $x['parent_id'] == $c['id']; });
        if(!empty($children)) {
            $html .= buildCatOptions($children, $map, $selectedId, $depth + 1);
        }
    }
    return $html;
}
$level1cats = array_filter($catMap, function($c){ return $c['level'] == 1; });
$editingCatId = $editingModel ? ($editingModel['category_id'] ?? 0) : 0;
$catOptions = buildCatOptions($level1cats, $catMap, $editingCatId);
?>
<div class="form-group">
<label class="form-label">分类</label>
<select name="category_id" class="form-control">
<option value="">未分类</option>
<?php echo $catOptions; ?>
</select>
</div>
<div class="form-group" style="flex:2">
<label class="form-label">名称</label>
<input type="text" name="name" required class="form-control" placeholder="如：入门型" value="<?php echo $editingModel ? h($editingModel['name']) : ''; ?>">
</div>
<div class="form-group">
<label class="form-label">网页空间 (MB)</label>
<input type="number" name="web_space" required class="form-control" value="<?php echo $editingModel ? $editingModel['web_space'] : ''; ?>">
</div>
<div class="form-group">
<label class="form-label">数据库 (MB)</label>
<input type="number" name="db_space" required class="form-control" value="<?php echo $editingModel ? $editingModel['db_space'] : ''; ?>">
</div>
<div class="form-group">
<label class="form-label">流量 (GB/月)</label>
<input type="number" name="flow" value="<?php echo $editingModel ? $editingModel['flow'] : '30'; ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">域名数</label>
<input type="number" name="domain_limit" value="<?php echo $editingModel ? $editingModel['domain_limit'] : '5'; ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">价格 (积分)</label>
<input type="number" name="price" required class="form-control" value="<?php echo $editingModel ? $editingModel['price'] : ''; ?>">
</div>
<div class="form-group">
<label class="form-label">排序</label>
<input type="number" name="sort_order" value="<?php echo $editingModel ? $editingModel['sort_order'] : '0'; ?>" class="form-control">
</div>
<div class="form-group">
<label class="form-label">MNBT服务器</label>
<select name="server_id" class="form-control">
<option value="">默认配置</option>
<?php try { $srvs=$DB->query("SELECT * FROM mnbt_servers ORDER BY sort_order,id")->fetchAll(); } catch(Exception $e) { $srvs=[]; } foreach($srvs as $srv): ?>
<option value="<?php echo $srv['id']; ?>" <?php echo $editingModel && $editingModel['server_id']==$srv['id']?'selected':''; ?>><?php echo h($srv['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group">
<label class="form-label">每人限量</label>
<input type="number" name="max_per_user" value="<?php echo $editingModel ? $editingModel['max_per_user'] : '0'; ?>" min="0" class="form-control" placeholder="0=不限">
<div class="form-tip">0 表示不限购，大于 0 则限制每用户购买该型号的最大数量</div>
</div>

<div class="form-group" style="grid-column:1/-1;display:flex;align-items:flex-end;margin-top:8px">
<button type="submit" class="btn btn-primary"><i class="fas <?php echo $editingModel?'fa-save':'fa-plus'; ?>"></i> <?php echo $editingModel?'保存修改':'添加型号'; ?></button>
</div>

<!-- 时长折扣 -->
<div class="card" style="grid-column:1/-1">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-clock"></i> 时长折扣</h3>
</div>
<div style="padding:0 24px 24px">
<?php
$durationKeys = ['month','quarter','half_year','year','2year','3year','5year','10year'];
$durationLabels = ['month'=>'月付','quarter'=>'季付','half_year'=>'半年付','year'=>'年付','2year'=>'两年付','3year'=>'三年付','5year'=>'五年付','10year'=>'十年付'];
?>
<div class="table-wrapper">
<table>
<thead>
<tr><th>时长</th><th>启用</th><th>折扣 (%)</th></tr>
</thead>
<tbody>
<?php foreach($durationKeys as $dk): ?>
<tr>
<td><strong><?php echo $durationLabels[$dk]; ?></strong></td>
<td><input type="checkbox" name="dur[<?php echo $dk; ?>][enabled]" value="1" <?php echo isset($editingDurations[$dk]) && $editingDurations[$dk]['enabled'] ? 'checked' : ''; ?>></td>
<td><input type="number" name="dur[<?php echo $dk; ?>][discount]" value="<?php echo isset($editingDurations[$dk]) ? $editingDurations[$dk]['discount'] : '0'; ?>" min="0" max="100" class="form-control" style="width:100px;display:inline-block"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div style="display:flex;align-items:flex-end;margin-top:16px">
<button type="submit" class="btn btn-primary"><i class="fas <?php echo $editingModel?'fa-save':'fa-plus'; ?>"></i> <?php echo $editingModel?'保存修改':'添加'; ?></button>
</div>
</div>
</div>

<!-- 弹性配置 -->
<div class="card" style="grid-column:1/-1">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-expand-arrows-alt"></i> 弹性配置</h3>
</div>
<div style="padding:0 24px 24px">
<div class="form-group">
<label class="form-label">
<input type="checkbox" name="is_elastic" value="1" <?php echo $editingModel && $editingModel['is_elastic'] ? 'checked' : ''; ?> onchange="toggleElasticFields(this)">
启用弹性配置
</label>
</div>
<?php
$elasticFields = ['web_space'=>'网页空间 (MB)','db_space'=>'数据库 (MB)','flow'=>'流量 (GB)','domain_limit'=>'域名数'];
?>
<div class="table-wrapper" id="elasticFieldsContainer" style="<?php echo $editingModel && $editingModel['is_elastic'] ? '' : 'display:none'; ?>">
<table>
<thead>
<tr><th>资源</th><th>启用</th><th>最小值</th><th>最大值</th><th>步进</th><th>单价 (积分/步)</th></tr>
</thead>
<tbody>
<?php foreach($elasticFields as $ek => $el): ?>
<tr>
<td><strong><?php echo $el; ?></strong></td>
<td><input type="checkbox" name="elastic[<?php echo $ek; ?>][enabled]" value="1" <?php echo isset($editingElastic[$ek]) && $editingElastic[$ek]['enabled'] ? 'checked' : ''; ?>></td>
<td><input type="number" name="elastic[<?php echo $ek; ?>][min]" value="<?php echo isset($editingElastic[$ek]) ? $editingElastic[$ek]['min_value'] : 0; ?>" class="form-control" style="width:80px;display:inline-block"></td>
<td><input type="number" name="elastic[<?php echo $ek; ?>][max]" value="<?php echo isset($editingElastic[$ek]) ? $editingElastic[$ek]['max_value'] : 0; ?>" class="form-control" style="width:80px;display:inline-block"></td>
<td><input type="number" name="elastic[<?php echo $ek; ?>][step]" value="<?php echo isset($editingElastic[$ek]) ? $editingElastic[$ek]['step'] : 1; ?>" class="form-control" style="width:80px;display:inline-block"></td>
<td><input type="number" name="elastic[<?php echo $ek; ?>][price]" value="<?php echo isset($editingElastic[$ek]) ? $editingElastic[$ek]['unit_price'] : 0; ?>" class="form-control" style="width:100px;display:inline-block"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div style="display:flex;align-items:flex-end;margin-top:16px">
<button type="submit" class="btn btn-primary"><i class="fas <?php echo $editingModel?'fa-save':'fa-plus'; ?>"></i> <?php echo $editingModel?'保存修改':'添加'; ?></button>
</div>
</div>
</div>
</form>
</div>

<script>
function toggleElasticFields(el) {
    document.getElementById('elasticFieldsContainer').style.display = el.checked ? '' : 'none';
}
</script>

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
<th>限量</th>
<th>服务器</th>
<th>状态</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php try { $models=$DB->query("SELECT vm.*,ms.name as server_name FROM vhost_models vm LEFT JOIN mnbt_servers ms ON vm.server_id=ms.id ORDER BY vm.sort_order,vm.id")->fetchAll(); } catch(Exception $e) { $models=$DB->query("SELECT * FROM vhost_models ORDER BY sort_order,id")->fetchAll(); foreach($models as &$m) $m['server_name']=null; unset($m); } foreach($models as $m): ?>
<tr>
<td><?php echo $m['id']; ?></td>
<td><strong><?php echo h($m['name']); ?></strong></td>
<td><?php echo $m['web_space']; ?> MB</td>
<td><?php echo $m['db_space']; ?> MB</td>
<td><?php echo $m['flow']; ?> GB</td>
<td><?php echo $m['domain_limit']; ?></td>
<td><span class="badge badge-purple"><?php echo $m['price']; ?> 积分</span></td>
<td><?php
$maxPerUser = isset($m['max_per_user']) ? intval($m['max_per_user']) : 0;
if ($maxPerUser > 0) {
    echo '<span class="badge badge-info">限' . $maxPerUser . '台</span>';
} else {
    echo '<span style="color:var(--gray-400)">不限</span>';
}
?></td>
<td><?php echo $m['server_name'] ? h($m['server_name']) : '<span style="color:var(--gray-500)">默认</span>'; ?></td>
<td>
<?php if($m['status']): ?>
<span class="badge badge-success"><i class="fas fa-check"></i> 上架</span>
<?php else: ?>
<span class="badge badge-danger"><i class="fas fa-times"></i> 下架</span>
<?php endif; ?>
</td>
<td>
<a href="?page=product&edit_model=<?php echo $m['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> 编辑</a>
<form method="post" style="display:inline">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="toggle_model">
<input type="hidden" name="id" value="<?php echo $m['id']; ?>">
<input type="hidden" name="status" value="<?php echo $m['status']?0:1; ?>">
<button type="submit" class="btn btn-sm <?php echo $m['status']?'btn-outline':'btn-success'; ?>">
<?php echo $m['status']?'下架':'上架'; ?>
</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_model">
<input type="hidden" name="id" value="<?php echo $m['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($models)): ?>
<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无型号数据
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- 虚拟主机/用户管理 -->
<?php if($page==='users_instances'): ?>
<?php if($subPage === 'vhosts' || !$subPage): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-search"></i> 搜索筛选</h3>
</div>
<form method="get" class="search-box">
<input type="hidden" name="page" value="users_instances">
<input type="hidden" name="sub" value="vhosts">
<div class="search-input">
<i class="fas fa-search"></i>
<input type="text" name="search" value="<?php echo h($_GET['search'] ?? ''); ?>" class="form-control" placeholder="搜索账号、邮箱或型号...">
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
<a href="?page=users_instances&sub=vhosts" class="btn btn-outline"><i class="fas fa-redo"></i> 重置</a>
</form>
</div>

<div class="batch-actions">
<label><i class="fas fa-tasks"></i> 批量操作：</label>
<button type="button" class="btn btn-sm btn-outline" onclick="vhostSelectAll()"><i class="fas fa-check-square"></i> 全选</button>
<button type="button" class="btn btn-sm btn-outline" onclick="vhostSelectNone()"><i class="fas fa-square"></i> 取消</button>
<button type="button" class="btn btn-sm btn-danger" onclick="submitVhostBatch('del_vhost_batch')"><i class="fas fa-trash"></i> 删除选中</button>
</div>

<form method="post" id="vhostBatchForm" onsubmit="return confirm('确定执行批量操作？')">
<?php echo csrfField(); ?>
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
<th>服务器</th>
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
    try {
        $stmt = $DB->prepare("SELECT v.*,u.email,vm.name as model_name,ms.name as server_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id LEFT JOIN mnbt_servers ms ON v.server_id=ms.id WHERE v.account LIKE ? OR u.email LIKE ? OR vm.name LIKE ? ORDER BY v.id DESC");
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
    } catch(Exception $e) {
        $stmt = $DB->prepare("SELECT v.*,u.email,vm.name as model_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.account LIKE ? OR u.email LIKE ? OR vm.name LIKE ? ORDER BY v.id DESC");
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
    }
} else {
    try {
        $stmt = $DB->query("SELECT v.*,u.email,vm.name as model_name,ms.name as server_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id LEFT JOIN mnbt_servers ms ON v.server_id=ms.id ORDER BY v.id DESC");
    } catch(Exception $e) {
        $stmt = $DB->query("SELECT v.*,u.email,vm.name as model_name FROM vhosts v LEFT JOIN users u ON v.user_id=u.id LEFT JOIN vhost_models vm ON v.model_id=vm.id ORDER BY v.id DESC");
    }
}
$vhosts = $stmt->fetchAll();
foreach($vhosts as $v): if(!isset($v['server_name'])) $v['server_name']=null; ?>
<tr>
<td><input type="checkbox" class="vhost-check" value="<?php echo $v['id']; ?>"></td>
<td><?php echo $v['id']; ?></td>
<td><?php echo h($v['email'] ?? '<span style="color:var(--gray-500)">已删除</span>'); ?></td>
<td><?php echo h($v['model_name'] ?? '<span style="color:var(--gray-500)">已删除</span>'); ?></td>
<td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?php echo h($v['account']); ?></code></td>
<td><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?php echo h($v['password']); ?></code></td>
<td><?php echo $v['server_name'] ? h($v['server_name']) : '<span style="color:var(--gray-500)">默认</span>'; ?></td>
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
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_vhost">
<input type="hidden" name="id" value="<?php echo $v['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($vhosts)): ?>
<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--gray-500)">
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
<?php if($subPage === 'users'): ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-search"></i> 搜索筛选</h3>
</div>
<form method="get" class="search-box">
<input type="hidden" name="page" value="users_instances">
<input type="hidden" name="sub" value="users">
<div class="search-input">
<i class="fas fa-search"></i>
<input type="text" name="search" value="<?php echo h($_GET['search'] ?? ''); ?>" class="form-control" placeholder="搜索邮箱或昵称...">
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 搜索</button>
<a href="?page=users_instances&sub=users" class="btn btn-outline"><i class="fas fa-redo"></i> 重置</a>
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
<?php echo csrfField(); ?>
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
<?php echo csrfField(); ?>
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
<h3 style="margin:0;font-size:1.2rem"><i class="fas fa-user-edit" style="color:#10b981"></i> 编辑用户</h3>
<button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#999">&times;</button>
</div>
<form method="post" id="editUserForm">
<?php echo csrfField(); ?>
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
<?php endif; ?>

<!-- 定价与优惠 -->
<?php if($page==='pricing'): ?>
<?php if($subPage === 'pricing_main' || !$subPage): ?>
<?php
$pkgEdit = null;
if (isset($_GET['edit'])) {
    $stmt = $DB->prepare("SELECT * FROM recharge_packages WHERE id=?");
    $stmt->execute([intval($_GET['edit'])]);
    $pkgEdit = $stmt->fetch();
}
$pkgList = $DB->query("SELECT * FROM recharge_packages ORDER BY sort_order,id")->fetchAll();
?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-coins"></i> 充值套餐管理</h3>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="<?php echo $pkgEdit ? 'edit_recharge_package' : 'add_recharge_package'; ?>">
<?php if ($pkgEdit): ?><input type="hidden" name="id" value="<?php echo $pkgEdit['id']; ?>"><?php endif; ?>
<div class="form-group">
<label class="form-label">积分数量</label>
<input type="number" name="points" min="1" required class="form-control" value="<?php echo $pkgEdit ? $pkgEdit['points'] : ''; ?>" placeholder="如 500">
</div>
<div class="form-group">
<label class="form-label">价格（元）</label>
<input type="number" step="0.01" name="price" min="0.01" required class="form-control" value="<?php echo $pkgEdit ? $pkgEdit['price'] : ''; ?>" placeholder="如 20">
</div>
<div class="form-group">
<label class="form-label">排序 <span style="color:var(--gray-500);font-weight:normal">(越小越靠前)</span></label>
<input type="number" name="sort_order" class="form-control" value="<?php echo $pkgEdit ? $pkgEdit['sort_order'] : '0'; ?>">
</div>
<div style="display:flex;align-items:flex-end;gap:10px">
<button type="submit" class="btn btn-primary"><i class="fas fa-<?php echo $pkgEdit ? 'save' : 'plus'; ?>"></i> <?php echo $pkgEdit ? '保存修改' : '添加套餐'; ?></button>
<?php if ($pkgEdit): ?><a href="?page=pricing&sub=pricing_main" class="btn btn-outline">取消</a><?php endif; ?>
</div>
</form>
</div>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-list"></i> 套餐列表</h3>
</div>
<div class="table-wrapper">
<table>
<thead><tr><th>积分</th><th>价格</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
<tbody>
<?php if(empty($pkgList)): ?>
<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400)"><i class="fas fa-coins" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>暂无套餐，请在上方添加</td></tr>
<?php else: foreach($pkgList as $p): ?>
<tr>
<td><?php echo number_format($p['points']); ?></td>
<td>¥<?php echo $p['price']; ?></td>
<td><?php echo $p['sort_order']; ?></td>
<td><span class="badge <?php echo $p['status'] ? 'badge-success' : 'badge-gray'; ?>"><?php echo $p['status'] ? '上架' : '下架'; ?></span></td>
<td>
<a href="?page=pricing&sub=pricing_main&edit=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> 编辑</a>
<form method="post" style="display:inline">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="toggle_recharge_package">
<input type="hidden" name="id" value="<?php echo $p['id']; ?>">
<input type="hidden" name="status" value="<?php echo $p['status'] ? 0 : 1; ?>">
<button type="submit" class="btn btn-sm <?php echo $p['status'] ? 'btn-outline' : 'btn-success'; ?>"><?php echo $p['status'] ? '下架' : '上架'; ?></button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_recharge_package">
<input type="hidden" name="id" value="<?php echo $p['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>

<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="save_config">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-ban"></i> 购买限制</h3>
</div>
<div class="form-row">
<div class="form-group">
<label class="form-label">每用户最多购买主机数</label>
<input type="number" name="max_hosts_per_user" value="<?php echo h(conf('max_hosts_per_user','5')); ?>" min="1" class="form-control">
<p class="form-hint">全局限制：每个用户最多可购买的主机总数（0=不限）。各型号还可在"主机型号"中单独设置限量</p>
</div>
</div>
</div>
<button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-save"></i> 保存设置</button>
</form>
<?php endif; ?>

<?php if($subPage === 'promotions'): ?>
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="save_config">

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
<div style="background:linear-gradient(135deg,#10b98122,#05966922);padding:16px;border-radius:12px;margin-top:12px">
<p style="color:var(--gray-700);font-size:.9rem;margin-bottom:8px"><i class="fas fa-info-circle"></i> 推荐奖励规则：</p>
<ul style="color:var(--gray-600);font-size:.85rem;padding-left:20px;line-height:1.8">
<li>被推荐人注册时输入推荐码，双方都可获得奖励积分</li>
<li>推荐码格式：系统自动生成，如 <code style="background:var(--gray-100);padding:2px 6px;border-radius:4px">INV8A3F2C</code></li>
<li>推荐人可在个人中心查看自己的推荐码和推荐记录</li>
</ul>
</div>
</div>

<button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-save"></i> 保存优惠设置</button>
</form>

<div class="tabs" style="margin-top:20px">
<a class="tab active" onclick="showCouponTab('tab-add')"><i class="fas fa-plus-circle"></i> 添加优惠码</a>
<a class="tab" onclick="showCouponTab('tab-batch')"><i class="fas fa-layer-group"></i> 批量生成</a>
</div>

<div id="tab-add" class="tab-content active">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-plus-circle"></i> 添加优惠码</h3>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="add_coupon">
<div class="form-group">
<label class="form-label">优惠码 <span style="color:var(--gray-500);font-weight:normal">(留空自动生成)</span></label>
<input type="text" name="code" class="form-control" placeholder="如：SALE20 或留空自动生成">
</div>
<div class="form-group">
<label class="form-label">折扣百分比</label>
<input type="number" name="discount" min="1" max="99" required class="form-control" placeholder="如 20 表示8折">
<p class="form-hint">填写1-99，如填20表示原价的80%（8折）</p>
</div>
<div class="form-group">
<label class="form-label">可用次数</label>
<input type="number" name="max_uses" min="0" value="1" required class="form-control">
<p class="form-hint">1=一次性使用，0=无限次使用</p>
</div>
<div class="form-group">
<label class="form-label">有效期至 <span style="color:var(--gray-500);font-weight:normal">(留空永久有效)</span></label>
<input type="datetime-local" name="expire_at" class="form-control">
</div>
<div class="form-group">
<label class="form-label">适用型号 <span style="color:var(--gray-500);font-weight:normal">(留空适用全部)</span></label>
<select name="model_id" class="form-control">
<option value="">全部型号</option>
<?php try { $couponModels=$DB->query("SELECT * FROM vhost_models ORDER BY sort_order,id")->fetchAll(); } catch(Exception $e) { $couponModels=[]; } foreach($couponModels as $cm): ?>
<option value="<?php echo $cm['id']; ?>"><?php echo h($cm['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> 添加</button>
</div>
</form>
</div>
</div>

<div id="tab-batch" class="tab-content">
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-layer-group"></i> 批量生成优惠码</h3>
</div>
<form method="post" class="form-row">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="batch_add_coupon">
<div class="form-group" style="flex:0.8">
<label class="form-label">优惠码前缀</label>
<input type="text" name="prefix" value="CP" class="form-control" placeholder="如：SALE">
</div>
<div class="form-group" style="flex:0.5">
<label class="form-label">生成数量</label>
<input type="number" name="count" min="1" max="100" value="10" required class="form-control">
</div>
<div class="form-group">
<label class="form-label">折扣百分比</label>
<input type="number" name="discount" min="1" max="99" value="10" required class="form-control" placeholder="如 20 表示8折">
</div>
<div class="form-group">
<label class="form-label">可用次数</label>
<input type="number" name="max_uses" min="0" value="1" required class="form-control">
</div>
<div class="form-group">
<label class="form-label">有效期至</label>
<input type="datetime-local" name="expire_at" class="form-control">
</div>
<div class="form-group">
<label class="form-label">适用型号</label>
<select name="model_id" class="form-control">
<option value="">全部型号</option>
<?php foreach($couponModels as $cm): ?>
<option value="<?php echo $cm['id']; ?>"><?php echo h($cm['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary"><i class="fas fa-magic"></i> 批量生成</button>
</div>
</form>
</div>
</div>

<script>
function showCouponTab(id){
    document.querySelectorAll('.tab-content').forEach(function(el){el.classList.remove('active')});
    document.querySelectorAll('.tab').forEach(function(el){el.classList.remove('active')});
    document.getElementById(id).classList.add('active');
    event.target.classList.add('active');
}
</script>

<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-list"></i> 优惠码列表</h3>
<?php
$couponTotal = 0;
$couponUsed = 0;
try {
    $couponTotal = $DB->query("SELECT COUNT(*) as c FROM coupons")->fetch()['c'];
    $couponUsed = $DB->query("SELECT COUNT(*) as c FROM coupons WHERE status=1")->fetch()['c'];
} catch(Exception $e) {}
?>
<span class="badge badge-info">共 <?php echo $couponTotal; ?> 个</span>
<span class="badge badge-warning" style="margin-left:6px">已用完 <?php echo $couponUsed; ?> 个</span>
</div>
<div class="table-wrapper">
<table>
<thead>
<tr>
<th>ID</th>
<th>优惠码</th>
<th>折扣</th>
<th>使用情况</th>
<th>有效期</th>
<th>适用型号</th>
<th>状态</th>
<th>创建时间</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php
try {
    $coupons = $DB->query("SELECT c.*, vm.name as model_name FROM coupons c LEFT JOIN vhost_models vm ON c.model_id=vm.id ORDER BY c.id DESC")->fetchAll();
} catch(Exception $e) {
    $coupons = $DB->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
    foreach($coupons as &$c) $c['model_name'] = null; unset($c);
}
foreach($coupons as $c):
    $isExpired = !empty($c['expire_at']) && strtotime($c['expire_at']) < time();
    $isExhausted = $c['max_uses'] > 0 && $c['used_count'] >= $c['max_uses'];
?>
<tr>
<td><?php echo $c['id']; ?></td>
<td><code style="background:var(--gray-100);padding:3px 10px;border-radius:6px;font-weight:600;letter-spacing:1px"><?php echo h($c['code']); ?></code></td>
<td><span class="badge badge-purple"><?php echo $c['discount']; ?>% OFF</span></td>
<td>
<?php if($c['max_uses'] == 0): ?>
<span class="badge badge-info"><?php echo $c['used_count']; ?> / 无限</span>
<?php else: ?>
<span class="badge <?php echo $isExhausted?'badge-danger':'badge-success'; ?>"><?php echo $c['used_count']; ?> / <?php echo $c['max_uses']; ?></span>
<?php endif; ?>
</td>
<td>
<?php if(empty($c['expire_at'])): ?>
<span style="color:var(--gray-500)">永久</span>
<?php elseif($isExpired): ?>
<span class="badge badge-danger">已过期</span>
<?php else: ?>
<span style="font-size:.85rem"><?php echo date('Y-m-d H:i', strtotime($c['expire_at'])); ?></span>
<?php endif; ?>
</td>
<td><?php echo $c['model_name'] ? h($c['model_name']) : '<span style="color:var(--gray-500)">全部</span>'; ?></td>
<td>
<?php if($c['status'] == 1): ?>
<span class="badge badge-danger"><i class="fas fa-times"></i> 已禁用</span>
<?php elseif($isExpired): ?>
<span class="badge badge-warning"><i class="fas fa-clock"></i> 已过期</span>
<?php elseif($isExhausted): ?>
<span class="badge badge-warning"><i class="fas fa-ban"></i> 已用完</span>
<?php else: ?>
<span class="badge badge-success"><i class="fas fa-check"></i> 可用</span>
<?php endif; ?>
</td>
<td style="font-size:.85rem;color:var(--gray-500)"><?php echo date('Y-m-d H:i', strtotime($c['created_at'])); ?></td>
<td>
<form method="post" style="display:inline">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="toggle_coupon">
<input type="hidden" name="id" value="<?php echo $c['id']; ?>">
<input type="hidden" name="status" value="<?php echo $c['status']?0:1; ?>">
<button type="submit" class="btn btn-sm <?php echo $c['status']?'btn-success':'btn-outline'; ?>">
<?php echo $c['status']?'启用':'禁用'; ?>
</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除此优惠码？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_coupon">
<input type="hidden" name="id" value="<?php echo $c['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($coupons)): ?>
<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-500)">
<i class="fas fa-ticket-alt" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
暂无优惠码，点击上方添加或批量生成
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 工单管理 -->
<?php if($page==='tickets'):
$tkFilter = $_GET['filter'] ?? 'all';
$tkWhere = '';
if ($tkFilter === 'pending') $tkWhere = 'WHERE t.status=0';
elseif ($tkFilter === 'replied') $tkWhere = 'WHERE t.status=1';
elseif ($tkFilter === 'closed') $tkWhere = 'WHERE t.status=2';

try {
    $tkList = $DB->query("SELECT t.*, u.email as user_email, u.nickname as user_nickname, vm.name as model_name, vh.account as vhost_account FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN vhosts vh ON t.vhost_id=vh.id LEFT JOIN vhost_models vm ON vh.model_id=vm.id {$tkWhere} ORDER BY t.updated_at DESC")->fetchAll();
} catch (Exception $e) { $tkList = []; }
$tkPending = $DB->query("SELECT COUNT(*) as c FROM tickets WHERE status=0")->fetch()['c'] ?? 0;
$tkStatusMap = [0=>'待处理',1=>'已回复',2=>'已关闭'];
$tkStatusBadge = [0=>'badge-warning',1=>'badge-success',2=>'badge-gray'];

$viewTicket = isset($_GET['view']) ? intval($_GET['view']) : 0;
if ($viewTicket > 0):
    try {
        $stmt = $DB->prepare("SELECT t.*, u.email as user_email, u.nickname as user_nickname, vm.name as model_name, vh.account as vhost_account FROM tickets t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN vhosts vh ON t.vhost_id=vh.id LEFT JOIN vhost_models vm ON vh.model_id=vm.id WHERE t.id=?");
        $stmt->execute([$viewTicket]);
        $vd = $stmt->fetch();
    } catch (Exception $e) { $vd = false; }
    if ($vd):
        try {
            $vdReplies = $DB->prepare("SELECT tr.*, u.nickname as user_nickname, a.username as admin_username FROM ticket_replies tr LEFT JOIN users u ON tr.user_id=u.id LEFT JOIN admins a ON tr.admin_id=a.id WHERE tr.ticket_id=? ORDER BY tr.created_at ASC");
            $vdReplies->execute([$viewTicket]);
            $vdReplyList = $vdReplies->fetchAll();
        } catch (Exception $e) { $vdReplyList = []; }
        $vdStatus = isset($vd['status']) ? intval($vd['status']) : 0;
?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-headset"></i> 工单 #<?php echo $vd['id']; ?> 详情</h3>
<a href="?page=tickets&filter=<?php echo $tkFilter; ?>" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> 返回列表</a>
</div>
<div style="padding:16px 0">
<div style="margin-bottom:16px">
<span class="badge badge-lg <?php echo $tkStatusBadge[$vdStatus]; ?>"><?php echo $tkStatusMap[$vdStatus]; ?></span>
</div>
<div style="margin-bottom:8px"><strong>标题：</strong><?php echo h($vd['subject'] ?? '(无标题)'); ?></div>
<div style="margin-bottom:8px;font-size:.9rem;color:var(--gray-500)">
<strong>提交人：</strong><?php echo h($vd['user_nickname'] ?: $vd['user_email']); ?> &nbsp;|&nbsp;
<strong>时间：</strong><?php echo $vd['created_at']; ?>
<?php if ($vd['vhost_account']): ?>
&nbsp;|&nbsp; <strong>关联主机：</strong><?php echo h($vd['model_name'].' - '.$vd['vhost_account']); ?>
<?php endif; ?>
</div>
</div>
<div style="border-top:1px solid var(--border-color);padding-top:16px">
<?php foreach($vdReplyList as $r): $isAdminR = !empty($r['admin_id']); ?>
<div style="margin-bottom:16px;padding:14px 16px;border-radius:10px;<?php echo $isAdminR ? 'background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(5,150,105,.08));border-left:3px solid #10b981' : 'background:var(--gray-50);border-left:3px solid #0ea5e9' ?>">
<div style="display:flex;justify-content:space-between;margin-bottom:6px">
<span style="font-weight:600;font-size:.9rem"><?php echo $isAdminR ? '<i class="fas fa-user-shield"></i> 管理员 ('.h($r['admin_username']).')' : '<i class="fas fa-user"></i> '.h($r['user_nickname'] ?: '用户'); ?></span>
<span style="font-size:.8rem;color:var(--gray-400)"><?php echo $r['created_at']; ?></span>
</div>
<div style="font-size:.9rem;line-height:1.7;word-break:break-all"><?php echo nl2br(h($r['content'])); ?></div>
</div>
<?php endforeach; ?>
</div>
<?php if ($vdStatus != 2): ?>
<div style="border-top:1px solid var(--border-color);padding-top:16px;margin-top:8px">
<form method="post">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="admin_reply_ticket">
<input type="hidden" name="ticket_id" value="<?php echo $vd['id']; ?>">
<div class="form-group">
<label class="form-label">管理员回复</label>
<textarea name="content" rows="4" required class="form-control" placeholder="输入回复内容..."></textarea>
</div>
<div style="display:flex;gap:10px">
<button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> 回复</button>
<button type="submit" name="action" value="close_ticket" class="btn btn-danger" onclick="return confirm('确定关闭此工单？')"><i class="fas fa-times-circle"></i> 回复并关闭</button>
</div>
</form>
</div>
<?php else: ?>
<div style="text-align:center;padding:16px;color:var(--gray-400);border-top:1px solid var(--border-color);margin-top:8px">
<i class="fas fa-lock" style="margin-right:6px"></i>此工单已关闭
<form method="post" style="display:inline;margin-left:12px" onsubmit="return confirm('确定删除此工单？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_ticket">
<input type="hidden" name="id" value="<?php echo $vd['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> 删除</button>
</form>
</div>
<?php endif; ?>
</div>
<?php else: ?>
<div class="card"><div class="card-header"><h3>工单不存在</h3></div><p style="padding:20px;color:var(--gray-400)"><a href="?page=tickets">返回列表</a></p></div>
<?php endif; ?>
<?php else: ?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-headset"></i> 工单管理</h3>
<div style="display:flex;gap:6px;flex-wrap:wrap">
<a href="?page=tickets&filter=all" class="btn btn-sm <?php echo $tkFilter==='all'?'btn-primary':'btn-outline'; ?>">全部</a>
<a href="?page=tickets&filter=pending" class="btn btn-sm <?php echo $tkFilter==='pending'?'btn-warning':'btn-outline'; ?>">待处理 <?php if($tkPending>0) echo "({$tkPending})"; ?></a>
<a href="?page=tickets&filter=replied" class="btn btn-sm <?php echo $tkFilter==='replied'?'btn-success':'btn-outline'; ?>">已回复</a>
<a href="?page=tickets&filter=closed" class="btn btn-sm <?php echo $tkFilter==='closed'?'btn-outline':'btn-outline'; ?>">已关闭</a>
</div>
</div>
<div style="padding:16px 20px;border-bottom:1px solid var(--border-color);background:var(--gray-50)">
<form method="post" class="form-row" style="align-items:flex-end;margin:0">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="save_config">
<div class="form-group" style="margin-bottom:0;flex:1;min-width:260px">
<label class="form-label"><i class="fas fa-envelope"></i> 管理员工单提醒邮箱</label>
<input type="email" name="admin_email" value="<?php echo h(conf('admin_email','')); ?>" class="form-control" placeholder="如：admin@example.com">
</div>
<div class="form-group" style="margin-bottom:0">
<label class="form-label">新工单邮件提醒</label>
<select name="admin_email_notify" class="form-control">
<option value="1" <?php echo conf('admin_email_notify','1')==='1'?'selected':''; ?>>开启</option>
<option value="0" <?php echo conf('admin_email_notify','1')!=='1'?'selected':''; ?>>关闭</option>
</select>
</div>
<div style="display:flex;align-items:flex-end">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
</div>
<div class="form-tip" style="width:100%;margin-top:8px;margin-bottom:0">有新工单提交时，系统会自动向该邮箱发送提醒邮件（需先配置邮件服务）</div>
</form>
</div>
<div class="table-wrapper">
<table>
<thead><tr><th>ID</th><th>标题</th><th>提交人</th><th>关联主机</th><th>状态</th><th>更新时间</th><th>操作</th></tr></thead>
<tbody>
<?php if(empty($tkList)): ?>
<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gray-400)"><i class="fas fa-headset" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>暂无工单</td></tr>
<?php else: foreach($tkList as $tk): ?>
<tr>
<td>#<?php echo $tk['id']; ?></td>
<td><?php echo h($tk['subject']); ?></td>
<td><?php echo h($tk['user_nickname'] ?: substr($tk['user_email'],0,3).'***'); ?></td>
<td><?php echo $tk['vhost_account'] ? h($tk['model_name'].' - '.$tk['vhost_account']) : '<span style="color:var(--gray-400)">-</span>'; ?></td>
<td><span class="badge <?php echo $tkStatusBadge[$tk['status']]; ?>"><?php echo $tkStatusMap[$tk['status']]; ?></span></td>
<td style="font-size:.85rem;color:var(--gray-400)"><?php echo date('Y-m-d H:i', strtotime($tk['updated_at'])); ?></td>
<td>
<a href="?page=tickets&view=<?php echo $tk['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> 查看</a>
<?php if($tk['status']!=2): ?>
<form method="post" style="display:inline" onsubmit="return confirm('确定关闭？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="close_ticket">
<input type="hidden" name="ticket_id" value="<?php echo $tk['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
</form>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('确定删除？')">
<?php echo csrfField(); ?>
<input type="hidden" name="action" value="del_ticket">
<input type="hidden" name="id" value="<?php echo $tk['id']; ?>">
<button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- 公告管理 -->
<?php if($page==='announcement'): ?>
<form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="save_announcement">

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

<!-- 关于项目 -->
<?php if($page==='about'):
$latestInfo = null;
$checking = isset($_GET['check_update']);
if ($checking) {
    unset($_SESSION['staridc_update_check']);
    $latestInfo = checkUpdate();
}
?>
<div class="card">
<div class="card-header">
<h3 class="card-title"><i class="fas fa-star"></i> 关于 StarIDC</h3>
</div>
<div style="text-align:center;padding:30px 20px">
<div style="font-size:3rem;color:var(--primary-solid);margin-bottom:16px"><i class="fas fa-cloud"></i></div>
<h2 style="margin:0 0 8px">StarIDC</h2>
<p style="color:var(--gray-500);margin:0 0 24px">轻量级虚拟主机分销管理平台</p>
<p style="font-size:1.1rem;color:var(--dark);margin:0 0 30px"><i class="fas fa-heart" style="color:#ef4444"></i> <strong>仰望星辰工作室出品</strong></p>

<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-bottom:30px">
<a href="?page=about&check_update=1" class="btn btn-primary"><i class="fas fa-sync-alt"></i> 检测更新</a>
<button type="button" class="btn btn-outline" onclick="document.getElementById('donateBox').style.display=document.getElementById('donateBox').style.display==='none'?'block':'none'"><i class="fas fa-heart"></i> 赞助我们</button>
</div>

<div id="donateBox" style="display:none;max-width:360px;margin:0 auto;text-align:center">
<p style="color:var(--gray-500);margin-bottom:12px">感谢您的支持，StarIDC 因您变得更好</p>
<img src="https://clearlove.kazx.top/%E6%8D%90%E8%B5%A0.webp" alt="赞助我们" style="max-width:100%;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1)">
</div>

<?php if($checking): ?>
<div class="alert <?php echo $latestInfo ? 'alert-warning' : 'alert-success'; ?>" style="margin-top:24px;text-align:left">
<?php if($latestInfo): ?>
<i class="fas fa-bell"></i> 发现新版本 <strong><?php echo h($latestInfo['version']); ?></strong>，当前版本 <strong><?php echo h(conf('current_version','1.4.0')); ?></strong>。
<?php if(!empty($latestInfo['release_note'])): ?><br><span style="opacity:.9"><?php echo nl2br(h($latestInfo['release_note'])); ?></span><?php endif; ?>
<br><a href="<?php echo h($latestInfo['download_url']); ?>" target="_blank" class="btn btn-primary" style="margin-top:12px;background:#f59e0b;border-color:#f59e0b"><i class="fas fa-download"></i> 立即下载</a>
<?php else: ?>
<i class="fas fa-check-circle"></i> 当前已是最新版本 <strong><?php echo h(conf('current_version','1.4.0')); ?></strong>。
<?php endif; ?>
</div>
<?php endif; ?>

<div style="margin-top:30px;padding-top:20px;border-top:1px solid var(--gray-200);color:var(--gray-500);font-size:.85rem">
<p style="margin:4px 0">当前版本：<?php echo h(conf('current_version','1.4.0')); ?></p>
<p style="margin:4px 0">更新接口：<?php echo h(conf('update_api_url','https://staridc.fangqihang.cn/api.php')); ?></p>
</div>
</div>
</div>
<?php endif; ?>

</main>
</div>
<script>
function toggleMenuGroup(el){var g=el.parentElement;g.classList.toggle('open')}
</script>
</body>
</html>
