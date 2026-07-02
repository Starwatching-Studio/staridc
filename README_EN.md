<p align="right">
  English | <a href="./README.md">中文</a>
</p>

<p align="center">
</p>

<h1 align="center">🌟 StarIDC</h1>

<p align="center">
  <b>Lightweight Virtual Hosting Reseller Platform</b><br>
  Integrated with MNBT BT Panel API for automated virtual host provisioning and management
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.6%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/BT%20Panel-API-20A53E?style=for-the-badge&logo=btw&logoColor=white" alt="BT Panel">
  <img src="https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge&logo=open-source-initiative&logoColor=white" alt="License">
</p>

<p align="center">
  <a href="#-features">Features</a> •
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-configuration-guide">Configuration</a> •
  <a href="#-project-structure">Structure</a> •
  <a href="#-development">Development</a>
</p>

***

## ✨ Features

### 🖥️ Automated Virtual Hosting

- Integrated with **MNBT BT Panel API** for automatic virtual host provisioning upon order
- **Multi-server support**: Connect to multiple BT MNBT servers simultaneously, assign different server to each hosting plan
- Full lifecycle management: **renewal**, **suspend/unsuspend**, **deletion**, **password reset**
- Flexible hosting plan configuration with customizable space, traffic, and domain limits

### 💰 Points Economy System

- **Daily check-in**: Users earn random points daily (configurable range), one-click check-in from the user panel
- **Registration bonus**: New users receive points upon registration
- **Referral rewards**: Earn points by inviting friends to register
- **Points top-up**: Purchase points online via EPay
- **Custom Recharge Packages**: Admin can flexibly add/edit/list/unlist points packages
- **Coupon codes**: Admin can generate discount coupons with expiry, usage limits and applicable plans

### 🔐 User System

- Email registration/login with **email verification codes**
- Login lockout mechanism (5 failed attempts locks for 15 minutes)
- **"Remember me"** auto-login (7-day validity)
- Password recovery
- Email domain whitelist restriction

### ⚙️ Admin Dashboard

- **Dashboard**: Data statistics overview (users, hosts, servers, orders, visits)
- **System Config**: MNBT integration, payment gateway, email service, site settings
- **Server Management**: Add/edit/delete/toggle MNBT servers with connection testing
- **Hosting Plan Management**: Add/listing control, assign server to each plan
- **User Management**: Search/edit, batch points, batch delete
- **Host Management**: View/delete/sync
- **Announcement**: Homepage popup announcements
- **Consumption Stats**: Order data analysis

### 🎨 Theme System

- Built-in themes: Fresh Mint (default), Neumorphism, Modern Gradient, Flat, and more
- One-click theme switching without code changes
- Responsive homepage design that adapts to all theme color schemes

### 📧 Email Notifications

- SMTP email sending (powered by PHPMailer)
- Registration verification emails
- Automatic expiry alerts 5 days before host expiration (cron job)

***

## 🚀 Quick Start

### Requirements

| Component | Version |
| --------- | ------- |
| Web Server | Apache / Nginx |
| PHP | 7.4+ (requires PDO, GD, cURL extensions) |
| MySQL | 5.6+ / MariaDB 10.0+ |
| BT Panel | Any version (requires MNBT plugin deployed) |

### Installation Steps

| Step | Action |
|:----:|--------|
| 1 | **Download source code** and extract locally |
| 2 | **Upload to server**: Create a website (PHP 8.0 recommended), upload project files to web root |
| 3 | **Run installation wizard**: Visit `http://your-domain/install/`, enter database info and admin credentials, system auto-creates tables and seed data |
| 4 | **Configure BT MNBT Plugin** (see details below) |
| 5 | **Configure Payment Gateway**: Register EPay merchant → Admin → System Config → Payment |
| 6 | **Configure Email Service (Optional)**: Admin → System Config → Email, enter SMTP details |
| 7 | **Configure Cron Job (Optional)**: Set up Crontab for daily expiry alerts |

#### Step 4 Details: Configure BT MNBT Plugin

1. Install the **MNBT system** plugin in BT Panel
2. Log in to MNBT backend and obtain the following three parameters:

   | Parameter | Where to Find |
   |-----------|---------------|
   | API URL | MNBT Backend → System Management → API Settings (format: `mnbt_system_connection/api/api.php`) |
   | Panel ID | MNBT Backend → BT Panel List → BT Panel Number |
   | BT Call Key | Same location (**Note: this is the BT Call Key, NOT the BT Panel Key**) |

3. Go to StarIDC Admin → System Config → MNBT and fill in the above three items (this is your default server)
4. For multiple servers, go to **Server Management** page and add additional MNBT panels using the same method

> **Note**: The API URL must be directly accessible from your server. Do not use domains proxied through Cloudflare

### Default Hosting Plans

The installer pre-configures 4 hosting plans, adjustable as needed:

| Plan | Web Space | DB Space | Monthly Traffic | Domains | Price (Points) |
| ---- | --------- | -------- | --------------- | ------- | :------------: |
| 🪐 Starter | 500 MB | 100 MB | 10 GB | 3 | 300 |
| 🌙 Standard | 1000 MB | 300 MB | 30 GB | 5 | 600 |
| ☀️ Advanced | 2000 MB | 500 MB | 50 GB | 10 | 1200 |
| 💎 Premium | 5000 MB | 1000 MB | 100 GB | 20 | 2500 |

***

## ⚙️ Configuration Guide

