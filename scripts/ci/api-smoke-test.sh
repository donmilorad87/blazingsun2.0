#!/usr/bin/env bash
# Starts the PHP API with `php -S` against a real database and checks every endpoint over HTTP.
# Usage: DB_HOST=… DB_PORT=… DB_NAME=… DB_USER=… DB_PASSWORD=… scripts/ci/api-smoke-test.sh
# The database must be empty/disposable: the test runs migrations and records visits.
set -euo pipefail
cd "$(dirname "$0")/../.."

: "${DB_HOST:?}" "${DB_PORT:?}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD:?}"
port="${API_PORT:-8099}"
base="http://127.0.0.1:$port/api"
token="smoke-$RANDOM$RANDOM"

workdir="$(mktemp -d)"
trap 'kill "$server_pid" 2>/dev/null || true; rm -rf "$workdir"' EXIT
MIGRATION_TOKEN="$token" scripts/write-api-config.sh "$workdir/config.php"

BLAZINGSUN_API_CONFIG="$workdir/config.php" php -S "127.0.0.1:$port" -t server/public server/public/index.php > "$workdir/server.log" 2>&1 &
server_pid=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "$base/health" && break; sleep 0.1; done

failures=0
check() {
	local name="$1" expected_status="$2" expected_body="$3"; shift 3
	local response status body
	response="$(curl -s -w $'\n%{http_code}' "$@")"
	status="${response##*$'\n'}"
	body="${response%$'\n'*}"
	if [[ "$status" == "$expected_status" && ( -z "$expected_body" || "$body" == "$expected_body" ) ]]; then
		printf '  ✅ %-48s %s %s\n' "$name" "$status" "$body"
	else
		printf '  ❌ %-48s got %s %s (expected %s %s)\n' "$name" "$status" "$body" "$expected_status" "$expected_body"
		failures=$((failures + 1))
	fi
}

echo "API smoke test against $base"
check "migrations: rejected without token"      401 '{"error":"Unauthorized"}' -X POST "$base/migrations"
check "migrations: rejected with wrong token"   401 '{"error":"Unauthorized"}' -X POST -H "Authorization: Bearer wrong" "$base/migrations"
check "migrations: run with token"              200 '{"applied":["001_create_page_visits.sql"]}' -X POST -H "Authorization: Bearer $token" "$base/migrations"
check "migrations: idempotent second run"       200 '{"applied":["001_create_page_visits.sql"]}' -X POST -H "Authorization: Bearer $token" "$base/migrations"
check "health: database reachable"              200 '{"status":"ok","database":"ok","visits":0}' "$base/health"
check "visits: first visit"                     200 '{"total":1}' -X POST "$base/visits"
check "visits: second visit"                    200 '{"total":2}' -X POST "$base/visits"
check "visits: GET is not allowed"              405 '{"error":"Method not allowed"}' "$base/visits"
check "health: reads without counting"          200 '{"status":"ok","database":"ok","visits":2}' "$base/health"
check "unknown route"                           404 '{"error":"Not found"}' "$base/nope"
check "config file is not reachable"            404 '{"error":"Not found"}' "$base/config/config.php"
if ! curl -s -D - -o /dev/null "$base/health" | grep -qi '^cache-control: no-store'; then
	echo "  ❌ Cache-Control: no-store header missing"; failures=$((failures + 1))
fi

if (( failures > 0 )); then
	echo "PHP server log:"; cat "$workdir/server.log"
	echo "$failures check(s) failed"; exit 1
fi
echo "All API checks passed"
