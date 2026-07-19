<?php

define('IN_SYS', true);
define('ROOT', __DIR__ . '/../');
define('API_CALL', true);
require ROOT . 'rd/bootstrap.php';
require ROOT . 'rd/MNBT_API.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }


$apiKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
} elseif (!empty($_GET['api_key'])) {
    $apiKey = trim($_GET['api_key']);
} elseif (!empty($_POST['api_key'])) {
    $apiKey = trim($_POST['api_key']);
}

if (empty($apiKey)) {
    jsonExit(401, 'Missing API Key. Provide via X-API-Key header or api_key parameter.');
}


$stmt = $DB->prepare("SELECT * FROM api_keys WHERE api_key = ? AND status = 1");
$stmt->execute([$apiKey]);
$keyRow = $stmt->fetch();

if (!$keyRow) {
    jsonExit(403, 'Invalid or disabled API Key.');
}


$DB->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?")->execute([$keyRow['id']]);

$userId = $keyRow['user_id'];


$stmt = $DB->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    jsonExit(404, 'User not found.');
}


$action = $_REQUEST['action'] ?? '';



switch ($action) {
    
    case 'user_info':
        jsonExit(200, 'ok', [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'nickname' => $user['nickname'],
            'points' => (int)$user['points'],
            'last_sign_date' => $user['last_sign_date'],
            'created_at' => $user['created_at'],
        ]);
        break;

    
    case 'points':
        jsonExit(200, 'ok', [
            'points' => (int)$user['points'],
        ]);
        break;

    
    case 'sign':
        $lock = signLockAcquire($userId);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', time() - 86400);
        $chk = $DB->prepare("SELECT last_sign_date, points, sign_streak FROM users WHERE id = ?");
        $chk->execute([$userId]);
        $row = $chk->fetch();
        if ($row && $row['last_sign_date'] === $today) {
            signLockRelease($lock);
            jsonExit(400, 'Already signed in today.');
        }
        if (riskIsBlocked('sign', 'account', (string)$userId) || riskIsBlocked('sign', 'ip', getClientIp())) {
            signLockRelease($lock);
            jsonExit(429, 'Sign-in rate limited. Try again tomorrow.');
        }
        $streak = ($row && $row['last_sign_date'] === $yesterday) ? intval($row['sign_streak']) + 1 : 1;
        $points = calcSignPoints($streak);
        $DB->prepare("UPDATE users SET last_sign_date = ?, sign_streak = ? WHERE id = ?")->execute([$today, $streak, $userId]);
        $newBal = addPoints($userId, $points, 'sign', '', '每日签到（连续' . $streak . '天）');
        riskRecord('sign', 'account', (string)$userId);
        riskRecord('sign', 'ip', getClientIp());
        signLockRelease($lock);
        jsonExit(200, 'Sign-in successful', [
            'points_earned' => $points,
            'total_points' => (int)$newBal,
            'streak' => $streak,
        ]);
        break;

    
    case 'host_list':
        $stmt = $DB->prepare("SELECT v.*, vm.name as model_name FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.user_id = ? ORDER BY v.id DESC");
        $stmt->execute([$userId]);
        $hosts = $stmt->fetchAll();
        $result = [];
        foreach ($hosts as $h) {
            $daysLeft = $h['expire_time'] ? max(0, floor((strtotime($h['expire_time']) - time()) / 86400)) : null;
            $result[] = [
                'id' => (int)$h['id'],
                'model_name' => $h['model_name'],
                'account' => $h['account'],
                'expire_time' => $h['expire_time'],
                'days_left' => $daysLeft,
                'mnbt_opened' => (bool)$h['mnbt_opened'],
                'web_space' => (int)($h['web_space'] ?? 0),
                'db_space' => (int)($h['db_space'] ?? 0),
                'flow' => (int)($h['flow'] ?? 0),
                'domain_limit' => (int)($h['domain_limit'] ?? 0),
            ];
        }
        jsonExit(200, 'ok', $result);
        break;

    
    case 'host_detail':
        $hostId = intval($_REQUEST['host_id'] ?? 0);
        if ($hostId <= 0) {
            jsonExit(400, 'Missing host_id parameter.');
        }
        $stmt = $DB->prepare("SELECT v.*, vm.name as model_name FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.id = ? AND v.user_id = ?");
        $stmt->execute([$hostId, $userId]);
        $host = $stmt->fetch();
        if (!$host) {
            jsonExit(404, 'Host not found.');
        }
        $daysLeft = $host['expire_time'] ? max(0, floor((strtotime($host['expire_time']) - time()) / 86400)) : null;
        jsonExit(200, 'ok', [
            'id' => (int)$host['id'],
            'model_name' => $host['model_name'],
            'account' => $host['account'],
            'password' => $host['password'],
            'expire_time' => $host['expire_time'],
            'days_left' => $daysLeft,
            'mnbt_opened' => (bool)$host['mnbt_opened'],
            'web_space' => (int)($host['web_space'] ?? 0),
            'db_space' => (int)($host['db_space'] ?? 0),
            'flow' => (int)($host['flow'] ?? 0),
            'domain_limit' => (int)($host['domain_limit'] ?? 0),
        ]);
        break;

    
    case 'ticket_list':
        $stmt = $DB->prepare("SELECT t.*, vm.name as model_name FROM tickets t LEFT JOIN vhosts vh ON t.vhost_id = vh.id LEFT JOIN vhost_models vm ON vh.model_id = vm.id WHERE t.user_id = ? ORDER BY t.updated_at DESC");
        $stmt->execute([$userId]);
        $tickets = $stmt->fetchAll();
        $statusMap = [0 => 'pending', 1 => 'replied', 2 => 'closed'];
        $result = [];
        foreach ($tickets as $t) {
            $result[] = [
                'id' => (int)$t['id'],
                'subject' => $t['subject'],
                'status' => $statusMap[$t['status']] ?? 'unknown',
                'vhost_name' => $t['model_name'],
                'created_at' => $t['created_at'],
                'updated_at' => $t['updated_at'],
            ];
        }
        jsonExit(200, 'ok', $result);
        break;

    
    case 'ticket_detail':
        $ticketId = intval($_REQUEST['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            jsonExit(400, 'Missing ticket_id parameter.');
        }
        $stmt = $DB->prepare("SELECT t.* FROM tickets t WHERE t.id = ? AND t.user_id = ?");
        $stmt->execute([$ticketId, $userId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            jsonExit(404, 'Ticket not found.');
        }
        $statusMap = [0 => 'pending', 1 => 'replied', 2 => 'closed'];
        $replies = $DB->prepare("SELECT tr.*, u.email as user_email FROM ticket_replies tr LEFT JOIN users u ON tr.user_id = u.id WHERE tr.ticket_id = ? ORDER BY tr.created_at ASC");
        $replies->execute([$ticketId]);
        $replyList = [];
        foreach ($replies->fetchAll() as $r) {
            $replyList[] = [
                'id' => (int)$r['id'],
                'is_admin' => !empty($r['admin_id']),
                'content' => $r['content'],
                'created_at' => $r['created_at'],
            ];
        }
        jsonExit(200, 'ok', [
            'id' => (int)$ticket['id'],
            'subject' => $ticket['subject'],
            'status' => $statusMap[$ticket['status']] ?? 'unknown',
            'created_at' => $ticket['created_at'],
            'updated_at' => $ticket['updated_at'],
            'replies' => $replyList,
        ]);
        break;

    
    case 'ticket_create':
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $vhostId = !empty($_POST['host_id']) ? intval($_POST['host_id']) : null;
        if (empty($subject) || empty($content)) {
            jsonExit(400, 'Missing subject or content.');
        }
        if (mb_strlen($subject) > 200) {
            jsonExit(400, 'Subject too long (max 200 chars).');
        }
        try {
            $DB->prepare("INSERT INTO tickets(user_id, vhost_id, subject) VALUES(?,?,?)")->execute([$userId, $vhostId, $subject]);
            $ticketId = $DB->lastInsertId();
            $DB->prepare("INSERT INTO ticket_replies(ticket_id, user_id, content) VALUES(?,?,?)")->execute([$ticketId, $userId, $content]);
            
            if (conf('admin_email_notify', '1') === '1') {
                $adminEmail = conf('admin_email', '');
                if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $siteName = conf('site_name', '云主机');
                    $userEmail = '';
                    $userNickname = '';
                    try {
                        $uStmt = $DB->prepare("SELECT email, nickname FROM users WHERE id=?");
                        $uStmt->execute([$userId]);
                        $uRow = $uStmt->fetch();
                        if ($uRow) { $userEmail = $uRow['email']; $userNickname = $uRow['nickname']; }
                    } catch (Exception $e) {}
                    $mailBody = "有新的工单提交，请及时处理。\n\n";
                    $mailBody .= "工单编号：#{$ticketId}\n";
                    $mailBody .= "工单标题：{$subject}\n";
                    $mailBody .= "提交用户：" . ($userNickname ?: $userEmail) . " ({$userEmail})\n";
                    $mailBody .= "提交时间：" . date('Y-m-d H:i:s') . "\n\n";
                    $mailBody .= "内容摘要：\n" . mb_substr($content, 0, 200) . "\n\n";
                    $mailBody .= "站点：{$siteName}";
                    Mailer::sendNotify($adminEmail, "[{$siteName}] 新工单 #{$ticketId} 待处理", $mailBody);
                }
            }
            jsonExit(200, 'Ticket created', ['ticket_id' => (int)$ticketId]);
        } catch (Exception $e) {
            jsonExit(500, 'Failed to create ticket: ' . $e->getMessage());
        }
        break;

    
    case 'ticket_reply':
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($ticketId <= 0 || empty($content)) {
            jsonExit(400, 'Missing ticket_id or content.');
        }
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticketId, $userId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            jsonExit(404, 'Ticket not found.');
        }
        if ($ticket['status'] == 2) {
            jsonExit(400, 'Ticket is closed, cannot reply.');
        }
        try {
            $DB->prepare("INSERT INTO ticket_replies(ticket_id, user_id, content) VALUES(?,?,?)")->execute([$ticketId, $userId, $content]);
            $DB->prepare("UPDATE tickets SET status = 0, updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            jsonExit(200, 'Reply sent.');
        } catch (Exception $e) {
            jsonExit(500, 'Failed to reply: ' . $e->getMessage());
        }
        break;

    
    case 'ticket_close':
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        if ($ticketId <= 0) {
            jsonExit(400, 'Missing ticket_id parameter.');
        }
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
        $stmt->execute([$ticketId, $userId]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            jsonExit(404, 'Ticket not found.');
        }
        try {
            $DB->prepare("UPDATE tickets SET status = 2, updated_at = NOW() WHERE id = ?")->execute([$ticketId]);
            jsonExit(200, 'Ticket closed.');
        } catch (Exception $e) {
            jsonExit(500, 'Failed to close ticket: ' . $e->getMessage());
        }
        break;

    
    case 'referral_info':
        $referralEnabled = conf('referral_enabled', '1') === '1';
        $referralReward = intval(conf('referral_reward_points', '30'));
        $inviteCode = $user['invite_code'] ?? '';
        $stmt = $DB->prepare("SELECT COUNT(*) as c FROM referral_logs WHERE referrer_id = ?");
        $stmt->execute([$userId]);
        $refCount = (int)$stmt->fetch()['c'];
        jsonExit(200, 'ok', [
            'enabled' => $referralEnabled,
            'invite_code' => $inviteCode,
            'reward_points' => $referralReward,
            'referral_count' => $refCount,
        ]);
        break;

    
    case 'model_list':
        $stmt = $DB->prepare("SELECT m.*, s.name as server_name FROM vhost_models m LEFT JOIN mnbt_servers s ON m.server_id = s.id WHERE m.status = 1 ORDER BY m.sort_order, m.id");
        $stmt->execute();
        $models = $stmt->fetchAll();
        $result = [];
        foreach ($models as $m) {
            $durations = $DB->prepare("SELECT duration_type, discount FROM vhost_model_durations WHERE model_id = ? AND enabled = 1");
            $durations->execute([$m['id']]);
            $result[] = [
                'id' => (int)$m['id'],
                'name' => $m['name'],
                'price' => (int)$m['price'],
                'web_space' => (int)$m['web_space'],
                'db_space' => (int)$m['db_space'],
                'flow' => (int)$m['flow'],
                'domain_limit' => (int)$m['domain_limit'],
                'is_elastic' => (bool)$m['is_elastic'],
                'durations' => $durations->fetchAll(),
            ];
        }
        jsonExit(200, 'ok', $result);
        break;

    
    case 'host_buy':
        $modelId = intval($_POST['model_id'] ?? 0);
        $durationType = trim($_POST['duration_type'] ?? 'month');
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $elasticValues = [];
        if (!empty($_POST['elastic_values'])) {
            $elasticValues = json_decode($_POST['elastic_values'], true) ?: [];
        }
        if ($modelId <= 0) {
            jsonExit(400, 'Missing model_id parameter.');
        }

        $durationMonthsMap = [
            'month' => 1, 'quarter' => 3, 'half_year' => 6,
            'year' => 12, '2year' => 24, '3year' => 36,
            '5year' => 60, '10year' => 120
        ];
        if (!isset($durationMonthsMap[$durationType])) {
            jsonExit(400, 'Invalid duration_type. Supported: ' . implode(', ', array_keys($durationMonthsMap)));
        }

        $modelStmt = $DB->prepare("SELECT * FROM vhost_models WHERE id = ? AND status = 1");
        $modelStmt->execute([$modelId]);
        $model = $modelStmt->fetch();
        if (!$model) {
            jsonExit(404, 'Model not found or disabled.');
        }

        
        $globalLimit = intval(conf('max_hosts_per_user', '5'));
        if ($globalLimit > 0) {
            $vhostCount = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE user_id = ?");
            $vhostCount->execute([$userId]);
            $totalVhosts = $vhostCount->fetch()['c'];
            if ($totalVhosts >= $globalLimit) {
                jsonExit(403, 'Purchase limit reached: max ' . $globalLimit . ' hosts per user.');
            }
        }
        
        $maxPerUser = isset($model['max_per_user']) ? intval($model['max_per_user']) : 0;
        if ($maxPerUser > 0) {
            $modelCount = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE user_id = ? AND model_id = ?");
            $modelCount->execute([$userId, $modelId]);
            if ($modelCount->fetch()['c'] >= $maxPerUser) {
                jsonExit(403, 'Purchase limit reached: max ' . $maxPerUser . ' hosts of this model per user.');
            }
        }

        $months = $durationMonthsMap[$durationType];
        $server = getServer($model['server_id']);
        $account = genVhostAccount($userId, $modelId);
        $password = genVhostPassword();

        $finalWebSpace = $model['web_space'];
        $finalDbSpace = $model['db_space'];
        $finalFlow = $model['flow'];
        $finalDomainLimit = $model['domain_limit'];
        $elasticSurcharge = 0;

        if ($model['is_elastic'] && conf('elastic_enabled', '1') === '1') {
            try {
                $eStmt = $DB->prepare("SELECT * FROM vhost_model_elastic WHERE model_id = ? AND enabled = 1");
                $eStmt->execute([$modelId]);
                $elasticRows = $eStmt->fetchAll();
            } catch (Exception $e) {
                $elasticRows = [];
            }
            foreach ($elasticRows as $ec) {
                $fn = $ec['field_name'];
                $val = isset($elasticValues[$fn]) ? intval($elasticValues[$fn]) : intval($model[$fn]);
                if ($val > intval($model[$fn]) && intval($ec['step']) > 0) {
                    $elasticSurcharge += intval(($val - intval($model[$fn])) / intval($ec['step']) * intval($ec['unit_price']));
                }
                switch ($fn) {
                    case 'web_space': $finalWebSpace = $val; break;
                    case 'db_space': $finalDbSpace = $val; break;
                    case 'flow': $finalFlow = $val; break;
                    case 'domain_limit': $finalDomainLimit = $val; break;
                }
            }
        }

        
        $durDiscount = 0;
        $dStmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id = ? AND duration_type = ? AND enabled = 1");
        $dStmt->execute([$modelId, $durationType]);
        $durRow = $dStmt->fetch();
        if ($durRow) $durDiscount = intval($durRow['discount']);

        $basePrice = intval(ceil($model['price'] * $months * (100 - $durDiscount) / 100));
        $itemPrice = $basePrice + $elasticSurcharge;

        
        $couponId = null;
        $couponDiscount = 0;
        if (conf('coupons_enabled', '1') === '1' && !empty($couponCode)) {
            $cpStmt = $DB->prepare("SELECT * FROM coupons WHERE code = ? AND status = 0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
            $cpStmt->execute([$couponCode]);
            $cp = $cpStmt->fetch();
            if ($cp && ($cp['model_id'] === null || intval($cp['model_id']) === $modelId)) {
                $couponDiscount = intval($cp['discount']);
                $itemPrice = intval(ceil($itemPrice * (100 - $couponDiscount) / 100));
                $couponId = $cp['id'];
            }
        }

        if ($user['points'] < $itemPrice) {
            jsonExit(400, 'Insufficient points. Need ' . $itemPrice . ', have ' . $user['points'] . '.');
        }

        $expireTime = date('Y-m-d', strtotime('+' . $months . ' months'));
        $mnbtResult = MNBT_API::openHost($account, $password, $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit, $expireTime, $server);

        if (!$mnbtResult['success']) {
            jsonExit(500, 'MNBT API error: ' . $mnbtResult['message']);
        }

        addPoints($userId, -$itemPrice, 'exchange', 'model:' . $modelId, '购买主机【' . $model['name'] . '】');
        $DB->prepare("INSERT INTO vhosts(user_id, model_id, account, password, mnbt_opened, expire_time, server_id, web_space, db_space, flow, domain_limit) VALUES(?,?,?,?,1,?,?,?,?,?,?)")
            ->execute([$userId, $modelId, $account, $password, $expireTime, $model['server_id'], $finalWebSpace, $finalDbSpace, $finalFlow, $finalDomainLimit]);

        if ($couponId) {
            $DB->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
        }

        $hostId = $DB->lastInsertId();

        // 记录消费订单（积分明细）
        try {
            $orderNo = genOrderNo();
            $remark = '购买主机【' . $model['name'] . '】' . $months . '个月';
            if ($couponDiscount > 0) $remark .= '（优惠码 -' . $couponDiscount . '%）';
            $DB->prepare("INSERT INTO orders(order_no,user_id,type,amount,points,status,remark,params,paid_at) VALUES(?,?, 'host_buy',0,?,1,?,NOW())")
                ->execute([$orderNo, $userId, $itemPrice, $remark, json_encode(['host_id' => $hostId, 'model_id' => $modelId, 'duration_type' => $durationType])]);
        } catch (Exception $e) {}
        $newUser = $DB->prepare("SELECT points FROM users WHERE id = ?");
        $newUser->execute([$userId]);

        jsonExit(200, 'Host purchased successfully', [
            'host_id' => (int)$hostId,
            'account' => $account,
            'password' => $password,
            'expire_time' => $expireTime,
            'price' => $itemPrice,
            'remaining_points' => (int)$newUser->fetch()['points'],
        ]);
        break;

    
    case 'host_renew':
        $hostId = intval($_POST['host_id'] ?? 0);
        $durationType = trim($_POST['duration_type'] ?? 'month');
        $couponCode = trim($_POST['coupon_code'] ?? '');

        if ($hostId <= 0) {
            jsonExit(400, 'Missing host_id parameter.');
        }

        $durationMonthsMap = [
            'month' => 1, 'quarter' => 3, 'half_year' => 6,
            'year' => 12, '2year' => 24, '3year' => 36,
            '5year' => 60, '10year' => 120
        ];
        if (!isset($durationMonthsMap[$durationType])) {
            jsonExit(400, 'Invalid duration_type. Supported: ' . implode(', ', array_keys($durationMonthsMap)));
        }

        $stmt = $DB->prepare("SELECT v.*, vm.price, vm.name as model_name, vm.is_elastic, vm.web_space as model_web_space, vm.db_space as model_db_space, vm.flow as model_flow, vm.domain_limit as model_domain_limit, COALESCE(v.web_space, vm.web_space) as web_space, COALESCE(v.db_space, vm.db_space) as db_space, COALESCE(v.flow, vm.flow) as flow, COALESCE(v.domain_limit, vm.domain_limit) as domain_limit FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id = vm.id WHERE v.id = ? AND v.user_id = ?");
        $stmt->execute([$hostId, $userId]);
        $vhost = $stmt->fetch();

        if (!$vhost) {
            jsonExit(404, 'Host not found or not owned by you.');
        }

        $months = $durationMonthsMap[$durationType];

        $dStmt = $DB->prepare("SELECT discount FROM vhost_model_durations WHERE model_id = ? AND duration_type = ? AND enabled = 1");
        $dStmt->execute([$vhost['model_id'], $durationType]);
        $durRow = $dStmt->fetch();
        $durDiscount = $durRow ? intval($durRow['discount']) : 0;

        $basePrice = intval(ceil($vhost['price'] * $months * (100 - $durDiscount) / 100));

        $elasticSurcharge = 0;
        if ($vhost['is_elastic'] && conf('elastic_enabled', '1') === '1') {
            try {
                $eStmt = $DB->prepare("SELECT field_name, min_value, max_value, step, unit_price FROM vhost_model_elastic WHERE model_id = ? AND enabled = 1");
                $eStmt->execute([$vhost['model_id']]);
                $elasticRows = $eStmt->fetchAll();
            } catch (Exception $e) {
                $elasticRows = [];
            }
            foreach ($elasticRows as $ec) {
                $fn = $ec['field_name'];
                $baseVal = intval($vhost['model_' . $fn] ?? 0);
                $actualVal = intval($vhost[$fn] ?? $baseVal);
                if ($actualVal > $baseVal && intval($ec['step']) > 0) {
                    $elasticSurcharge += intval(($actualVal - $baseVal) / intval($ec['step']) * intval($ec['unit_price']));
                }
            }
        }

        $renewPrice = $basePrice + $elasticSurcharge;

        $couponId = null;
        if (conf('coupons_enabled', '1') === '1' && !empty($couponCode)) {
            $cpStmt = $DB->prepare("SELECT * FROM coupons WHERE code = ? AND status = 0 AND (used_count < max_uses OR max_uses = 0) AND (expire_at IS NULL OR expire_at > NOW())");
            $cpStmt->execute([$couponCode]);
            $cp = $cpStmt->fetch();
            if ($cp && ($cp['model_id'] === null || intval($cp['model_id']) === intval($vhost['model_id']))) {
                $renewPrice = intval(ceil($renewPrice * (100 - $cp['discount']) / 100));
                $couponId = $cp['id'];
            }
        }

        if ($user['points'] < $renewPrice) {
            jsonExit(400, 'Insufficient points. Need ' . $renewPrice . ', have ' . $user['points'] . '.');
        }

        $baseTime = strtotime($vhost['expire_time']) > time() ? $vhost['expire_time'] : date('Y-m-d H:i:s');
        $newExpire = date('Y-m-d', strtotime($baseTime . ' +' . $months . ' months'));
        $server = getServer($vhost['server_id']);

        $mnbtResult = MNBT_API::renewHost($vhost['account'], $newExpire, $server);
        if (!$mnbtResult['success']) {
            jsonExit(500, 'MNBT API error: ' . $mnbtResult['message']);
        }

        addPoints($userId, -$renewPrice, 'renew', 'host:' . $hostId, '续费主机【' . $vhost['model_name'] . '】' . $months . '个月');
        $DB->prepare("UPDATE vhosts SET expire_time = ?, expire_warned = 0 WHERE id = ?")->execute([$newExpire, $hostId]);

        if ($couponId) {
            $DB->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
        }

        // 记录续费消费订单
        try {
            $orderNo = genOrderNo();
            $remark = '续费主机【' . $vhost['model_name'] . '】' . $months . '个月';
            if ($couponId) $remark .= '（优惠码）';
            $DB->prepare("INSERT INTO orders(order_no,user_id,type,amount,points,status,remark,params,paid_at) VALUES(?,?, 'host_renew',0,?,1,?,NOW())")
                ->execute([$orderNo, $userId, $renewPrice, $remark, json_encode(['host_id' => $hostId, 'duration_type' => $durationType])]);
        } catch (Exception $e) {}

        $newUser = $DB->prepare("SELECT points FROM users WHERE id = ?");
        $newUser->execute([$userId]);

        jsonExit(200, 'Host renewed successfully', [
            'host_id' => (int)$hostId,
            'new_expire_time' => $newExpire,
            'months_added' => $months,
            'price' => $renewPrice,
            'remaining_points' => (int)$newUser->fetch()['points'],
        ]);
        break;

    
    default:
        jsonExit(200, 'API is running. Specify an action.', [
            'endpoints' => [
                'user_info'     => 'GET  - Get user info',
                'points'        => 'GET  - Get points balance',
                'sign'          => 'POST - Daily sign-in',
                'model_list'    => 'GET  - List available hosting plans',
                'host_list'     => 'GET  - List all hosts',
                'host_detail'   => 'GET  - Get host details (host_id)',
                'host_buy'      => 'POST - Purchase a host (model_id, duration_type, elastic_values?, coupon_code?)',
                'host_renew'    => 'POST - Renew a host (host_id, duration_type, coupon_code?)',
                'ticket_list'   => 'GET  - List tickets',
                'ticket_detail' => 'GET  - Get ticket with replies (ticket_id)',
                'ticket_create' => 'POST - Create ticket (subject, content, host_id?)',
                'ticket_reply'  => 'POST - Reply to ticket (ticket_id, content)',
                'ticket_close'  => 'POST - Close ticket (ticket_id)',
                'referral_info' => 'GET  - Get referral info',
            ],
        ]);
        break;
}