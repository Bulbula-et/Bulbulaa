#!/usr/bin/env bash
set -euo pipefail
KEY="concurrency-$(date +%s)-$$-$RANDOM"
OUT="$(mktemp)"
trap 'rm -f "$OUT"' EXIT
php -r 'define("BULBULA_ROOT", getcwd()); require "vendor/autoload.php"; require "src/Core/Autoloader.php"; Bulbula\Core\Autoloader::register(BULBULA_ROOT."/src"); Bulbula\Core\Config::load(BULBULA_ROOT."/config/env.json"); Bulbula\Core\RateLimiter::clear($argv[1]);' "$KEY"
export KEY
seq 1 24 | xargs -P 24 -I{} sh -c 'php tests/rate_limit_worker.php "$KEY" 5 60' >> "$OUT"
ALLOWED="$(grep -c '^1$' "$OUT" || true)"
DENIED="$(grep -c '^0$' "$OUT" || true)"
[[ "$ALLOWED" == "5" ]] || { echo "[FAIL] Expected exactly 5 concurrent allows, got $ALLOWED" >&2; exit 1; }
[[ "$DENIED" == "19" ]] || { echo "[FAIL] Expected 19 concurrent denials, got $DENIED" >&2; exit 1; }
php -r 'define("BULBULA_ROOT", getcwd()); require "vendor/autoload.php"; require "src/Core/Autoloader.php"; Bulbula\Core\Autoloader::register(BULBULA_ROOT."/src"); Bulbula\Core\Config::load(BULBULA_ROOT."/config/env.json"); Bulbula\Core\RateLimiter::clear($argv[1]);' "$KEY"
echo "[PASS] Atomic rate limiter: 5 allowed, 19 denied across 24 concurrent processes."
