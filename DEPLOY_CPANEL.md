# Bulbula.et V1 — Shared Hosting (cPanel + MySQL) Deployment Guide

This guide walks through deploying **Bulbula.et V1** to any standard shared hosting environment (`cPanel`, `DirectAdmin`, or `Plesk` running PHP 8.2+ and MySQL 8.0+ / MariaDB 10.6+) with **zero server restarts required**.

---

## 1. Directory Layout on Shared Hosting

Place the application root one level above your public web root (or point your addon domain's document root to `public_html/`):

```text
/home/cpanel_user/bulbula/
├── config/
│   ├── env.json                 # Hot-reloaded JSON configuration (no restart needed)
│   └── routes.php               # Declarative HTTP route table (33 routes)
├── database/
│   ├── schema_v1_mysql.sql      # Production MySQL 8.0+ / MariaDB 10.6+ schema (16 tables)
│   ├── seeds/seed_v1_pilot.sql  # Bole Bulbula V1 pilot seed data
│   └── init_sqlite.php          # Optional SQLite initializer for local/staging environments
├── public_html/                 # Web Server Document Root
│   ├── .htaccess                # Rewrite rules, security headers, cache-control
│   ├── index.php                # Single Front Controller
│   ├── robots.txt
│   └── assets/                  # CSS, JS, GSAP, Logo, and Photographic Card Assets
├── src/                         # PSR-4 Application Source (Core, Domain, Repositories, Services, Controllers, Views)
└── storage/                     # Writable by PHP (chmod 755/775, outside public_html)
    ├── cache/                   # File-based cache with atomic epoch invalidation
    ├── logs/                    # Application error & security logs
    ├── permissions_private/     # Private business permission artifacts (C-19)
    ├── rate_limits/             # IP & session rate-limiter buckets (C-32)
    └── sessions/                # Hardened PHP session storage
```

---

## 2. Database Setup (MySQL / MariaDB via phpMyAdmin)

1. In **cPanel -> MySQL Databases**, create a database (e.g., `cpaneluser_bulbula`) and a database user with `ALL PRIVILEGES`.
2. Open **phpMyAdmin**, select the new database, and import:
   - First: `database/schema_v1_mysql.sql`
   - Second: `database/seeds/seed_v1_pilot.sql`

---

## 3. Configure `config/env.json` (Hot-Reloaded on Every Request)

Edit `config/env.json` with your production MySQL credentials:

```json
{
  "app": {
    "name": "Bulbula.et",
    "env": "production",
    "debug": false,
    "url": "https://bulbula.et",
    "timezone": "Africa/Addis_Ababa"
  },
  "database": {
    "driver": "mysql",
    "host": "127.0.0.1",
    "port": 3306,
    "database": "cpaneluser_bulbula",
    "username": "cpaneluser_dbuser",
    "password": "YOUR_STRONG_DB_PASSWORD",
    "charset": "utf8mb4"
  },
  "cache": {
    "enabled": true,
    "default_ttl": 300
  }
}
```

> **Zero-Restart Guarantee:** `Bulbula\Core\Config` checks `filemtime(config/env.json)` on every request and reloads automatically when edited.

---

## 4. Zero-Restart Cache & OPcache Refresh

- **Automatic Static Asset Cache-Busting:** `View::asset('css/components.css')` appends `?v=<filemtime>` automatically, so browsers and Telegram Mini App WebViews immediately fetch updated CSS/JS/images after any file edit.
- **One-Click Staff Console Cache Flush:** Visit `/staff` and click **"Flush Cache & OPcache"** (`POST /staff/system/flush-cache`) to atomically increment the cache epoch in `storage/cache/_manifest.json`, remove stale `.cache.json` files, and invalidate PHP OPcache scripts without restarting Apache or PHP-FPM.
