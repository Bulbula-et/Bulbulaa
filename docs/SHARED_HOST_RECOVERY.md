# Shared-host backup and recovery runbook

This runbook covers Bulbula.et's MariaDB production profile and SQLite development/CI profile. A restore rehearsal is evidence of recoverability; merely creating a backup is not.

## Required policy decisions

The service owner must approve and record recovery-point and recovery-time objectives, backup frequency, retention, encryption/access rules, and an off-host copy location. Keep at least one copy outside the hosting account. Review access quarterly and test a critical-data restore at least monthly.

## MariaDB backup

Run from a private shell with credentials supplied through a protected option file or hosting-provider secret facility—never on the command line or in Git:

```sh
umask 077
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$HOME/backups/$STAMP"
mysqldump --defaults-extra-file="$HOME/.bulbula-my.cnf" \
  --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 DATABASE_NAME \
  | gzip -9 > "$HOME/backups/$STAMP/database.sql.gz"
tar -C /home/ACCOUNT -czf "$HOME/backups/$STAMP/application-media.tgz" \
  discoverbulbula.arkeonethiopia.com/storage/uploads
sha256sum "$HOME/backups/$STAMP"/* > "$HOME/backups/$STAMP/SHA256SUMS"
```

Copy the resulting directory to the approved off-host location. Application source is recovered from the verified Git commit and pinned `composer.lock`; do not treat generated `vendor/` as the authoritative source backup. After deployment or a change to `app.url`, run `php database/generate_robots.php` from the checkout to publish a static, origin-aware `public_html/robots.txt`. This generated file is excluded from Git; the routed PHP response remains a fallback. Verify the unparameterized `/robots.txt` returns the configured sitemap declaration on repeated requests from different edge locations. If Cloudflare still serves an older static copy, purge the exact `/robots.txt` URL from its cache; a query-string cache buster alone is not evidence that the default URL has been updated.

## Isolated MariaDB restore rehearsal

Never restore over production. Create an isolated database with a distinct rehearsal user and no public application traffic, then:

```sh
cd "$HOME/backups/BACKUP_ID"
sha256sum -c SHA256SUMS
gzip -dc database.sql.gz | mysql --defaults-extra-file="$HOME/.bulbula-rehearsal.cnf" REHEARSAL_DATABASE
mysql --defaults-extra-file="$HOME/.bulbula-rehearsal.cnf" REHEARSAL_DATABASE \
  -e 'CHECK TABLE users, businesses, branches, campaigns, audit_logs;'
```

Point a temporary, access-restricted checkout at `REHEARSAL_DATABASE`; run migrations in status mode, `php tests/verify_v1_all.php` against its local server, verify representative public/staff records, and confirm uploaded-media checksums. Record counts for critical tables before and after restore. Destroy the temporary checkout, database, and credentials after evidence is approved.

If shared-host permissions prevent temporary database creation, request an isolated database from the host or restore to a local MariaDB instance of the same major version. Never weaken production isolation to make a rehearsal possible.

## SQLite rehearsal

After creating the deterministic non-production database, run:

```sh
php tests/backup_restore_rehearsal.php
```

The test uses SQLite `VACUUM INTO`, restores to a separate temporary file, runs `PRAGMA integrity_check`, compares every application table's row count, and deletes the temporary artifacts. CI runs this on every supported PHP version.

## Evidence record

For each production backup or rehearsal, record:

- UTC start/end, operator, environment, application Git commit, and database engine/version;
- backup identifier, encrypted off-host copy confirmation, file sizes, and SHA-256 verification result;
- restore target (explicitly non-production), integrity/table-check output, critical record counts, and application smoke-test result;
- measured recovery duration, exceptions, follow-up owner/date, and independent reviewer approval.

Never put database credentials, customer data, raw dumps, or private filesystem paths in the repository or audit document.
