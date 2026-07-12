# StarIDC 主题开发文档

本文档适用于 StarIDC v1.4.9，用于指导开发者创建、安装和维护前台主题。

> 主题系统只负责前台页面的视觉样式，不应修改业务逻辑、PHP 数据处理、表单字段名称、元素 ID 或 JavaScript 行为。

## 1. 主题机制

StarIDC 会读取数据库 `config` 表中的 `theme` 配置项，并加载对应主题目录中的 `style.css`：

```text
theme/{主题目录名}/style.css
```

例如配置值为 `my-theme` 时，系统会加载：

```text
theme/my-theme/style.css
```

如果配置的主题目录不存在，系统会回退到 `nomorphism`。

管理后台会自动扫描 `theme/` 下的所有文件夹，因此通常不需要修改 PHP 代码。只要主题目录存在，后台「系统配置 → 网站设置 → 选择主题」中就会出现该主题。

## 2. 最小目录结构

每个主题必须使用独立目录，并至少包含一个 `style.css`：

```text
theme/
└── my-theme/
    └── style.css
```

也可以添加主题专用资源：

```text
theme/
└── my-theme/
    ├── style.css
    ├── images/
    │   └── background.webp
    └── fonts/
        └── custom.woff2
```

CSS 中的相对路径以当前 `style.css` 所在目录为基准：

```css
.hero {
    background-image: url("images/background.webp");
}

@font-face {
    font-family: "CustomFont";
    src: url("fonts/custom.woff2") format("woff2");
    font-display: swap;
}
```

## 3. 创建新主题

建议复制现有主题作为基础，以减少遗漏：

1. 复制 `theme/nomorphism/` 或其他完整主题目录。
2. 将新目录重命名为只包含安全字符的名称，例如 `fresh-green`。
3. 修改新目录中的 `style.css`。
4. 登录管理后台。
5. 进入「系统配置 → 网站设置」。
6. 在主题列表中选择新主题并保存。
7. 分别检查首页、登录注册、选购主机和个人面板。

推荐使用小写英文、数字和短横线命名目录：

```text
fresh-green
light-business
dark-space
```

