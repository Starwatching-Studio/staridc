<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/MNBT_API.php';
include ROOT . 'rd/PayAPI.php';

requireLogin();
$user = getUser();
$error = '';
$success = '';
if (isset($_GET['msg'])) {
    $msgType = $_GET['msg'] ?? '';
    if ($msgType === 'ticket_created') $success = L('panel_tickets_created');
    elseif ($msgType === 'ticket_replied') $success = L('panel_tickets_replied');
    elseif ($msgType === 'ticket_closed') $success = L('panel_tickets_closed');
    elseif ($msgType === 'oauth_bound') $success = L('panel_oauth_bound');
    elseif ($msgType === 'oauth_register') $success = L('panel_oauth_register');
}
$currentTab = $_GET['tab'] ?? 'info';
$validTabs = ['info','points','hosts','tickets','referral','api'];
if (!in_array($currentTab, $validTabs)) $currentTab = 'info';

function getRechargePackages() {
    global $DB;
    try {
        $stmt = $DB->prepare("SELECT * FROM recharge_packages WHERE status=1 ORDER BY sort_order,id");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!empty($rows)) return $rows;
    } catch (Exception $e) {}
    return [
        ['id' => 1, 'points' => 200,  'price' => conf('points_200_price', '10')],
        ['id' => 2, 'points' => 400,  'price' => conf('points_400_price', '18')],
        ['id' => 3, 'points' => 1000, 'price' => conf('points_1000_price', '40')],
        ['id' => 4, 'points' => 3000, 'price' => conf('points_3000_price', '100')],
    ];
}

