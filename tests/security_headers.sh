#!/usr/bin/env bash
set -euo pipefail
BASE="${BULBULA_BASE_URL:-http://127.0.0.1:8080}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
curl -fsS -D "$TMP/headers" "$BASE/b/birhan-medium-clinic/bole-bulbula-main" -o "$TMP/body"
grep -qi '^Content-Security-Policy:' "$TMP/headers"
grep -qi '^Permissions-Policy:' "$TMP/headers"
grep -qi '^Referrer-Policy: strict-origin-when-cross-origin' "$TMP/headers"
grep -qi '^X-Content-Type-Options: nosniff' "$TMP/headers"
grep -qi '^Cross-Origin-Opener-Policy: same-origin-allow-popups' "$TMP/headers"
CSP="$(grep -i '^Content-Security-Policy:' "$TMP/headers" | tr -d '\r')"
[[ "$CSP" == *"object-src 'none'"* && "$CSP" == *"frame-ancestors 'self' https://web.telegram.org https://*.telegram.org"* ]] || { echo '[FAIL] CSP object/frame policy missing' >&2; exit 1; }
[[ "$CSP" != *"script-src 'self' 'unsafe-inline'"* ]] || { echo '[FAIL] CSP permits unsafe inline scripts' >&2; exit 1; }
NONCE="$(printf '%s' "$CSP" | grep -o "nonce-[^' ]*" | head -1 | cut -d- -f2-)"
[[ -n "$NONCE" ]] && grep -q "nonce=\"$NONCE\" type=\"application/ld+json\"" "$TMP/body"
echo '[PASS] CSP nonce, Telegram frame policy, Permissions Policy, and browser hardening headers verified.'
