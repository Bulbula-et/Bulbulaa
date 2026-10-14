# Bulbula.et V1 — Bole Bulbula Local Business Discovery SaaS

Company-operated, verified local business and service discovery platform for **Bole Bulbula, Addis Ababa** (serving both **Web** and **Telegram Mini App** surfaces from a single unified responsive codebase, plus a Web-only **Internal Staff Operations Console**).

## Architecture & Tech Stack
- **Backend:** Plain PHP 8.2+ (PSR-4 custom autoloader, zero heavy frameworks, shared-hosting / cPanel ready).
- **Database:** Dual-driver PDO (`MySQL 8.0+ / MariaDB 10.6+` for shared-hosting production via `database/schema_v1_mysql.sql`, and `SQLite 3` via `database/init_sqlite.php` + `storage/bulbula_v1.sqlite` for instant zero-config local/staging execution).
- **Cache & Config:** Zero-restart file-based cache (`src/Core/Cache.php` -> `storage/cache/`) and hot-reloadable configuration (`src/Core/Config.php` -> `config/env.json`) with one-click Staff Console invalidation (`/staff/system/flush-cache`).
- **Frontend:** Unified semantic HTML5, CSS Custom Properties (`public_html/assets/css/tokens.css`, `layout.css`, `components.css`, `futuristic.css`, `staff.css`), Vanilla ES6+ modules, and self-hosted GSAP (`public_html/assets/js/vendor/gsap.min.js`).

## Quick Start (Local / Sandbox)
```bash
# 1. Start the built-in PHP server (SQLite DB is pre-seeded at storage/bulbula_v1.sqlite)
php -S 0.0.0.0:8080 -t public_html public_html/router.php

# 2. (Optional) Re-initialize SQLite database from MySQL schema + pilot seed data
php database/init_sqlite.php
```

## Shared Hosting (cPanel + MySQL) Deployment
1. Import `database/schema_v1_mysql.sql` and `database/seeds/seed_v1_pilot.sql` into your MySQL database.
2. Update `config/env.json` with `"driver": "mysql"` and your MySQL database credentials.
3. Point your domain document root to `public_html/` (which includes `.htaccess` routing and security headers).
