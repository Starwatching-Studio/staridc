<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
include ROOT . 'rd/MNBT_API.php';

$user = getUser();
$loggedIn = isLogin();
$error = '';
$success = '';

// AJAX 验证优惠码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_coupon' && $loggedIn) {
    header('Content-Type: application/json');
    $code = trim($_POST['code'] ?? '');
    $modelId = intval($_POST['model_id'] ?? 0);
    $stmt = $DB->prepare("SELECT * FROM vhost_models WHERE id=? AND status=1");
    $stmt->execute([$modelId]);
    $model = $stmt->fetch();
    if (!$model) {
        echo json_encode(['valid' => false, 'message' => '主机型号不存在']);
        exit;
    }
    $stmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0");
    $stmt->execute([$code]);
    $cp = $stmt->fetch();
    if (!$cp) {
        echo json_encode(['valid' => false, 'message' => '优惠码无效或已使用']);
        exit;
    }
    $finalPrice = ceil($model['price'] * (100 - $cp['discount']) / 100);
    echo json_encode(['valid' => true, 'discount' => $cp['discount'], 'original_price' => $model['price'], 'final_price' => $finalPrice]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loggedIn) {
    $action = $_POST['action'] ?? '';
    if ($action === 'buy_host') {
        $modelId = intval($_POST['model_id'] ?? 0);
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $stmt = $DB->prepare("SELECT * FROM vhost_models WHERE id=? AND status=1");
        $stmt->execute([$modelId]);
        $model = $stmt->fetch();
        if (!$model) {
            $error = '该主机型号不存在或已下架';
        } else {
            $finalPrice = $model['price'];
            $couponDiscount = 0;
            $couponId = 0;
            if ($couponCode) {
                $stmt = $DB->prepare("SELECT * FROM coupons WHERE code=? AND status=0");
                $stmt->execute([$couponCode]);
                $cp = $stmt->fetch();
                if ($cp) {
                    $couponDiscount = $cp['discount'];
                    $couponId = $cp['id'];
                    $finalPrice = ceil($model['price'] * (100 - $couponDiscount) / 100);
                } else {
                    $error = '优惠码无效或已使用';
                }
            }
            if (!$error && $user['points'] < $finalPrice) {
                $error = '积分不足，请先充值积分';
            }
            if (!$error) {
                $vhostCount = $DB->prepare("SELECT COUNT(*) as c FROM vhosts WHERE user_id=?");
                $vhostCount->execute([$user['id']]);
                if ($vhostCount->fetch()['c'] >= 5) {
                    $error = '每人最多购买5台虚拟主机';
                } else {
                    $account = genVhostAccount($user['id'], $modelId);
                    $password = genVhostPassword();
                    $expireDate = date('Y-m-d', strtotime('+30 days'));
                    $mnbtResult = MNBT_API::openHost($account, $password, $model['web_space'], $model['db_space'], $model['flow'], $model['domain_limit'], $expireDate);
                    if ($mnbtResult['success']) {
                        if ($couponId) {
                            $cpStmt = $DB->prepare("UPDATE coupons SET status=1, used_by=?, used_at=NOW() WHERE id=? AND status=0");
                            $cpStmt->execute([$user['id'], $couponId]);
                        }
                        $stmt2 = $DB->prepare("UPDATE users SET points=points-? WHERE id=?");
                        $stmt2->execute([$finalPrice, $user['id']]);
                        $expireTime = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $stmt3 = $DB->prepare("INSERT INTO vhosts(user_id,model_id,account,password,mnbt_opened,expire_time) VALUES(?,?,?,?,1,?)");
                        $stmt3->execute([$user['id'], $modelId, $account, $password, $expireTime]);
                        $discountInfo = $couponDiscount ? "（优惠码省了 " . ($model['price'] - $finalPrice) . " 积分）" : "";
                        $success = '主机开通成功！账号：' . h($account) . $discountInfo;
                        $user = getUser();
                        if (conf('mail_notify_host') === '1') {
                            $notifySubject = '主机开通成功 - ' . conf('site_name', '云主机');
                            $notifyBody = "您的虚拟主机已成功开通！\n\n"
                                . "型号：" . $model['name'] . "\n"
                                . "账号：" . $account . "\n"
                                . "密码：" . $password . "\n"
                                . "到期时间：" . date('Y-m-d', strtotime($expireTime)) . "\n\n"
                                . "请及时登录管理面板查看。";
                            Mailer::sendNotify($user['email'], $notifySubject, $notifyBody);
                        }
                    } else {
                        $error = 'MNBT开通失败：' . $mnbtResult['message'];
                    }
                }
            }
        }
    }
}

