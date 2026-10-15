#!/usr/bin/env bash
set -euo pipefail
BASE="${BULBULA_BASE_URL:-http://127.0.0.1:8080}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

fail() { echo "[FAIL] $*" >&2; exit 1; }
pass() { echo "[PASS] $*"; }
csrf_from() { grep -o 'name="_csrf" value="[^"]*"' "$1" | head -1 | sed 's/.*value="\([^"]*\)"/\1/'; }
db_scalar() { php tests/db_test_tool.php scalar "$@"; }
db_exec() { php tests/db_test_tool.php execute "$@"; }

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
grep -q 'Staff access register' "$TMP/staff-users.html" || fail "Authenticated staff access page inaccessible"
pass "Staff CSRF + password + RFC 6238 TOTP + session + Administrator RBAC flow"

# Real multipart media pipeline: byte MIME validation, GD decode/re-encode, random filename, measured metadata.
CSRF="$(csrf_from "$TMP/staff-users.html")"
php -r '$im=imagecreatetruecolor(420,300); $c=imagecolorallocate($im,234,106,21); imagefill($im,0,0,$c); imagepng($im,$argv[1]); imagedestroy($im);' "$TMP/upload.png"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff/listings/1/media" \
  -F "_csrf=$CSRF" -F 'alt_text_en=CI verified branch image' -F "branch_image=@$TMP/upload.png;type=image/png")"
[[ "$STATUS" == "302" ]] || fail "Secure media upload expected 302, got $STATUS"
MEDIA_PATH="$(db_scalar "SELECT file_path FROM media_assets WHERE branch_id=1 AND alt_text_en='CI verified branch image' ORDER BY id DESC LIMIT 1")"
[[ "$MEDIA_PATH" =~ ^/media/branches/1/[a-f0-9]{32}\.jpg$ ]] || fail "Secure media record/path missing"
[[ -f "public_html$MEDIA_PATH" ]] || fail "Processed media file missing"
MIME="$(file -b --mime-type "public_html$MEDIA_PATH")"; [[ "$MIME" == "image/jpeg" ]] || fail "Processed media is not JPEG"
db_exec 'DELETE FROM media_assets WHERE file_path = ?' "$MEDIA_PATH"
rm -f "public_html$MEDIA_PATH"
pass "Multipart media byte validation + decode/re-encode + randomized storage flow"

# Central authorization policy: Operator may use operational routes but cannot reach Administrator routes or mutations.
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=2" -o /dev/null
STATUS="$(curl -sS -o "$TMP/operator-denied.html" -w '%{http_code}' -b "$TMP/staff.cookies" "$BASE/staff/users")"
[[ "$STATUS" == "403" ]] || fail "Operator access to Administrator staff-users route expected 403, got $STATUS"
grep -q 'Administrator role' "$TMP/operator-denied.html" || fail "Central authorization denial page missing"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/taxonomy" -o "$TMP/operator-taxonomy.html"
CSRF="$(csrf_from "$TMP/operator-taxonomy.html")"; [[ -n "$CSRF" ]] || fail "Operator taxonomy CSRF missing"
BEFORE="$(db_scalar 'SELECT COUNT(*) FROM categories')"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/taxonomy/save" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode 'name_en=Unauthorized Category' --data-urlencode 'slug=unauthorized-category')"
AFTER="$(db_scalar 'SELECT COUNT(*) FROM categories')"
[[ "$STATUS" == "403" && "$BEFORE" == "$AFTER" ]] || fail "Administrator-only taxonomy mutation was not blocked before state change"
pass "Central Staff policy denies Operator access and mutation on Administrator routes"

# Staff mutation negatives: authentication and CSRF rejection must occur before database writes.
BUSINESSES_BEFORE="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -X POST "$BASE/staff/listings/create" \
  --data-urlencode '_csrf=missing' --data-urlencode 'business_name_en=Unauthenticated Mutation' --data-urlencode 'branch_name_en=Forbidden')"
BUSINESSES_AFTER="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
[[ "$STATUS" == "302" && "$BUSINESSES_BEFORE" == "$BUSINESSES_AFTER" ]] || fail "Unauthenticated Staff mutation changed database state"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/listings/create" \
  --data-urlencode '_csrf=invalid' --data-urlencode 'business_name_en=CSRF Mutation' --data-urlencode 'branch_name_en=Forbidden')"
