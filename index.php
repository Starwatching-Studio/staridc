<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
$siteName = h(conf('site_name', '云主机'));

$totalUsers = $DB->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$totalVhosts = $DB->query("SELECT COUNT(*) as c FROM vhosts")->fetch()['c'];
$totalOrders = $DB->query("SELECT COUNT(*) as c FROM orders WHERE status=1")->fetch()['c'];
$models = $DB->query("SELECT * FROM vhost_models WHERE status=1 ORDER BY sort_order,id")->fetchAll();

renderHeader('', '<style>
/* ========== 首页样式 - 双语优化版 ========== */
.hp-hero{position:relative;text-align:center;padding:80px 24px 60px;overflow:hidden}
.hp-hero::before{content:"";position:absolute;top:-40%;left:50%;transform:translateX(-50%);width:900px;height:900px;background:radial-gradient(circle,var(--accent)15,transparent 60%);pointer-events:none;z-index:0}
.hp-hero::after{content:"";position:absolute;bottom:-30%;right:-10%;width:500px;height:500px;background:radial-gradient(circle,var(--accent-light)12,transparent 60%);pointer-events:none;z-index:0}
.hp-hero>*{position:relative;z-index:1}
.hp-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:50px;font-size:.82rem;font-weight:600;color:var(--accent);background:var(--bg);box-shadow:4px 4px 8px var(--shadow-dark),-4px -4px 8px var(--shadow-light);margin-bottom:24px;white-space:nowrap}
.hp-badge .dot{width:8px;height:8px;border-radius:50%;background:var(--success);animation:hp-pulse 2s infinite;flex-shrink:0}
@keyframes hp-pulse{0%,100%{opacity:1}50%{opacity:.4}}
.hp-hero h1{font-size:3.4rem;font-weight:900;letter-spacing:-1.5px;margin-bottom:0;line-height:1.2;word-break:break-word}
.hp-hero h1 .grad{background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hp-hero .hp-desc{font-size:1.05rem;color:var(--muted);max-width:680px;margin:16px auto 0;line-height:1.7;word-break:break-word;overflow-wrap:break-word}
.hp-hero .actions{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.hp-stats-row{display:flex;gap:40px;justify-content:center;margin-top:48px;flex-wrap:wrap}
.hp-stat{text-align:center;min-width:100px}
.hp-stat .num{font-size:2.2rem;font-weight:900;background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hp-stat .label{font-size:.85rem;color:var(--muted);margin-top:4px;white-space:nowrap}

/* 产品展示 */
.hp-products{padding:60px 0}
.hp-section-title{text-align:center;margin-bottom:48px;padding:0 16px}
.hp-section-title h2{font-size:2rem;font-weight:800;margin-bottom:10px;word-break:break-word}
.hp-section-title p{color:var(--muted);font-size:1.05rem;max-width:600px;margin:0 auto;word-break:break-word;overflow-wrap:break-word}
.hp-product-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:24px}
.hp-product-card{background:var(--bg);border-radius:var(--radius-lg);padding:32px 24px;text-align:center;box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition);position:relative;overflow:hidden;display:flex;flex-direction:column}
.hp-product-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:var(--gradient-accent);transform:scaleX(0);transition:transform var(--transition)}
.hp-product-card:hover{transform:translateY(-8px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-product-card:hover::before{transform:scaleX(1)}
.hp-product-card .name{font-size:1.2rem;font-weight:700;margin-bottom:12px;word-break:break-word}
.hp-product-card .price{font-size:2.4rem;font-weight:900;background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:6px;word-break:break-word}
.hp-product-card .price span{font-size:.9rem;font-weight:500;color:var(--muted)}
.hp-product-card .specs{list-style:none;text-align:left;margin:20px 0;flex:1}
.hp-product-card .specs li{padding:8px 0;border-bottom:1px solid rgba(100,120,110,.1);font-size:.88rem;color:var(--fg);word-break:break-word}
.hp-product-card .specs li::before{content:"✓ ";color:var(--success);font-weight:bold}

/* 特性区 */
.hp-features{padding:60px 0}
.hp-feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}
.hp-feature-card{background:var(--bg);border-radius:var(--radius-lg);padding:36px 28px;box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition);position:relative;overflow:hidden;display:flex;flex-direction:column;min-height:200px}
.hp-feature-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:var(--gradient-accent);transform:scaleX(0);transition:transform var(--transition)}
.hp-feature-card:hover{transform:translateY(-6px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-feature-card:hover::before{transform:scaleX(1)}
.hp-feature-card .icon{width:56px;height:56px;border-radius:14px;background:var(--gradient-accent);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;flex-shrink:0;box-shadow:0 4px 12px rgba(45,139,107,.25)}
.hp-feature-card .icon svg{width:28px;height:28px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hp-feature-card h3{font-size:1.15rem;margin-bottom:10px;word-break:break-word}
.hp-feature-card p{color:var(--muted);font-size:.92rem;line-height:1.7;word-break:break-word;overflow-wrap:break-word;hyphens:auto}

/* 流程区 */
.hp-steps-section{padding:60px 0}
.hp-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;position:relative}
.hp-step{text-align:center;padding:32px 20px;background:var(--bg);border-radius:var(--radius-lg);box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition);display:flex;flex-direction:column;align-items:center;min-height:180px}
.hp-step:hover{transform:translateY(-4px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-step .num{width:52px;height:52px;border-radius:50%;background:var(--gradient-accent);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;margin-bottom:16px;flex-shrink:0;box-shadow:0 4px 15px rgba(45,139,107,.4)}
.hp-step h3{font-size:1.1rem;margin-bottom:8px;word-break:break-word}
.hp-step p{color:var(--muted);font-size:.88rem;word-break:break-word;overflow-wrap:break-word}

/* CTA */
.hp-cta{text-align:center;padding:70px 24px;margin-top:20px}
.hp-cta-box{background:var(--gradient-accent);border-radius:var(--radius-xl);padding:56px 32px;box-shadow:0 12px 40px rgba(45,139,107,.3);position:relative;overflow:hidden}
.hp-cta-box::before{content:"";position:absolute;top:-50%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(255,255,255,.15),transparent 60%)}
.hp-cta-box h2{color:#fff;font-size:2rem;font-weight:800;margin-bottom:12px;position:relative;word-break:break-word}
.hp-cta-box p{color:rgba(255,255,255,.9);font-size:1.05rem;margin-bottom:28px;position:relative;max-width:500px;margin-left:auto;margin-right:auto;word-break:break-word;overflow-wrap:break-word}
.hp-cta-box .btn-white{display:inline-flex;padding:14px 36px;border-radius:var(--radius);background:#fff;color:var(--accent);font-size:1rem;font-weight:700;text-decoration:none;box-shadow:0 4px 15px rgba(0,0,0,.15);transition:all var(--transition);position:relative}
.hp-cta-box .btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.2)}

/* 中英文长度适配 */
html[lang="en"] .hp-hero .hp-desc{max-width:760px}
html[lang="en"] .hp-section-title p{max-width:680px}
html[lang="en"] .hp-product-card .specs li{font-size:.84rem}
html[lang="en"] .hp-feature-card p{font-size:.88rem;hyphens:auto}
html[lang="en"] .hp-cta-box p{max-width:560px}

@media(max-width:768px){
  .hp-hero h1{font-size:2.2rem}
  .hp-hero .hp-desc{font-size:.95rem}
  .hp-stats-row{gap:24px}
  .hp-stat .num{font-size:1.6rem}
  .hp-cta-box h2{font-size:1.5rem}
  .hp-feature-grid{grid-template-columns:1fr}
  .hp-steps{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){
  .hp-hero h1{font-size:1.8rem}
  .hp-hero .hp-desc{font-size:.9rem;max-width:100%}
  .hp-stats-row{gap:18px}
  .hp-steps{grid-template-columns:1fr}
}
</style>');
$jsonLd = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":' . json_encode(conf('site_name', 'StarIDC')) . ',"url":' . json_encode(siteUrl()) . '}</script>';
?>

<?php echo $jsonLd; ?>

<div class="hp-hero">
    <div class="hp-badge"><span class="dot"></span> <?php echo L('index_hero_badge'); ?></div>
    <h1><?php echo L('index_hero_line1'); ?><br><span class="grad"><?php echo L('index_hero_line2'); ?></span></h1>
    <p class="hp-desc"><?php echo L('index_hero_subtitle'); ?></p>
    <div class="actions">
        <a href="cart.php" class="btn-primary"><?php echo L('index_start_btn'); ?></a>
        <a href="login.php" class="btn-secondary"><?php echo L('nav_login_register'); ?></a>
    </div>
    <div class="hp-stats-row">
        <div class="hp-stat"><div class="num"><?php echo $totalUsers; ?></div><div class="label"><?php echo L('index_stats_users'); ?></div></div>
        <div class="hp-stat"><div class="num"><?php echo $totalVhosts; ?></div><div class="label"><?php echo L('index_stats_hosts'); ?></div></div>
        <div class="hp-stat"><div class="num"><?php echo $totalOrders; ?></div><div class="label"><?php echo L('index_stats_tickets'); ?></div></div>
        <div class="hp-stat"><div class="num">99.9%</div><div class="label"><?php echo L('index_stats_uptime'); ?></div></div>
    </div>
</div>

<?php
// 侧边栏广告（custom_links 按权重随机展示一个）
$adBox = getAdGlobal();
$adLinks = (!empty($adBox['enabled']) && !empty($adBox['custom_links']) && is_array($adBox['custom_links'])) ? $adBox['custom_links'] : [];
$adPick = null;
if ($adLinks) {
    $totalW = 0;
    foreach ($adLinks as $al) $totalW += max(1, intval($al['weight'] ?? 1));
    $r = mt_rand(1, max(1, $totalW));
    foreach ($adLinks as $al) { $r -= max(1, intval($al['weight'] ?? 1)); if ($r <= 0) { $adPick = $al; break; } }
}
?>
<?php if ($adPick): ?>
<div class="hp-sidead" id="hpSideAd">
    <span class="hp-sidead-close" onclick="document.getElementById('hpSideAd').style.display='none'">&times;</span>
    <span class="hp-sidead-tag">广告</span>
    <a href="<?php echo h($adPick['url']); ?>" target="_blank" rel="noopener"><?php echo h($adPick['name']); ?></a>
</div>
<style>
.hp-sidead{position:fixed;right:16px;bottom:96px;z-index:60;max-width:210px;background:var(--bg);border:1px solid var(--gray-200);border-radius:12px;padding:14px 16px;box-shadow:0 10px 30px rgba(0,0,0,.15)}
.hp-sidead .hp-sidead-tag{display:inline-block;font-size:.7rem;color:#fff;background:#ef4444;padding:1px 7px;border-radius:4px;margin-bottom:8px}
.hp-sidead a{color:var(--accent);font-weight:600;text-decoration:none;font-size:.92rem;line-height:1.4;word-break:break-word}
.hp-sidead .hp-sidead-close{position:absolute;top:4px;right:8px;cursor:pointer;color:#9ca3af;font-size:1.1rem;line-height:1}
@media(max-width:768px){.hp-sidead{display:none}}
</style>
<?php endif; ?>

<?php if (!empty($models)): ?>
<div class="hp-products">
    <div class="hp-section-title">
        <h2><?php echo L('index_products_title'); ?></h2>
        <p><?php echo L('site_slogan'); ?></p>
    </div>
    <div class="hp-product-grid">
        <?php foreach ($models as $m): ?>
        <div class="hp-product-card">
            <div class="name"><?php echo h($m['name']); ?></div>
            <div class="price"><?php echo h($m['price']); ?><span> <?php echo L('buy_points'); ?>/<?php echo L('buy_month'); ?></span></div>
            <ul class="specs">
                <li><?php echo L('buy_web_space'); ?>: <?php echo h($m['web_space']); ?> MB</li>
                <li><?php echo L('buy_db_space'); ?>: <?php echo h($m['db_space']); ?> MB</li>
                <li><?php echo L('buy_flow'); ?>: <?php echo h($m['flow']); ?> GB</li>
                <li><?php echo L('buy_domains'); ?>: <?php echo h($m['domain_limit']); ?> <?php echo L('panel_hosts_per_unit'); ?></li>
            </ul>
            <a href="cart.php" class="btn-primary btn-sm"><?php echo L('buy_confirm'); ?></a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="hp-features">
    <div class="hp-section-title">
        <h2><?php echo L('index_features_title'); ?></h2>
        <p><?php echo L('index_features_subtitle'); ?></p>
    </div>
    <div class="hp-feature-grid">
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
            <h3><?php echo L('index_feature_1_title'); ?></h3>
            <p><?php echo L('index_feature_1_desc'); ?></p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3><?php echo L('index_feature_2_title'); ?></h3>
            <p><?php echo L('index_feature_2_desc'); ?></p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9.5c0-1.4-1.3-2.5-3-2.5s-3 1.1-3 2.5c0 3 6 1.5 6 4.5 0 1.4-1.3 2.5-3 2.5s-3-1.1-3-2.5"/></svg></div>
            <h3><?php echo L('index_feature_3_title'); ?></h3>
            <p><?php echo L('index_feature_3_desc'); ?></p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg></div>
            <h3><?php echo L('index_feature_4_title'); ?></h3>
            <p><?php echo L('index_feature_4_desc'); ?></p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <h3><?php echo L('index_feature_5_title'); ?></h3>
            <p><?php echo L('index_feature_5_desc'); ?></p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <h3><?php echo L('index_feature_6_title'); ?></h3>
            <p><?php echo L('index_feature_6_desc'); ?></p>
        </div>
    </div>
</div>

<div class="hp-steps-section">
    <div class="hp-section-title">
        <h2><?php echo L('index_steps_title'); ?></h2>
        <p><?php echo L('index_steps_subtitle'); ?></p>
    </div>
    <div class="hp-steps">
        <div class="hp-step">
            <div class="num">1</div>
            <h3><?php echo L('index_step_1'); ?></h3>
            <p><?php echo L('index_step_1_desc'); ?></p>
        </div>
        <div class="hp-step">
            <div class="num">2</div>
            <h3><?php echo L('index_step_2'); ?></h3>
            <p><?php echo L('index_step_2_desc'); ?></p>
        </div>
        <div class="hp-step">
            <div class="num">3</div>
            <h3><?php echo L('index_step_3'); ?></h3>
            <p><?php echo L('index_step_3_desc'); ?></p>
        </div>
        <div class="hp-step">
            <div class="num">4</div>
            <h3><?php echo L('index_step_4'); ?></h3>
            <p><?php echo L('index_step_4_desc'); ?></p>
        </div>
    </div>
</div>

<div class="hp-cta">
    <div class="hp-cta-box">
        <h2><?php echo L('index_cta_title'); ?></h2>
        <p><?php echo L('index_cta_subtitle'); ?></p>
        <a href="login.php" class="btn-white"><?php echo L('index_cta_btn'); ?></a>
    </div>
</div>

<?php renderFooter(); ?>