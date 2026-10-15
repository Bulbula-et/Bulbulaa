#!/usr/bin/env bash
# CI-only synthetic, signed Telegram launch data. Never a substitute for testing physical Telegram clients.
set -euo pipefail
[[ "${GITHUB_ACTIONS:-}" == true ]] || { echo 'CI only.' >&2; exit 1; }
BASE="${BULBULA_BASE_URL:-http://127.0.0.1:8080}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
fail() { echo "[FAIL] $*" >&2; exit 1; }
csrf() { grep -o 'name="_csrf" value="[^"]*"' "$1" | head -1 | sed 's/.*value="\([^"]*\)"/\1/'; }
query() { php -r '$t=getenv("BULBULA_TELEGRAM_BOT_TOKEN");$f=["auth_date"=>(string)(time()+(int)($argv[1]??0)),"query_id"=>"ci-only","user"=>json_encode(["id"=>987654321,"first_name"=>"Fixture"])];ksort($f);$c=implode("\n",array_map(fn($k,$v)=>"$k=$v",array_keys($f),$f));$f["hash"]=hash_hmac("sha256",$c,hash_hmac("sha256",$t,"WebAppData",true));echo http_build_query($f,"","&",PHP_QUERY_RFC3986);' -- "$1"; }
[[ -n "${BULBULA_TELEGRAM_BOT_TOKEN:-}" ]] || fail 'Missing synthetic bot token'
RAW=$(query 0)
STALE=$(query -301)
PASSWORD='synthetic-ci-only-password'
HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$PASSWORD")
PREFIX="tgci$(date +%s)$$"
php tests/db_test_tool.php execute 'INSERT INTO customers (email,display_name,google_sub_id,password_hash,email_verified_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)' "$PREFIX-primary@example.test" 'Primary Google account' "$PREFIX-google-primary" "$HASH"
php tests/db_test_tool.php execute 'INSERT INTO customers (email,display_name,google_sub_id,password_hash,email_verified_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)' "$PREFIX-secondary@example.test" 'Other Google account' "$PREFIX-google-other" "$HASH"
PRIMARY=$(php tests/db_test_tool.php scalar 'SELECT id FROM customers WHERE email=?' "$PREFIX-primary@example.test")
SECONDARY=$(php tests/db_test_tool.php scalar 'SELECT id FROM customers WHERE email=?' "$PREFIX-secondary@example.test")
BUSINESS=$(php tests/db_test_tool.php scalar 'SELECT id FROM businesses ORDER BY id LIMIT 1')
php tests/db_test_tool.php execute 'INSERT INTO customer_saved_businesses (customer_id,business_id) VALUES (?,?)' "$SECONDARY" "$BUSINESS"
login() {
  local jar="$1" email="$2" html="$3"
  curl -fsS -c "$jar" "$BASE/auth/login" -o "$html"
  local token; token=$(csrf "$html")
  [[ -n "$token" ]] || fail 'Login CSRF missing'
  local status; status=$(curl -sS -b "$jar" -c "$jar" -o /dev/null -w '%{http_code}' -X POST "$BASE/auth/password/login" --data-urlencode "_csrf=$token" --data-urlencode "email=$email" --data-urlencode "password=$PASSWORD")
  [[ "$status" == 302 ]] || fail "Password login failed: $status"
  curl -fsS -b "$jar" -c "$jar" "$BASE/account" -o "$html"
  grep -q "$email" "$html" || fail 'Account identity mismatch'
}
request() {
  local jar="$1" token="$2" raw="$3" operation="${4:-status}" confirm="${5:-}"
  curl -sS -b "$jar" -c "$jar" -X POST "$BASE/auth/telegram/session" --data-urlencode "_csrf=$token" --data-urlencode "init_data=$raw" --data-urlencode "operation=$operation" --data-urlencode "confirm=$confirm"
}
login "$TMP/mobile.cookie" "$PREFIX-primary@example.test" "$TMP/mobile.html"
MOBILE_CSRF=$(csrf "$TMP/mobile.html")
[[ $(request "$TMP/mobile.cookie" "$MOBILE_CSRF" "$RAW" link yes) == *'"state":"linked"'* ]] || fail 'Explicit primary account link failed'
[[ $(php tests/db_test_tool.php scalar 'SELECT customer_id FROM customer_telegram_links LIMIT 1') == "$PRIMARY" ]] || fail 'Wrong primary account linked'

login "$TMP/desktop.cookie" "$PREFIX-secondary@example.test" "$TMP/desktop.html"
DESKTOP_CSRF=$(csrf "$TMP/desktop.html")
[[ $(request "$TMP/desktop.cookie" "$DESKTOP_CSRF" "$RAW") == *'"state":"switch_confirmation"'* ]] || fail 'Silently switched different desktop account'
[[ $(request "$TMP/desktop.cookie" "$DESKTOP_CSRF" "$RAW" link yes) == *'"state":"already_linked_elsewhere"'* ]] || fail 'Second account stole Telegram link'
curl -fsS -b "$TMP/desktop.cookie" "$BASE/account" -o "$TMP/desktop.html"
grep -q "$PREFIX-secondary@example.test" "$TMP/desktop.html" || fail 'Desktop account replaced before confirmation'