### System Configuration

The admin dashboard `?page=config` provides a full visual configuration interface. All settings are stored in the `config` table.

#### MNBT API Configuration

The system supports connecting to multiple MNBT servers. Add them in Admin → Server Management:

| Setting | Description |
| ------- | ----------- |
| Server Name | Custom label (e.g., "Hong Kong Node") |
| MNBT API URL | BT Panel URL + MNBT plugin port |
| Panel ID | BT Panel API ID |
| API Key (mn_key) | MNBT plugin communication key |
| BT Call Key (mn_keye) | BT Panel call key |
| Plugin Version (mn_vs) | MNBT plugin version number |

> After adding servers, assign the corresponding server to each hosting plan in plan management.

#### Payment Gateway Configuration

| Setting | Description |
| ------- | ----------- |
| Gateway URL | EPay gateway URL |
| Merchant ID | EPay merchant ID |
| Merchant Key | EPay communication key |

#### Email Service Configuration

| Setting | Description |
| ------- | ----------- |
| SMTP Server | Mail server address |
| SMTP Port | Usually 465 (SSL) or 587 (TLS) |
| Email/Password | SMTP authentication credentials |
| Sender Address | Same as email account |
| Email Whitelist | Only allow specified email suffixes (empty = no restriction) |

### Points Pricing Configuration

Configure in Admin → Pricing:

| Setting | Default | Description |
| ------- | :-----: | ----------- |
| Min Check-in Points | 50 | Daily check-in random points lower bound |
| Max Check-in Points | 100 | Daily check-in random points upper bound |
| Registration Points | 100 | Points given to new users |
| Referral Points | 50 | Points for successful referral |
| Points Packages | Custom | Multiple top-up tiers with points ratio |

***

## 📁 Project Structure

```
staridc/
├── index.php                 # Homepage
├── login.php                 # Login / Register
├── personalpanel.php         # User Dashboard
├── cart.php                  # Hosting Purchase
├── captcha.php               # Image Captcha
├── forgot.php                # Password Recovery
├── pay_notify.php            # Payment Async Notification (EPay callback)
├── pay_return.php            # Payment Sync Redirect
├── pay_callback.php          # Payment Callback (legacy compat)
├── cron_expire_warning.php   # Cron Job: Expiry Alert
├── upgrade.php               # Upgrade Script (DB migration for existing users)
│
├── rd/                       # Core Runtime Library
│   ├── bootstrap.php         # Framework core: DB connection, Session, helpers,
│   │                         #              Captcha class, Mailer class, rendering
│   ├── MNBT_API.php          # MNBT BT Panel API wrapper
│   ├── PayAPI.php            # EPay API wrapper
│   └── logout.php            # Logout handler
│
├── admin/
│   └── index.php             # Admin Dashboard (SPA + tab switching)
│
├── install/
│   └── index.php             # Installation Wizard (table creation + seed data)
│
├── theme/                    # Frontend Themes
│   ├── nomorphism/           # Neumorphism style
│   ├── modern-gradient/      # Modern Gradient style
│   ├── 扁平化-春来江水/       # Flat style
│   └── 新拟态2.0/            # Neumorphism 2.0 style
│
├── mail/
│   └── vendor/               # PHPMailer library (Composer)
│
└── data/                     # Data storage directory
```

***

## 🔧 Development

### Theme Development

Create a new folder and `style.css` under `theme/`, overriding the system-defined CSS class names. Switch themes in Admin → System Config → Site Settings.

### Core Class Extension

- **MNBT API**: [rd/MNBT\_API.php](file:///d:/yun/26-5-6/rd/MNBT_API.php) — Wraps BT Panel communication, replaceable with other virtual host management interfaces
- **Payment Gateway**: [rd/PayAPI.php](file:///d:/yun/26-5-6/rd/PayAPI.php) — EPay integration, reference for implementing other payment channels
- **Framework Core**: [rd/bootstrap.php](file:///d:/yun/26-5-6/rd/bootstrap.php) — Core functions, DB connection, Mailer class

### Database Extension

All table creation statements are managed centrally in [install/index.php](file:///d:/yun/26-5-6/install/index.php#L36-L113). New fields or tables can be maintained there.

### Core Business Flow

```
User Registration → Receive initial points
    ↓
Daily Check-in → Earn random points (one-click from dashboard)
    ↓
Purchase Hosting → Pay with points (coupon codes supported)
    ↓
Based on plan's assigned server → Call corresponding MNBT API to auto-provision
    ↓
5 days before expiry → Cron job sends expiry alert email
    ↓
Renewable after expiry (consume points to extend, auto-calls corresponding server API)
```

***

## 📄 License

This project is open-sourced under the **MIT License**. See the [LICENSE](LICENSE) file for details.

***

## ⭐ Star History

<p align="center">
  <a href="https://star-history.com/#Starwatching-Studio/staridc&Date">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://api.star-history.com/svg?repos=Starwatching-Studio/staridc&type=Date&theme=dark" />
      <source media="(prefers-color-scheme: light)" srcset="https://api.star-history.com/svg?repos=Starwatching-Studio/staridc&type=Date" />
      <img alt="Star History Chart" src="https://api.star-history.com/svg?repos=Starwatching-Studio/staridc&type=Date" />
    </picture>
  </a>
</p>

***

<p align="center">
  <b>StarIDC</b> — by Starwatching Studio<br>
  <sub>Built with ❤️ for the open-source community</sub>
</p>
