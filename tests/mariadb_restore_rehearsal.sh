#!/usr/bin/env bash
# Synthetic CI-only MariaDB logical-backup restore. Never accepts production DBs.
set -euo pipefail

[[ "${GITHUB_ACTIONS:-}" == 'true' && "${BULBULA_APP_ENV:-}" == 'development' \
   && "${BULBULA_DB_DRIVER:-}" == 'mysql' && "${BULBULA_DB_HOST:-}" == '127.0.0.1' \
   && "${BULBULA_DB_NAME:-}" == 'bulbula_ci' ]] || {
  echo '[FAIL] Rehearsal is restricted to the isolated CI fixture.' >&2; exit 1;
}
: "${BULBULA_DB_USER:?}"
: "${BULBULA_DB_PASSWORD:?}"
: "${BULBULA_RESTORE_ADMIN_USER:?}"
: "${BULBULA_RESTORE_ADMIN_PASSWORD:?}"

umask 077
tmp=$(mktemp -d)
restore="bulbula_restore_$(printf '%s' "$tmp" | sha256sum | cut -c1-12)"
created=0
cleanup() {
  if [[ "$created" == 1 ]]; then
    mariadb --defaults-extra-file="$tmp/admin.cnf" -e "DROP DATABASE IF EXISTS \`$restore\`" >&2 || true
  fi
  rm -rf "$tmp"
}
trap cleanup EXIT

# Credentials stay in 0600 temporary option files, never in arguments or logs.
printf '[client]\nhost=127.0.0.1\nport=%s\nuser=%s\npassword=%s\n' \
  "${BULBULA_DB_PORT:-3306}" "$BULBULA_DB_USER" "$BULBULA_DB_PASSWORD" > "$tmp/source.cnf"
printf '[client]\nhost=127.0.0.1\nport=%s\nuser=%s\npassword=%s\n' \
  "${BULBULA_DB_PORT:-3306}" "$BULBULA_RESTORE_ADMIN_USER" "$BULBULA_RESTORE_ADMIN_PASSWORD" > "$tmp/admin.cnf"

mariadb --defaults-extra-file="$tmp/admin.cnf" -e "CREATE DATABASE \`$restore\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created=1
mariadb-dump --defaults-extra-file="$tmp/source.cnf" --single-transaction --quick \
  --routines --triggers --events --default-character-set=utf8mb4 \
  "$BULBULA_DB_NAME" | gzip -9 > "$tmp/backup.sql.gz"
[[ -s "$tmp/backup.sql.gz" ]] || { echo '[FAIL] Empty logical backup' >&2; exit 1; }
(cd "$tmp" && sha256sum backup.sql.gz > SHA256SUMS && sha256sum -c SHA256SUMS)
gzip -dc "$tmp/backup.sql.gz" | mariadb --defaults-extra-file="$tmp/admin.cnf" "$restore"

mariadb --defaults-extra-file="$tmp/source.cnf" -N -B "$BULBULA_DB_NAME" \
  -e "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" | cut -f1 | sort > "$tmp/source-tables"
mariadb --defaults-extra-file="$tmp/admin.cnf" -N -B "$restore" \
  -e "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" | cut -f1 | sort > "$tmp/restored-tables"
diff -u "$tmp/source-tables" "$tmp/restored-tables"
[[ -s "$tmp/source-tables" ]] || { echo '[FAIL] No application tables in backup' >&2; exit 1; }

while IFS= read -r table; do
  [[ "$table" =~ ^[a-zA-Z0-9_]+$ ]] || { echo '[FAIL] Unsafe table identifier' >&2; exit 1; }
  original=$(mariadb --defaults-extra-file="$tmp/source.cnf" -N -B "$BULBULA_DB_NAME" -e "SELECT COUNT(*) FROM \`$table\`")
  copied=$(mariadb --defaults-extra-file="$tmp/admin.cnf" -N -B "$restore" -e "SELECT COUNT(*) FROM \`$table\`")
  [[ "$original" == "$copied" ]] || { echo "[FAIL] Row count mismatch: $table" >&2; exit 1; }
  result=$(mariadb --defaults-extra-file="$tmp/admin.cnf" -N -B "$restore" -e "CHECK TABLE \`$table\`" | tail -1 | cut -f4)
  [[ "$result" == 'OK' ]] || { echo "[FAIL] Restored table check failed: $table" >&2; exit 1; }
done < "$tmp/source-tables"

# Verify application migrations can read the restored database, without writing.
BULBULA_DB_NAME="$restore" BULBULA_DB_USER="$BULBULA_RESTORE_ADMIN_USER" \
  BULBULA_DB_PASSWORD="$BULBULA_RESTORE_ADMIN_PASSWORD" php database/migrate.php status \
  | grep -q '^APPLIED'
# A write to the isolated target must not appear in the CI source database.
mariadb --defaults-extra-file="$tmp/admin.cnf" "$restore" -e 'CREATE TABLE _restore_isolation_probe (id INT PRIMARY KEY)'
probe=$(mariadb --defaults-extra-file="$tmp/source.cnf" -N -B "$BULBULA_DB_NAME" \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '_restore_isolation_probe'")
[[ "$probe" == 0 ]] || { echo '[FAIL] Source database modified by restore' >&2; exit 1; }
printf '[PASS] Isolated synthetic MariaDB restore: %d tables, row parity, CHECK TABLE, migration status, and source isolation.\n' "$(wc -l < "$tmp/source-tables")"