不要在目录名称中使用 `/`、`\`、`..` 等路径字符。

## 4. 推荐的 CSS 变量

主题没有强制要求完全统一的变量名称，但系统部分内联样式会优先读取 `--primary`。为了兼容全部页面，建议同时定义 `--primary` 和主题内部需要的其他变量。

```css
:root {
    --primary: #10b981;
    --primary-hover: #059669;
    --primary-light: #d1fae5;

    --bg: #f6faf8;
    --fg: #17211d;
    --muted: #64748b;
    --card: #ffffff;
    --border: #dfe9e4;

    --success: #16a34a;
    --warning: #d97706;
    --danger: #dc2626;

    --radius-sm: 8px;
    --radius: 14px;
    --radius-lg: 22px;

    --shadow: 0 12px 32px rgba(30, 64, 48, 0.08);
    --transition: 0.2s ease;
}
```

注意事项：

- `--primary` 建议始终提供，购物车、价格和部分按钮会使用它。
- 文本与背景应保持足够对比度。
- 成功、警告和错误状态不要只通过颜色区分。
- 不建议大量使用高强度渐变、强光晕和复杂循环动画。

## 5. 必须覆盖的基础结构

### 5.1 全局与页面布局

```css
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; }
body { background: var(--bg); color: var(--fg); }
.app { min-height: 100vh; display: flex; flex-direction: column; }
.main { flex: 1; }
```

主要类名：

| 类名 | 用途 |
|---|---|
| `.app` | 整个前台应用容器 |
| `.header` | 顶部导航栏 |
| `.header-inner` | 导航内部布局 |
| `.logo` | 站点名称或 Logo |
| `.nav` | 导航链接容器 |
| `.main` | 页面主体 |
| `.footer` | 页脚 |
| `.footer-inner` | 页脚内容 |

导航必须考虑长文本、中英文切换和移动端换行。

## 6. 页面样式接口

### 6.1 首页

| 类名 | 用途 |
|---|---|
| `.hero` | 首页主视觉区域 |
| `.hero-content` | 主视觉内容容器 |
| `.hero-title` | 首页主标题 |
| `.hero-desc` | 首页说明文字 |
| `.hero-actions` | 行动按钮区域 |
| `.features` | 功能介绍区域 |
| `.section-title` | 区块标题 |
| `.feature-grid` | 功能卡片网格 |
| `.feature-card` | 单个功能卡片 |
| `.feature-icon` | 功能图标 |
| `.how-it-works` | 使用流程区域 |
| `.steps` | 步骤列表 |
| `.step` | 单个步骤 |
| `.step-num` | 步骤序号 |

### 6.2 通用按钮与消息

| 类名 | 用途 |
|---|---|
| `.btn-primary` | 主操作按钮 |
| `.btn-secondary` | 次操作按钮 |
| `.btn-sm` | 小尺寸按钮修饰类 |
| `.msg` | 消息提示基础类 |
| `.msg-success` | 成功提示 |
| `.msg-error` | 错误提示 |
| `.msg-warn` | 警告提示 |

按钮必须提供以下状态：

```css
.btn-primary:hover { /* 悬停 */ }
.btn-primary:active { /* 按下 */ }
.btn-primary:focus-visible { /* 键盘焦点 */ }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; }
```

### 6.3 登录、注册、找回密码与 OAuth

| 类名 | 用途 |
|---|---|
| `.auth-container` | 认证页面外层 |
| `.auth-card` | 认证卡片 |
| `.auth-tabs` | 登录/注册标签容器 |
| `.auth-tab` | 单个标签 |
| `.auth-form` | 表单 |
| `.form-group` | 表单字段组 |
| `.form-control` | 输入框、选择框 |
| `.captcha-group` | 验证码区域 |
| `.captcha-row` | 图片验证码行 |
| `.captcha-img` | 验证码图片 |
| `.code-row` | 邮箱验证码行 |
| `.btn-send-code` | 发送验证码按钮 |
| `.auth-switch` | 登录、注册切换提示 |

输入控件需覆盖键盘焦点和错误状态：

```css
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 16%, transparent);
}
```

如需兼容较老浏览器，可用固定的 `rgba()` 颜色替代 `color-mix()`。

### 6.4 购物页面

| 类名 | 用途 |
|---|---|
| `.page-header` | 页面标题区域 |
| `.user-points-bar` | 用户积分栏 |
| `.category-filter` | 分类筛选区域 |
| `.cat-tabs` | 分类标签容器 |
| `.cat-subtabs` | 二、三级分类标签容器 |
| `.cat-tab` | 分类标签 |
| `.product-grid` | 产品卡片网格 |
| `.product-card` | 产品卡片 |
| `.product-name` | 型号名称 |
| `.product-price` | 产品价格 |
| `.product-features` | 产品参数列表 |
| `.empty-state` | 无产品空状态 |
| `.modal` | 弹窗遮罩 |
| `.modal-content` | 弹窗主体 |
| `.modal-header` | 弹窗头部 |
| `.modal-body` | 弹窗内容 |
| `.modal-footer` | 弹窗操作区 |
| `.modal-close` | 关闭按钮 |

购物页面还包含时长选择、弹性配置、优惠码和价格汇总。开发主题时应实际打开购买弹窗检查，不要只检查产品列表。

### 6.5 用户面板

| 类名 | 用途 |
|---|---|
| `.panel-grid` | 面板整体布局 |
| `.panel-sidebar` | 左侧用户栏 |
| `.user-card` | 用户资料卡 |
| `.user-avatar` | 用户头像 |
| `.user-name` | 用户昵称 |
| `.user-email` | 用户邮箱 |
| `.user-points` | 用户积分 |
| `.panel-nav` | 面板导航 |
| `.panel-nav-item` | 导航项目 |
| `.panel-main` | 主内容区 |
| `.panel-section` | 面板内容区 |
| `.section-card` | 内容卡片 |
| `.section-header` | 卡片标题行 |
| `.info-row` | 信息行 |
| `.points-packages` | 充值套餐网格 |
| `.pkg-card` | 充值套餐卡片 |
| `.vhost-list` | 主机列表 |
| `.vhost-card` | 主机卡片 |
| `.vhost-header` | 主机卡片头部 |
| `.vhost-info` | 主机信息区域 |
| `.vhost-actions` | 主机操作区 |
| `.copyable` | 可复制文本 |
| `.copy-btn` | 复制按钮 |
| `.badge` | 状态标签基础类 |
| `.badge-green` | 成功状态标签 |
| `.badge-red` | 异常状态标签 |
| `.badge-blue` | 信息状态标签 |
| `.text-muted` | 次要文本 |

用户面板功能较多，至少需要测试：

- 基本资料和第三方账号绑定
- 签到及积分充值
- 主机列表、续费弹窗和主机操作按钮
- 工单列表及新建工单表单
- 邀请记录
- API 密钥、复制按钮和在线调试区域

### 6.6 公告与购物车侧栏

| 类名 | 用途 |
|---|---|
| `.announcement-bar` | 公告区域 |
| `.announcement-inner` | 公告内容 |
| `.cart-sidebar` | 购物车侧栏 |
| `.cart-item` | 购物车条目 |
| `.cart-overlay` | 购物车遮罩 |
| `.nav-cart` | 导航购物车入口 |
| `.cart-badge` | 购物车数量标记 |
| `.api-loading-overlay` | 全屏加载遮罩 |
| `.api-loading-spinner` | 加载动画 |

购物车侧栏和加载遮罩有基础内联样式。主题可以通过更具体的选择器覆盖，但不要改变显示状态相关类名，例如 `.open`、`.show` 和 `.hidden`。

## 7. 响应式要求

主题必须兼容桌面、平板和手机。建议至少提供两个断点：

```css
@media (max-width: 768px) {
    .header-inner { flex-wrap: wrap; }
    .panel-grid { grid-template-columns: 1fr; }
    .product-grid { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .hero-title { font-size: 2rem; }
    .hero-actions { flex-direction: column; }
    .modal-content { width: calc(100% - 24px); }
}
```

移动端检查重点：

- 导航链接不能溢出屏幕。
- 按钮点击区域建议不小于 40px。
- 弹窗不能超出视口，应允许内容滚动。
- 表格或长文本需要横向滚动或自动换行。
- 用户面板侧栏应改为单列或横向导航。

## 8. 兼容性与安全约束

开发主题时请遵守以下规则：

1. 不修改 PHP 文件来实现纯视觉效果。
2. 不删除或重命名现有类名、ID、表单字段和 `data-*` 属性。
3. 不使用 CSS 隐藏安全提示、错误消息、价格、购买确认信息或表单字段。
4. 不在 CSS 中引用不可信的远程脚本。
5. 不覆盖 `.hidden`、`.show`、`.active` 等状态类的业务含义。
6. 不依赖仅在单一浏览器可用的实验特性；使用时应提供降级方案。
7. 图片、字体尽量随主题本地分发，避免第三方资源失效或泄露访问数据。
8. 主题目录中不要放置配置文件、密钥、数据库备份或用户数据。

## 9. 主题安装与分发

### 安装

1. 将主题目录上传到 `theme/`。
2. 确认 `theme/主题名/style.css` 存在。
3. 在后台「系统配置 → 网站设置」中选择主题。
4. 保存并刷新前台页面。

### 分发

推荐压缩包结构：

```text
my-theme.zip
└── my-theme/
    ├── style.css
    ├── images/
    └── fonts/
```

不要将主题文件直接散放在 ZIP 根目录，否则用户解压时容易覆盖其他主题资源。

## 10. 开发检查清单

发布主题前逐项确认：

- [ ] 主题目录包含 `style.css`
- [ ] 后台可以识别并选择主题
- [ ] 首页布局、按钮和功能卡片正常
- [ ] 登录、注册、验证码和找回密码页面正常
- [ ] OAuth 登录或绑定页面正常
- [ ] 购物分类、产品卡片和空状态正常
- [ ] 普通型号购买弹窗正常
- [ ] 弹性型号购买弹窗、滑块和价格汇总正常
- [ ] 购物车侧栏及结算按钮正常
- [ ] 用户面板各标签页正常
- [ ] 主机续费弹窗和加载动画正常
- [ ] 成功、错误和警告提示清晰可辨
- [ ] 中英文文本不会溢出
- [ ] 768px 与 480px 以下布局正常
- [ ] 键盘焦点清晰可见
- [ ] Chrome、Edge、Firefox、Safari 至少完成基础检查
- [ ] 不包含密钥、数据库、日志或用户数据

## 11. 最小主题示例

下面的示例仅用于展示结构，正式主题仍需补齐各业务页面样式：

```css
:root {
    --primary: #10b981;
    --primary-hover: #059669;
    --bg: #f5faf7;
    --fg: #17211d;
    --muted: #64748b;
    --card: #ffffff;
    --border: #dce8e1;
    --danger: #dc2626;
    --radius: 14px;
    --shadow: 0 12px 30px rgba(30, 64, 48, .08);
}

* { box-sizing: border-box; }

body {
    margin: 0;
    background: var(--bg);
    color: var(--fg);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
    line-height: 1.6;
}

.app { min-height: 100vh; display: flex; flex-direction: column; }
.main { flex: 1; }

.header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255, 255, 255, .94);
    border-bottom: 1px solid var(--border);
}

