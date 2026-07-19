<?php
// 强制刷新本文件的 OPcache，确保模板修改立即生效
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}
// 侧边栏广告位：自定义广告（多条时轮播）。
// 渲染逻辑统一交由 renderLocationAds('sidebar')。
renderLocationAds('sidebar');
?>
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
                <input type="radio" name="panel-tab" value="info" class="panel-tab-radio"<?php echo $currentTab==='info'?' checked':''; ?> onchange="switchPanel(this.value)"> 个人中心
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="hosts" class="panel-tab-radio"<?php echo $currentTab==='hosts'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_hosts'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="points" class="panel-tab-radio"<?php echo $currentTab==='points'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_points'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="tickets" class="panel-tab-radio"<?php echo $currentTab==='tickets'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_tickets'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="bill" class="panel-tab-radio"<?php echo $currentTab==='bill'?' checked':''; ?> onchange="switchPanel(this.value)"> 积分账单
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="orders" class="panel-tab-radio"<?php echo $currentTab==='orders'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_orders'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="messages" class="panel-tab-radio"<?php echo $currentTab==='messages'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_messages'); ?><?php if (!empty($unreadMsgCount)): ?><span class="nav-badge"><?php echo $unreadMsgCount; ?></span><?php endif; ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="security" class="panel-tab-radio"<?php echo $currentTab==='security'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_security'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="referral" class="panel-tab-radio"<?php echo $currentTab==='referral'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_referral'); ?>
            </label>
            <label class="panel-nav-item">
                <input type="radio" name="panel-tab" value="api" class="panel-tab-radio"<?php echo $currentTab==='api'?' checked':''; ?> onchange="switchPanel(this.value)"> <?php echo L('panel_api'); ?>
            </label>
        </nav>
