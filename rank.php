<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';

$totalIssued = 0;
$totalUsers = 0;
$rankRows = [];
try {
    $totalIssued = intval($DB->query("SELECT COALESCE(SUM(delta),0) s FROM points_log WHERE delta>0")->fetch()['s']);
    $totalUsers = intval($DB->query("SELECT COUNT(*) c FROM users")->fetch()['c']);
    $rankRows = $DB->query("SELECT pl.user_id, u.email, u.nickname, SUM(pl.delta) earned FROM points_log pl LEFT JOIN users u ON pl.user_id=u.id WHERE pl.delta>0 GROUP BY pl.user_id ORDER BY earned DESC LIMIT 50")->fetchAll();
} catch (Exception $e) {}

renderHeader('公益公示 · 积分贡献榜', '');
?>
<style>
.rank-wrap{max-width:880px;margin:40px auto;padding:0 16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;color:#1f2937}
.rank-hero{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:18px;padding:32px;margin-bottom:24px;box-shadow:0 10px 30px rgba(99,102,241,.25)}
.rank-hero h1{margin:0 0 8px;font-size:1.6rem}
.rank-hero p{margin:4px 0;opacity:.92}
.rank-stats{display:flex;gap:16px;margin-top:18px;flex-wrap:wrap}
.rank-stat{background:rgba(255,255,255,.15);border-radius:12px;padding:12px 18px;min-width:140px}
.rank-stat .num{font-size:1.4rem;font-weight:700}
.rank-stat .lbl{font-size:.8rem;opacity:.85}
.rank-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.04)}
.rank-card h2{margin:0;padding:18px 22px;font-size:1.1rem;border-bottom:1px solid #f1f5f9}
.rank-table{width:100%;border-collapse:collapse}
.rank-table th,.rank-table td{padding:12px 18px;text-align:left;font-size:.92rem}
.rank-table thead th{background:#f8fafc;color:#64748b;font-weight:600}
.rank-table tbody tr:nth-child(even){background:#fafbff}
.rank-no{display:inline-flex;width:26px;height:26px;align-items:center;justify-content:center;border-radius:50%;background:#eef2ff;color:#6366f1;font-weight:700;font-size:.85rem}
.rank-no.top{background:#fde68a;color:#92400e}
.rank-amt{color:#059669;font-weight:700}
.rank-empty{text-align:center;color:#94a3b8;padding:40px}
.rank-foot{text-align:center;color:#94a3b8;font-size:.8rem;margin:20px 0}
.rank-back{display:inline-block;margin-top:8px;color:#6366f1;text-decoration:none;font-weight:600}
</style>
<div class="rank-wrap">
    <div class="rank-hero">
        <h1>🤝 公益云 · 积分贡献榜</h1>
        <p>感谢每一位伙伴的参与，积分由签到与充值共同汇聚，用于兑换虚拟主机。</p>
        <div class="rank-stats">
            <div class="rank-stat"><div class="num"><?php echo number_format($totalIssued); ?></div><div class="lbl">平台累计发放积分</div></div>
            <div class="rank-stat"><div class="num"><?php echo number_format($totalUsers); ?></div><div class="lbl">注册用户数</div></div>
            <div class="rank-stat"><div class="num"><?php echo count($rankRows); ?></div><div class="lbl">贡献者人数</div></div>
        </div>
    </div>

    <div class="rank-card">
        <h2>🏆 积分贡献 TOP 榜</h2>
        <?php if(empty($rankRows)): ?>
        <div class="rank-empty">还没有积分发放记录，快来签到或充值成为第一位贡献者吧！</div>
        <?php else: ?>
        <table class="rank-table">
            <thead><tr><th>排名</th><th>用户</th><th>累计获得积分</th></tr></thead>
            <tbody>
            <?php foreach($rankRows as $i=>$r): ?>
            <tr>
                <td><span class="rank-no <?php echo $i<3?'top':''; ?>"><?php echo $i+1; ?></span></td>
                <td><?php echo h($r['nickname'] ?: ($r['email'] ?? '匿名用户')); ?></td>
                <td class="rank-amt">+<?php echo number_format($r['earned']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="rank-foot">
        本榜单仅展示累计获得积分（签到 + 充值 + 邀请），不含消耗。<br>
        <a class="rank-back" href="index.php">← 返回首页</a>
    </div>
</div>
<?php renderFooter(); ?>