.header-inner {
    max-width: 1200px;
    min-height: 68px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.logo { color: var(--fg); font-size: 1.25rem; font-weight: 750; text-decoration: none; }
.nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.nav a { color: var(--muted); text-decoration: none; }
.nav a:hover { color: var(--primary); }

.btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 10px;
    padding: 11px 20px;
    background: var(--primary);
    color: #fff;
    font: inherit;
    font-weight: 650;
    cursor: pointer;
    transition: background .2s ease, transform .2s ease;
}

.btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); }
.btn-primary:focus-visible { outline: 3px solid rgba(16, 185, 129, .25); outline-offset: 2px; }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; }

.feature-card,
.auth-card,
.product-card,
.section-card,
.vhost-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.form-control,
input,
select,
textarea {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 11px 13px;
    background: #fff;
    color: var(--fg);
    font: inherit;
}

.form-control:focus,
input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, .14);
}

.msg-success { color: #166534; background: #dcfce7; }
.msg-error { color: #991b1b; background: #fee2e2; }
.msg-warn { color: #92400e; background: #fef3c7; }

.footer {
    margin-top: 48px;
    padding: 28px 20px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    text-align: center;
}

@media (max-width: 768px) {
    .header-inner { align-items: flex-start; padding-top: 14px; padding-bottom: 14px; }
    .panel-grid { grid-template-columns: 1fr; }
    .product-grid { grid-template-columns: 1fr; }
}
```

## 12. 参考主题

开发时可参考项目内现有主题：

- `nomorphism`：类名覆盖较完整，适合作为开发模板。
- `modern-gradient`：深色渐变风格参考。
- `清新薄荷主题`：明亮清新配色参考。
- `扁平化-春来江水`：扁平化设计参考。
- `新拟态2.0`：另一套新拟态实现参考。

建议优先复制覆盖最完整的主题，然后只调整变量、排版、边框、阴影和组件细节，避免从空文件开始导致页面样式缺失。