BUSINESSES_AFTER="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
[[ "$STATUS" == "302" && "$BUSINESSES_BEFORE" == "$BUSINESSES_AFTER" ]] || fail "Invalid-CSRF Staff mutation changed database state"
pass "Unauthenticated and invalid-CSRF Staff mutations preserve database state"

# C-19 onboarding integrity: explicit evidence, valid reference data, normalized duplicate rejection, atomic failures.
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/operator-listings.html"
CSRF="$(csrf_from "$TMP/operator-listings.html")"; [[ -n "$CSRF" ]] || fail "Listing onboarding CSRF missing"
CATEGORY_ID="$(db_scalar 'SELECT id FROM categories WHERE is_active=1 ORDER BY id LIMIT 1')"
AREA_ID="$(db_scalar 'SELECT id FROM areas WHERE is_active=1 ORDER BY id LIMIT 1')"
ONBOARD_NAME="CI Integrity Clinic $RANDOM-$$"
BEFORE="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/listings/create" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode "business_name_en=$ONBOARD_NAME" --data-urlencode "primary_category_id=$CATEGORY_ID" \
  --data-urlencode "area_id=$AREA_ID" --data-urlencode 'address_line_en=93 Integrity Avenue' --data-urlencode 'phone_primary=+251911222333' \
  --data-urlencode 'rep_full_name=CI Authorizer' --data-urlencode 'rep_role_title=Owner' --data-urlencode 'rep_phone=+251911444555' \
  --data-urlencode 'permission_method=RECORDED_VISIT' --data-urlencode 'permission_evidence_reference=CI-VISIT-001')"
AFTER_CREATE="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
[[ "$STATUS" == "302" && "$AFTER_CREATE" == "$((BEFORE + 1))" ]] || fail "Valid evidence-backed listing onboarding failed"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/operator-listings-2.html"
CSRF="$(csrf_from "$TMP/operator-listings-2.html")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/listings/create" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode "business_name_en=  $ONBOARD_NAME  " --data-urlencode "primary_category_id=$CATEGORY_ID" \
  --data-urlencode "area_id=$AREA_ID" --data-urlencode 'address_line_en=93--Integrity Avenue' --data-urlencode 'rep_full_name=CI Authorizer' \
  --data-urlencode 'rep_role_title=Owner' --data-urlencode 'rep_phone=+251911444555' --data-urlencode 'permission_method=RECORDED_VISIT' \
  --data-urlencode 'permission_evidence_reference=CI-VISIT-002')"
AFTER_DUPLICATE="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
[[ "$STATUS" == "302" && "$AFTER_DUPLICATE" == "$AFTER_CREATE" ]] || fail "Normalized duplicate listing was not rejected"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/operator-listings-3.html"
CSRF="$(csrf_from "$TMP/operator-listings-3.html")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/listings/create" \
  --data-urlencode "_csrf=$CSRF" --data-urlencode 'business_name_en=Missing Evidence Business' --data-urlencode "primary_category_id=$CATEGORY_ID" \
  --data-urlencode "area_id=$AREA_ID" --data-urlencode 'address_line_en=44 Evidence Road' --data-urlencode 'rep_full_name=CI Authorizer' \
  --data-urlencode 'rep_role_title=Owner' --data-urlencode 'rep_phone=+251911444555' --data-urlencode 'permission_method=RECORDED_VISIT')"
AFTER_INVALID="$(db_scalar 'SELECT COUNT(*) FROM businesses')"
[[ "$STATUS" == "302" && "$AFTER_INVALID" == "$AFTER_CREATE" ]] || fail "Missing permission evidence created a partial listing"
pass "C-19 onboarding validates evidence and references, rejects normalized duplicates, and preserves atomicity"