$durationMonthsMap = [
    'month' => 1, 'quarter' => 3, 'half_year' => 6,
    'year' => 12, '2year' => 24, '3year' => 36,
    '5year' => 60, '10year' => 120
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // AJAX: check renew coupon
    if ($action === 'check_renew_coupon') {
        header('Content-Type: application/json; charset=utf-8');
        $code = trim($_POST['code'] ?? '');
        $modelId = intval($_POST['model_id'] ?? 0);
        $subtotal = intval($_POST['subtotal'] ?? 0);
        $stmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
        $stmt->execute([$code]);
        $cp = $stmt->fetch();
        if (!$cp) {
            echo json_encode(['valid' => false, 'message' => L('coupon_invalid')]);
            exit;
        }
        if ($cp['model_id'] !== null && intval($cp['model_id']) !== $modelId) {
            echo json_encode(['valid' => false, 'message' => L('panel_renew_coupon_not_applicable')]);
            exit;
        }
        $finalPrice = intval(ceil($subtotal * (100 - $cp['discount']) / 100));
        echo json_encode([
            'valid' => true,
            'discount' => intval($cp['discount']),
            'final_price' => $finalPrice
        ]);
        exit;
    }

    // AJAX: get available durations for renew
    if ($action === 'get_renew_durations') {
        header('Content-Type: application/json; charset=utf-8');
        $modelId = intval($_POST['model_id'] ?? 0);
        $stmt = $DB->prepare("SELECT duration_type, discount FROM vhost_model_durations WHERE model_id=? AND enabled=1 ORDER BY FIELD(duration_type, 'month','quarter','half_year','year','2year','3year','5year','10year')");
        $stmt->execute([$modelId]);
        echo json_encode(['durations' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'sign') {
        $today = date('Y-m-d');
        if ($user['last_sign_date'] === $today) {
            $error = L('panel_sign_already');
        } else {
            $min = intval(conf('sign_min', 50));
            $max = intval(conf('sign_max', 100));
            $points = mt_rand($min, $max);
            $stmt = $DB->prepare("UPDATE users SET points=points+?, last_sign_date=? WHERE id=?");
            $stmt->execute([$points, $today, $user['id']]);
            $success = L('panel_signed_success') . ' ' . $points . ' ' . L('panel_signed_points');
            $user = getUser();
        }
    }

    if ($action === 'buy_points') {
        $pkgId = intval($_POST['package'] ?? 0);
        $packages = getRechargePackages();
        $selected = null;
        foreach ($packages as $p) {
            if ($p['id'] == $pkgId) { $selected = $p; break; }
        }
        if (!$selected) {
            $error = L('panel_recharge_invalid_package');
        } else {
            $payType = in_array(trim($_POST['pay_type'] ?? ''), ['alipay', 'wxpay']) ? trim($_POST['pay_type']) : 'alipay';
            $orderNo = genOrderNo();
            $stmt = $DB->prepare("INSERT INTO orders(order_no,user_id,type,amount,points,status) VALUES(?,?,'points',?,?,0)");
            $stmt->execute([$orderNo, $user['id'], $selected['price'], $selected['points']]);
            $notifyUrl = siteUrl() . 'pay_notify.php';
            $returnUrl = siteUrl() . 'pay_return.php';
            PayAPI::createPayment($orderNo, L('panel_points') . ' - ' . $selected['points'] . L('panel_points_unit'), $selected['price'], $payType, $notifyUrl, $returnUrl);
            exit;
        }
    }

    if ($action === 'renew') {
        $vhostId = intval($_POST['vhost_id'] ?? 0);
        $durationType = trim($_POST['duration_type'] ?? 'month');
        $couponCode = trim($_POST['coupon_code'] ?? '');
        if (!isset($durationMonthsMap[$durationType])) { $error = L('panel_renew_err_no_duration'); }
        else {
        $stmt = $DB->prepare("SELECT v.*,vm.price,vm.name as model_name,vm.is_elastic,vm.web_space as model_web_space,vm.db_space as model_db_space,vm.flow as model_flow,vm.domain_limit as model_domain_limit,COALESCE(v.web_space, vm.web_space) as web_space,COALESCE(v.db_space, vm.db_space) as db_space,COALESCE(v.flow, vm.flow) as flow,COALESCE(v.domain_limit, vm.domain_limit) as domain_limit FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.id=? AND v.user_id=?");
        $stmt->execute([$vhostId, $user['id']]);
        $vhost = $stmt->fetch();
        if (!$vhost) {
            $error = L('panel_hosts_not_exist');
        } else {
            $months = $durationMonthsMap[$durationType] ?? 1;
            $stmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id=? AND duration_type=? AND enabled=1");
            $stmt->execute([$vhost['model_id'], $durationType]);
            $durRow = $stmt->fetch();
            $durationDiscount = $durRow ? intval($durRow['discount']) : 0;
            $basePrice = intval(ceil($vhost['price'] * $months * (100 - $durationDiscount) / 100));
            $elasticSurcharge = 0;
            if ($vhost['is_elastic']) {
                $eStmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                $eStmt->execute([$vhost['model_id']]);
                $elasticConfigs = $eStmt->fetchAll();
                foreach ($elasticConfigs as $ec) {
                    $fn = $ec['field_name'];
                    $baseVal = intval($vhost['model_' . $fn] ?? 0);
                    $actualVal = intval($vhost[$fn] ?? $baseVal);
                    if ($actualVal > $baseVal && intval($ec['step']) > 0) {
                        $elasticSurcharge += intval(($actualVal - $baseVal) / intval($ec['step']) * intval($ec['unit_price']));
                    }
                }
            }
            $renewPrice = $basePrice + $elasticSurcharge;
            $couponDiscount = 0;
            $couponId = null;
            if (!empty($couponCode)) {
                $cpStmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
                $cpStmt->execute([$couponCode]);
                $cp = $cpStmt->fetch();
                if ($cp) {
                    if ($cp['model_id'] === null || intval($cp['model_id']) === intval($vhost['model_id'])) {
                        $couponDiscount = intval($cp['discount']);
                        $couponId = $cp['id'];
                        $renewPrice = intval(ceil($renewPrice * (100 - $couponDiscount) / 100));
                    }
                }
            }
            if ($user['points'] < $renewPrice) {
                $error = L('panel_renew_err_insufficient') . $renewPrice . L('panel_renew_err_insufficient_suffix') . $user['points'] . L('panel_points_unit');
            } else {
                $baseTime = strtotime($vhost['expire_time']) > time() ? $vhost['expire_time'] : date('Y-m-d H:i:s');
                $newExpire = date('Y-m-d', strtotime($baseTime . ' +' . $months . ' months'));
                $server = getServer($vhost['server_id']);
                $mnbtResult = MNBT_API::renewHost($vhost['account'], $newExpire, $server);
                if ($mnbtResult['success']) {
                    $stmt2 = $DB->prepare("UPDATE users SET points=points-? WHERE id=?");
                    $stmt2->execute([$renewPrice, $user['id']]);
                    $stmt3 = $DB->prepare("UPDATE vhosts SET expire_time=?,expire_warned=0 WHERE id=?");
                    $stmt3->execute([$newExpire, $vhostId]);
                    if ($couponId) {
                        $DB->prepare("UPDATE coupons SET used_count=used_count+1 WHERE id=?")->execute([$couponId]);
                    }
                    $success = L('panel_renew_success_msg') . $newExpire;
                    $user = getUser();
                } else {
                    $error = L('panel_renew_fail_mnbt') . $mnbtResult['message'];
                }
            }
        }
        }
    }

    if ($action === 'mnbt_login') {
        $vhostId = intval($_POST['vhost_id'] ?? 0);
        $stmt = $DB->prepare("SELECT account,password,mnbt_opened,server_id FROM vhosts WHERE id=? AND user_id=?");
        $stmt->execute([$vhostId, $user['id']]);
        $vhost = $stmt->fetch();
        if (!$vhost) {
            $error = L('panel_hosts_not_exist');
        } elseif (!$vhost['mnbt_opened']) {
            $error = L('panel_hosts_not_opened_error');
        } else {
            $server = getServer($vhost['server_id']);
            $apiUrl = $server ? $server['api_url'] : conf('mnbt_api_url', '');
            if (empty($apiUrl)) {
                $error = L('panel_hosts_mnbt_not_configured');
            } else {
                $parsed = parse_url(rtrim($apiUrl, '/'));
                $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                if (!empty($parsed['port'])) $baseUrl .= ':' . $parsed['port'];
                $loginUrl = $baseUrl . '/user/idcdl.php?GN=LOGINE';
                $mnVs = $server ? ($server['mn_vs'] ?? '16') : conf('mnbt_vs', '16');
                renderHeader(L('panel_hosts_mnbt_login_title'));
                echo '<form id="mnbt_login_form" method="post" action="' . h($loginUrl) . '">';
                echo '<input type="hidden" name="USERNAME" value="' . h($vhost['account']) . '">';
                echo '<input type="hidden" name="PASSWORD" value="' . h($vhost['password']) . '">';
                echo '<input type="hidden" name="MN_VS" value="' . h($mnVs) . '">';
                echo '</form>';
                echo '<p style="text-align:center;padding:40px;">' . L('panel_hosts_mnbt_login_text') . '</p>';
                echo '<script>document.getElementById("mnbt_login_form").submit();</script>';
                renderFooter();
                exit;
            }
        }
    }

    if ($action === 'update_nickname') {
        $nickname = trim($_POST['nickname'] ?? '');
        if (mb_strlen($nickname) > 20) $nickname = mb_substr($nickname, 0, 20);
        $stmt = $DB->prepare("UPDATE users SET nickname=? WHERE id=?");
        $stmt->execute([$nickname, $user['id']]);
        $success = L('panel_nickname_updated');
        $user = getUser();
    }

    if ($action === 'unbind_oauth') {
        $bindId = intval($_POST['bind_id'] ?? 0);
        $stmt = $DB->prepare("DELETE FROM oauth_bindings WHERE id=? AND user_id=?");
        $stmt->execute([$bindId, $user['id']]);
        $success = L('panel_oauth_updated');
    }

    // === Ticket operations ===
    if ($action === 'create_ticket') {
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $vhostId = !empty($_POST['vhost_id']) ? intval($_POST['vhost_id']) : null;
            if (empty($subject) || empty($content)) {
                $error = L('panel_tickets_err_empty');
            } elseif (mb_strlen($subject) > 200) {
                $error = L('panel_tickets_err_subject_long');
            } else {
                try {
                    $stmt = $DB->prepare("INSERT INTO tickets(user_id,vhost_id,subject) VALUES(?,?,?)");
                    $stmt->execute([$user['id'], $vhostId, $subject]);
                    $ticketId = $DB->lastInsertId();
                    $stmt2 = $DB->prepare("INSERT INTO ticket_replies(ticket_id,user_id,content) VALUES(?,?,?)");
                    $stmt2->execute([$ticketId, $user['id'], $content]);
                    // 邮件通知管理员
                    if (conf('admin_email_notify', '1') === '1') {
                        $adminEmail = conf('admin_email', '');
                        if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                            $siteName = conf('site_name', '云主机');
                            $mailBody = "有新的工单提交，请及时处理。\n\n";
                            $mailBody .= "工单编号：#{$ticketId}\n";
                            $mailBody .= "工单标题：{$subject}\n";
                            $mailBody .= "提交用户：" . ($user['nickname'] ?: $user['email']) . " ({$user['email']})\n";
                            $mailBody .= "提交时间：" . date('Y-m-d H:i:s') . "\n\n";
                            $mailBody .= "内容摘要：\n" . mb_substr($content, 0, 200) . "\n\n";
                            $mailBody .= "站点：{$siteName}";
                            Mailer::sendNotify($adminEmail, "[{$siteName}] 新工单 #{$ticketId} 待处理", $mailBody);
                        }
                    }
                    $success = L('panel_tickets_created');
                    $currentTab = 'tickets';
                } catch (Exception $e) {
                    $error = L('panel_tickets_err_create_fail') . $e->getMessage();
                }
            }
        }

    if ($action === 'reply_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
        $stmt->execute([$ticketId, $user['id']]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            $error = L('panel_tickets_err_not_exist');
        } elseif ($ticket['status'] == 2) {
            $error = L('panel_tickets_err_closed');
        } elseif (empty($content)) {
            $error = L('panel_tickets_err_need_content');
        } else {
            try {
                $stmt2 = $DB->prepare("INSERT INTO ticket_replies(ticket_id,user_id,content) VALUES(?,?,?)");
                $stmt2->execute([$ticketId, $user['id'], $content]);
                $stmt3 = $DB->prepare("UPDATE tickets SET status=0, updated_at=NOW() WHERE id=?");
                $stmt3->execute([$ticketId]);
                $success = L('panel_tickets_replied');
                $currentTab = 'tickets';
            } catch (Exception $e) {
                $error = L('panel_tickets_err_reply_fail') . $e->getMessage();
            }
        }
    }

    if ($action === 'close_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
        $stmt->execute([$ticketId, $user['id']]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            $error = L('panel_tickets_err_not_exist');
        } else {
            try {
                $stmt2 = $DB->prepare("UPDATE tickets SET status=2, updated_at=NOW() WHERE id=?");
                $stmt2->execute([$ticketId]);
                $success = L('panel_tickets_closed');
                $currentTab = 'tickets';
            } catch (Exception $e) {
                $error = L('panel_tickets_err_close_fail') . $e->getMessage();
            }
        }
    }

    // === API Key 管理 ===
    if ($action === 'create_api_key') {
        $keyName = trim($_POST['key_name'] ?? 'Default');
        if (empty($keyName)) $keyName = 'Default';
        try {
            $apiKey = 'sk-' . bin2hex(random_bytes(24));
            $stmt = $DB->prepare("INSERT INTO api_keys(user_id, api_key, name) VALUES(?,?,?)");
            $stmt->execute([$user['id'], $apiKey, $keyName]);
            $success = L('panel_api_created');
        } catch (Exception $e) {
            $error = L('panel_api_err') . ': ' . $e->getMessage();
        }
        $currentTab = 'api';
    }

    if ($action === 'delete_api_key') {
        $keyId = intval($_POST['key_id'] ?? 0);
        try {
            $stmt = $DB->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?");
            $stmt->execute([$keyId, $user['id']]);
            $success = L('panel_api_deleted');
        } catch (Exception $e) {
            $error = L('panel_api_err') . ': ' . $e->getMessage();
        }
        $currentTab = 'api';
    }
}

$vhosts = $DB->prepare("SELECT v.*,vm.name as model_name,vm.price,vm.is_elastic,vm.web_space as model_web_space,vm.db_space as model_db_space,vm.flow as model_flow,vm.domain_limit as model_domain_limit,COALESCE(v.web_space, vm.web_space) as web_space,COALESCE(v.db_space, vm.db_space) as db_space,COALESCE(v.flow, vm.flow) as flow,COALESCE(v.domain_limit, vm.domain_limit) as domain_limit FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.user_id=? ORDER BY v.id DESC");
$vhosts->execute([$user['id']]);
$vhostList = $vhosts->fetchAll();

$today = date('Y-m-d');
$canSign = $user['last_sign_date'] !== $today;
$signEnabled = intval(conf('sign_min', '50')) > 0;

$inviteCode = $user['invite_code'] ?? '';
if (empty($inviteCode)) {
    $inviteCode = 'INV' . strtoupper(substr(md5($user['email'] . $user['id']), 0, 6));
    $stmtCode = $DB->prepare("UPDATE users SET invite_code = ? WHERE id = ?");
    $stmtCode->execute([$inviteCode, $user['id']]);
}

$referralLogs = $DB->prepare("SELECT r.*, u.email as referred_email FROM referral_logs r LEFT JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? ORDER BY r.created_at DESC LIMIT 20");
$referralLogs->execute([$user['id']]);
$referralList = $referralLogs->fetchAll();

$apiKeyList = [];
try {
    $apiKeys = $DB->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY created_at DESC");
    $apiKeys->execute([$user['id']]);
    $apiKeyList = $apiKeys->fetchAll();
} catch (Exception $e) {
    $apiKeyList = [];
}

$referralEnabled = conf('referral_enabled', '1') === '1';
$referralReward = intval(conf('referral_reward_points', '30'));

renderHeader(L('panel_title'), '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">');
?>
<!-- ========== Panel HTML ========== -->
<div class="panel-grid">
    <div class="panel-sidebar">
        <div class="user-card">
            <div class="user-avatar"><?php echo mb_strtoupper(mb_substr($user['nickname'], 0, 1)); ?></div>
            <div class="user-name"><?php echo h($user['nickname']); ?></div>
            <div class="user-email"><?php echo h($user['email']); ?></div>
            <div class="user-points">💰 <?php echo $user['points']; ?> <?php echo L('points'); ?></div>
            <?php if ($canSign && $signEnabled): ?>
            <form method="post" class="quick-sign-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="sign">
                <button type="submit" class="quick-sign-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                    <?php echo L('panel_sign'); ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <nav class="panel-nav">
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="info" class="panel-tab-radio"<?php echo $currentTab==='info'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_info'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="points" class="panel-tab-radio"<?php echo $currentTab==='points'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_points'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="hosts" class="panel-tab-radio"<?php echo $currentTab==='hosts'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_hosts'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="tickets" class="panel-tab-radio"<?php echo $currentTab==='tickets'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_tickets'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="referral" class="panel-tab-radio"<?php echo $currentTab==='referral'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_referral'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="api" class="panel-tab-radio"<?php echo $currentTab==='api'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_api'); ?>
            </label>
        </nav>
    </div>

    <div class="panel-main">
        <?php if ($error): ?><div class="msg msg-error"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg msg-success"><?php echo h($success); ?></div><?php endif; ?>

        <div id="panel-info" class="panel-section">
            <div class="section-card">
                <h3><?php echo L('panel_info'); ?></h3>
                <form method="post">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update_nickname">
                    <div class="form-group">
                        <label><?php echo L('panel_nickname'); ?></label>
                        <input type="text" name="nickname" value="<?php echo h($user['nickname']); ?>" maxlength="20">
                    </div>
                    <button type="submit" class="btn-primary"><?php echo L('panel_nickname_save'); ?></button>
                </form>
                <div class="info-row"><span><?php echo L('panel_email'); ?></span><span><?php echo h($user['email']); ?></span></div>
                <div class="info-row"><span><?php echo L('panel_register_time'); ?></span><span><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span></div>
            </div>

            <?php
            if (conf('oauth_enabled') === '1') {
                $oauthNames = [
                    'qq'=>'QQ','wx'=>'微信','alipay'=>'支付宝','sina'=>'微博','baidu'=>'百度','douyin'=>'抖音',
                    'huawei'=>'华为','xiaomi'=>'小米','google'=>'谷歌','microsoft'=>'微软','dingtalk'=>'钉钉',
                    'feishu'=>'飞书','gitee'=>'Gitee','github'=>'GitHub'
                ];
                $oauthIcons = [
                    'qq'=>'fab fa-qq','wx'=>'fab fa-weixin','alipay'=>'fab fa-alipay','sina'=>'fab fa-weibo',
                    'baidu'=>'svg','douyin'=>'fab fa-tiktok','google'=>'fab fa-google',
                    'microsoft'=>'svg','dingtalk'=>'custom','feishu'=>'custom',
                    'gitee'=>'fab fa-git-alt','github'=>'fab fa-github','huawei'=>'svg','xiaomi'=>'svg',
                ];
                try {
                    $stmtOauth = $DB->prepare("SELECT * FROM oauth_bindings WHERE user_id=?");
                    $stmtOauth->execute([$user['id']]);
                    $bindings = $stmtOauth->fetchAll();
                } catch (Exception $e) { $bindings = []; }
                require_once ROOT . 'oauth.php';
            ?>
            <div class="section-card" style="margin-top:16px">
                <h3><i class="fas fa-link"></i> <?php echo L('panel_oauth_title'); ?></h3>
                <?php if (empty($bindings)): ?>
                <p style="color:#888;padding:12px 0"><?php echo L('panel_oauth_none'); ?></p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:12px;margin:12px 0">
                    <?php foreach ($bindings as $b):
                        $typeName = $oauthNames[$b['oauth_type']] ?? $b['oauth_type'];
                        $typeKey = $b['oauth_type'];
                        $typeInfo = $OAUTH_TYPES[$typeKey] ?? ['name'=>$typeName,'icon_type'=>'fa','icon'=>'fas fa-link','color'=>'#666'];
                    ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:#f8f9fa;border-radius:10px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <?php $bindBg = oauthNeedBackground($typeKey, $typeInfo) ? 'background:' . h($typeInfo['color']) . ';color:#fff;' : 'background:none;color:#333;'; ?>
                            <span style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;<?php echo $bindBg; ?>"><?php echo renderOauthIconByKey($typeKey, $typeInfo); ?></span>
                            <div>
                                <div style="font-weight:600"><?php echo h($typeName); ?></div>
                                <?php if (!empty($b['nickname'])): ?>
                                <div style="font-size:.85rem;color:#888"><?php echo h($b['nickname']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" style="margin:0" onsubmit="return confirm('<?php echo addslashes(L('panel_oauth_unbind_confirm')); ?><?php echo addslashes(h($typeName)); ?><?php echo addslashes(L('panel_oauth_unbind_confirm_suffix')); ?>')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="unbind_oauth">
                            <input type="hidden" name="bind_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" style="padding:6px 14px;border-radius:8px;border:1px solid #ddd;background:#fff;color:#e74c3c;cursor:pointer;font-size:.85rem"><?php echo L('panel_oauth_unbind'); ?></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <a href="login.php" style="display:inline-block;margin-top:8px;color:#6c5ce7;text-decoration:none;font-size:.85rem">← <?php echo L('panel_oauth_bind_link'); ?></a>
            </div>
            <?php } ?>
        </div>

        <div id="panel-points" class="panel-section">
            <div class="section-card">
                <h3><?php echo L('panel_sign'); ?></h3>
                <?php if ($canSign): ?>
                <form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="sign">
                    <p><?php echo L('panel_sign_points_range'); ?> <?php echo conf('sign_min','50'); ?><?php echo L('panel_sign_points_range_to'); ?><?php echo conf('sign_max','100'); ?> <?php echo L('panel_sign_points_random'); ?></p>
                    <button type="submit" class="btn-primary">🎯 <?php echo L('panel_sign_btn'); ?></button>
                </form>
                <?php else: ?>
                <p class="text-muted"><?php echo L('panel_sign_already'); ?></p>
                <?php endif; ?>
            </div>
            <div class="section-card">
                <h3><?php echo L('panel_points'); ?></h3>
                <div style="margin-bottom:16px">
                    <label style="font-size:0.9rem;color:#636e72;margin-right:12px"><?php echo L('panel_pay_method'); ?></label>
                    <label class="pay-type-label"><input type="radio" name="pay_type" value="alipay" checked> <?php echo L('panel_pay_alipay'); ?></label>
                    <label class="pay-type-label"><input type="radio" name="pay_type" value="wxpay"> <?php echo L('panel_pay_wxpay'); ?></label>
                </div>
                <div class="points-packages">
                    <?php
                    $pkgs = getRechargePackages();
                    foreach ($pkgs as $p):
                        $pid = isset($p['id']) ? $p['id'] : $p['points'];
                    ?>
                    <form method="post" class="pkg-card">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="buy_points">
                        <input type="hidden" name="package" value="<?php echo $pid; ?>">
                        <input type="hidden" name="pay_type" value="" id="paytype_<?php echo $pid; ?>">
                        <div class="pkg-points"><?php echo $p['points']; ?><?php echo L('panel_points_unit'); ?></div>
                        <div class="pkg-price">¥<?php echo $p['price']; ?></div>
                        <button type="submit" class="btn-primary btn-sm"><?php echo L('panel_points_buy'); ?></button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="panel-hosts" class="panel-section">
            <div class="section-card">
                <div class="section-header">
                    <h3><?php echo L('panel_hosts_title'); ?></h3>
                    <a href="cart.php" class="btn-primary btn-sm">+ <?php echo L('panel_hosts_buy'); ?></a>
                </div>
                <?php if (empty($vhostList)): ?>
                <div class="empty-state"><?php echo L('panel_hosts_empty'); ?><a href="cart.php"><?php echo L('panel_hosts_empty_link'); ?></a></div>
                <?php else: ?>
                <div class="vhost-list">
                    <?php foreach ($vhostList as $v):
                        $daysLeft = $v['expire_time'] ? max(0, floor((strtotime($v['expire_time']) - time()) / 86400)) : 999;
                        $daysClass = $daysLeft <= 7 ? 'danger' : ($daysLeft <= 15 ? 'warning' : 'normal');
                        $renewPrice = intval($v['price']);
                        if ($v['is_elastic']) {
                            $eStmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id=? AND enabled=1");
                            $eStmt->execute([$v['model_id']]);
                            $elasticConfigs = $eStmt->fetchAll();
                            foreach ($elasticConfigs as $ec) {
                                $fn = $ec['field_name'];
                                $baseVal = intval($v['model_' . $fn] ?? 0);
                                $actualVal = intval($v[$fn] ?? $baseVal);
                                if ($actualVal > $baseVal && intval($ec['step']) > 0) {
                                    $renewPrice += intval(($actualVal - $baseVal) / intval($ec['step']) * intval($ec['unit_price']));
                                }
                            }
                        }
                        $loginUrl = '';
                        if ($v['mnbt_opened']) {
                            $server = getServer($v['server_id']);
                            $apiUrl = $server ? $server['api_url'] : conf('mnbt_api_url', '');
                            if (!empty($apiUrl)) {
                                $parsed = parse_url(rtrim($apiUrl, '/'));
                                $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                                if (!empty($parsed['port'])) $baseUrl .= ':' . $parsed['port'];
                                $loginUrl = $baseUrl . '/user/idcdl.php?gn=logine&username=' . urlencode($v['account']) . '&password=' . urlencode($v['password']);
                            }
                        }
                    ?>
                    <div class="vhost-card">
                        <div class="vhost-header">
                            <span class="vhost-name"><?php echo h($v['model_name']); ?></span>
                            <span class="vhost-days <?php echo $daysClass; ?>"><?php echo $v['expire_time'] ? $daysLeft . L('panel_hosts_days') : L('panel_hosts_forever'); ?></span>
                        </div>
                        <div class="vhost-info">
                            <div class="info-row"><span><?php echo L('panel_account'); ?></span><span class="copyable" onclick="copyText(this)"><?php echo h($v['account']); ?></span><button class="copy-btn" onclick="event.stopPropagation();copyText(this.previousElementSibling)" title="<?php echo L('panel_copy'); ?>"><?php echo file_get_contents(ROOT.'theme/copy.svg'); ?></button></div>
                            <div class="info-row"><span><?php echo L('panel_password'); ?></span><span class="copyable" onclick="copyText(this)"><?php echo h($v['password']); ?></span><button class="copy-btn" onclick="event.stopPropagation();copyText(this.previousElementSibling)" title="<?php echo L('panel_copy'); ?>"><?php echo file_get_contents(ROOT.'theme/copy.svg'); ?></button></div>
                            <div class="info-row"><span><?php echo L('panel_hosts_space'); ?></span><span><?php echo $v['web_space']>=1024?round($v['web_space']/1024,1).L('panel_hosts_gb'):$v['web_space'].L('panel_hosts_mb'); ?> / <?php echo $v['db_space']>=1024?round($v['db_space']/1024,1).L('panel_hosts_gb'):$v['db_space'].L('panel_hosts_mb'); ?></span></div>
                            <div class="info-row"><span><?php echo L('panel_hosts_flow'); ?></span><span><?php echo $v['flow']; ?>GB</span></div>
                            <div class="info-row"><span><?php echo L('panel_hosts_domains'); ?></span><span><?php echo $v['domain_limit']; ?><?php echo L('panel_hosts_per_unit'); ?></span></div>
                            <div class="info-row"><span><?php echo L('panel_hosts_expire'); ?></span><span><?php echo $v['expire_time']?date('Y-m-d',strtotime($v['expire_time'])):L('panel_hosts_forever'); ?></span></div>
                            <div class="info-row"><span><?php echo L('panel_hosts_mnbt'); ?></span><span class="badge <?php echo $v['mnbt_opened']?'badge-green':'badge-red'; ?>"><?php echo $v['mnbt_opened']?L('panel_hosts_opened'):L('panel_hosts_not_opened'); ?></span></div>
                        </div>
                        <div class="vhost-actions">
                        <button type="button" class="btn-primary btn-sm" onclick="openRenewModal(<?php echo $v['id']; ?>,<?php echo $v['model_id']; ?>,<?php echo intval($v['price']); ?>,<?php echo $v['is_elastic'] ? ($renewPrice - intval($v['price'])) : 0; ?>)"><?php echo L('panel_renew'); ?></button>
                        <?php if ($v['mnbt_opened'] && !empty($loginUrl)): ?>
                        <a href="<?php echo h($loginUrl); ?>" target="_blank" class="btn-primary btn-sm" style="background:linear-gradient(135deg,#6c5ce7,#a29bfe);text-decoration:none;display:inline-block;text-align:center"><?php echo L('panel_login_mnbt'); ?></a>
                        <?php endif; ?>
                        </div>
                    </div>
                    <!-- Renew modal -->
                    <div class="renew-overlay" id="renewOverlay_<?php echo $v['id']; ?>" style="display:none" onclick="if(event.target===this)closeRenewModal(<?php echo $v['id']; ?>)"></div>
                    <div class="renew-modal" id="renewModal_<?php echo $v['id']; ?>" style="display:none">
                        <div class="renew-modal-content">
                            <div class="renew-modal-header">
                                <h3><?php echo L('panel_renew_modal_title'); ?> - <?php echo h($v['model_name']); ?></h3>
                                <button type="button" class="modal-close" onclick="closeRenewModal(<?php echo $v['id']; ?>)">&times;</button>
                            </div>
                            <div class="renew-modal-body">
                                <div class="duration-section">
                                    <label class="section-label">📅 <?php echo L('buy_select_duration'); ?></label>
                                    <div class="duration-btns" id="renewDurations_<?php echo $v['id']; ?>"><?php echo L('loading'); ?></div>
                                </div>
                                <div class="coupon-section">
                                    <div class="coupon-toggle" onclick="toggleRenewCoupon(<?php echo $v['id']; ?>)">
                                        🎫 <?php echo L('buy_coupon'); ?>
                                        <span id="renewCouponArrow_<?php echo $v['id']; ?>" style="margin-left:auto;transition:transform .2s;font-size:.8rem">▼</span>
                                    </div>
                                    <div id="renewCouponArea_<?php echo $v['id']; ?>" class="coupon-area" style="display:none">
                                        <div class="coupon-row">
                                            <input type="text" id="renewCoupon_<?php echo $v['id']; ?>" placeholder="<?php echo L('buy_coupon_placeholder'); ?>" maxlength="32" autocomplete="off">
                                            <button type="button" class="btn-coupon" onclick="applyRenewCoupon(<?php echo $v['id']; ?>)"><?php echo L('buy_coupon_check'); ?></button>
                                        </div>
                                        <div id="renewCouponMsg_<?php echo $v['id']; ?>" class="coupon-msg"></div>
                                        <div id="renewCouponInfo_<?php echo $v['id']; ?>" class="coupon-discount-info" style="display:none">
                                            ✅ <?php echo L('buy_coupon_discount'); ?>：<strong id="renewDiscountPercent_<?php echo $v['id']; ?>">0</strong>%
                                            &nbsp;|&nbsp; <?php echo L('buy_final_price'); ?> <strong id="renewDiscountedPrice_<?php echo $v['id']; ?>" style="color:#e74c3c">0</strong> <?php echo L('buy_points'); ?>
                                            <button type="button" class="btn-remove-coupon" onclick="removeRenewCoupon(<?php echo $v['id']; ?>)">✕ <?php echo L('cancel'); ?></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="price-summary" id="renewPriceSummary_<?php echo $v['id']; ?>">
                                    <div class="price-row"><span><?php echo L('buy_base_price'); ?></span><span id="renewDurationPrice_<?php echo $v['id']; ?>">0</span></div>
                                    <div class="price-row" id="renewElasticRow_<?php echo $v['id']; ?>" style="display:none"><span><?php echo L('buy_elastic_price'); ?></span><span id="renewElasticSurcharge_<?php echo $v['id']; ?>">0</span></div>
                                    <div class="price-row" id="renewCouponRow_<?php echo $v['id']; ?>" style="display:none"><span><?php echo L('buy_coupon_discount'); ?></span><span id="renewCouponDiscount_<?php echo $v['id']; ?>" style="color:#10b981">0</span></div>
                                    <div class="price-row price-total"><span><?php echo L('buy_final_price'); ?></span><span id="renewTotal_<?php echo $v['id']; ?>">0</span></div>
                                </div>
                                <div class="user-balance" id="renewBalance_<?php echo $v['id']; ?>">
                                    <?php echo L('points'); ?>：<strong><?php echo $user['points']; ?></strong>
                                    <span id="renewBalanceAfter_<?php echo $v['id']; ?>" style="display:none;margin-left:8px">
                                        → <?php echo L('buy_final_price'); ?>：<strong id="renewRemaining_<?php echo $v['id']; ?>" style="color:#10b981">0</strong>
                                    </span>
                                </div>
                            </div>
                            <div class="renew-modal-footer">
                                <button type="button" class="btn-cancel" onclick="closeRenewModal(<?php echo $v['id']; ?>)"><?php echo L('cancel'); ?></button>
                                <button type="button" class="btn-confirm" id="renewSubmit_<?php echo $v['id']; ?>" onclick="confirmRenew(<?php echo $v['id']; ?>)"><?php echo L('panel_renew_confirm'); ?></button>
                            </div>
                        </div>
                    </div>
                    <form id="renewForm_<?php echo $v['id']; ?>" method="post" style="display:none">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="renew">
                        <input type="hidden" name="vhost_id" value="<?php echo $v['id']; ?>">
                        <input type="hidden" name="duration_type" id="renewDurationType_<?php echo $v['id']; ?>" value="">
                        <input type="hidden" name="coupon_code" id="renewCouponCode_<?php echo $v['id']; ?>" value="">
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="panel-tickets" class="panel-section">
            <div class="section-card">
                <div class="section-header">
                    <h3><?php echo L('panel_tickets_title'); ?></h3>
                    <button class="btn-primary btn-sm" onclick="document.getElementById('ticket-form-new').style.display='block';this.style.display='none'">+ <?php echo L('panel_tickets_create'); ?></button>
                </div>
                <div id="ticket-form-new" style="display:none;margin-bottom:20px;padding:20px;border-radius:12px;background:var(--bg-card,#f8f9fa)">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="create_ticket">
                        <div class="form-group">
                            <label><?php echo L('panel_tickets_subject'); ?></label>
                            <input type="text" name="subject" maxlength="200" required placeholder="<?php echo L('panel_tickets_subject_placeholder'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php echo L('panel_tickets_vhost'); ?> <span style="color:var(--gray-500,#999);font-weight:normal">(<?php echo L('optional'); ?>)</span></label>
                            <select name="vhost_id" class="form-control">
                                <option value=""><?php echo L('panel_tickets_vhost_none'); ?></option>
                                <?php foreach($vhostList as $vh): ?>
                                <option value="<?php echo $vh['id']; ?>"><?php echo h($vh['model_name'].' - '.$vh['account']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo L('panel_tickets_content'); ?></label>
                            <textarea name="content" rows="4" required placeholder="<?php echo L('panel_tickets_content_placeholder'); ?>"></textarea>
                        </div>
                        <div style="display:flex;gap:10px">
                            <button type="submit" class="btn-primary btn-sm"><?php echo L('panel_tickets_submit'); ?></button>
                            <button type="button" class="btn-sm" style="background:var(--gray-200,#e5e7eb);color:#666;border:none;padding:6px 16px;border-radius:8px;cursor:pointer" onclick="document.getElementById('ticket-form-new').style.display='none';this.parentElement.previousElementSibling.previousElementSibling.parentElement.parentElement.querySelector('.section-header .btn-primary').style.display=''"><?php echo L('panel_tickets_cancel'); ?></button>
                        </div>
                    </form>
                </div>
                <?php
                $tkList = $DB->prepare("SELECT t.*, vm.name as model_name, vh.account as vhost_account FROM tickets t LEFT JOIN vhosts vh ON t.vhost_id=vh.id LEFT JOIN vhost_models vm ON vh.model_id=vm.id WHERE t.user_id=? ORDER BY t.updated_at DESC");
                $tkList->execute([$user['id']]);
                $tickets = $tkList->fetchAll();
                $statusMap = [0=>L('panel_tickets_status_pending'),1=>L('panel_tickets_status_replied'),2=>L('panel_tickets_status_closed')];
                $statusColor = [0=>'#f59e0b',1=>'#10b981',2=>'#9ca3af'];
                if (empty($tickets)):
                ?>
                <div class="empty-state"><i class="fas fa-ticket-alt" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i><p><?php echo L('panel_tickets_empty'); ?></p></div>
                <?php else: ?>
                <div class="hp-ticket-list">
                    <?php foreach($tickets as $tk): ?>
                    <div class="hp-ticket-item" onclick="toggleTicketDetail(<?php echo $tk['id']; ?>)">
                        <div class="hp-ticket-row">
                            <span class="hp-ticket-id">#<?php echo $tk['id']; ?></span>
                            <span class="hp-ticket-subject"><?php echo h($tk['subject']); ?></span>
                            <span class="hp-ticket-status" style="color:<?php echo $statusColor[$tk['status']]; ?>"><?php echo $statusMap[$tk['status']]; ?></span>
                            <span class="hp-ticket-time"><?php echo date('m-d H:i', strtotime($tk['updated_at'])); ?></span>
                        </div>
                        <?php if ($tk['vhost_account']): ?>
                        <div style="font-size:.8rem;color:#999;margin-top:4px"><?php echo L('panel_tickets_vhost'); ?>：<?php echo h($tk['model_name'].' - '.$tk['vhost_account']); ?></div>
                        <?php endif; ?>
                        <div id="ticket-detail-<?php echo $tk['id']; ?>" class="hp-ticket-detail" style="display:none">
                            <?php
                            $replies = $DB->prepare("SELECT tr.*, u.email as user_email FROM ticket_replies tr LEFT JOIN users u ON tr.user_id=u.id WHERE tr.ticket_id=? ORDER BY tr.created_at ASC");
                            $replies->execute([$tk['id']]);
                            $replyList = $replies->fetchAll();
                            foreach($replyList as $r):
                                $isAdminReply = !empty($r['admin_id']);
                            ?>
                            <div class="hp-reply-item <?php echo $isAdminReply ? 'hp-reply-admin' : 'hp-reply-user'; ?>">
                                <div class="hp-reply-header">
                                    <span class="hp-reply-author"><?php echo $isAdminReply ? L('panel_tickets_admin') : h($user['nickname'] ?: substr($user['email'],0,3).'***'); ?></span>
                                    <span class="hp-reply-time"><?php echo date('Y-m-d H:i', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div class="hp-reply-content"><?php echo nl2br(h($r['content'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($tk['status'] != 2): ?>
                            <div class="hp-reply-form" onclick="event.stopPropagation()">
                                <form method="post">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $tk['id']; ?>">
                                    <textarea name="content" rows="3" required placeholder="<?php echo L('panel_tickets_reply_placeholder'); ?>" onclick="event.stopPropagation()"></textarea>
                                    <div style="display:flex;gap:8px;margin-top:8px">
                                        <button type="submit" class="btn-primary btn-sm"><?php echo L('panel_tickets_reply_btn'); ?></button>
                                        <button type="submit" name="action" value="close_ticket" class="btn-sm" style="background:#ef4444;color:#fff;border:none;padding:6px 16px;border-radius:8px;cursor:pointer" onclick="return confirm('<?php echo addslashes(L('panel_tickets_close_confirm')); ?>')"><?php echo L('panel_tickets_close_btn'); ?></button>
                                    </div>
                                </form>
                            </div>
                            <?php else: ?>
                            <div style="text-align:center;padding:12px;color:#999;font-size:.85rem"><?php echo L('panel_tickets_closed_text'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="panel-referral" class="panel-section">
            <?php if (!$referralEnabled): ?>
            <div class="section-card">
                <div class="empty-state">
                    <i class="fas fa-users-slash" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i>
                    <p><?php echo L('panel_referral_disabled'); ?></p>
                </div>
            </div>
            <?php else: ?>
            <div class="section-card">
                <h3><i class="fas fa-gift"></i> <?php echo L('panel_referral_title'); ?></h3>
                <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:24px;border-radius:16px;text-align:center;margin:16px 0">
                    <p style="font-size:.9rem;opacity:0.9;margin-bottom:8px"><?php echo L('panel_referral_desc'); ?> <?php echo $referralReward; ?> <?php echo L('panel_referral_step3_suffix'); ?></p>
                    <div style="font-size:2rem;font-weight:700;letter-spacing:4px;margin:16px 0" id="myInviteCode"><?php echo h($inviteCode); ?></div>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                        <button onclick="copyInviteCode()" class="btn-primary" style="background:rgba(255,255,255,0.2);border:none"><i class="fas fa-copy"></i> <?php echo L('panel_referral_copy'); ?></button>
                        <button onclick="shareToFriend()" class="btn-primary" style="background:rgba(255,255,255,0.2);border:none"><i class="fas fa-share"></i> <?php echo L('panel_referral_share'); ?></button>
                    </div>
                </div>
                <div style="background:#f8f9fa;padding:16px;border-radius:12px;margin-top:16px">
                    <p style="font-size:.85rem;color:#666;line-height:1.8">
                        <strong><?php echo L('panel_referral_howto'); ?></strong><br>
                        <?php echo L('panel_referral_step1'); ?><br>
                        <?php echo L('panel_referral_step2'); ?><br>
                        <?php echo L('panel_referral_step3'); ?> <strong style="color:#667eea"><?php echo $referralReward; ?> <?php echo L('panel_referral_step3_suffix'); ?></strong>
                    </p>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> <?php echo L('panel_referral_history'); ?></h3>
                    <span class="badge"><?php echo L('panel_referral_count_prefix'); ?> <?php echo count($referralList); ?> <?php echo L('panel_referral_count_suffix'); ?></span>
                </div>
                <?php if (empty($referralList)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i>
                    <p><?php echo L('panel_referral_empty'); ?></p>
                    <p style="font-size:.85rem;color:#999;margin-top:8px"><?php echo L('panel_referral_empty_hint'); ?></p>
                </div>
                <?php else: ?>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid #eee">
                            <th style="text-align:left;padding:12px 8px;color:#666;font-size:.85rem"><?php echo L('panel_referral_th_email'); ?></th>
                            <th style="text-align:center;padding:12px 8px;color:#666;font-size:.85rem"><?php echo L('panel_referral_th_points'); ?></th>
                            <th style="text-align:right;padding:12px 8px;color:#666;font-size:.85rem"><?php echo L('panel_referral_th_time'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referralList as $log): ?>
                        <tr style="border-bottom:1px solid #f5f5f5">
                            <td style="padding:12px 8px"><?php echo h(substr($log['referred_email'], 0, 3) . '***' . strstr($log['referred_email'], '@')); ?></td>
                            <td style="text-align:center;padding:12px 8px"><span class="badge badge-success">+<?php echo $log['reward_points']; ?></span></td>
                            <td style="text-align:right;padding:12px 8px;color:#999;font-size:.85rem"><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="panel-api" class="panel-section">
            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-key"></i> <?php echo L('panel_api'); ?></h3>
                </div>
                <p style="color:var(--muted);font-size:.9rem;margin-bottom:16px"><?php echo L('panel_api_desc'); ?></p>

                <form method="post" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create_api_key">
                    <input type="text" name="key_name" placeholder="<?php echo L('panel_api_name_placeholder'); ?>" maxlength="50" style="flex:1;min-width:180px;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;outline:none">
                    <button type="submit" class="btn-primary btn-sm" style="white-space:nowrap"><?php echo L('panel_api_create'); ?></button>
                </form>

                <?php if (empty($apiKeyList)): ?>
                <div class="empty-state">
                    <i class="fas fa-key" style="font-size:3rem;opacity:0.3;margin-bottom:12px;display:block"></i>
                    <p><?php echo L('panel_api_empty'); ?></p>
                </div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px">
                    <?php foreach ($apiKeyList as $k): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--bg-card,#f8f9fa);border-radius:10px;gap:12px;flex-wrap:wrap">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;font-size:.92rem;margin-bottom:4px"><?php echo h($k['name']); ?></div>
                            <div style="font-family:monospace;font-size:.82rem;color:var(--muted);word-break:break-all;background:#e8ecf1;padding:4px 10px;border-radius:6px;display:inline-block;max-width:100%"><?php echo h($k['api_key']); ?></div>
                            <div style="font-size:.75rem;color:#999;margin-top:4px">
                                <?php echo L('created_at'); ?>: <?php echo date('Y-m-d H:i', strtotime($k['created_at'])); ?>
                                <?php if ($k['last_used_at']): ?> | <?php echo L('panel_api_last_used'); ?>: <?php echo date('Y-m-d H:i', strtotime($k['last_used_at'])); ?><?php endif; ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0">
                            <button type="button" onclick="copyApiKey('<?php echo addslashes($k['api_key']); ?>', this)" class="btn-sm" style="background:var(--primary,#6366f1);color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.82rem"><?php echo L('panel_copy'); ?></button>
                            <form method="post" onsubmit="return confirm('<?php echo addslashes(L('panel_api_delete_confirm')); ?>')" style="margin:0">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_api_key">
                                <input type="hidden" name="key_id" value="<?php echo $k['id']; ?>">
                                <button type="submit" class="btn-sm" style="background:#ef4444;color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.82rem"><?php echo L('delete'); ?></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- 在线调试 -->
            <div class="section-card" style="margin-top:16px">
                <h3 style="margin-bottom:16px"><i class="fas fa-terminal"></i> <?php echo L('panel_api_debug'); ?></h3>
                <div class="form-group">
                    <label><?php echo L('panel_api_key'); ?></label>
                    <div style="display:flex;gap:8px">
                        <input type="text" id="debug-apikey" placeholder="sk-xxxxxxxxxxxx" style="flex:1;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;font-family:monospace;outline:none" onfocus="this.style.borderColor='var(--primary,#6366f1)'" onblur="this.style.borderColor='var(--border-color,#dee2ed)'">
                        <button class="btn-sm" style="background:var(--bg-card,#f1f3f5);color:var(--muted);border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:.82rem;white-space:nowrap;flex-shrink:0" onclick="saveDebugApiKey()"><?php echo L('panel_api_save_key'); ?></button>
                    </div>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <div class="form-group" style="flex:1;min-width:120px">
                        <label><?php echo L('panel_api_method'); ?></label>
                        <select id="debug-method" style="width:100%;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;outline:none;background:#fff"><option value="GET">GET</option><option value="POST">POST</option></select>
                    </div>
                    <div class="form-group" style="flex:1;min-width:180px">
                        <label><?php echo L('panel_api_endpoint'); ?></label>
                        <select id="debug-action" onchange="onDebugActionChange()" style="width:100%;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;outline:none;background:#fff">
                            <option value="user_info">user_info - <?php echo L('panel_api_ep_user'); ?></option>
                            <option value="points">points - <?php echo L('panel_api_ep_points'); ?></option>
                            <option value="sign">sign - <?php echo L('panel_api_ep_sign'); ?></option>
                            <option value="model_list">model_list - <?php echo L('panel_api_ep_models'); ?></option>
                            <option value="host_list">host_list - <?php echo L('panel_api_ep_hosts'); ?></option>
                            <option value="host_detail">host_detail - <?php echo L('panel_api_ep_host_detail'); ?></option>
                            <option value="host_buy">host_buy - <?php echo L('panel_api_ep_buy'); ?></option>
                            <option value="host_renew">host_renew - <?php echo L('panel_api_ep_renew'); ?></option>
                            <option value="ticket_list">ticket_list - <?php echo L('panel_api_ep_tickets'); ?></option>
                            <option value="ticket_detail">ticket_detail - <?php echo L('panel_api_ep_ticket_detail'); ?></option>
                            <option value="ticket_create">ticket_create - <?php echo L('panel_api_ep_create_ticket'); ?></option>
                            <option value="ticket_reply">ticket_reply - <?php echo L('panel_api_ep_reply'); ?></option>
                            <option value="ticket_close">ticket_close - <?php echo L('panel_api_ep_close'); ?></option>
                            <option value="referral_info">referral_info - <?php echo L('panel_api_ep_referral'); ?></option>
                        </select>
                    </div>
                </div>
                <div id="debug-params"></div>
                <button class="btn-primary btn-sm" onclick="sendDebugRequest()" id="debug-send" style="margin-top:8px"><?php echo L('panel_api_send'); ?></button>
                <div id="debug-response" style="display:none;margin-top:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span id="debug-status" style="font-size:.85rem;font-weight:600"></span>
                        <span id="debug-time" style="font-size:.78rem;color:var(--muted)"></span>
                    </div>
                    <pre id="debug-result" style="background:var(--code,#f1f5f9);border-radius:8px;padding:16px;overflow-x:auto;font-size:.85rem;line-height:1.5;font-family:monospace;white-space:pre-wrap;word-break:break-all;max-height:400px;overflow-y:auto"></pre>
                </div>
            </div>

            <!-- 文档 -->
            <div class="section-card" style="margin-top:16px">
                <h3 style="margin-bottom:16px"><i class="fas fa-book"></i> <?php echo L('panel_api_docs_title'); ?></h3>
                <p style="color:var(--muted);font-size:.9rem;margin-bottom:16px"><?php echo L('panel_api_docs_desc'); ?></p>
                <div style="background:var(--code,#f1f5f9);border-radius:8px;padding:16px;font-size:.85rem;line-height:1.8;font-family:monospace;margin-bottom:12px">
                    <strong><?php echo L('panel_api_auth'); ?></strong><br>
                    Header: X-API-Key: sk-xxxxxxxxxxxx<br>
                    Query: ?api_key=sk-xxxxxxxxxxxx
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px">
                    <?php
                    $apiEndpoints = [
                        ['GET', 'user_info', 'panel_api_ep_user'],
                        ['GET', 'points', 'panel_api_ep_points'],
                        ['POST', 'sign', 'panel_api_ep_sign'],
                        ['GET', 'model_list', 'panel_api_ep_models'],
                        ['GET', 'host_list', 'panel_api_ep_hosts'],
                        ['GET', 'host_detail', 'panel_api_ep_host_detail'],
                        ['POST', 'host_buy', 'panel_api_ep_buy'],
                        ['POST', 'host_renew', 'panel_api_ep_renew'],
                        ['GET', 'ticket_list', 'panel_api_ep_tickets'],
                        ['GET', 'ticket_detail', 'panel_api_ep_ticket_detail'],
                        ['POST', 'ticket_create', 'panel_api_ep_create_ticket'],
                        ['POST', 'ticket_reply', 'panel_api_ep_reply'],
                        ['POST', 'ticket_close', 'panel_api_ep_close'],
                        ['GET', 'referral_info', 'panel_api_ep_referral'],
                    ];
                    foreach ($apiEndpoints as $ep):
                        $methodColor = $ep[0] === 'GET' ? '#10b981' : '#6366f1';
                    ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg-card,#f8f9fa);border-radius:8px;font-size:.82rem;cursor:pointer" onclick="tryDebugEndpoint('<?php echo $ep[1]; ?>','<?php echo $ep[0]; ?>')" title="<?php echo L('panel_api_try'); ?>">
                        <span style="font-weight:700;color:<?php echo $methodColor; ?>;min-width:36px;font-size:.75rem"><?php echo $ep[0]; ?></span>
                        <span style="font-family:monospace;font-size:.8rem"><?php echo $ep[1]; ?></span>
                        <span style="color:var(--muted);margin-left:auto;font-size:.78rem;white-space:nowrap"><?php echo L($ep[2]); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchPanel(name) {
    document.querySelectorAll('.panel-section').forEach(function(s){ s.style.display = 'none'; });
    document.querySelectorAll('.panel-nav-item').forEach(function(n){ n.classList.remove('active'); });
    var section = document.getElementById('panel-' + name);
    if (section) section.style.display = 'block';
    var radio = document.querySelector('.panel-tab-radio[value="' + name + '"]');
    if (radio) radio.closest('.panel-nav-item').classList.add('active');
}
(function(){
    var checked = document.querySelector('.panel-tab-radio:checked');
    if (checked) switchPanel(checked.value);
})();

// ========== Renew Modal ==========
var _L = Object.assign(_L || {}, {
    month1: <?php echo json_encode(L('buy_1month')); ?>,
    month3: <?php echo json_encode(L('buy_3months')); ?>,
    month6: <?php echo json_encode(L('buy_6months')); ?>,
    year1: <?php echo json_encode(L('buy_1year')); ?>,
    year2: <?php echo json_encode(L('buy_2year')); ?>,
    year3: <?php echo json_encode(L('buy_3year')); ?>,
    year5: <?php echo json_encode(L('buy_5year')); ?>,
    year10: <?php echo json_encode(L('buy_10year')); ?>,
    noDurations: <?php echo json_encode(L('panel_no_durations')); ?>,
    discount: <?php echo json_encode(L('buy_discount')); ?>,
    points: <?php echo json_encode(L('points')); ?>,
    needSelectDuration: <?php echo json_encode(L('panel_renew_need_select_duration')); ?>,
    confirmDialog: <?php echo json_encode(L('panel_renew_confirm_dialog')); ?>,
    checking: <?php echo json_encode(L('panel_renew_checking')); ?>,
    copied: <?php echo json_encode(L('panel_referral_copied')); ?>,
    linkCopied: <?php echo json_encode(L('panel_referral_link_copied')); ?>,
    linkPrompt: <?php echo json_encode(L('panel_referral_link_prompt')); ?>,
    shareText: <?php echo json_encode(L('panel_referral_share_text')); ?>,
    shareTextSuffix: <?php echo json_encode(L('panel_referral_share_text_suffix')); ?>,
    couponNeedDuration: <?php echo json_encode(L('panel_renew_coupon_need_duration')); ?>
});
var durationLabels = {
    'month': _L.month1, 'quarter': _L.month3, 'half_year': _L.month6,
    'year': _L.year1, '2year': _L.year2, '3year': _L.year3,
    '5year': _L.year5, '10year': _L.year10
};
var durationMonths = {
    'month': 1, 'quarter': 3, 'half_year': 6,
    'year': 12, '2year': 24, '3year': 36,
    '5year': 60, '10year': 120
};
var renewData = {};

function openRenewModal(vhostId, modelId, price, elasticSurcharge) {
    if (!renewData[vhostId]) {
        renewData[vhostId] = { modelId: modelId, price: price, elasticSurcharge: elasticSurcharge, durations: [], selectedDuration: null, couponDiscount: 0, couponCode: '', couponApplied: false };
        var fd = new FormData();
        fd.append('action', 'get_renew_durations');
        fd.append('model_id', modelId);
        fetch('', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renewData[vhostId].durations = data.durations || [];
                renderRenewDurations(vhostId);
            });
    }
    document.getElementById('renewOverlay_' + vhostId).style.display = 'block';
    document.getElementById('renewModal_' + vhostId).style.display = 'block';
    document.body.style.overflow = 'hidden';
    if (renewData[vhostId].durations.length > 0) renderRenewDurations(vhostId);
}

function closeRenewModal(vhostId) {
    document.getElementById('renewOverlay_' + vhostId).style.display = 'none';
    document.getElementById('renewModal_' + vhostId).style.display = 'none';
    document.body.style.overflow = '';
}

function renderRenewDurations(vhostId) {
    var data = renewData[vhostId];
    var container = document.getElementById('renewDurations_' + vhostId);
    if (!data.durations.length) {
        container.innerHTML = '<span style="color:#999;font-size:.85rem">' + _L.noDurations + '</span>';
        return;
    }
    var html = '';
    for (var i = 0; i < data.durations.length; i++) {
        var d = data.durations[i];
        var months = durationMonths[d.duration_type] || 1;
        var price = Math.ceil(data.price * months * (100 - d.discount) / 100);
        var label = durationLabels[d.duration_type] || d.duration_type;
        var discountBadge = '';
        if (d.discount > 0) {
            var zhe = ((100 - d.discount) / 10).toFixed(1);
            if (zhe.indexOf('.0') === zhe.length - 2) zhe = zhe.slice(0, -2);
            discountBadge = '<span class="duration-badge">' + zhe + _L.discount + '</span>';
        }
        html += '<button type="button" class="duration-btn" data-type="' + d.duration_type + '" data-discount="' + d.discount + '" data-price="' + price + '" onclick="selectRenewDuration(' + vhostId + ',\'' + d.duration_type + '\',' + d.discount + ',' + price + ')">' + discountBadge + label + '<br><small>' + price + _L.points + '</small></button>';
    }
    container.innerHTML = html;
    var firstBtn = container.querySelector('.duration-btn');
    if (firstBtn) firstBtn.click();
}

function selectRenewDuration(vhostId, durationType, discount, price) {
    var data = renewData[vhostId];
    data.selectedDuration = durationType;
    data.selectedDurationPrice = price;
    data.selectedDurationDiscount = discount;
    var btns = document.querySelectorAll('#renewDurations_' + vhostId + ' .duration-btn');
    for (var i = 0; i < btns.length; i++) btns[i].classList.remove('active');
    var activeBtn = document.querySelector('#renewDurations_' + vhostId + ' .duration-btn[data-type="' + durationType + '"]');
    if (activeBtn) activeBtn.classList.add('active');
    removeRenewCoupon(vhostId, true);
    updateRenewPrice(vhostId);
}

function updateRenewPrice(vhostId) {
    var data = renewData[vhostId];
    if (!data.selectedDuration) return;
    var durationPrice = data.selectedDurationPrice || 0;
    var elasticSurcharge = data.elasticSurcharge;
    var subtotal = durationPrice + elasticSurcharge;
    var total = subtotal;
    if (data.couponApplied) {
        total = Math.ceil(subtotal * (100 - data.couponDiscount) / 100);
    }
    document.getElementById('renewDurationPrice_' + vhostId).textContent = durationPrice + _L.points;
    if (elasticSurcharge > 0) {
        document.getElementById('renewElasticRow_' + vhostId).style.display = 'flex';
        document.getElementById('renewElasticSurcharge_' + vhostId).textContent = '+' + elasticSurcharge + _L.points + '/<?php echo addslashes(L('buy_month')); ?>';
    } else {
        document.getElementById('renewElasticRow_' + vhostId).style.display = 'none';
    }
    if (data.couponApplied) {
        document.getElementById('renewCouponRow_' + vhostId).style.display = 'flex';
        document.getElementById('renewCouponDiscount_' + vhostId).textContent = '-' + data.couponDiscount + '%';
    } else {
        document.getElementById('renewCouponRow_' + vhostId).style.display = 'none';
    }
    document.getElementById('renewTotal_' + vhostId).textContent = total + _L.points;
    var balanceEl = document.getElementById('renewBalanceAfter_' + vhostId);
    var remainingEl = document.getElementById('renewRemaining_' + vhostId);
    var userPoints = <?php echo $user['points']; ?>;
    if (total > 0) {
        balanceEl.style.display = 'inline';
        var remaining = userPoints - total;
        remainingEl.textContent = remaining;
        remainingEl.style.color = remaining >= 0 ? '#10b981' : '#e74c3c';
    } else {
        balanceEl.style.display = 'none';
    }
}

function toggleRenewCoupon(vhostId) {
    var area = document.getElementById('renewCouponArea_' + vhostId);
    var arrow = document.getElementById('renewCouponArrow_' + vhostId);
    if (area.style.display === 'none') {
        area.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        area.style.display = 'none';
        arrow.style.transform = '';
    }
}

function applyRenewCoupon(vhostId) {
    var code = document.getElementById('renewCoupon_' + vhostId).value.trim();
    var msgEl = document.getElementById('renewCouponMsg_' + vhostId);
    var data = renewData[vhostId];
    if (!code) { msgEl.innerHTML = ''; msgEl.className = 'coupon-msg'; return; }
    if (!data.selectedDuration) { msgEl.innerHTML = _L.couponNeedDuration; msgEl.className = 'coupon-msg error'; return; }
    var subtotal = (data.selectedDurationPrice || 0) + data.elasticSurcharge;
    msgEl.innerHTML = _L.checking;
    msgEl.className = 'coupon-msg loading';
    var fd = new FormData();
    fd.append('action', 'check_renew_coupon');
    fd.append('code', code);
    fd.append('model_id', data.modelId);
    fd.append('subtotal', subtotal);
    fetch('', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.valid) {
                data.couponDiscount = res.discount;
                data.couponCode = code;
                data.couponApplied = true;
                document.getElementById('renewCouponCode_' + vhostId).value = code;
                msgEl.innerHTML = '';
                msgEl.className = 'coupon-msg';
                var infoEl = document.getElementById('renewCouponInfo_' + vhostId);
                infoEl.style.display = 'flex';
                document.getElementById('renewDiscountPercent_' + vhostId).textContent = res.discount;
                document.getElementById('renewDiscountedPrice_' + vhostId).textContent = res.final_price;
                updateRenewPrice(vhostId);
            } else {
                data.couponApplied = false;
                data.couponCode = '';
                data.couponDiscount = 0;
                document.getElementById('renewCouponCode_' + vhostId).value = '';
                msgEl.innerHTML = res.message;
                msgEl.className = 'coupon-msg error';
                document.getElementById('renewCouponInfo_' + vhostId).style.display = 'none';
                updateRenewPrice(vhostId);
            }
        });
}

function removeRenewCoupon(vhostId, silent) {
    var data = renewData[vhostId];
    data.couponApplied = false;
    data.couponCode = '';
    data.couponDiscount = 0;
    if (!silent) {
        document.getElementById('renewCoupon_' + vhostId).value = '';
        document.getElementById('renewCouponMsg_' + vhostId).innerHTML = '';
        document.getElementById('renewCouponMsg_' + vhostId).className = 'coupon-msg';
        document.getElementById('renewCouponInfo_' + vhostId).style.display = 'none';
    }
    document.getElementById('renewCouponCode_' + vhostId).value = '';
    updateRenewPrice(vhostId);
}

function confirmRenew(vhostId) {
    var data = renewData[vhostId];
    if (!data.selectedDuration) { alert(_L.needSelectDuration); return; }
    var total = parseInt(document.getElementById('renewTotal_' + vhostId).textContent) || 0;
    if (!confirm(_L.confirmDialog + ' ' + total + ' ' + _L.points)) return;
    document.getElementById('renewDurationType_' + vhostId).value = data.selectedDuration;
    document.getElementById('renewCouponCode_' + vhostId).value = data.couponCode;
    var btn = document.getElementById('renewSubmit_' + vhostId);
    if (btn) btn.disabled = true;
    if (typeof showLoading === 'function') showLoading();
    document.getElementById('renewForm_' + vhostId).submit();
}
</script>

<style>
.panel-tab-radio{position:absolute;width:0;height:0;opacity:0;pointer-events:none}
.panel-section{display:none}
.panel-nav-item{cursor:pointer;text-decoration:none;display:block}
.panel-nav-item.active{color:var(--accent,#667eea);font-weight:700}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:.75rem;font-weight:600;background:#667eea;color:#fff}
.badge-success{background:#10b981;color:#fff}
.quick-sign-form{margin-top:16px}
.quick-sign-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 16px;border:none;border-radius:var(--radius-sm);background:var(--gradient-accent);color:#fff;font-size:.92rem;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(45,139,107,.3);transition:all var(--transition)}
.quick-sign-btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(45,139,107,.4)}
.quick-sign-btn:active{transform:translateY(0)}
.hp-ticket-list{display:flex;flex-direction:column;gap:10px}
.hp-ticket-item{padding:14px 16px;border-radius:10px;background:var(--bg-card,#f8f9fa);cursor:pointer;transition:all .2s}
.hp-ticket-item:hover{filter:brightness(.97)}
.hp-ticket-row{display:flex;align-items:center;gap:10px}
.hp-ticket-id{font-weight:700;color:var(--primary-solid,#667eea);font-size:.85rem;min-width:40px}
.hp-ticket-subject{flex:1;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hp-ticket-status{font-weight:600;font-size:.8rem;min-width:50px;text-align:center}
.hp-ticket-time{font-size:.8rem;color:#999;min-width:80px;text-align:right}
.hp-ticket-detail{margin-top:14px;padding-top:14px;border-top:1px solid var(--border-color,#eee)}
.hp-reply-item{margin-bottom:12px;padding:12px;border-radius:10px;max-width:85%}
.hp-reply-admin{background:linear-gradient(135deg,#667eea11,#764ba211);border-left:3px solid #667eea;margin-left:0}
.hp-reply-user{background:var(--bg-card,#f5f5f5);margin-left:auto;border-left:3px solid #10b981}
.hp-reply-header{display:flex;justify-content:space-between;margin-bottom:6px}
.hp-reply-author{font-weight:600;font-size:.85rem}
.hp-reply-time{font-size:.75rem;color:#999}
.hp-reply-content{font-size:.9rem;line-height:1.6;word-break:break-all}
.hp-reply-form{margin-top:16px;padding-top:12px;border-top:1px dashed var(--border-color,#ddd)}
.hp-reply-form textarea{width:100%;padding:10px 12px;border:1px solid var(--border-color,#ddd);border-radius:8px;font-size:.9rem;resize:vertical;background:var(--bg-input,#fff)}
.hp-reply-form textarea:focus{outline:none;border-color:var(--primary-solid,#667eea)}
.copy-btn{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;background:none;border:1px solid var(--border-color,#ddd);border-radius:6px;cursor:pointer;padding:3px;opacity:.5;transition:all .2s;vertical-align:middle;margin-left:4px;flex-shrink:0}
.copy-btn:hover{opacity:1;border-color:var(--primary-solid,#667eea);background:var(--primary-solid,#667eea)}
.copy-btn:hover svg{stroke:#fff}
.copy-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.renew-overlay { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);z-index:9999;backdrop-filter:blur(4px);animation:fadeIn .2s ease }
.renew-modal { position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10000;width:500px;max-width:94vw;animation:slideUp .25s ease }
.renew-modal-content { background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3) }
.renew-modal-header { display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0 }
.renew-modal-header h3 { margin:0;font-size:1.15rem }
.renew-modal-body { padding:16px 24px;max-height:70vh;overflow-y:auto }
.modal-close { background:none;border:none;font-size:1.6rem;cursor:pointer;color:#999;line-height:1;padding:0 4px }
.modal-close:hover { color:#333 }
.duration-section { margin-bottom:16px }
.duration-section .section-label { display:block;font-size:.85rem;color:#888;margin-bottom:8px;font-weight:500 }
.duration-btns { display:flex;flex-wrap:wrap;gap:8px }
.duration-btn { padding:8px 14px;border:2px solid #dee2ed;border-radius:8px;background:#fff;cursor:pointer;font-size:.85rem;transition:all .2s;text-align:center;min-width:70px;position:relative;overflow:hidden }
.duration-btn:hover { border-color:var(--primary,#6366f1);color:var(--primary,#6366f1) }
.duration-btn.active { border-color:var(--primary,#6366f1);background:var(--primary,#6366f1);color:#fff;font-weight:600 }
.duration-badge { position:absolute;top:0;right:0;background:#f97316;color:#fff;font-size:.65rem;padding:1px 5px;border-radius:0 7px 0 5px;line-height:1.4;font-weight:600 }
.coupon-section { background:#f8f9fa;border-radius:10px;padding:12px 14px;margin-bottom:12px }
.coupon-toggle { display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--primary,#6366f1);font-weight:500;font-size:.95rem;user-select:none }
.coupon-toggle .fa-chevron-down { margin-left:auto;transition:transform .2s;font-size:.8rem }
.coupon-toggle .fa-chevron-down.rotated { transform:rotate(180deg) }
.coupon-area { margin-top:10px }
.coupon-row { display:flex;gap:8px }
.coupon-row input { flex:1;padding:9px 12px;border:2px solid #dee2ed;border-radius:8px;font-size:.92rem;outline:none;transition:border .2s }
.coupon-row input:focus { border-color:var(--primary,#6366f1) }
.btn-coupon { padding:9px 18px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;font-size:.9rem;white-space:nowrap;transition:opacity .2s }
.btn-coupon:hover { opacity:.88 }
.btn-coupon:disabled { opacity:.5;cursor:not-allowed }
.coupon-msg { font-size:.85rem;margin-top:6px;min-height:20px }
.coupon-msg.error { color:#e74c3c }
.coupon-msg.success { color:#10b981 }
.coupon-msg.loading { color:#999 }
.coupon-discount-info { margin-top:8px;padding:8px 12px;background:#ecfdf5;border-radius:8px;font-size:.85rem;color:#065f46;display:flex;align-items:center;flex-wrap:wrap;gap:4px }
.coupon-discount-info i { color:#10b981;font-size:1rem }
.btn-remove-coupon { background:none;border:none;color:#999;cursor:pointer;font-size:.8rem;padding:2px 6px;margin-left:auto }
.btn-remove-coupon:hover { color:#e74c3c }
.price-summary { background:#f8f9fa;border-radius:10px;padding:12px 14px;margin-bottom:12px }
.price-summary .price-row { display:flex;justify-content:space-between;align-items:center;font-size:.88rem;color:#555;padding:3px 0 }
.price-summary .price-row span:last-child { font-weight:500 }
.price-total { border-top:1px dashed #dee2ed;margin-top:6px;padding-top:8px;font-size:1rem;font-weight:700;color:#333 }
.price-total span:last-child { color:var(--primary,#6366f1);font-size:1.1rem }
.user-balance { font-size:.9rem;color:#666;padding:4px 0 }
.renew-modal-footer { display:flex;gap:10px;padding:0 24px 20px;justify-content:flex-end }
.btn-cancel { padding:10px 24px;background:#f1f3f5;color:#555;border:none;border-radius:10px;cursor:pointer;font-weight:500;font-size:.9rem;transition:background .2s }
.btn-cancel:hover { background:#e9ecef }
.btn-confirm { padding:10px 28px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:600;font-size:.9rem;transition:opacity .2s }
.btn-confirm:hover { opacity:.88 }
.btn-confirm:disabled { opacity:.5;cursor:not-allowed }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes slideUp { from{opacity:0;transform:translate(-50%,-50%) scale(.94)} to{opacity:1;transform:translate(-50%,-50%) scale(1)} }
</style>

<script>
function toggleTicketDetail(id){
    var el=document.getElementById('ticket-detail-'+id);
    el.style.display=el.style.display==='none'?'block':'none';
}
function copyText(el){
    var r=document.createRange();r.selectNode(el);window.getSelection().removeAllRanges();window.getSelection().addRange(r);
    try{document.execCommand('copy');el.classList.add('copied');setTimeout(function(){el.classList.remove('copied')},1000)}catch(e){}
    window.getSelection().removeAllRanges();
}
function copyToClipboard(text, btn) {
    var successMsg = _L.copied || '复制成功';
    var fallback = function() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, text.length);
        try {
            document.execCommand('copy');
            if (btn && btn.tagName === 'BUTTON') {
                var old = btn.textContent;
                btn.textContent = successMsg;
                setTimeout(function(){ btn.textContent = old; }, 1200);
            } else {
                alert(successMsg);
            }
        } catch (e) {
            prompt('请手动复制：', text);
        }
        document.body.removeChild(ta);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            if (btn && btn.tagName === 'BUTTON') {
                var old = btn.textContent;
                btn.textContent = successMsg;
                setTimeout(function(){ btn.textContent = old; }, 1200);
            } else {
                alert(successMsg);
            }
        }).catch(fallback);
    } else {
        fallback();
    }
}
function copyInviteCode() {
    var code = document.getElementById('myInviteCode').textContent;
    copyToClipboard(code);
}
function copyApiKey(key, btn) {
    copyToClipboard(key, btn);
}
function shareToFriend() {
    var code = document.getElementById('myInviteCode').textContent;
    var shareUrl = window.location.origin + '/login.php?mode=register&invite=' + code;
    var text = _L.shareText + ' ' + code + ' ' + _L.shareTextSuffix;
    if (navigator.share) {
        navigator.share({ title: '<?php echo addslashes(L('register_title')); ?>', text: text, url: shareUrl }).catch(function() {});
    } else {
        copyToClipboard(shareUrl);
    }
}
document.addEventListener('DOMContentLoaded', function(){
    var radios = document.querySelectorAll('input[name="pay_type"]');
    var forms = document.querySelectorAll('.pkg-card');
    radios.forEach(function(radio){
        radio.addEventListener('change', function(){
            var val = this.value;
            forms.forEach(function(form){
                form.querySelector('input[name="pay_type"]').value = val;
            });
        });
    });
    forms.forEach(function(form){
        form.querySelector('input[name="pay_type"]').value = document.querySelector('input[name="pay_type"]:checked').value;
    });
});

// ========== API 调试器 ==========
var debugParamDefs = {
    user_info: {method:'GET',params:{}},
    points: {method:'GET',params:{}},
    sign: {method:'POST',params:{}},
    model_list: {method:'GET',params:{}},
    host_list: {method:'GET',params:{}},
    host_detail: {method:'GET',params:{host_id:{l:'主机ID',p:'输入主机ID'}}},
    host_buy: {method:'POST',params:{model_id:{l:'型号ID',p:'输入型号ID'},duration_type:{l:'时长类型',p:'month/quarter/half_year/year/2year/3year/5year/10year'},elastic_values:{l:'弹性配置(JSON)',p:'{"web_space":512}',ta:true},coupon_code:{l:'优惠码',p:'选填'}}},
    host_renew: {method:'POST',params:{host_id:{l:'主机ID',p:'输入主机ID'},duration_type:{l:'时长类型',p:'month/quarter/half_year/year/2year/3year/5year/10year'},coupon_code:{l:'优惠码',p:'选填'}}},
    ticket_list: {method:'GET',params:{}},
    ticket_detail: {method:'GET',params:{ticket_id:{l:'工单ID',p:'输入工单ID'}}},
    ticket_create: {method:'POST',params:{subject:{l:'标题',p:'简要描述问题'},content:{l:'内容',p:'详细描述问题',ta:true},host_id:{l:'关联主机ID',p:'选填'}}},
    ticket_reply: {method:'POST',params:{ticket_id:{l:'工单ID',p:'输入工单ID'},content:{l:'回复内容',p:'输入回复内容',ta:true}}},
    ticket_close: {method:'POST',params:{ticket_id:{l:'工单ID',p:'输入工单ID'}}},
    referral_info: {method:'GET',params:{}},
};

function onDebugActionChange() {
    var action = document.getElementById('debug-action').value;
    var def = debugParamDefs[action];
    if (def) {
        document.getElementById('debug-method').value = def.method;
        renderDebugParams(action);
    }
}

function renderDebugParams(action) {
    var def = debugParamDefs[action];
    var container = document.getElementById('debug-params');
    if (!def || Object.keys(def.params).length === 0) {
        container.innerHTML = '<p style="color:var(--muted);font-size:.85rem;margin-top:8px"><?php echo addslashes(L('panel_api_no_params')); ?></p>';
        return;
    }
    var html = '';
    for (var key in def.params) {
        var p = def.params[key];
        if (p.ta) {
            html += '<div class="form-group"><label>'+p.l+' ('+key+')</label><textarea id="param-'+key+'" placeholder="'+p.p+'" style="width:100%;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;resize:vertical;min-height:60px;outline:none"></textarea></div>';
        } else {
            html += '<div class="form-group"><label>'+p.l+' ('+key+')</label><input type="text" id="param-'+key+'" placeholder="'+p.p+'" style="width:100%;padding:10px 14px;border:2px solid var(--border-color,#dee2ed);border-radius:8px;font-size:.9rem;outline:none"></div>';
        }
    }
    container.innerHTML = html;
}

function tryDebugEndpoint(action, method) {
    document.getElementById('debug-action').value = action;
    document.getElementById('debug-method').value = method;
    renderDebugParams(action);
    document.getElementById('panel-api').scrollIntoView({behavior:'smooth'});
}

function saveDebugApiKey() {
    var key = document.getElementById('debug-apikey').value.trim();
    localStorage.setItem('staridc_api_key', key);
    alert('<?php echo addslashes(L('panel_api_saved')); ?>');
}

function sendDebugRequest() {
    var apiKey = document.getElementById('debug-apikey').value.trim();
    var action = document.getElementById('debug-action').value;
    var method = document.getElementById('debug-method').value;
    var startTime = Date.now();
    var respBox = document.getElementById('debug-response');
    var statusEl = document.getElementById('debug-status');
    var timeEl = document.getElementById('debug-time');
    var resultEl = document.getElementById('debug-result');
    var sendBtn = document.getElementById('debug-send');

    if (!apiKey) { alert('<?php echo addslashes(L('panel_api_need_key')); ?>'); return; }

    var params = {};
    var def = debugParamDefs[action];
    if (def) {
        for (var key in def.params) {
            var el = document.getElementById('param-'+key);
            if (el) params[key] = el.value.trim();
        }
    }

    sendBtn.disabled = true;
    sendBtn.textContent = '<?php echo addslashes(L('panel_api_requesting')); ?>';
    respBox.style.display = 'block';
    resultEl.textContent = 'Loading...';

    var url = 'api/index.php?action=' + action + '&api_key=' + encodeURIComponent(apiKey);
    var options = {method: method, headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'}};

    if (method === 'GET') {
        for (var k in params) {
            if (params[k]) url += '&' + k + '=' + encodeURIComponent(params[k]);
        }
    } else {
        var body = [];
        for (var k in params) {
            if (params[k]) body.push(k + '=' + encodeURIComponent(params[k]));
        }
        options.body = body.join('&');
    }

    fetch(url, options)
        .then(function(r) {
            var elapsed = Date.now() - startTime;
            timeEl.textContent = elapsed + 'ms';
            if (r.ok) {
                statusEl.textContent = 'HTTP ' + r.status + ' OK';
                statusEl.style.color = '#10b981';
            } else {
                statusEl.textContent = 'HTTP ' + r.status;
                statusEl.style.color = '#ef4444';
            }
            return r.text();
        })
        .then(function(text) {
            try {
                var obj = JSON.parse(text);
                resultEl.textContent = JSON.stringify(obj, null, 2);
            } catch(e) {
                resultEl.textContent = text;
            }
            sendBtn.disabled = false;
            sendBtn.textContent = '<?php echo addslashes(L('panel_api_send')); ?>';
        })
        .catch(function(err) {
            statusEl.textContent = 'Error';
            statusEl.style.color = '#ef4444';
            timeEl.textContent = (Date.now() - startTime) + 'ms';
            resultEl.textContent = err.message;
            sendBtn.disabled = false;
            sendBtn.textContent = '<?php echo addslashes(L('panel_api_send')); ?>';
        });
}

(function(){
    var saved = localStorage.getItem('staridc_api_key');
    if (saved) {
        var el = document.getElementById('debug-apikey');
        if (el && !el.value) el.value = saved;
    }
    renderDebugParams('user_info');
})();
</script>
<!-- ========== Panel HTML End ========== -->
<?php renderFooter(); ?>