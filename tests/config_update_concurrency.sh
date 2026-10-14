#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
printf '%s' '{"app":{"env":"development"},"security":{},"auth":{},"mail":{}}' > "$TMP/env.json"
pids=()
for i in $(seq 1 12); do php "$ROOT/tests/config_update_worker.php" "$TMP" "$i" & pids+=("$!"); done
for pid in "${pids[@]}"; do wait "$pid"; done
php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $h=$d["mail"]["host"]??""; if(!preg_match("/^smtp(?:[1-9]|1[0-2])\\.example\\.com$/",$h)) exit(1);' "$TMP/env.local.json"
if compgen -G "$TMP/env.local.json.tmp.*" >/dev/null; then echo '[FAIL] Temporary config files remain.' >&2; exit 1; fi
echo '[PASS] Atomic config updates: 12 concurrent writers produced one complete validated JSON document.'
