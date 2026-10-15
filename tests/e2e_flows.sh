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
MEDIA_ID="$(db_scalar "SELECT id FROM media_assets WHERE file_path = ?" "$MEDIA_PATH")"
[[ "$MEDIA_PATH" =~ ^/media/branches/1/[a-f0-9]{32}\.jpg$ ]] || fail "Secure media record/path missing"
[[ -f "public_html$MEDIA_PATH" ]] || fail "Processed media file missing"
MIME="$(file -b --mime-type "public_html$MEDIA_PATH")"; [[ "$MIME" == "image/jpeg" ]] || fail "Processed media is not JPEG"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/listings/1/media/$MEDIA_ID/remove" --data-urlencode "_csrf=$CSRF" --data-urlencode 'reason=Automated lifecycle removal test')"
MEDIA_ACTIVE="$(db_scalar 'SELECT is_active FROM media_assets WHERE id = ?' "$MEDIA_ID")"
[[ "$STATUS" == "302" && "$MEDIA_ACTIVE" == "0" && ! -f "public_html$MEDIA_PATH" ]] || fail "Audited media removal did not deactivate record and purge file"
pass "Multipart validation, randomized re-encoding, and recoverable media removal flow"

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

# C-21 maker-checker QC: preparer cannot self-publish while another active reviewer exists.
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/verification" -o "$TMP/operator-qc.html"; CSRF="$(csrf_from "$TMP/operator-qc.html")"
QC_BEFORE="$(db_scalar "SELECT COUNT(*) FROM verification_reviews WHERE branch_id=$CREATED_BRANCH_ID")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" -X POST "$BASE/staff/verification/$CREATED_BRANCH_ID/qc" --data-urlencode "_csrf=$CSRF" --data-urlencode 'outcome=PASSED' --data-urlencode 'permission_verified=1' --data-urlencode 'coordinates_checked=1' --data-urlencode 'category_checked=1' --data-urlencode 'reviewer_notes=Operator attempted a complete self review.'
QC_AFTER_SELF="$(db_scalar "SELECT COUNT(*) FROM verification_reviews WHERE branch_id=$CREATED_BRANCH_ID")"
[[ "$QC_AFTER_SELF" == "$QC_BEFORE" ]] || fail "Preparer self-review was accepted while another reviewer was available"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=1" -o /dev/null
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/verification" -o "$TMP/admin-qc.html"; CSRF="$(csrf_from "$TMP/admin-qc.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/verification/$CREATED_BRANCH_ID/qc" --data-urlencode "_csrf=$CSRF" --data-urlencode 'outcome=PASSED' --data-urlencode 'permission_verified=1' --data-urlencode 'coordinates_checked=1' --data-urlencode 'category_checked=1' --data-urlencode 'reviewer_notes=Independent Administrator review completed.'
QC_AFTER_ADMIN="$(db_scalar "SELECT COUNT(*) FROM verification_reviews WHERE branch_id=$CREATED_BRANCH_ID")"
PUBLISHED_STATUS="$(db_scalar "SELECT publication_status FROM branches WHERE id=$CREATED_BRANCH_ID")"
[[ "$QC_AFTER_ADMIN" == "$((QC_BEFORE + 1))" && "$PUBLISHED_STATUS" == "PUBLISHED" ]] || fail "Independent QC review did not publish the listing atomically"
pass "C-21 independent reviewer separation, explicit checklist, and atomic publication are enforced"

# C-22 permission withdrawal must atomically unpublish every affected Branch and detach granted permission.
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/listings" -o "$TMP/admin-listings.html"; CSRF="$(csrf_from "$TMP/admin-listings.html")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/listings/$CREATED_BRANCH_ID/permission/revoke" --data-urlencode "_csrf=$CSRF" --data-urlencode 'reason=Business representative withdrew publication permission')"
REVOKED_STATUS="$(db_scalar "SELECT status FROM business_permissions WHERE id=(SELECT permission_id FROM verification_reviews WHERE branch_id=$CREATED_BRANCH_ID ORDER BY id DESC LIMIT 1)")"
UNPUBLISHED_STATUS="$(db_scalar "SELECT publication_status FROM branches WHERE id=$CREATED_BRANCH_ID")"
ACTIVE_PERMISSION="$(db_scalar "SELECT COALESCE(active_permission_id,0) FROM branches WHERE id=$CREATED_BRANCH_ID")"
[[ "$STATUS" == "302" && "$REVOKED_STATUS" == "REVOKED" && "$UNPUBLISHED_STATUS" == "UNPUBLISHED" && "$ACTIVE_PERMISSION" == "0" ]] || fail "Permission withdrawal did not close publication and permission gates atomically"
pass "C-22 permission withdrawal atomically unpublishes listings and removes permission linkage"

# C-23/C-24 governed reference data: duplicate-safe aliases and assignment-protected deactivation.
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/taxonomy" -o "$TMP/admin-taxonomy.html"; CSRF="$(csrf_from "$TMP/admin-taxonomy.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/taxonomy/save" --data-urlencode "_csrf=$CSRF" --data-urlencode 'name_en=CI Reference Category' --data-urlencode 'slug=ci-reference-category' --data-urlencode 'aliases=translit:ci ref' --data-urlencode 'is_active=1'
REF_CAT_ID="$(db_scalar "SELECT id FROM categories WHERE slug='ci-reference-category'")"; [[ -n "$REF_CAT_ID" ]] || fail "Reference category was not created"
CAT_COUNT_BEFORE="$(db_scalar "SELECT COUNT(*) FROM categories WHERE slug='ci-reference-category'")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/taxonomy/save" --data-urlencode "_csrf=$CSRF" --data-urlencode 'name_en=Duplicate CI Reference' --data-urlencode 'slug=ci-reference-category' --data-urlencode 'is_active=1'
CAT_COUNT_AFTER="$(db_scalar "SELECT COUNT(*) FROM categories WHERE slug='ci-reference-category'")"; [[ "$CAT_COUNT_BEFORE" == "$CAT_COUNT_AFTER" ]] || fail "Duplicate controlled category was accepted"
ASSIGNED_ACTIVE_BEFORE="$(db_scalar 'SELECT is_active FROM categories WHERE id=1')"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/taxonomy/save" --data-urlencode "_csrf=$CSRF" --data-urlencode 'id=1' --data-urlencode "name_en=$(db_scalar 'SELECT name_en FROM categories WHERE id=1')" --data-urlencode "slug=$(db_scalar 'SELECT slug FROM categories WHERE id=1')"
ASSIGNED_ACTIVE_AFTER="$(db_scalar 'SELECT is_active FROM categories WHERE id=1')"; [[ "$ASSIGNED_ACTIVE_BEFORE" == "1" && "$ASSIGNED_ACTIVE_AFTER" == "1" ]] || fail "Assigned category deactivation was not blocked"
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/locations" -o "$TMP/admin-locations.html"; CSRF="$(csrf_from "$TMP/admin-locations.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/locations/save" --data-urlencode "_csrf=$CSRF" --data-urlencode 'name_en=CI Controlled Area' --data-urlencode 'slug=ci-controlled-area' --data-urlencode 'sub_city_en=Bole' --data-urlencode 'aliases=translit:ci area' --data-urlencode 'center_latitude=8.95' --data-urlencode 'center_longitude=38.78' --data-urlencode 'is_active=1'
REF_AREA_ID="$(db_scalar "SELECT id FROM areas WHERE slug='ci-controlled-area'")"; AREA_ALIAS="$(db_scalar "SELECT alias_term FROM area_aliases WHERE area_id=$REF_AREA_ID")"
[[ -n "$REF_AREA_ID" && "$AREA_ALIAS" == "ci area" ]] || fail "Controlled area alias was not persisted transactionally"
pass "C-23/C-24 duplicate-safe aliases and assignment-protected reference lifecycles are enforced"

# C-27 Administrator-only early termination removes inventory while retaining campaign history.
ACTIVE_CAMPAIGN_ID="$(db_scalar "SELECT id FROM sponsorship_campaigns WHERE status='ACTIVE' ORDER BY id LIMIT 1")"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=2" -o /dev/null
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/campaigns" -o "$TMP/operator-campaigns.html"; STAFF_CSRF="$(csrf_from "$TMP/operator-campaigns.html")"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" -X POST "$BASE/staff/campaigns/$ACTIVE_CAMPAIGN_ID/terminate" --data-urlencode "_csrf=$STAFF_CSRF" --data-urlencode 'termination_reason=Operator must not terminate campaigns')"
CAMPAIGN_STATUS="$(db_scalar "SELECT status FROM sponsorship_campaigns WHERE id=$ACTIVE_CAMPAIGN_ID")"; [[ "$STATUS" == "403" && "$CAMPAIGN_STATUS" == "ACTIVE" ]] || fail "Operator bypassed campaign termination policy"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=1" -o /dev/null
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/campaigns" -o "$TMP/admin-campaigns.html"; STAFF_CSRF="$(csrf_from "$TMP/admin-campaigns.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/campaigns/$ACTIVE_CAMPAIGN_ID/terminate" --data-urlencode "_csrf=$STAFF_CSRF" --data-urlencode 'termination_reason=Commercial agreement ended before scheduled completion'
CAMPAIGN_STATUS="$(db_scalar "SELECT status FROM sponsorship_campaigns WHERE id=$ACTIVE_CAMPAIGN_ID")"; TERMINATION_REASON="$(db_scalar "SELECT termination_reason FROM sponsorship_campaigns WHERE id=$ACTIVE_CAMPAIGN_ID")"
[[ "$CAMPAIGN_STATUS" == "TERMINATED_EARLY" && "$TERMINATION_REASON" == "Commercial agreement ended before scheduled completion" ]] || fail "Administrator termination did not remove campaign inventory with reason"
METRICS_BEFORE="$(db_scalar "SELECT COALESCE(SUM(clicks_count),0) FROM sponsorship_daily_metrics WHERE campaign_id=$ACTIVE_CAMPAIGN_ID")"; CAMPAIGN_BIZ_SLUG="$(db_scalar "SELECT bz.slug FROM sponsorship_campaigns sc JOIN businesses bz ON bz.id=sc.business_id WHERE sc.id=$ACTIVE_CAMPAIGN_ID")"; CAMPAIGN_BRANCH_SLUG="$(db_scalar "SELECT br.branch_slug FROM sponsorship_campaigns sc JOIN branches br ON br.id=sc.branch_id WHERE sc.id=$ACTIVE_CAMPAIGN_ID")"
curl -sS -o /dev/null -X POST "$BASE/b/$CAMPAIGN_BIZ_SLUG/$CAMPAIGN_BRANCH_SLUG/act" --data-urlencode 'action_type=share' --data-urlencode "campaign_id=$ACTIVE_CAMPAIGN_ID"
METRICS_AFTER="$(db_scalar "SELECT COALESCE(SUM(clicks_count),0) FROM sponsorship_daily_metrics WHERE campaign_id=$ACTIVE_CAMPAIGN_ID")"; [[ "$METRICS_AFTER" == "$METRICS_BEFORE" ]] || fail "Terminated campaign accepted post-termination delivery metrics"
pass "C-27 Operator denial, immediate termination, and stale-delivery metric rejection are enforced"

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

# C-25 Customer appeal evidence and Administrator-only independent final decision.
db_exec "INSERT INTO reviews(branch_id,customer_id,rating,review_text,status,edit_count) VALUES(1,?,4,'CI appeal workflow review','PENDING_MODERATION',0)" "$CUSTOMER_ID"
APPEAL_REVIEW_ID="$(db_scalar "SELECT id FROM reviews WHERE customer_id=$CUSTOMER_ID AND review_text='CI appeal workflow review'")"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=2" -o /dev/null
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/moderation" -o "$TMP/operator-moderation.html"; STAFF_CSRF="$(csrf_from "$TMP/operator-moderation.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/moderation/$APPEAL_REVIEW_ID/decide" --data-urlencode "_csrf=$STAFF_CSRF" --data-urlencode 'decision=REJECTED' --data-urlencode 'reason_code=POLICY_VIOLATION' --data-urlencode 'notes=Initial operator policy decision'
curl -fsS -b "$TMP/customer.cookies" "$BASE/account/reviews" -o "$TMP/customer-reviews.html"; CSRF="$(csrf_from "$TMP/customer-reviews.html")"
curl -sS -o /dev/null -b "$TMP/customer.cookies" -X POST "$BASE/account/reviews/$APPEAL_REVIEW_ID/appeal" --data-urlencode "_csrf=$CSRF" --data-urlencode 'appeal_statement=Additional context shows this review describes a genuine customer experience.'
APPEAL_ID="$(db_scalar "SELECT id FROM review_appeals WHERE review_id=$APPEAL_REVIEW_ID")"; [[ -n "$APPEAL_ID" ]] || fail "Customer appeal evidence was not preserved"
STATUS="$(curl -sS -o /dev/null -w '%{http_code}' -b "$TMP/staff.cookies" "$BASE/staff/review-appeals")"; [[ "$STATUS" == "403" ]] || fail "Operator reached Administrator-only appeal queue"
curl -fsS -b "$TMP/staff.cookies" -c "$TMP/staff.cookies" "$BASE/staff?switch_staff=1" -o /dev/null
curl -fsS -b "$TMP/staff.cookies" "$BASE/staff/review-appeals" -o "$TMP/admin-appeals.html"; STAFF_CSRF="$(csrf_from "$TMP/admin-appeals.html")"
curl -sS -o /dev/null -b "$TMP/staff.cookies" -X POST "$BASE/staff/review-appeals/$APPEAL_ID/decide" --data-urlencode "_csrf=$STAFF_CSRF" --data-urlencode 'outcome=OVERTURNED' --data-urlencode 'reason_code=CONTEXT_CHANGED_DECISION' --data-urlencode 'decision_notes=Customer context supports restoration after independent Administrator review.'
APPEAL_OUTCOME="$(db_scalar "SELECT outcome FROM review_appeal_decisions WHERE appeal_id=$APPEAL_ID")"; APPEAL_REVIEW_STATUS="$(db_scalar "SELECT status FROM reviews WHERE id=$APPEAL_REVIEW_ID")"
[[ "$APPEAL_OUTCOME" == "OVERTURNED" && "$APPEAL_REVIEW_STATUS" == "PUBLISHED" ]] || fail "Administrator appeal decision did not restore review atomically"
pass "C-25 immutable Customer appeal, Operator denial, and independent Administrator outcome are enforced"

echo 'ALL AUTHENTICATED END-TO-END FLOWS PASSED.'