$models = $DB->query("SELECT * FROM vhost_models WHERE status=1 ORDER BY sort_order,id")->fetchAll();

renderHeader('选购主机');
?>
<!-- ========== 购物中心HTML区域 ========== -->
<div class="page-header">
    <h1>🛒 选购主机</h1>
    <p>选择适合您的主机方案，使用积分兑换</p>
</div>

<?php if ($error): ?><div class="msg msg-error"><?php echo h($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="msg msg-success"><?php echo h($success); ?></div><?php endif; ?>

<?php if (!$loggedIn): ?>
<div class="msg msg-warn">请先 <a href="login.php">登录</a> 后再购买主机</div>
<?php else: ?>
<div class="user-points-bar">
    <span>💰 当前积分：<strong><?php echo $user['points']; ?></strong></span>
    <a href="personalpanel.php" class="btn-sm">充值积分</a>
</div>
<?php endif; ?>

<div class="product-grid">
    <?php foreach ($models as $m): ?>
    <div class="product-card">
        <div class="product-name"><?php echo h($m['name']); ?></div>
        <div class="product-price"><?php echo $m['price']; ?> <span>积分/月</span></div>
        <ul class="product-features">
            <li>💾 网页空间：<?php echo $m['web_space'] >= 1024 ? round($m['web_space']/1024,1).'GB' : $m['web_space'].'MB'; ?></li>
            <li>🗄️ 数据库：<?php echo $m['db_space'] >= 1024 ? round($m['db_space']/1024,1).'GB' : $m['db_space'].'MB'; ?></li>
            <li>📊 月流量：<?php echo $m['flow']; ?>GB</li>
            <li>🌐 域名绑定：<?php echo $m['domain_limit']; ?>个</li>
        </ul>
        <?php if ($loggedIn): ?>
        <button type="button" class="btn-primary" style="width:100%" <?php echo $user['points'] < $m['price'] ? 'disabled' : ''; ?>
            onclick="openBuyModal(<?php echo $m['id']; ?>, <?php echo $m['price']; ?>)">
            <?php echo $user['points'] < $m['price'] ? '积分不足' : '立即购买'; ?>
        </button>
        <?php else: ?>
        <a href="login.php" class="btn-primary" style="width:100%;display:block;text-align:center">登录后购买</a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($models)): ?>
    <div class="empty-state">暂无可购买的主机型号</div>
    <?php endif; ?>
</div>
<!-- ========== 购物中心HTML区域结束 ========== -->
<!-- 购买确认弹窗 -->
<div id="buyOverlay" class="modal-overlay" style="display:none"></div>
<div id="buyModal" class="buy-modal" style="display:none">
    <div class="buy-modal-content">
        <div class="buy-modal-header">
            <h3><i class="fas fa-shopping-cart"></i> 确认购买</h3>
            <button type="button" class="modal-close" onclick="closeBuyModal()">&times;</button>
        </div>
        <div class="buy-modal-body">
            <p class="confirm-text" id="confirmText">确定花费 <strong id="displayPrice">0</strong> 积分购买此主机？</p>

            <div class="coupon-section">
                <div class="coupon-toggle" onclick="toggleCoupon()">
                    <i class="fas fa-ticket-alt"></i> 有优惠码？
                    <i class="fas fa-chevron-down" id="couponArrow"></i>
                </div>
                <div id="couponArea" class="coupon-area" style="display:none">
                    <div class="coupon-row">
                        <input type="text" id="couponInput" placeholder="输入优惠码" maxlength="32" autocomplete="off">
                        <button type="button" class="btn-coupon" onclick="applyCoupon()">使用</button>
                    </div>
                    <div id="couponMsg" class="coupon-msg"></div>
                    <div id="couponDiscountInfo" class="coupon-discount-info" style="display:none">
                        <i class="fas fa-check-circle"></i>
                        优惠已应用：<strong id="discountPercent">0</strong>% 折扣
                        &nbsp;|&nbsp; 原价 <s id="originalPrice">0</s> 积分
                        &nbsp;→&nbsp; <strong id="discountedPrice" style="color:var(--red)">0</strong> 积分
                        <button type="button" class="btn-remove-coupon" onclick="removeCoupon()"><i class="fas fa-times"></i> 移除</button>
                    </div>
                </div>
            </div>

            <div class="user-balance" id="balanceInfo">
                当前积分：<strong id="userPointsDisplay"><?php echo $user['points']; ?></strong>
                <span id="balanceAfter" style="display:none;margin-left:8px">
                    → 剩余：<strong id="remainingPoints" style="color:var(--green)">0</strong>
                </span>
            </div>
        </div>
        <div class="buy-modal-footer">
            <button type="button" class="btn-cancel" onclick="closeBuyModal()">取消</button>
            <button type="button" class="btn-confirm" id="confirmBuyBtn" onclick="confirmBuy()">确认购买</button>
        </div>
    </div>
