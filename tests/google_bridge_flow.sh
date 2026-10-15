#!/usr/bin/env bash
# Synthetic CI-only handoff test. It does NOT simulate a real Google sign-in.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true ]] || { echo 'CI only: never mark a production flow verified.' >&2; exit 1; }
BASE="${BULBULA_BASE_URL:-http://127.0.0.1:8080}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
fail() { echo "[FAIL] $*" >&2; exit 1; }
csrf_from() { grep -o 'name="_csrf" value="[^"]*"' "$1" | head -1 | sed 's/.*value="\([^"]*\)"/\1/'; }

curl -fsS -c "$TMP/mini.cookies" "$BASE/auth/login" -o "$TMP/mini-login"
MINI_CSRF=$(csrf_from "$TMP/mini-login")
[[ -n "$MINI_CSRF" ]] || fail 'Mini App login CSRF missing'
START=$(curl -sS -b "$TMP/mini.cookies" -c "$TMP/mini.cookies" -o /dev/null -w '%{http_code} %{redirect_url}' -X POST "$BASE/auth/google/bridge/start" --data-urlencode "_csrf=$MINI_CSRF" --data-urlencode 'return_to=/account/saved')
[[ "$START" == *'302 '*'/auth/google/bridge/wait' ]] || fail "Handoff start did not redirect to waiting page: $START"
curl -fsS -b "$TMP/mini.cookies" "$BASE/auth/google/bridge/wait" -o "$TMP/wait"
FLOW=$(grep -oE 'bridge/continue\?flow=[a-f0-9]{48}' "$TMP/wait" | head -1 | sed 's/.*flow=//')
[[ "$FLOW" =~ ^[a-f0-9]{48}$ ]] || fail 'Handoff URL missing random flow'
! grep -q 'source_secret_hash' "$TMP/wait" || fail 'Session secret leaked to page'

curl -fsS -c "$TMP/browser.cookies" "$BASE/auth/google/bridge/continue?flow=$FLOW" -o "$TMP/browser"
BROWSER_CSRF=$(csrf_from "$TMP/browser")
[[ -n "$BROWSER_CSRF" ]] || fail 'External-browser CSRF missing'
STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/browser.cookies" -X POST "$BASE/auth/google/bridge/verify" \
  --data-urlencode "_csrf=$BROWSER_CSRF" --data-urlencode "flow=$FLOW" --data-urlencode 'credential=not-a-google-id-token')
[[ "$STATUS" == 403 ]] || fail 'Forged Google token accepted'
PENDING=$(curl -fsS -b "$TMP/mini.cookies" -X POST "$BASE/auth/google/bridge/complete" --data-urlencode "_csrf=$MINI_CSRF")
[[ "$PENDING" == *'"status":"pending"'* ]] || fail 'Invalid Google token advanced handoff'

curl -fsS -c "$TMP/other.cookies" "$BASE/auth/login" -o "$TMP/other-login"
OTHER_CSRF=$(csrf_from "$TMP/other-login")
OTHER=$(curl -fsS -b "$TMP/other.cookies" -X POST "$BASE/auth/google/bridge/complete" --data-urlencode "_csrf=$OTHER_CSRF")
[[ "$OTHER" == *'"status":"expired"'* ]] || fail 'Different session gained access to handoff'

# The CI fixture directly marks a verified Customer ID; production has no such shortcut.
FLOW_HASH=$(printf '%s' "$FLOW" | sha256sum | cut -d' ' -f1)
CUSTOMER=$(php tests/db_test_tool.php scalar "SELECT id FROM customers WHERE account_status='ACTIVE' ORDER BY id LIMIT 1")
[[ "$CUSTOMER" =~ ^[0-9]+$ ]] || fail 'No active fixture Customer'
php tests/db_test_tool.php execute 'UPDATE google_auth_bridges SET customer_id = ?, completed_at = ? WHERE flow_hash = ?' "$CUSTOMER" "$(date '+%Y-%m-%d %H:%M:%S')" "$FLOW_HASH"
OTHER=$(curl -fsS -b "$TMP/other.cookies" -X POST "$BASE/auth/google/bridge/complete" --data-urlencode "_csrf=$OTHER_CSRF")
[[ "$OTHER" == *'"status":"expired"'* ]] || fail 'Different session consumed verified handoff'
COMPLETE=$(curl -fsS -b "$TMP/mini.cookies" -c "$TMP/mini.cookies" -X POST "$BASE/auth/google/bridge/complete" --data-urlencode "_csrf=$MINI_CSRF")
[[ "$COMPLETE" == *'"status":"complete"'* && "$COMPLETE" == *'"redirect":"/account/saved"'* ]] || fail "Source session did not complete verified handoff: $COMPLETE"
USED=$(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM google_auth_bridges WHERE flow_hash = ? AND consumed_at IS NOT NULL' "$FLOW_HASH")
[[ "$USED" == 1 ]] || fail 'Handoff was not consumed exactly once'
STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/mini.cookies" "$BASE/account/saved")
[[ "$STATUS" == 200 ]] || fail 'Mini App session was not authenticated'
REPLAY=$(curl -sS -b "$TMP/mini.cookies" -X POST "$BASE/auth/google/bridge/complete" --data-urlencode "_csrf=$MINI_CSRF")
[[ "$REPLAY" == *'"status":"invalid"'* || "$REPLAY" == *'"status":"expired"'* ]] || fail 'Consumed flow replayed'
echo '[PASS] Google bridge: invalid credential rejected, session-bound, one-time, CI fixture signs in without live Google request.'
