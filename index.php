<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
$siteName = h(conf('site_name', '云主机'));

// 首页数据统计（用于展示信任指标）
$totalUsers = $DB->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$totalVhosts = $DB->query("SELECT COUNT(*) as c FROM vhosts")->fetch()['c'];
$totalOrders = $DB->query("SELECT COUNT(*) as c FROM orders WHERE status=1")->fetch()['c'];
$models = $DB->query("SELECT * FROM vhost_models WHERE status=1 ORDER BY sort_order,id")->fetchAll();

renderHeader('', '<style>
/* ========== 首页专属样式 - 使用主题CSS变量，自动适配各主题配色 ========== */
.hp-hero{position:relative;text-align:center;padding:80px 24px 60px;overflow:hidden}
.hp-hero::before{content:"";position:absolute;top:-40%;left:50%;transform:translateX(-50%);width:900px;height:900px;background:radial-gradient(circle,var(--accent)15,transparent 60%);pointer-events:none;z-index:0}
.hp-hero::after{content:"";position:absolute;bottom:-30%;right:-10%;width:500px;height:500px;background:radial-gradient(circle,var(--accent-light)12,transparent 60%);pointer-events:none;z-index:0}
.hp-hero>*{position:relative;z-index:1}
.hp-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:50px;font-size:.82rem;font-weight:600;color:var(--accent);background:var(--bg);box-shadow:4px 4px 8px var(--shadow-dark),-4px -4px 8px var(--shadow-light);margin-bottom:24px}
.hp-badge .dot{width:8px;height:8px;border-radius:50%;background:var(--success);animation:hp-pulse 2s infinite}
@keyframes hp-pulse{0%,100%{opacity:1}50%{opacity:.4}}
.hp-hero h1{font-size:3.4rem;font-weight:900;letter-spacing:-1.5px;margin-bottom:20px;line-height:1.15}
.hp-hero h1 .grad{background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hp-hero .sub{font-size:1.18rem;color:var(--muted);max-width:620px;margin:0 auto 36px;line-height:1.8}
.hp-hero .actions{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.hp-stats-row{display:flex;gap:40px;justify-content:center;margin-top:48px;flex-wrap:wrap}
.hp-stat{text-align:center}
.hp-stat .num{font-size:2.2rem;font-weight:900;background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hp-stat .label{font-size:.85rem;color:var(--muted);margin-top:4px}

/* 产品展示 */
.hp-products{padding:60px 0}
.hp-section-title{text-align:center;margin-bottom:48px}
.hp-section-title h2{font-size:2rem;font-weight:800;margin-bottom:10px}
.hp-section-title p{color:var(--muted);font-size:1.05rem}
.hp-product-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px}
.hp-product-card{background:var(--bg);border-radius:var(--radius-lg);padding:32px 24px;text-align:center;box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition);position:relative;overflow:hidden}
.hp-product-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:var(--gradient-accent);transform:scaleX(0);transition:transform var(--transition)}
.hp-product-card:hover{transform:translateY(-8px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-product-card:hover::before{transform:scaleX(1)}
.hp-product-card .name{font-size:1.2rem;font-weight:700;margin-bottom:12px}
.hp-product-card .price{font-size:2.4rem;font-weight:900;background:var(--gradient-accent);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:6px}
.hp-product-card .price span{font-size:.9rem;font-weight:500;color:var(--muted)}
.hp-product-card .specs{list-style:none;text-align:left;margin:20px 0}
.hp-product-card .specs li{padding:8px 0;border-bottom:1px solid rgba(100,120,110,.1);font-size:.88rem;color:var(--fg)}
.hp-product-card .specs li::before{content:"✓ ";color:var(--success);font-weight:bold}

/* 特性区 */
.hp-features{padding:60px 0}
.hp-feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px}
.hp-feature-card{background:var(--bg);border-radius:var(--radius-lg);padding:36px 28px;box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition);position:relative;overflow:hidden}
.hp-feature-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:var(--gradient-accent);transform:scaleX(0);transition:transform var(--transition)}
.hp-feature-card:hover{transform:translateY(-6px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-feature-card:hover::before{transform:scaleX(1)}
.hp-feature-card .icon{width:56px;height:56px;border-radius:14px;background:var(--gradient-accent);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;box-shadow:0 4px 12px rgba(45,139,107,.25)}
.hp-feature-card .icon svg{width:28px;height:28px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.hp-feature-card h3{font-size:1.15rem;margin-bottom:10px}
.hp-feature-card p{color:var(--muted);font-size:.92rem;line-height:1.7}

/* 流程区 */
.hp-steps-section{padding:60px 0}
.hp-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;position:relative}
.hp-step{text-align:center;padding:32px 20px;background:var(--bg);border-radius:var(--radius-lg);box-shadow:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);transition:all var(--transition)}
.hp-step:hover{transform:translateY(-4px);box-shadow:12px 12px 24px var(--shadow-dark),-12px -12px 24px var(--shadow-light)}
.hp-step .num{width:52px;height:52px;border-radius:50%;background:var(--gradient-accent);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;margin-bottom:16px;box-shadow:0 4px 15px rgba(45,139,107,.4)}
.hp-step h3{font-size:1.1rem;margin-bottom:8px}
.hp-step p{color:var(--muted);font-size:.88rem}

/* CTA */
.hp-cta{text-align:center;padding:70px 24px;margin-top:20px}
.hp-cta-box{background:var(--gradient-accent);border-radius:var(--radius-xl);padding:56px 32px;box-shadow:0 12px 40px rgba(45,139,107,.3);position:relative;overflow:hidden}
.hp-cta-box::before{content:"";position:absolute;top:-50%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(255,255,255,.15),transparent 60%)}
.hp-cta-box h2{color:#fff;font-size:2rem;font-weight:800;margin-bottom:12px;position:relative}
.hp-cta-box p{color:rgba(255,255,255,.9);font-size:1.05rem;margin-bottom:28px;position:relative}
.hp-cta-box .btn-white{display:inline-flex;padding:14px 36px;border-radius:var(--radius);background:#fff;color:var(--accent);font-size:1rem;font-weight:700;text-decoration:none;box-shadow:0 4px 15px rgba(0,0,0,.15);transition:all var(--transition);position:relative}
.hp-cta-box .btn-white:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(0,0,0,.2)}

@media(max-width:768px){.hp-hero h1{font-size:2.2rem}.hp-hero .sub{font-size:1rem}.hp-stats-row{gap:24px}.hp-stat .num{font-size:1.6rem}.hp-cta-box h2{font-size:1.5rem}}
@media(max-width:480px){.hp-hero h1{font-size:1.8rem}.hp-stats-row{gap:18px}}
</style>');
?>
<!-- ========== 首页HTML区域 - 可自由修改以下HTML代码来自定义首页 ========== -->
<div class="hp-hero">
    <div class="hp-badge"><span class="dot"></span> 全自动开通 · 即买即用</div>
    <h1>高性能虚拟主机<br><span class="grad">一键开通，稳定可靠</span></h1>
    <p class="sub">基于宝塔面板 MNBT 系统驱动，提供快速、稳定、安全的虚拟主机服务，购买后秒级开通，无需等待人工处理</p>
    <div class="actions">
        <a href="cart.php" class="btn-primary">立即选购</a>
        <a href="login.php" class="btn-secondary">登录 / 注册</a>
    </div>
    <div class="hp-stats-row">
        <div class="hp-stat"><div class="num"><?php echo $totalUsers; ?></div><div class="label">注册用户</div></div>
        <div class="hp-stat"><div class="num"><?php echo $totalVhosts; ?></div><div class="label">开通主机</div></div>
        <div class="hp-stat"><div class="num"><?php echo $totalOrders; ?></div><div class="label">成功订单</div></div>
        <div class="hp-stat"><div class="num">99.9%</div><div class="label">稳定运行</div></div>
    </div>
</div>

<?php if (!empty($models)): ?>
<div class="hp-products">
    <div class="hp-section-title">
        <h2>主机套餐</h2>
        <p>多种配置灵活选择，满足不同规模网站需求</p>
    </div>
    <div class="hp-product-grid">
        <?php foreach ($models as $m): ?>
        <div class="hp-product-card">
            <div class="name"><?php echo h($m['name']); ?></div>
            <div class="price"><?php echo h($m['price']); ?><span> 积分</span></div>
            <ul class="specs">
                <li>网页空间 <?php echo h($m['web_space']); ?> MB</li>
                <li>数据库 <?php echo h($m['db_space']); ?> MB</li>
                <li>月流量 <?php echo h($m['flow']); ?> GB</li>
                <li>绑定域名 <?php echo h($m['domain_limit']); ?> 个</li>
            </ul>
            <a href="cart.php" class="btn-primary btn-sm">立即购买</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="hp-features">
    <div class="hp-section-title">
        <h2>为什么选择我们</h2>
        <p>稳定、高效、易用的虚拟主机服务</p>
    </div>
    <div class="hp-feature-grid">
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
            <h3>极速开通</h3>
            <p>对接 MNBT 系统，购买后秒级自动开通，无需等待人工处理，即刻上线</p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3>安全稳定</h3>
            <p>基于宝塔面板管理，多重安全防护，数据定期备份，保障业务连续性</p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9.5c0-1.4-1.3-2.5-3-2.5s-3 1.1-3 2.5c0 3 6 1.5 6 4.5 0 1.4-1.3 2.5-3 2.5s-3-1.1-3-2.5"/></svg></div>
            <h3>积分体系</h3>
            <p>每日签到领积分，积分兑换主机，也可直接购买积分，灵活消费</p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg></div>
            <h3>灵活配置</h3>
            <p>多种主机型号可选，从个人博客到企业官网，满足不同规模需求</p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
            <h3>可视管理</h3>
            <p>独立控制面板，实时查看主机状态、到期时间，一键续费方便快捷</p>
        </div>
        <div class="hp-feature-card">
            <div class="icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <h3>推荐奖励</h3>
            <p>邀请好友注册获得积分奖励，推荐越多奖励越多，共享好礼</p>
        </div>
    </div>
</div>

<div class="hp-steps-section">
    <div class="hp-section-title">
        <h2>使用流程</h2>
        <p>简单四步，即刻上云</p>
    </div>
    <div class="hp-steps">
        <div class="hp-step">
            <div class="num">1</div>
            <h3>注册账号</h3>
            <p>使用邮箱快速注册</p>
        </div>
        <div class="hp-step">
            <div class="num">2</div>
            <h3>获取积分</h3>
            <p>签到或购买积分</p>
        </div>
        <div class="hp-step">
            <div class="num">3</div>
            <h3>选购主机</h3>
            <p>选择合适的主机型号</p>
        </div>
        <div class="hp-step">
            <div class="num">4</div>
            <h3>开始使用</h3>
            <p>自动开通立即使用</p>
        </div>
    </div>
</div>

<div class="hp-cta">
    <div class="hp-cta-box">
        <h2>准备好开始了吗？</h2>
        <p>立即注册，开启你的云端之旅</p>
        <a href="login.php" class="btn-white">免费注册</a>
    </div>
</div>
<!-- ========== 首页HTML区域结束 ========== -->
<?php renderFooter(); ?>