curl -fsS -c "$TMP/guest.cookie" "$BASE/auth/login" -o "$TMP/guest.html"
GUEST_CSRF=$(csrf "$TMP/guest.html")
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "${RAW/987654321/987654320}") == *'"state":"invalid_launch"'* ]] || fail 'Forged identity accepted'
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "$STALE") == *'"state":"invalid_launch"'* ]] || fail 'Expired launch accepted'
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "$RAW") == *'"state":"signed_in"'* ]] || fail 'Other device did not auto sign in to linked Customer'
curl -fsS -b "$TMP/guest.cookie" "$BASE/account" -o "$TMP/guest.html"
grep -q "$PREFIX-primary@example.test" "$TMP/guest.html" || fail 'Other device opened wrong account'
grep -q 'Sign out this device' "$TMP/guest.html" || fail 'Current-device logout missing from Account settings'
grep -q 'b-bottom-nav__label">Account</span>' "$TMP/guest.html" || fail 'Account bottom navigation mislabeled'
SIGNED_IN_CSRF=$(csrf "$TMP/guest.html")
STATUS=$(curl -sS -b "$TMP/guest.cookie" -c "$TMP/guest.cookie" -o /dev/null -w '%{http_code}' -X POST "$BASE/auth/logout" --data-urlencode "_csrf=$SIGNED_IN_CSRF")
[[ "$STATUS" == 302 ]] || fail 'Current-device logout failed'
STATUS=$(curl -sS -b "$TMP/guest.cookie" -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/account")
[[ "$STATUS" == *'302 '*'/auth/login'* ]] || fail 'Logged-out device retained account access'
curl -fsS -b "$TMP/guest.cookie" -c "$TMP/guest.cookie" "$BASE/auth/login" -o "$TMP/guest.html"
grep -q 'telegram-resume-panel' "$TMP/guest.html" || fail 'Explicit Telegram resume option missing'
GUEST_CSRF=$(csrf "$TMP/guest.html")
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "$RAW") == *'"state":"paused"'* ]] || fail 'Telegram immediately undid explicit logout'
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "$RAW" resume) == *'"state":"confirmation_required"'* ]] || fail 'Telegram resume without confirmation succeeded'
[[ $(request "$TMP/guest.cookie" "$GUEST_CSRF" "$RAW" resume yes) == *'"state":"signed_in"'* ]] || fail 'Explicit Telegram resume failed'
curl -fsS -b "$TMP/guest.cookie" "$BASE/account" -o "$TMP/guest.html"
grep -q "$PREFIX-primary@example.test" "$TMP/guest.html" || fail 'Resumed device opened wrong account'
[[ $(request "$TMP/desktop.cookie" "$DESKTOP_CSRF" "$RAW" switch yes) == *'"state":"signed_in"'* ]] || fail 'Explicit desktop switch failed'
curl -fsS -b "$TMP/desktop.cookie" "$BASE/account" -o "$TMP/desktop.html"
grep -q "$PREFIX-primary@example.test" "$TMP/desktop.html" || fail 'Desktop did not switch to primary'
[[ $(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM customers WHERE id IN (?,?)' "$PRIMARY" "$SECONDARY") == 2 ]] || fail 'Separate accounts were merged'
[[ $(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id=? AND business_id=?' "$SECONDARY" "$BUSINESS") == 1 ]] || fail 'Other account data moved during switch'
[[ $(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id=? AND business_id=?' "$PRIMARY" "$BUSINESS") == 0 ]] || fail 'Other account saves silently merged'


STATUS=$(curl -sS -b "$TMP/mobile.cookie" -c "$TMP/mobile.cookie" -o /dev/null -w '%{http_code}' -X POST "$BASE/account/telegram/unlink" --data-urlencode "_csrf=$MOBILE_CSRF" --data-urlencode 'confirm=UNLINK')
[[ "$STATUS" == 302 ]] || fail 'Disconnect failed'
[[ $(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM customer_telegram_links WHERE customer_id=?' "$PRIMARY") == 0 ]] || fail 'Link persisted after revocation'
STATUS=$(curl -sS -b "$TMP/guest.cookie" -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/account")
[[ "$STATUS" == *'302 '*'/auth/login'* ]] || fail 'Telegram-derived session survived unlink'
curl -fsS -c "$TMP/new.cookie" "$BASE/auth/login" -o "$TMP/new.html"
NEW_CSRF=$(csrf "$TMP/new.html")
[[ $(request "$TMP/new.cookie" "$NEW_CSRF" "$RAW") == *'"state":"unlinked"'* ]] || fail 'Unlinked identity could still authenticate'
STATUS=$(curl -sS -b "$TMP/desktop.cookie" -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/account")
[[ "$STATUS" == *'302 '*'/auth/login'* ]] || fail 'Switched desktop session survived unlink'
# Permanent erasure destroys any re-established link; test on disposable CI fixture only.
login "$TMP/relink.cookie" "$PREFIX-primary@example.test" "$TMP/relink.html"
RELINK_CSRF=$(csrf "$TMP/relink.html")
[[ $(request "$TMP/relink.cookie" "$RELINK_CSRF" "$RAW" link yes) == *'"state":"linked"'* ]] || fail 'Re-link after web revocation failed'
php tests/db_test_tool.php execute "UPDATE customers SET account_status='PENDING_DELETION', deletion_effective_at='2000-01-01 00:00:00' WHERE id=?" "$PRIMARY"
php -r 'require "tests/bootstrap.php"; (new Bulbula\Repositories\CustomerRepository())->permanentlyErasePendingDeletion((int)$argv[1]);' "$PRIMARY"
[[ $(php tests/db_test_tool.php scalar 'SELECT COUNT(*) FROM customer_telegram_links WHERE customer_id=?' "$PRIMARY") == 0 ]] || fail 'Permanent deletion retained Telegram link'

printf '[PASS] Verified Telegram link: forged/stale rejected; primary auto sign-in; conflicting account needs confirmation; unlink revokes sessions; no merge.\n'
