#!/usr/bin/env bash
# Deploys dist/ to Hostinger over SSH:
#   1. writes the PHP API config            4. runs DB migrations (/api stays reachable during maintenance)
#   2. maintenance mode on (.maintenance)   5. checks /api/health
#   3. rsync: only changed files are sent   6. maintenance mode off
# Nothing on the server is ever deleted: public_html also holds other sites (talkila/) and files outside dist/ (PDFs).
# If a step after 2 fails, the site STAYS in maintenance (never half-deployed). Recover: rm <DEPLOY_PATH>/.maintenance
# Credentials come from environment variables (GitHub Actions vars/secrets) or, locally, from .env (see .env.example).
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ -f .env ]]; then
	# Export everything from .env: helper scripts (write-api-config.sh) run as child processes.
	set -a
	# shellcheck disable=SC1091
	source .env
	set +a
fi
: "${SSH_HOST:?missing — set it in .env or as a variable}" "${SSH_PORT:?missing}" "${SSH_USER:?missing}" "${DEPLOY_PATH:?missing}" "${SSH_KEY_FILE:?missing}"
: "${DB_HOST:?missing}" "${DB_PORT:?missing}" "${DB_NAME:?missing}" "${DB_USER:?missing}" "${DB_PASSWORD:?missing}" "${MIGRATION_TOKEN:?missing}"
SITE_URL="${SITE_URL:-https://blazingsun.space}"
summary="${GITHUB_STEP_SUMMARY:-/dev/null}"
for required in dist/asset-manifest.json dist/.htaccess dist/maintenance.php dist/api/index.php dist/api/page.php dist/api/.htaccess; do
	# The .htaccess files route the page and /api/* to PHP; without them every page is a 404 (one was once lost in a CI artifact).
	[[ -f "$required" ]] || { echo "dist/ is incomplete: $required is missing — run npm run build first" >&2; exit 1; }
done
[[ "$DEPLOY_PATH" != /* && "$DEPLOY_PATH" != *..* ]] \
	|| { echo "DEPLOY_PATH must be relative to the SSH home and contain no '..' (got: $DEPLOY_PATH)" >&2; exit 1; }

ssh_opts=(-p "$SSH_PORT" -i "$SSH_KEY_FILE" -o BatchMode=yes -o IdentitiesOnly=yes
	-o StrictHostKeyChecking=yes -o UserKnownHostsFile="$PWD/deploy/known_hosts" -o ConnectTimeout=30)
remote() { # remote <script>: runs a bash script in DEPLOY_PATH on the server
	ssh "${ssh_opts[@]}" "$SSH_USER@$SSH_HOST" "cd $(printf '%q' "$DEPLOY_PATH") && bash -euo pipefail -s" <<< "$1"
}

maintenance=off
# The API config holds the DB password: it exists in dist/ only during the upload.
on_exit() {
	local status=$?
	rm -f dist/api/config/config.php
	if (( status != 0 )) && [[ "$maintenance" == on ]]; then
		echo "::error title=Deploy failed — site left in maintenance mode::Fix the cause, then redeploy or run: rm $DEPLOY_PATH/.maintenance"
		echo "### ❌ Deploy failed — blazingsun.space is in **maintenance mode** until a redeploy or \`rm .maintenance\`" >> "$summary"
	fi
}
trap on_exit EXIT
scripts/write-api-config.sh dist/api/config/config.php

echo "▶ Maintenance mode on"
remote "touch .maintenance"
maintenance=on
code=$(curl --silent --output /dev/null --write-out '%{http_code}' --max-time 30 -A "blazingsun-deploy" "$SITE_URL/?ci=$RANDOM" || true)
if [[ "$code" == 503 ]]; then
	echo "  maintenance page is live (HTTP 503)"
else
	echo "::warning title=Maintenance page not confirmed::$SITE_URL returned HTTP $code instead of 503 (bot protection, or the new .htaccess is not live yet — it is on the next deploy)."
fi

echo "▶ Uploading changed files (rsync)"
started=$SECONDS
# --delay-updates: changed files are swapped in together at the end, so a failed transfer changes nothing.
# No --delete (see header). .maintenance is the flag file, talkila/ another site: both are never touched.
rsync -rlz --checksum --delay-updates --omit-dir-times --no-perms --no-owner --no-group --chmod=D755,F644 \
	--itemize-changes --exclude='/.maintenance' --exclude='/talkila/' \
	-e "ssh ${ssh_opts[*]}" dist/ "$SSH_USER@$SSH_HOST:$DEPLOY_PATH/" > rsync.log
grep '^<f' rsync.log | head -n 300 || true # only shows the list; rsync's own status is checked above
transferred=$(grep -c '^<f' rsync.log || true)
elapsed=$((SECONDS - started))
echo "  $transferred files sent in ${elapsed}s"

# The page used to be a static index.html; it is rendered by api/page.php now. Remove the old file so it can never be
# served instead. The config holds the DB password: readable by the site's own user only.
remote "rm -f index.html && chmod 600 api/config/config.php"

# Migrations run through the token-protected API endpoint (/api is excluded from maintenance mode).
echo "▶ Running database migrations"
migration_response="$(curl --silent --show-error --fail-with-body --retry 2 --max-time 60 -X POST \
	-H "Authorization: Bearer $MIGRATION_TOKEN" -A "blazingsun-deploy" "$SITE_URL/api/migrations")" || {
	echo "Migrations failed: $migration_response" >&2
	exit 1
}
echo "  migrations: $migration_response"

echo "▶ Checking API health"
health_response="$(curl --silent --show-error --fail-with-body --retry 2 --max-time 30 -A "blazingsun-deploy" "$SITE_URL/api/health")" || {
	echo "Health check failed: $health_response" >&2
	exit 1
}
echo "  health: $health_response"
if [[ "$health_response" == *'"webp":false'* ]]; then
	echo "::warning title=No WebP on the host::PHP GD cannot write WebP here; admin uploads get PNG variants only."
fi

echo "▶ Maintenance mode off"
remote "rm -f .maintenance"
maintenance=off

{
	echo "### 🚀 Uploaded $transferred changed files in ${elapsed}s (rsync over SSH)"
	echo
	echo '```text'
	grep '^<f' rsync.log | awk '{print $2}' | head -n 200 || true
	echo '```'
	echo "### 🐘 PHP API"
	echo "- Migrations: \`$migration_response\`"
	echo "- Health: \`$health_response\`"
	echo
} >> "$summary"
