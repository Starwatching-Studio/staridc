<p align="center">
  <img src="https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=Modern+sleek+cloud+hosting+control+panel+dashboard+with+server+racks+and+network+visualization+professional+design+clean+minimalist+style&image_size=landscape_16_9" alt="StarIDC Banner" width="100%">
</p>

<h1 align="center">🌟 StarIDC</h1>

<p align="center">
  <b>轻量级虚拟主机分销管理平台</b><br>
  集成宝塔面板 API，实现虚拟主机自动化开通与管理
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.6%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/BT%20Panel-API-20A53E?style=for-the-badge&logo=btw&logoColor=white" alt="BT Panel">
  <img src="https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge&logo=open-source-initiative&logoColor=white" alt="License">
</p>

<p align="center">
  <a href="#-功能特性">功能特性</a> •
  <a href="#-快速开始">快速开始</a> •
  <a href="#-配置指南">配置指南</a> •
  <a href="#-项目结构">项目结构</a> •
  <a href="#-数据库">数据库</a> •
  <a href="#-二次开发">二次开发</a>
</p>

***

## ✨ 功能特性

### 🖥️ 虚拟主机自动化

- 集成 **MNBT 宝塔插件 API**，下单后自动开通虚拟主机
- 支持主机**续费**、**暂停/解停**、**删除**、**重置密码**等全生命周期管理
- 多种主机型号配置，灵活设定空间、流量、绑定域名数等参数

### 💰 积分经济体系

- **签到积分**：用户每日签到随机获得积分（可配置范围）
- **注册奖励**：新用户注册自动赠送积分
- **推荐奖励**：邀请好友注册获得奖励积分
- **积分充值**：通过易支付在线购买积分
- **优惠码**：支持折扣优惠码，灵活营销

### 🔐 用户系统

- 邮箱注册/登录，支持**邮箱验证码**
- 登录失败锁定机制（5次失败锁定15分钟）
- **"记住我"** 自动登录（7天有效期）
- 找回密码功能
- 邮箱域名白名单限制

### ⚙️ 管理后台

- **仪表盘**：数据统计总览（用户数、主机数、订单数、访问量）
- **系统配置**：MNBT 对接、支付接口、邮件服务、站点设置
- **主机型号管理**：添加/上下架型号
- **用户管理**：搜索编辑、批量加积分、批量删除
- **主机管理**：查看/删除/同步
- **公告管理**：首页公告弹窗
- **消费统计**：订单数据分析

### 🎨 主题系统

- 内置多套精美主题：新拟态、现代渐变、扁平化风格等
- 一键切换主题，无需修改代码

### 📧 邮件通知

- SMTP 邮件发送（基于 PHPMailer）
- 注册验证码邮件
- 主机到期前 5 天自动告警（定时任务）

***

## 🚀 快速开始

### 环境要求

| 环境      | 版本                     |
| ------- | ---------------------- |
| Web 服务器 | Apache / Nginx         |
| PHP     | 7.4+（需 PDO、GD、cURL 扩展） |
| MySQL   | 5.6+ / MariaDB 10.0+   |
| 宝塔面板    | 任意版本（需安装 MNBT 插件）      |

### 安装步骤

1. **下载源码**
   ```bash
   git clone https://github.com/your-username/staridc.git
   ```
2. **上传至服务器**
   将项目文件上传至 Web 服务器根目录
3. **运行安装向导**
   浏览器访问 `http://你的域名/install/`，按提示：
   - 填写数据库连接信息
   - 设置管理员账号密码
   - 系统自动创建数据库表结构及初始数据
4. **配置宝塔 MNBT 插件**
   - 在宝塔面板安装 **MNBT 插件**
   - 获取 API 地址、编号和密钥
   - 在管理后台 → 系统配置 → MNBT 配置 中填入
5. **配置支付接口**
   - 注册易支付商户账号
   - 在管理后台 → 系统配置 → 支付配置 中填入商户信息
6. **配置邮件服务（可选）**
   - 在管理后台 → 系统配置 → 邮件配置 中填入 SMTP 信息
7. **配置定时任务（可选）**
   ```bash
   # 每天凌晨执行，检测即将到期的主机并发送告警邮件
   crontab -e
   0 0 * * * php /path/to/staridc/cron_expire_warning.php
   ```

### 默认主机型号

安装向导预设 4 种主机型号，可根据需求自由调整：

| 型号     | 网页空间    | 数据库空间   | 月流量    | 域名绑定 | 价格（积分） |
| ------ | ------- | ------- | ------ | ---- | :----: |
| 🪐 入门型 | 500 MB  | 100 MB  | 10 GB  | 3 个  |   300  |
| 🌙 标准型 | 1000 MB | 300 MB  | 30 GB  | 5 个  |   600  |
| ☀️ 高级型 | 2000 MB | 500 MB  | 50 GB  | 10 个 |  1200  |
| 💎 旗舰型 | 5000 MB | 1000 MB | 100 GB | 20 个 |  2500  |

