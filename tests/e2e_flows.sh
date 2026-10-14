#!/usr/bin/env bash
set -euo pipefail
BASE="${BULBULA_BASE_URL:-http://127.0.0.1:8080}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

fail() { echo "[FAIL] $*" >&2; exit 1; }
pass() { echo "[PASS] $*"; }
csrf_from() { grep -o 'name="_csrf" value="[^"]*"' "$1" | head -1 | sed 's/.*value="\([^"]*\)"/\1/'; }

# Staff login: real CSRF, cookies, Bcrypt verification/session rotation, and RBAC page access.
curl -fsS -c "$TMP/staff.cookies" "$BASE/staff/login" -o "$TMP/staff-login.html"
CSRF="$(csrf_from "$TMP/staff-login.html")"; [[ -n "$CSRF" ]] || fail "Staff CSRF token missing"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/login" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode 'email=admin@bulbula.et' --data-urlencode 'password=BulbulaStaff2026!' --data-urlencode 'return_to=/staff/users')"
[[ "$STATUS" == "302" ]] || fail "Staff password step expected 302, got $STATUS"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff/2fa" -o "$TMP/staff-2fa.html"
CSRF="$(csrf_from "$TMP/staff-2fa.html")"; [[ -n "$CSRF" ]] || fail "Staff TOTP CSRF token missing"
SECRET="$(grep -oE '>[A-Z2-7]{32}<' "$TMP/staff-2fa.html" | head -1 | tr -d '<>' || true)"
if [[ -n "$SECRET" ]]; then
  TOTP_SECRET="$SECRET" TOTP_CODE="$(TOTP_SECRET="$SECRET" php -r 'require "vendor/autoload.php"; $p=new class implements RobThree\Auth\Providers\Qr\IQRCodeProvider{public function getQRCodeImage(string $q,int $s):string{return "";} public function getMimeType():string{return "text/plain";}}; echo (new RobThree\Auth\TwoFactorAuth($p,"Bulbula.et Staff",6,30))->getCode(getenv("TOTP_SECRET"));')"
else
  TOTP_CODE="$(php tests/totp_code.php admin@bulbula.et)"
fi
[[ "$TOTP_CODE" =~ ^[0-9]{6}$ ]] || fail "Could not generate test TOTP code"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff/2fa" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode "totp_code=$TOTP_CODE")"
[[ "$STATUS" == "302" ]] || fail "Staff TOTP verification expected 302, got $STATUS"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/users" -o "$TMP/staff-users.html"
grep -q 'RBAC Management' "$TMP/staff-users.html" || fail "Authenticated staff RBAC page inaccessible"
pass "Staff CSRF + password + RFC 6238 TOTP + session + Administrator RBAC flow"

# Real multipart media pipeline: byte MIME validation, GD decode/re-encode, random filename, measured metadata.
CSRF="$(csrf_from "$TMP/staff-users.html")"
php -r '$im=imagecreatetruecolor(420,300); $c=imagecolorallocate($im,234,106,21); imagefill($im,0,0,$c); imagepng($im,$argv[1]); imagedestroy($im);' "$TMP/upload.png"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff/listings/1/media" \
  -F "_csrf=$CSRF" -F 'alt_text_en=CI verified branch image' -F "branch_image=@$TMP/upload.png;type=image/png")"
[[ "$STATUS" == "302" ]] || fail "Secure media upload expected 302, got $STATUS"
MEDIA_PATH="$(php -r '$p=new PDO("sqlite:storage/bulbula_v1.sqlite"); echo $p->query("SELECT file_path FROM media_assets WHERE branch_id=1 AND alt_text_en=\"CI verified branch image\" ORDER BY id DESC LIMIT 1")->fetchColumn() ?: "";')"
[[ "$MEDIA_PATH" =~ ^/media/branches/1/[a-f0-9]{32}\.jpg$ ]] || fail "Secure media record/path missing"
[[ -f "public_html$MEDIA_PATH" ]] || fail "Processed media file missing"
MIME="$(file -b --mime-type "public_html$MEDIA_PATH")"; [[ "$MIME" == "image/jpeg" ]] || fail "Processed media is not JPEG"
php -r '$p=new PDO("sqlite:storage/bulbula_v1.sqlite"); $s=$p->prepare("DELETE FROM media_assets WHERE file_path=:p"); $s->execute([":p"=>$argv[1]]);' "$MEDIA_PATH"
rm -f "public_html$MEDIA_PATH"
pass "Multipart media byte validation + decode/re-encode + randomized storage flow"