# C-20 lifecycle transitions require reasons and cannot bypass quality control.
CREATED_BRANCH_ID="$(db_scalar "SELECT br.id FROM branches br INNER JOIN businesses bz ON bz.id=br.business_id WHERE bz.name_en='$ONBOARD_NAME' ORDER BY br.id DESC LIMIT 1")"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/lifecycle-listings.html"; CSRF="$(csrf_from "$TMP/lifecycle-listings.html")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/listings/$CREATED_BRANCH_ID/lifecycle" --data-urlencode "_csrf=$CSRF" --data-urlencode 'target_status=UNPUBLISHED' --data-urlencode 'reason=CI verified the listing should not remain available.')"
LIFECYCLE_STATUS="$(db_scalar "SELECT publication_status FROM branches WHERE id=$CREATED_BRANCH_ID")"
[[ "$STATUS" == "302" && "$LIFECYCLE_STATUS" == "UNPUBLISHED" ]] || fail "Valid unpublish lifecycle transition failed"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/lifecycle-listings-2.html"; CSRF="$(csrf_from "$TMP/lifecycle-listings-2.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/listings/$CREATED_BRANCH_ID/lifecycle" --data-urlencode "_csrf=$CSRF" --data-urlencode 'target_status=PUBLISHED' --data-urlencode 'reason=Attempt to bypass required quality control.'
LIFECYCLE_STATUS="$(db_scalar "SELECT publication_status FROM branches WHERE id=$CREATED_BRANCH_ID")"
[[ "$LIFECYCLE_STATUS" == "UNPUBLISHED" ]] || fail "Lifecycle workflow allowed direct republishing without QC"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/lifecycle-listings-3.html"; CSRF="$(csrf_from "$TMP/lifecycle-listings-3.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/listings/$CREATED_BRANCH_ID/lifecycle" --data-urlencode "_csrf=$CSRF" --data-urlencode 'target_status=PENDING_QC' --data-urlencode 'reason=Business supplied evidence to begin reverification.'
LIFECYCLE_STATUS="$(db_scalar "SELECT publication_status FROM branches WHERE id=$CREATED_BRANCH_ID")"
[[ "$LIFECYCLE_STATUS" == "PENDING_QC" ]] || fail "Reopening did not return listing to QC"
pass "C-20 lifecycle reasons, valid transitions, and mandatory re-verification are enforced"

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

# Customer mutation state: invalid CSRF and cross-customer review withdrawal must not mutate; valid save toggles must.
CSRF="$(csrf_from "$TMP/account.html")"; [[ -n "$CSRF" ]] || fail "Authenticated Customer CSRF missing"
CUSTOMER_ID="$(db_scalar 'SELECT id FROM customers WHERE email = ?' "$TEST_EMAIL")"
SAVED_BEFORE="$(db_scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id = ?' "$CUSTOMER_ID")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/customer.cookies" -X POST "$BASE/account/saved/toggle" \
  --data-urlencode '_csrf=invalid' --data-urlencode 'business_id=1' --data-urlencode 'return_to=/account')"
SAVED_AFTER="$(db_scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id = ?' "$CUSTOMER_ID")"
[[ "$STATUS" == "302" && "$SAVED_BEFORE" == "$SAVED_AFTER" ]] || fail "Invalid-CSRF Customer save changed database state"
curl -sS -o /dev/null -b "$TMP/customer.cookies" -X POST "$BASE/account/saved/toggle" --data-urlencode "_csrf=$CSRF" --data-urlencode 'business_id=1' --data-urlencode 'return_to=/account'
SAVED_ADDED="$(db_scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id = ?' "$CUSTOMER_ID")"
[[ "$SAVED_ADDED" -eq $((SAVED_BEFORE + 1)) ]] || fail "Valid Customer save did not create expected database state"
curl -sS -o /dev/null -b "$TMP/customer.cookies" -X POST "$BASE/account/saved/toggle" --data-urlencode "_csrf=$CSRF" --data-urlencode 'business_id=1' --data-urlencode 'return_to=/account'
SAVED_REMOVED="$(db_scalar 'SELECT COUNT(*) FROM customer_saved_businesses WHERE customer_id = ?' "$CUSTOMER_ID")"
[[ "$SAVED_REMOVED" == "$SAVED_BEFORE" ]] || fail "Customer save toggle cleanup did not restore database state"
REVIEW_ID="$(db_scalar 'SELECT id FROM reviews ORDER BY id LIMIT 1')"
REVIEW_BEFORE="$(db_scalar 'SELECT status FROM reviews WHERE id = ?' "$REVIEW_ID")"
curl -sS -o /dev/null -b "$TMP/customer.cookies" -X POST "$BASE/account/reviews/$REVIEW_ID/withdraw" --data-urlencode "_csrf=$CSRF"
REVIEW_AFTER="$(db_scalar 'SELECT status FROM reviews WHERE id = ?' "$REVIEW_ID")"
[[ "$REVIEW_BEFORE" == "$REVIEW_AFTER" ]] || fail "Customer modified another account's review"
pass "Customer CSRF, positive mutation-state, cleanup, and object-ownership checks"

echo 'ALL AUTHENTICATED END-TO-END FLOWS PASSED.'