***

## ⚙️ 配置指南

### 系统配置

管理后台 `?page=config` 提供完整的可视化配置界面，所有配置项存储在 `config` 表中。

#### MNBT API 配置

| 配置项         | 说明                 |
| ----------- | ------------------ |
| MNBT API 地址 | 宝塔面板地址 + MNBT 插件端口 |
| 宝塔编号        | 宝塔面板 API 编号        |
| MNBT 秘钥     | MNBT 插件通信密钥        |

#### 支付接口配置

| 配置项    | 说明      |
| ------ | ------- |
| 支付接口地址 | 易支付网关地址 |
| 商户ID   | 易支付商户号  |
| 商户密钥   | 易支付通信密钥 |

#### 邮件服务配置

| 配置项      | 说明                     |
| -------- | ---------------------- |
| SMTP 服务器 | 邮件发送服务器地址              |
| SMTP 端口  | 一般为 465（SSL）或 587（TLS） |
| 邮箱账号/密码  | SMTP 认证信息              |
| 发件人地址    | 与邮箱账号一致                |
| 邮箱白名单    | 仅允许指定后缀邮箱注册（留空则不限制）    |

### 积分价格配置

在管理后台 → 价格设置 中配置：

| 配置项    | 默认值 | 说明           |
| ------ | :-: | ------------ |
| 签到最小积分 |  50 | 每日签到随机积分下限   |
| 签到最大积分 | 100 | 每日签到随机积分上限   |
| 注册赠送积分 | 100 | 新用户注册赠送积分    |
| 推荐奖励积分 |  50 | 邀请好友注册成功奖励积分 |
| 积分套餐   | 自定义 | 多档位充值金额与积分比例 |

***

## 📁 项目结构

```
staridc/
├── index.php                 # 首页
├── login.php                 # 登录 / 注册
├── personalpanel.php         # 个人中心
├── cart.php                  # 选购主机
├── captcha.php               # 图片验证码
├── forgot.php                # 找回密码
├── pay_notify.php            # 支付异步通知（易支付回调）
├── pay_return.php            # 支付同步跳转
├── pay_callback.php          # 支付回调（兼容旧版）
├── cron_expire_warning.php   # 定时任务：到期告警
│
├── rd/                       # 核心运行库
│   ├── bootstrap.php         # 框架核心：数据库连接、Session 管理、辅助函数、
│   │                         #              Captcha 类、Mailer 类、页面渲染
│   ├── MNBT_API.php          # MNBT 宝塔主机 API 封装
│   ├── PayAPI.php            # 易支付 API 封装
│   └── logout.php            # 退出登录处理
│
├── admin/
│   └── index.php             # 管理后台（单页应用 + 标签页切换）
│
├── install/
│   └── index.php             # 安装向导（建表 + 初始数据）
│
├── theme/                    # 前端主题样式
│   ├── nomorphism/           # 新拟态风格
│   ├── modern-gradient/      # 现代渐变风格
│   ├── 扁平化-春来江水/       # 扁平化风格
│   └── 新拟态2.0/            # 新拟态 2.0 风格
│
├── mail/
│   └── vendor/               # PHPMailer 邮件库（Composer）
│
└── data/                     # 数据存储目录
```

***

## 🔧 二次开发

### 主题开发

在 `theme/` 目录下创建新主题文件夹和 `style.css`，覆盖系统定义的 CSS 类名即可。在管理后台 → 系统配置 → 网站设置 中切换主题。

### 核心类扩展

- **MNBT API**：[rd/MNBT\_API.php](file:///d:/yun/26-5-6/rd/MNBT_API.php) — 封装了与宝塔面板的通信，可替换为其他虚拟主机管理接口
- **支付接口**：[rd/PayAPI.php](file:///d:/yun/26-5-6/rd/PayAPI.php) — 易支付对接，可参考实现其他支付通道
- **系统框架**：[rd/bootstrap.php](file:///d:/yun/26-5-6/rd/bootstrap.php) — 核心函数、数据库连接、Mailer 邮件类

### 数据库扩展

所有建表语句在 [install/index.php](file:///d:/yun/26-5-6/install/index.php#L36-L113) 中集中管理，新增字段或表可在该文件中维护。

### 核心业务流程

```
用户注册 → 获得初始积分
    ↓
每日签到 → 随机获得积分
    ↓
选购主机 → 积分支付（支持优惠码折扣）
    ↓
调用 MNBT API → 自动在宝塔面板开通虚拟主机
    ↓
到期前 5 天 → 定时任务发送到期告警邮件
    ↓
到期后可续费（消耗积分延长有效期）
```

***

## 📄 开源协议

本项目基于 **MIT 协议** 开源，详见 [LICENSE](LICENSE) 文件。

***

<p align="center">
  <b>StarIDC</b> — 让虚拟主机分销更简单<br>
  <sub>Built with ❤️ for the open-source community</sub>
</p>
