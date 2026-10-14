#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${BULBULA_PREFLIGHT_PORT:-18081}"
BULBULA_APP_ENV=production BULBULA_APP_DEBUG=0 BULBULA_PREVIEW_FEATURES=0 \
BULBULA_APP_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef \
BULBULA_STAFF_LOGIN_REQUIRED=1 \
php -S "127.0.0.1:$PORT" -t "$ROOT/public_html" "$ROOT/public_html/router.php" > /tmp/bulbula-preflight.log 2>&1 &
pid=$!
trap 'kill "$pid" 2>/dev/null || true' EXIT
for _ in $(seq 1 30); do curl -sS "http://127.0.0.1:$PORT/" -o /tmp/bulbula-preflight-body 2>/dev/null && break || sleep 0.1; done
status="$(curl -sS -D /tmp/bulbula-preflight-headers -o /tmp/bulbula-preflight-body -w '%{http_code}' "http://127.0.0.1:$PORT/")"
[[ "$status" == '503' ]] || { echo "[FAIL] Unsafe production pilot deployment returned HTTP $status" >&2; exit 1; }
grep -q 'temporarily unavailable' /tmp/bulbula-preflight-body
grep -qi '^Strict-Transport-Security: max-age=31536000; includeSubDomains' /tmp/bulbula-preflight-headers
grep -qi 'upgrade-insecure-requests' /tmp/bulbula-preflight-headers
echo '[PASS] Production preflight blocks preview deployment and emits HSTS/HTTPS-upgrade policy.'