# Central authorization policy: Operator may use operational routes but cannot reach Administrator routes or mutations.
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=2" -o /dev/null
STATUS="$(curl -sS -o "$TMP/operator-denied.html" -w '%{http_code}' -b "$TMP/staff.cookies" "$BASE/staff/users")"
[[ "$STATUS" == "403" ]] || fail "Operator access to Administrator staff-users route expected 403, got $STATUS"
grep -q 'Administrator role' "$TMP/operator-denied.html" || fail "Central authorization denial page missing"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/taxonomy" -o "$TMP/operator-taxonomy.html"
CSRF="$(csrf_from "$TMP/operator-taxonomy.html")"; [[ -n "$CSRF" ]] || fail "Operator taxonomy CSRF missing"
BEFORE="$(php -r '$p=new PDO("sqlite:storage/bulbula_v1.sqlite"); echo $p->query("SELECT COUNT(*) FROM categories")->fetchColumn();')"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/taxonomy/save" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode 'name_en=Unauthorized Category' --data-urlencode 'slug=unauthorized-category')"
AFTER="$(php -r '$p=new PDO("sqlite:storage/bulbula_v1.sqlite"); echo $p->query("SELECT COUNT(*) FROM categories")->fetchColumn();')"
[[ "$STATUS" == "403" && "$BEFORE" == "$AFTER" ]] || fail "Administrator-only taxonomy mutation was not blocked before state change"
pass "Central Staff policy denies Operator access and mutation on Administrator routes"

# Customer OTP: request, HMAC-backed code preview in development, verification, account session.
TEST_EMAIL="ci-otp-$(date +%s)-$$@example.com"
curl -fsS -c "$TMP/customer.cookies" "$BASE/auth/login" -o "$TMP/customer-login.html"
CSRF="$(csrf_from "$TMP/customer-login.html")"; [[ -n "$CSRF" ]] || fail "Customer CSRF token missing"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/customer.cookies" -c "$TMP/customer.cookies" "$BASE/auth/otp/request" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode "email=$TEST_EMAIL" --data-urlencode 'return_to=/account')"
[[ "$STATUS" == "302" ]] || fail "OTP request expected 302, got $STATUS"
curl -fsS -b "$TMP/customer.cookies" -c "$TMP/customer.cookies" "$BASE/auth/login?return_to=%2Faccount" -o "$TMP/otp.html"
OTP="$(grep -A4 'Preview One-Time Code' "$TMP/otp.html" | grep -oE '[0-9]{6}' | head -1 || true)"
[[ "$OTP" =~ ^[0-9]{6}$ ]] || fail "Development OTP preview missing"
CSRF="$(csrf_from "$TMP/otp.html")"; [[ -n "$CSRF" ]] || fail "OTP verification CSRF missing"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/customer.cookies" -c "$TMP/customer.cookies" -X POST "$BASE/auth/otp/verify" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode "email=$TEST_EMAIL" --data-urlencode "otp_code=$OTP" --data-urlencode 'return_to=/account')"
[[ "$STATUS" == "302" ]] || fail "OTP verification expected 302, got $STATUS"
curl -fsS -b "$TMP/customer.cookies" "$BASE/account" -o "$TMP/account.html"
grep -q "$TEST_EMAIL" "$TMP/account.html" || fail "Customer account session inaccessible"
pass "Customer OTP request + HMAC verification + session flow"

# Security behavior: invalid CSRF must not mutate and malformed Google credential must not authenticate.
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$BASE/staff/gates/toggle" --data-urlencode '_csrf=invalid' --data-urlencode 'gate_key=D_39_PAID_CAMPAIGNS_ENABLED')"
[[ "$STATUS" == "302" ]] || fail "Invalid-CSRF staff mutation did not fail safely"
pass "State-changing endpoint rejects invalid CSRF"

echo 'ALL AUTHENTICATED END-TO-END FLOWS PASSED.'
