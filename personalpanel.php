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

function getRechargePackages() {
    global $DB;
    try {
        $stmt = $DB->prepare("SELECT * FROM recharge_packages WHERE status=1 ORDER BY sort_order,id");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (!empty($rows)) return $rows;
    } catch (Exception $e) {}
    // 兼容旧版：如果表不存在或没有数据，返回旧的固定套餐
    return [
        ['id' => 1, 'points' => 200,  'price' => conf('points_200_price', '10')],
        ['id' => 2, 'points' => 400,  'price' => conf('points_400_price', '18')],
        ['id' => 3, 'points' => 1000, 'price' => conf('points_1000_price', '40')],
        ['id' => 4, 'points' => 3000, 'price' => conf('points_3000_price', '100')],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sign') {
        $today = date('Y-m-d');
        if ($user['last_sign_date'] === $today) {
            $error = '今日已签到';
        } else {
            $min = intval(conf('sign_min', 50));
            $max = intval(conf('sign_max', 100));
            $points = mt_rand($min, $max);
            $stmt = $DB->prepare("UPDATE users SET points=points+?, last_sign_date=? WHERE id=?");
            $stmt->execute([$points, $today, $user['id']]);
            $success = '签到成功！获得 ' . $points . ' 积分';
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
            $error = '无效的积分套餐';
        } else {
            $payType = in_array(trim($_POST['pay_type'] ?? ''), ['alipay', 'wxpay']) ? trim($_POST['pay_type']) : 'alipay';
            $orderNo = genOrderNo();
            $stmt = $DB->prepare("INSERT INTO orders(order_no,user_id,type,amount,points,status) VALUES(?,?,'points',?,?,0)");
            $stmt->execute([$orderNo, $user['id'], $selected['price'], $selected['points']]);
            $notifyUrl = siteUrl() . 'pay_notify.php';
            $returnUrl = siteUrl() . 'pay_return.php';
            PayAPI::createPayment($orderNo, '积分充值 - ' . $selected['points'] . '积分', $selected['price'], $payType, $notifyUrl, $returnUrl);
            exit;
        }
    }

    if ($action === 'renew') {
        $vhostId = intval($_POST['vhost_id'] ?? 0);
        $stmt = $DB->prepare("SELECT v.*,vm.price,vm.name as model_name FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.id=? AND v.user_id=?");
        $stmt->execute([$vhostId, $user['id']]);
        $vhost = $stmt->fetch();
        if (!$vhost) {
            $error = '主机不存在';
        } elseif ($user['points'] < $vhost['price']) {
            $error = '积分不足，无法续费';
        } else {
            $baseTime = strtotime($vhost['expire_time']) > time() ? $vhost['expire_time'] : date('Y-m-d H:i:s');
            $newExpire = date('Y-m-d', strtotime($baseTime . ' +30 days'));
            $server = getServer($vhost['server_id']);
            $mnbtResult = MNBT_API::renewHost($vhost['account'], $newExpire, $server);
            if ($mnbtResult['success']) {
                $stmt2 = $DB->prepare("UPDATE users SET points=points-? WHERE id=?");
                $stmt2->execute([$vhost['price'], $user['id']]);
                $newExpireFull = date('Y-m-d H:i:s', strtotime($baseTime . ' +30 days'));
                $stmt3 = $DB->prepare("UPDATE vhosts SET expire_time=? WHERE id=?");
                $stmt3->execute([$newExpireFull, $vhostId]);
                $success = '续费成功！新到期时间：' . $newExpire;
                $user = getUser();
            } else {
                $error = 'MNBT续费失败：' . $mnbtResult['message'];
            }
        }
    }

    if ($action === 'mnbt_login') {
        $vhostId = intval($_POST['vhost_id'] ?? 0);
        $stmt = $DB->prepare("SELECT account,password,mnbt_opened,server_id FROM vhosts WHERE id=? AND user_id=?");
        $stmt->execute([$vhostId, $user['id']]);
        $vhost = $stmt->fetch();
        if (!$vhost) {
            $error = '主机不存在';
        } elseif (!$vhost['mnbt_opened']) {
            $error = '主机未开通，无法登录';
        } else {
            $server = getServer($vhost['server_id']);
            $apiUrl = $server ? $server['api_url'] : conf('mnbt_api_url', '');
            if (empty($apiUrl)) {
                $error = 'MNBT接口未配置';
            } else {
                $parsed = parse_url(rtrim($apiUrl, '/'));
                $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                if (!empty($parsed['port'])) $baseUrl .= ':' . $parsed['port'];
                $loginUrl = $baseUrl . '/user/idcdl.php?GN=LOGINE';
                $mnVs = $server ? ($server['mn_vs'] ?? '16') : conf('mnbt_vs', '16');
                renderHeader('正在跳转到MNBT控制面板...');
                echo '<form id="mnbt_login_form" method="post" action="' . h($loginUrl) . '">';
                echo '<input type="hidden" name="USERNAME" value="' . h($vhost['account']) . '">';
                echo '<input type="hidden" name="PASSWORD" value="' . h($vhost['password']) . '">';
                echo '<input type="hidden" name="MN_VS" value="' . h($mnVs) . '">';
                echo '</form>';
                echo '<p style="text-align:center;padding:40px;">正在自动登录MNBT控制面板...</p>';
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
        $success = '昵称已更新';
        $user = getUser();
    }

    // === 工单操作 ===
    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $vhostId = !empty($_POST['vhost_id']) ? intval($_POST['vhost_id']) : null;
        if (empty($subject) || empty($content)) {
            $error = '请填写工单标题和内容';
        } elseif (mb_strlen($subject) > 200) {
            $error = '工单标题不能超过200字';
        } else {
            $stmt = $DB->prepare("INSERT INTO tickets(user_id,vhost_id,subject) VALUES(?,?,?)");
            $stmt->execute([$user['id'], $vhostId, $subject]);
            $ticketId = $DB->lastInsertId();
            $stmt2 = $DB->prepare("INSERT INTO ticket_replies(ticket_id,user_id,content) VALUES(?,?,?)");
            $stmt2->execute([$ticketId, $user['id'], $content]);
            $success = '工单创建成功';
        }
    }

    if ($action === 'reply_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
        $stmt->execute([$ticketId, $user['id']]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            $error = '工单不存在';
        } elseif ($ticket['status'] == 2) {
            $error = '工单已关闭，无法回复';
        } elseif (empty($content)) {
            $error = '请输入回复内容';
        } else {
            $stmt2 = $DB->prepare("INSERT INTO ticket_replies(ticket_id,user_id,content) VALUES(?,?,?)");
            $stmt2->execute([$ticketId, $user['id'], $content]);
            $stmt3 = $DB->prepare("UPDATE tickets SET status=0, updated_at=NOW() WHERE id=?");
            $stmt3->execute([$ticketId]);
            $success = '回复成功';
        }
    }

    if ($action === 'close_ticket') {
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $stmt = $DB->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
        $stmt->execute([$ticketId, $user['id']]);
        $ticket = $stmt->fetch();
        if (!$ticket) {
            $error = '工单不存在';
        } else {
            $stmt2 = $DB->prepare("UPDATE tickets SET status=2, updated_at=NOW() WHERE id=?");
            $stmt2->execute([$ticketId]);
            $success = '工单已关闭';
        }
    }
}

$vhosts = $DB->prepare("SELECT v.*,vm.name as model_name,vm.web_space,vm.db_space FROM vhosts v LEFT JOIN vhost_models vm ON v.model_id=vm.id WHERE v.user_id=? ORDER BY v.id DESC");
$vhosts->execute([$user['id']]);
$vhostList = $vhosts->fetchAll();

$today = date('Y-m-d');
$canSign = $user['last_sign_date'] !== $today;
$signEnabled = intval(conf('sign_min', '50')) > 0;

// 获取推荐码，如果没有则生成
$inviteCode = $user['invite_code'] ?? '';
if (empty($inviteCode)) {
    $inviteCode = 'INV' . strtoupper(substr(md5($user['email'] . $user['id']), 0, 6));
    $stmtCode = $DB->prepare("UPDATE users SET invite_code = ? WHERE id = ?");
    $stmtCode->execute([$inviteCode, $user['id']]);
}

// 获取推荐记录
$referralLogs = $DB->prepare("SELECT r.*, u.email as referred_email FROM referral_logs r LEFT JOIN users u ON r.referred_id = u.id WHERE r.referrer_id = ? ORDER BY r.created_at DESC LIMIT 20");
$referralLogs->execute([$user['id']]);
$referralList = $referralLogs->fetchAll();

// 获取推荐奖励设置
$referralEnabled = conf('referral_enabled', '1') === '1';
$referralReward = intval(conf('referral_reward_points', '30'));

renderHeader('个人中心');
?>
<!-- ========== 个人中心HTML区域 ========== -->
<div class="panel-grid">
    <div class="panel-sidebar">
        <div class="user-card">
            <div class="user-avatar"><?php echo mb_strtoupper(mb_substr($user['nickname'], 0, 1)); ?></div>
            <div class="user-name"><?php echo h($user['nickname']); ?></div>
            <div class="user-email"><?php echo h($user['email']); ?></div>
            <div class="user-points">💰 <?php echo $user['points']; ?> 积分</div>
            <?php if ($canSign && $signEnabled): ?>
            <form method="post" class="quick-sign-form">
                <input type="hidden" name="action" value="sign">
                <button type="submit" class="quick-sign-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                    每日签到领积分
                </button>
            </form>
            <?php endif; ?>
        </div>
        <nav class="panel-nav">
            <a href="#" class="panel-nav-item active" onclick="showPanel('info')">个人信息</a>
            <a href="#" class="panel-nav-item" onclick="showPanel('points')">积分中心</a>
            <a href="#" class="panel-nav-item" onclick="showPanel('hosts')">我的主机</a>
            <a href="#" class="panel-nav-item" onclick="showPanel('tickets')">我的工单</a>
            <a href="#" class="panel-nav-item" onclick="showPanel('referral')">推荐奖励</a>
        </nav>
    </div>

    <div class="panel-main">
        <?php if ($error): ?><div class="msg msg-error"><?php echo h($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg msg-success"><?php echo h($success); ?></div><?php endif; ?>

        <div id="panel-info" class="panel-section">
            <div class="section-card">
                <h3>个人信息</h3>
                <form method="post">
                    <input type="hidden" name="action" value="update_nickname">
                    <div class="form-group">
                        <label>昵称</label>
                        <input type="text" name="nickname" value="<?php echo h($user['nickname']); ?>" maxlength="20">
                    </div>
                    <button type="submit" class="btn-primary">保存修改</button>
                </form>
                <div class="info-row"><span>邮箱</span><span><?php echo h($user['email']); ?></span></div>
                <div class="info-row"><span>注册时间</span><span><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span></div>
            </div>
        </div>

        <div id="panel-points" class="panel-section" style="display:none">
            <div class="section-card">
                <h3>每日签到</h3>
                <?php if ($canSign): ?>
                <form method="post"><input type="hidden" name="action" value="sign">
                    <p>签到可获得 <?php echo conf('sign_min','50'); ?>~<?php echo conf('sign_max','100'); ?> 随机积分</p>
                    <button type="submit" class="btn-primary">🎯 立即签到</button>
                </form>
                <?php else: ?>
                <p class="text-muted">今日已签到，明天再来吧~</p>
                <?php endif; ?>
            </div>
            <div class="section-card">
                <h3>充值积分</h3>
                <div style="margin-bottom:16px">
                    <label style="font-size:0.9rem;color:#636e72;margin-right:12px">支付方式：</label>
                    <label class="pay-type-label"><input type="radio" name="pay_type" value="alipay" checked> 支付宝</label>
                    <label class="pay-type-label"><input type="radio" name="pay_type" value="wxpay"> 微信支付</label>
                </div>
                <div class="points-packages">
                    <?php
                    $pkgs = getRechargePackages();
                    foreach ($pkgs as $p):
                        $pid = isset($p['id']) ? $p['id'] : $p['points'];
                    ?>
                    <form method="post" class="pkg-card">
                        <input type="hidden" name="action" value="buy_points">
                        <input type="hidden" name="package" value="<?php echo $pid; ?>">
                        <input type="hidden" name="pay_type" value="" id="paytype_<?php echo $pid; ?>">
                        <div class="pkg-points"><?php echo $p['points']; ?>积分</div>
                        <div class="pkg-price">¥<?php echo $p['price']; ?></div>
                        <button type="submit" class="btn-primary btn-sm">购买</button>
                    </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="panel-hosts" class="panel-section" style="display:none">
            <div class="section-card">
                <div class="section-header">
                    <h3>我的虚拟主机</h3>
                    <a href="cart.php" class="btn-primary btn-sm">+ 购买新主机</a>
                </div>
                <?php if (empty($vhostList)): ?>
                <div class="empty-state">还没有虚拟主机，<a href="cart.php">去选购</a></div>
                <?php else: ?>
                <div class="vhost-list">
                    <?php foreach ($vhostList as $v):
                        $daysLeft = $v['expire_time'] ? max(0, floor((strtotime($v['expire_time']) - time()) / 86400)) : 999;
                        $daysClass = $daysLeft <= 7 ? 'danger' : ($daysLeft <= 15 ? 'warning' : 'normal');
                    ?>
                    <div class="vhost-card">
                        <div class="vhost-header">
                            <span class="vhost-name"><?php echo h($v['model_name']); ?></span>
                            <span class="vhost-days <?php echo $daysClass; ?>"><?php echo $v['expire_time'] ? $daysLeft.'天' : '永久'; ?></span>
                        </div>
                        <div class="vhost-info">
                            <div class="info-row"><span>账号</span><span class="copyable" onclick="copyText(this)"><?php echo h($v['account']); ?></span></div>
                            <div class="info-row"><span>密码</span><span class="copyable" onclick="copyText(this)"><?php echo h($v['password']); ?></span></div>
                            <div class="info-row"><span>空间</span><span><?php echo $v['web_space']>=1024?round($v['web_space']/1024,1).'GB':$v['web_space'].'MB'; ?> / <?php echo $v['db_space']>=1024?round($v['db_space']/1024,1).'GB':$v['db_space'].'MB'; ?></span></div>
                            <div class="info-row"><span>到期</span><span><?php echo $v['expire_time']?date('Y-m-d',strtotime($v['expire_time'])):'永久'; ?></span></div>
                            <div class="info-row"><span>MNBT</span><span class="badge <?php echo $v['mnbt_opened']?'badge-green':'badge-red'; ?>"><?php echo $v['mnbt_opened']?'已开通':'未开通'; ?></span></div>
                        </div>
                        <div class="vhost-actions">
                        <form method="post" onsubmit="return confirm('确定续费？将扣除对应积分')">
                            <input type="hidden" name="action" value="renew">
                            <input type="hidden" name="vhost_id" value="<?php echo $v['id']; ?>">
                            <button type="submit" class="btn-primary btn-sm">续费30天</button>
                        </form>
                        <?php if ($v['mnbt_opened']): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="mnbt_login">
                            <input type="hidden" name="vhost_id" value="<?php echo $v['id']; ?>">
                            <button type="submit" class="btn-primary btn-sm" style="background:linear-gradient(135deg,#6c5ce7,#a29bfe)">立即登录</button>
                        </form>
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="panel-tickets" class="panel-section" style="display:none">
            <div class="section-card">
                <div class="section-header">
                    <h3>我的工单</h3>
                    <button class="btn-primary btn-sm" onclick="document.getElementById('ticket-form-new').style.display='block';this.style.display='none'">+ 新建工单</button>
                </div>
                <div id="ticket-form-new" style="display:none;margin-bottom:20px;padding:20px;border-radius:12px;background:var(--bg-card,#f8f9fa)">
                    <form method="post">
                        <input type="hidden" name="action" value="create_ticket">
                        <div class="form-group">
                            <label>标题</label>
                            <input type="text" name="subject" maxlength="200" required placeholder="简要描述您的问题">
                        </div>
                        <div class="form-group">
                            <label>关联主机 <span style="color:var(--gray-500,#999);font-weight:normal">(可选)</span></label>
                            <select name="vhost_id" class="form-control">
                                <option value="">不关联</option>
                                <?php foreach($vhostList as $vh): ?>
                                <option value="<?php echo $vh['id']; ?>"><?php echo h($vh['model_name'].' - '.$vh['account']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>问题描述</label>
                            <textarea name="content" rows="4" required placeholder="请详细描述您遇到的问题..."></textarea>
                        </div>
                        <div style="display:flex;gap:10px">
                            <button type="submit" class="btn-primary btn-sm">提交工单</button>
                            <button type="button" class="btn-sm" style="background:var(--gray-200,#e5e7eb);color:#666;border:none;padding:6px 16px;border-radius:8px;cursor:pointer" onclick="document.getElementById('ticket-form-new').style.display='none';this.parentElement.previousElementSibling.previousElementSibling.parentElement.parentElement.querySelector('.section-header .btn-primary').style.display=''">取消</button>
                        </div>
                    </form>
                </div>
                <?php
                $tkList = $DB->prepare("SELECT t.*, vm.name as model_name, vh.account as vhost_account FROM tickets t LEFT JOIN vhosts vh ON t.vhost_id=vh.id LEFT JOIN vhost_models vm ON vh.model_id=vm.id WHERE t.user_id=? ORDER BY t.updated_at DESC");
                $tkList->execute([$user['id']]);
                $tickets = $tkList->fetchAll();
                $statusMap = [0=>'待处理',1=>'已回复',2=>'已关闭'];
                $statusColor = [0=>'#f59e0b',1=>'#10b981',2=>'#9ca3af'];
                if (empty($tickets)):
                ?>
                <div class="empty-state"><i class="fas fa-ticket-alt" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i><p>暂无工单</p></div>
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
                        <div style="font-size:.8rem;color:#999;margin-top:4px">关联主机：<?php echo h($tk['model_name'].' - '.$tk['vhost_account']); ?></div>
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
                                    <span class="hp-reply-author"><?php echo $isAdminReply ? '管理员' : h($user['nickname'] ?: substr($user['email'],0,3).'***'); ?></span>
                                    <span class="hp-reply-time"><?php echo date('Y-m-d H:i', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div class="hp-reply-content"><?php echo nl2br(h($r['content'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($tk['status'] != 2): ?>
                            <div class="hp-reply-form" onclick="event.stopPropagation()">
                                <form method="post">
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo $tk['id']; ?>">
                                    <textarea name="content" rows="3" required placeholder="输入回复内容..." onclick="event.stopPropagation()"></textarea>
                                    <div style="display:flex;gap:8px;margin-top:8px">
                                        <button type="submit" class="btn-primary btn-sm">回复</button>
                                        <button type="submit" name="action" value="close_ticket" class="btn-sm" style="background:#ef4444;color:#fff;border:none;padding:6px 16px;border-radius:8px;cursor:pointer" onclick="return confirm('确定关闭此工单？')">关闭工单</button>
                                    </div>
                                </form>
                            </div>
                            <?php else: ?>
                            <div style="text-align:center;padding:12px;color:#999;font-size:.85rem">工单已关闭</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="panel-referral" class="panel-section" style="display:none">
            <?php if (!$referralEnabled): ?>
            <div class="section-card">
                <div class="empty-state">
                    <i class="fas fa-users-slash" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i>
                    <p>推荐奖励功能已关闭</p>
                </div>
            </div>
            <?php else: ?>
            <div class="section-card">
                <h3><i class="fas fa-gift"></i> 我的推荐码</h3>
                <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:24px;border-radius:16px;text-align:center;margin:16px 0">
                    <p style="font-size:.9rem;opacity:0.9;margin-bottom:8px">分享您的推荐码，好友注册双方各得 <?php echo $referralReward; ?> 积分</p>
                    <div style="font-size:2rem;font-weight:700;letter-spacing:4px;margin:16px 0" id="myInviteCode"><?php echo h($inviteCode); ?></div>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                        <button onclick="copyInviteCode()" class="btn-primary" style="background:rgba(255,255,255,0.2);border:none"><i class="fas fa-copy"></i> 复制推荐码</button>
                        <button onclick="shareToFriend()" class="btn-primary" style="background:rgba(255,255,255,0.2);border:none"><i class="fas fa-share"></i> 分享链接</button>
                    </div>
                </div>
                <div style="background:#f8f9fa;padding:16px;border-radius:12px;margin-top:16px">
                    <p style="font-size:.85rem;color:#666;line-height:1.8">
                        <strong>如何获得推荐奖励？</strong><br>
                        1. 复制您的推荐码或分享专属链接<br>
                        2. 好友注册时输入您的推荐码<br>
                        3. 好友注册成功，双方各获得 <strong style="color:#667eea"><?php echo $referralReward; ?> 积分</strong>
                    </p>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> 推荐记录</h3>
                    <span class="badge">已推荐 <?php echo count($referralList); ?> 人</span>
                </div>
                <?php if (empty($referralList)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends" style="font-size:3rem;opacity:0.3;margin-bottom:16px;display:block"></i>
                    <p>暂无推荐记录</p>
                    <p style="font-size:.85rem;color:#999;margin-top:8px">分享您的推荐码给好友开始赚取积分吧！</p>
                </div>
                <?php else: ?>
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid #eee">
                            <th style="text-align:left;padding:12px 8px;color:#666;font-size:.85rem">被推荐人</th>
                            <th style="text-align:center;padding:12px 8px;color:#666;font-size:.85rem">获得积分</th>
                            <th style="text-align:right;padding:12px 8px;color:#666;font-size:.85rem">推荐时间</th>
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
    </div>
</div>

<script>
function copyInviteCode() {
    var code = document.getElementById('myInviteCode').textContent;
    navigator.clipboard.writeText(code).then(function() {
        alert('推荐码已复制到剪贴板！');
    }).catch(function() {
        var input = document.createElement('input');
        input.value = code;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('推荐码已复制到剪贴板！');
    });
}
function shareToFriend() {
    var code = document.getElementById('myInviteCode').textContent;
    var shareUrl = window.location.origin + '/login.php?mode=register&invite=' + code;
    var text = '注册即送积分！使用我的推荐码 ' + code + ' 注册，双方各获积分奖励！';
    
    if (navigator.share) {
        navigator.share({
            title: '推荐注册',
            text: text,
            url: shareUrl
        }).catch(function() {});
    } else {
        navigator.clipboard.writeText(shareUrl).then(function() {
            alert('分享链接已复制到剪贴板！\n\n链接：' + shareUrl);
        }).catch(function() {
            prompt('请复制以下分享链接：', shareUrl);
        });
    }
}
</script>

<style>
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
</style>

<script>
function showPanel(id){
    document.querySelectorAll('.panel-section').forEach(function(el){el.style.display='none'});
    document.querySelectorAll('.panel-nav-item').forEach(function(el){el.classList.remove('active')});
    document.getElementById('panel-'+id).style.display='block';
    event.target.classList.add('active');
}
function toggleTicketDetail(id){
    var el=document.getElementById('ticket-detail-'+id);
    el.style.display=el.style.display==='none'?'block':'none';
}
function copyText(el){
    var r=document.createRange();r.selectNode(el);window.getSelection().removeAllRanges();window.getSelection().addRange(r);
    try{document.execCommand('copy');el.classList.add('copied');setTimeout(function(){el.classList.remove('copied')},1000)}catch(e){}
    window.getSelection().removeAllRanges();
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
</script>
<!-- ========== 个人中心HTML区域结束 ========== -->
<?php renderFooter(); ?>