</div>

<!-- 隐藏表单，用于最终提交 -->
<form id="buyForm" method="post" style="display:none">
    <input type="hidden" name="action" value="buy_host">
    <input type="hidden" name="model_id" id="formModelId" value="">
    <input type="hidden" name="coupon_code" id="formCouponCode" value="">
</form>

<style>
.modal-overlay {
    position: fixed; top:0; left:0; right:0; bottom:0;
    background: rgba(0,0,0,.55); z-index:9999;
    backdrop-filter: blur(4px);
    animation: fadeIn .2s ease;
}
.buy-modal {
    position: fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    z-index:10000; width:440px; max-width:92vw;
    animation: slideUp .25s ease;
}
.buy-modal-content {
    background: #fff; border-radius:16px; overflow:hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.buy-modal-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px 0;
}
.buy-modal-header h3 { margin:0; font-size:1.15rem; }
.buy-modal-header h3 i { margin-right:8px; color:var(--primary, #6366f1); }
.modal-close {
    background:none; border:none; font-size:1.6rem; cursor:pointer;
    color:#999; line-height:1; padding:0 4px;
}
.modal-close:hover { color:#333; }
.buy-modal-body { padding:16px 24px; }
.confirm-text { margin:0 0 16px; font-size:1rem; color:#333; }
.confirm-text strong { color:var(--primary, #6366f1); font-size:1.1rem; }

.coupon-section {
    background:#f8f9fa; border-radius:10px; padding:12px 14px; margin-bottom:12px;
}
.coupon-toggle {
    display:flex; align-items:center; gap:8px;
    cursor:pointer; color:var(--primary, #6366f1); font-weight:500; font-size:.95rem;
    user-select:none;
}
.coupon-toggle i:first-child { font-size:1rem; }
.coupon-toggle .fa-chevron-down { margin-left:auto; transition:transform .2s; font-size:.8rem; }
.coupon-toggle .fa-chevron-down.rotated { transform:rotate(180deg); }
.coupon-area { margin-top:10px; }
.coupon-row {
    display:flex; gap:8px;
}
.coupon-row input {
    flex:1; padding:9px 12px; border:2px solid #dee2ed; border-radius:8px;
    font-size:.92rem; outline:none; transition:border .2s;
}
.coupon-row input:focus { border-color:var(--primary, #6366f1); }
.btn-coupon {
    padding:9px 18px; background:var(--primary, #6366f1); color:#fff;
    border:none; border-radius:8px; cursor:pointer; font-weight:500; font-size:.9rem;
    white-space:nowrap; transition:opacity .2s;
}
.btn-coupon:hover { opacity:.88; }
.btn-coupon:disabled { opacity:.5; cursor:not-allowed; }
.coupon-msg { font-size:.85rem; margin-top:6px; min-height:20px; }
.coupon-msg.error { color:#e74c3c; }
.coupon-msg.success { color:var(--green, #10b981); }
.coupon-msg.loading { color:#999; }
.coupon-discount-info {
    margin-top:8px; padding:8px 12px; background:#ecfdf5; border-radius:8px;
    font-size:.85rem; color:#065f46; display:flex; align-items:center; flex-wrap:wrap; gap:4px;
}
.coupon-discount-info i { color:var(--green, #10b981); font-size:1rem; }
.coupon-discount-info s { color:#999; }
.btn-remove-coupon {
    background:none; border:none; color:#999; cursor:pointer;
    font-size:.8rem; padding:2px 6px; margin-left:auto;
}
.btn-remove-coupon:hover { color:#e74c3c; }

.user-balance { font-size:.9rem; color:#666; padding:4px 0; }

.buy-modal-footer {
    display:flex; gap:10px; padding:0 24px 20px; justify-content:flex-end;
}
.btn-cancel {
    padding:10px 24px; background:#f1f3f5; color:#555; border:none;
    border-radius:10px; cursor:pointer; font-weight:500; font-size:.9rem;
    transition:background .2s;
}
.btn-cancel:hover { background:#e9ecef; }
.btn-confirm {
    padding:10px 28px; background:var(--primary, #6366f1); color:#fff;
    border:none; border-radius:10px; cursor:pointer; font-weight:600; font-size:.9rem;
    transition:opacity .2s;
}
.btn-confirm:hover { opacity:.88; }
.btn-confirm:disabled { opacity:.5; cursor:not-allowed; }

@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes slideUp { from{opacity:0;transform:translate(-50%,-50%) scale(.94)} to{opacity:1;transform:translate(-50%,-50%) scale(1)} }
</style>

<script>
var currentModelId = 0;
var currentPrice = 0;
var currentDiscount = 0;
var appliedCouponCode = '';
var userPoints = <?php echo $user['points']; ?>;

function openBuyModal(modelId, price) {
    currentModelId = modelId;
    currentPrice = price;
    currentDiscount = 0;
    appliedCouponCode = '';
    document.getElementById('formModelId').value = modelId;
    document.getElementById('formCouponCode').value = '';
    document.getElementById('displayPrice').textContent = price;
    document.getElementById('userPointsDisplay').textContent = userPoints;
    document.getElementById('couponInput').value = '';
    document.getElementById('couponMsg').textContent = '';
    document.getElementById('couponMsg').className = 'coupon-msg';
    document.getElementById('couponDiscountInfo').style.display = 'none';
    document.getElementById('balanceAfter').style.display = 'none';
    document.getElementById('confirmText').innerHTML = '确定花费 <strong id="displayPrice">' + price + '</strong> 积分购买此主机？';
    document.getElementById('confirmBuyBtn').disabled = false;
    document.getElementById('buyForm').reset();
    document.getElementById('buyForm').querySelector('input[name="action"]').value = 'buy_host';
    document.getElementById('buyOverlay').style.display = 'block';
    document.getElementById('buyModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeBuyModal() {
    document.getElementById('buyOverlay').style.display = 'none';
    document.getElementById('buyModal').style.display = 'none';
    document.body.style.overflow = '';
}

function toggleCoupon() {
    var area = document.getElementById('couponArea');
    var arrow = document.getElementById('couponArrow');
    if (area.style.display === 'none') {
        area.style.display = 'block';
        arrow.classList.add('rotated');
    } else {
        area.style.display = 'none';
        arrow.classList.remove('rotated');
    }
}

function applyCoupon() {
    var code = document.getElementById('couponInput').value.trim();
    var msg = document.getElementById('couponMsg');
    if (!code) { msg.textContent = '请输入优惠码'; msg.className = 'coupon-msg error'; return; }

    msg.textContent = '验证中...'; msg.className = 'coupon-msg loading';
    document.querySelector('.btn-coupon').disabled = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        document.querySelector('.btn-coupon').disabled = false;
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.valid) {
                currentDiscount = res.discount;
                appliedCouponCode = code;
                var finalP = res.final_price;

                document.getElementById('discountPercent').textContent = res.discount;
                document.getElementById('originalPrice').textContent = res.original_price;
                document.getElementById('discountedPrice').textContent = finalP;
                document.getElementById('couponDiscountInfo').style.display = 'flex';
                document.getElementById('couponMsg').textContent = '';
                document.getElementById('couponMsg').className = 'coupon-msg';

                var dp = document.getElementById('displayPrice');
                dp.textContent = finalP;
                var remain = userPoints - finalP;
                document.getElementById('remainingPoints').textContent = remain;
                document.getElementById('balanceAfter').style.display = 'inline';

                if (userPoints < finalP) {
                    document.getElementById('confirmBuyBtn').disabled = true;
                    msg.textContent = '积分不足，请先充值'; msg.className = 'coupon-msg error';
                } else {
                    document.getElementById('confirmBuyBtn').disabled = false;
                }
            } else {
                msg.textContent = res.message; msg.className = 'coupon-msg error';
            }
        } catch(e) {
            msg.textContent = '验证失败，请重试'; msg.className = 'coupon-msg error';
        }
    };
    xhr.send('action=check_coupon&code=' + encodeURIComponent(code) + '&model_id=' + currentModelId);
}

function removeCoupon() {
    currentDiscount = 0;
    appliedCouponCode = '';
    document.getElementById('couponDiscountInfo').style.display = 'none';
    document.getElementById('couponInput').value = '';
    document.getElementById('couponMsg').textContent = '';
    document.getElementById('couponMsg').className = 'coupon-msg';
    document.getElementById('displayPrice').textContent = currentPrice;
    document.getElementById('balanceAfter').style.display = 'none';
    document.getElementById('confirmBuyBtn').disabled = userPoints < currentPrice;
}

function confirmBuy() {
    document.getElementById('formModelId').value = currentModelId;
    document.getElementById('formCouponCode').value = appliedCouponCode;
    document.getElementById('confirmBuyBtn').disabled = true;
    document.getElementById('buyForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('buyOverlay');
    overlay.addEventListener('click', closeBuyModal);
});
</script>
<?php renderFooter(); ?>
