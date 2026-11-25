#!/usr/bin/env bash
# Serves the built site like production for the Playwright E2E tests: PHP's built-in server on dist/, with the page
# and API rendered by PHP against a disposable database that is wiped and re-seeded on every start.
# Defaults: the local Docker MySQL (127.0.0.1:3307, database blazingsun_e2e). CI sets DB_* to its MySQL service.
# Admin login for the tests: e2e-admin / e2e-password.
set -euo pipefail
cd "$(dirname "$0")/.."

export DB_HOST="${DB_HOST:-127.0.0.1}" DB_PORT="${DB_PORT:-3307}" DB_NAME="${DB_NAME:-blazingsun_e2e}"
export DB_USER="${DB_USER:-blazingsun}" DB_PASSWORD="${DB_PASSWORD:-blazingsun}"
port="${E2E_PORT:-4173}"
[[ -f dist/asset-manifest.json ]] || { echo "dist/ is missing — run npm run build first" >&2; exit 1; }

work="$(mktemp -d)"
server=""
trap '[[ -n "$server" ]] && kill "$server" 2>/dev/null; rm -rf "$work"' EXIT
trap 'exit 0' INT TERM
export SITE_URL="http://127.0.0.1:$port" ADMIN_USERNAME=e2e-admin ADMIN_PASSWORD=e2e-password
scripts/write-api-config.sh "$work/config.php" > /dev/null
# Local paths for this run: its own uploads, page cache and no Vite dev server.
php -r '
	$config = require $argv[1];
	$config["paths"] = ["dist" => $argv[2], "uploads" => $argv[3] . "/uploads", "cache" => $argv[3] . "/cache", "hot" => $argv[3] . "/no-hot-file"];
	file_put_contents($argv[1], "<?php\nreturn " . var_export($config, true) . ";\n");
' "$work/config.php" "$PWD/dist" "$work"
mkdir -p "$work/uploads"

export BLAZINGSUN_API_CONFIG="$work/config.php"
php server/bin/migrate.php --fresh
php -S "127.0.0.1:$port" -q -t dist server/dev-router.php &
server=$!
wait "$server"
