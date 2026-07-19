# StarIDC 部署指南

基于原生 PHP + MySQL 的主机销售/分销系统。本指南帮助你在拿到代码包后快速跑起来。

---

## 一、环境要求

| 组件 | 最低版本 | 说明 |
|---|---|---|
| PHP | 7.4+（推荐 8.0+） | 需开启以下扩展： |
| ├─ PDO / mysqli | — | 数据库驱动（二选一即可，系统默认用 PDO） |
| ├─ curl | — | 对接 MNBT、支付、OAuth 等外部接口 |
| ├─ mbstring | — | 多字节字符串处理 |
| ├─ gd | — | 图形验证码 / 头像处理 |
| ├─ openssl | — | 安全随机、HTTPS 回调 |
| MySQL | 5.7+（推荐 8.0+） | 字符集建议 utf8mb4 |
| Web 服务器 | Nginx / Apache | 需支持 PHP-FPM 或 mod_php |

> 临时部署可用 PHP 内置服务器：`php -S 0.0.0.0:8080`（仅开发测试，勿用于生产）。

---

## 二、快速部署步骤

### 1. 上传代码
将代码包解压到网站根目录，确保 `index.php`、`install/`、`rd/` 同级。

### 2. 复制配置文件
```bash
cp config.example.php config.php
```
> 程序只读取 `config.php`。若 `config.php` 不存在，访问网站会自动跳转到 `/install/`。

### 3. 修改数据库信息
编辑 `config.php`，把 `$dbconfig` 改成你自己的数据库：
```php
$dbconfig = array(
    'host'   => 'localhost',
    'port'   => 3306,
    'user'   => '你的数据库用户名',
    'pwd'    => '你的数据库密码',
    'dbname' => '你的数据库名',
);
```

### 4. 设置目录权限
确保以下目录**可写**（用于缓存、上传、会话数据）：
```bash
chmod -R 755 data/ cache/ uploads/
# 部分虚拟主机若仍提示不可写，可临时设为 777：
# chmod -R 777 data/ cache/ uploads/
```

### 5. 访问网站完成安装
浏览器打开你的域名，会自动跳转到 `/install/`：
1. 填写数据库信息与管理员账号；
2. 系统自动建表并写入初始配置；
3. 安装完成后访问后台「系统配置」完善 MNBT、支付、邮件等。

---

## 三、常见问题（FAQ）

### Q1：数据库连接失败怎么办？
- 确认 `config.php` 中 `host / user / pwd / dbname / port` 填写正确；
- 确认 MySQL 服务在运行，且该用户对你指定的库有**全部权限**；
- `localhost` 连不上时，尝试把 `host` 改成 `127.0.0.1`（部分环境 localhost 走 socket）；
- 远程数据库需确认服务商放行了当前服务器 IP 的 3306 端口；
- 可在后台「配置体检」`admin/check_config.php` 一键排查。

### Q2：页面空白 / 报错看不到内容？
- 编辑 `config.php` 取消注释 `define('DEBUG', true);` 显示错误详情；
- 常见原因：PHP 版本过低、缺少 curl/gd/mbstring 扩展、目录无写权限。

### Q3：一直跳转到 /install/ 死循环？
说明系统认为**尚未安装**，通常因为：
- `config.php` 不存在或不可读（确认已 `cp` 且权限正确）；
- 已存在 `config.php` 却仍跳转：检查 `config.php` 是否**为空或语法错误**，PHP 解析失败会被当作"不存在"。

### Q4：支付 / 邮件 / MNBT 不生效？
这些**不在** `config.php` 里，而是在安装后后台「系统配置」中填写：
- 支付：易支付接口地址 / PID / KEY / 回调地址；
- 邮件：SMTP 主机 / 端口 / 账号 / 密码；
- MNBT：`mnbt_api_url` / `mnbt_bh` / `mnbt_key` / `mnbt_vs` 等。

### Q5：域名检查工具无法使用？
`admin/domain_check.php` 通过宝塔面板 API 获取站点信息。请在后台「系统配置 → 宝塔面板」中添加宝塔面板的接口地址和 API 密钥（登录宝塔面板 → 面板设置 → API 接口 → 获取密钥，并将服务器 IP 加入 API 白名单）。

---

## 四、配置说明
详见 [`CONFIG.md`](./CONFIG.md)，列出所有配置项含义与默认值。

## 五、安全提示
- 生产环境务必将 `DEBUG` 保持为 `false`；
- `config.php` 含数据库凭据，请勿提交到公开仓库，可用 `.gitignore` 忽略；
- 安装完成后建议删除或重命名 `install/` 目录，避免被重复执行。
