<?php
define('IN_SYS', true);
define('ROOT', __DIR__ . '/');
include ROOT . 'rd/bootstrap.php';
$siteName = h(conf('site_name', '云主机'));
renderHeader('');
?>
<!-- ========== 首页HTML区域 - 可自由修改以下HTML代码来自定义首页 ========== -->
<div class="hero">
    <div class="hero-content">
        <h1 class="hero-title">🚀 <?php echo $siteName; ?></h1>
        <p class="hero-desc">高性能虚拟主机分发平台，一键开通，稳定可靠。<br>基于MNBT系统驱动，支持多种配置灵活选择。</p>
        <div class="hero-actions">
            <a href="cart.php" class="btn-primary">立即选购</a>
            <a href="login.php" class="btn-secondary">登录 / 注册</a>
        </div>
    </div>
</div>

<div class="features">
    <div class="section-title">
        <h2>为什么选择我们</h2>
        <p>稳定、高效、易用的虚拟主机服务</p>
    </div>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <h3>极速开通</h3>
            <p>对接MNBT系统，购买后秒级开通，无需等待人工处理</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <h3>安全稳定</h3>
            <p>基于宝塔面板管理，多重安全防护，数据定期备份</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💰</div>
            <h3>积分体系</h3>
            <p>每日签到领积分，积分兑换主机，也可直接购买积分</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🔧</div>
            <h3>灵活配置</h3>
            <p>多种主机型号可选，满足不同规模网站的需求</p>
        </div>
    </div>
</div>

<div class="how-it-works">
    <div class="section-title">
        <h2>使用流程</h2>
        <p>简单四步，即刻上云</p>
    </div>
    <div class="steps">
        <div class="step">
            <div class="step-num">1</div>
            <h3>注册账号</h3>
            <p>使用邮箱快速注册</p>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <h3>获取积分</h3>
            <p>签到或购买积分</p>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <h3>选购主机</h3>
            <p>选择合适的主机型号</p>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <h3>开始使用</h3>
            <p>自动开通立即使用</p>
        </div>
    </div>
</div>
<!-- ========== 首页HTML区域结束 ========== -->
<?php renderFooter(); ?>
