# StarIDC 配置说明（CONFIG.md）

本文件说明 StarIDC 部署/分发的配置方式。系统的配置分为**两层**：

1. **编译期配置（本文件 `config.php`）**：数据库连接、站点/支付域名、MNBT 面板路径、调试开关。
   分发给他人时，**只需修改 `config.php`**。
2. **运行时配置（数据库 `config` 表）**：MNBT 对接密钥、支付接口、邮件服务、广告、公告等。
   在后台「系统配置」中管理，不写在本文件。

> 设计原则：不破坏现有功能；旧版本缺失的配置给出合理默认值；重复项统一为同一常量。

---

## 一、编译期配置（config.php）

| 配置项 | 位置 | 含义 | 默认值 | 是否必改 |
|---|---|---|---|---|
| `$dbconfig['host']` | config.php | 主站数据库主机 | `localhost` | 按需 |
| `$dbconfig['port']` | config.php | 数据库端口 | `3306` | 一般否 |
| `$dbconfig['user']` | config.php | 数据库用户名 | — | **必改** |
| `$dbconfig['pwd']` | config.php | 数据库密码 | — | **必改** |
| `$dbconfig['dbname']` | config.php | 数据库名 | — | **必改** |
| `SITE_URL` | config.php（可选覆盖） | 主站访问地址（留空自动探测） | 未定义→自动探测 | 按需 |
| `DEBUG` | config.php（可选覆盖） | 调试模式，true 显示错误详情 | `false` | 生产保持 false |

### 修改方法

编辑网站根目录 `config.php`。必填项（`$dbconfig`）已生成；可选覆盖项以注释形式提供，**取消对应行的注释并改成你自己的值即可生效**。

---

## 二、运行时配置（数据库 config 表，后台「系统配置」管理）

以下配置存于 `config` 表，键名即下方“配置键”，可在后台修改，也可用 `conf('键名')` 读取。
它们**不应**写死在业务代码中（本项目已统一通过 `conf()` 读取）。

| 配置键 | 用途 |
|---|---|
| `site_name` | 站点名称 |
| `mnbt_api_url` / `mnbt_bh` / `mnbt_key` / `mnbt_keye` / `mnbt_vs` | MNBT 服务端对接信息 |
| `pay_api_url` / `pay_pid` / `pay_key` | 支付接口（易支付） |
| `mail_host` / `mail_port` / `mail_user` / `mail_pass` / `mail_name` / `mail_security` / `mail_enabled` | 邮件服务（SMTP） |
| `email_domain_restrict_enabled` / `email_domain_whitelist` | 注册邮箱域名限制 |
| `admin_email` / `admin_email_notify` | 管理员通知邮箱 |
| `ad_global_config` / `ad_enable` / `ad_config` | 广告配置 |
| `announcement` / `announcement_btn` | 公告内容与按钮 |
| `oauth_*` | 聚合登录配置 |

---

## 三、宝塔面板配置（域名检查工具专用）

`admin/domain_check.php` 通过 **宝塔面板 API** 获取站点列表和绑定域名。
管理员需在后台「系统配置 → 宝塔面板」中添加宝塔面板的接口地址和 API 密钥。

- 配置存储在 `config` 表的 `bt_panels_config` 键（JSON 数组）。
- 支持添加多个宝塔面板，域名检查会遍历所有面板拉取站点。
- 获取密钥：登录宝塔面板 → 面板设置 → API 接口 → 开启接口并复制密钥（需将服务器 IP 加入 API 白名单）。

---

## 四、不同环境的建议值

| 环境 | DEBUG | 说明 |
|---|---|---|
| 本地开发 | `true` | 便于排错 |
| 测试服 | `true`/`false` | — |
| 生产环境 | **`false`** | 务必关闭调试，避免泄露路径 |

---

## 五、配置完整性自检

后台访问 `admin/check_config.php` 可一键检查：
- `config.php` 是否存在、`$dbconfig` 是否完整、数据库能否连通；
- 关键运行时配置键（`mnbt_*`、`pay_*`、`mail_*` 等）是否填写；
- `cache/`、`uploads/`、`data/` 目录是否可写。

---

## 六、兼容性说明

- 旧版本没有 `SITE_URL` / `DEBUG` 等常量：未定义时由 `bootstrap.php` 提供安全默认值，不影响运行。
- `config.php` 不再被 `install/index.php` 与 `migrate.php` 之外的文件直接读取数据库连接，
  所有业务代码统一经 `bootstrap.php` 的 `$DB`（PDO）访问数据库。
- 不建议把 API 密钥写进 `config.php`：运行时密钥请在后台「系统配置」管理，便于不改代码即可更新。
