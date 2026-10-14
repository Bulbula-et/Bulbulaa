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

## 2. Install Mature Authentication & Mail Dependencies

Bulbula.et pins production dependencies in `composer.lock`:

- `phpmailer/phpmailer` — mature SMTP and transactional email transport.
- `google/apiclient` — official Google Identity ID-token verification.
- `robthree/twofactorauth` — standalone zero-dependency RFC 6238 TOTP verification for Staff Gate D-45.

PHP must include:

- `ext-sodium` for encrypted Staff TOTP secrets.
- `ext-fileinfo` for server-side image MIME detection.
- `ext-gd` for safe image decoding, resizing, metadata stripping, and JPEG re-encoding.

In cPanel, enable these under **Select PHP Version → Extensions** before deployment.

From cPanel Terminal (or locally before upload), run from the application root:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
composer audit --no-dev
```

The generated `vendor/` directory must exist on the server but is intentionally not committed. If the hosting account does not provide Composer, run the command locally with the same PHP version and upload the resulting `vendor/` directory.

The front controller loads `vendor/autoload.php` before Bulbula.et's internal PSR-4 loader. Do not enable Google login or production OTP email until `composer install` succeeds and `/staff/settings` reports successful SMTP delivery.

---

## 3. Database Setup (MySQL / MariaDB via phpMyAdmin)

1. In **cPanel -> MySQL Databases**, create a database (e.g., `cpaneluser_bulbula`) and a database user with `ALL PRIVILEGES`.
2. Open **phpMyAdmin**, select the new database, and import:
   - First: `database/schema_v1_mysql.sql`
   - Second: `database/seeds/seed_v1_pilot.sql` (pilot/staging only; do not import known staff credentials in production).
3. Run the framework-free migration runner from cPanel Terminal:

```bash
php database/migrate.php status
php database/migrate.php migrate
```

Migrations are ordered, checksummed, batch-tracked, locked against concurrent deployment, and idempotent. Never edit an applied migration; add a new timestamped file in `database/migrations/`.

### Production logs

Structured JSON logs rotate daily under `storage/logs/` and retain fourteen files. Ensure PHP can write this directory while it remains inaccessible from the web root. Every HTTP response includes an `X-Request-ID`; use that value to correlate a user-visible failure with its log record. Never expose or download logs publicly.

---

## 3. Configure `config/env.json` (Hot-Reloaded on Every Request)

Never place production credentials in tracked `config/env.json`. Copy `config/env.local.example.json` to the ignored `config/env.local.json`, set file permissions to `0600`, and enter deployment-specific values there. On hosts that support environment variables, use the documented `BULBULA_*` variables instead; they take precedence over both JSON layers.

```bash
cp config/env.local.example.json config/env.local.json
chmod 600 config/env.local.json
```

Example local overlay:

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

> **Zero-Restart Guarantee:** `Bulbula\Core\Config` reloads the tracked defaults, ignored local overlay, and server environment on each request. The Staff Settings page updates the local overlay using a locked temporary file plus atomic rename.

Supported production environment variables include `BULBULA_APP_ENV`, `BULBULA_APP_DEBUG`, `BULBULA_APP_URL`, `BULBULA_APP_SECRET`, `BULBULA_DB_*`, `BULBULA_STAFF_LOGIN_REQUIRED`, `BULBULA_GOOGLE_CLIENT_*`, `BULBULA_TELEGRAM_BOT_*`, and `BULBULA_MAIL_*`. See `src/Core/Config.php` for the exact mapping.

---

## 5. Zero-Restart Cache & OPcache Refresh

- **Automatic Static Asset Cache-Busting:** `View::asset('css/components.css')` appends `?v=<filemtime>` automatically, so browsers and Telegram Mini App WebViews immediately fetch updated CSS/JS/images after any file edit.
- **One-Click Staff Console Cache Flush:** Visit `/staff` and click **"Flush Cache & OPcache"** (`POST /staff/system/flush-cache`) to atomically increment the cache epoch in `storage/cache/_manifest.json`, remove stale `.cache.json` files, and invalidate PHP OPcache scripts without restarting Apache or PHP-FPM.
