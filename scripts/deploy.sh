#!/usr/bin/env bash
# Deploys dist/ to Hostinger: writes the PHP API config, uploads over FTPS, runs DB migrations, checks /api/health.
# Credentials come from environment variables (GitHub Actions secrets) or, locally, from .env (see .env.example).
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ -f .env ]]; then
	# Export everything from .env: helper scripts (write-api-config.sh) run as child processes.
	set -a
	# shellcheck disable=SC1091
	source .env
	set +a
fi
: "${FTP_HOST:?missing — set it in .env or as a secret}" "${FTP_USER:?missing}" "${FTP_PASSWORD:?missing}" "${FTP_REMOTE_DIR:?missing}"
: "${DB_HOST:?missing}" "${DB_PORT:?missing}" "${DB_NAME:?missing}" "${DB_USER:?missing}" "${DB_PASSWORD:?missing}" "${MIGRATION_TOKEN:?missing}"
SITE_URL="${SITE_URL:-https://blazingsun.space}"
for required in dist/index.html dist/api/index.php dist/api/.htaccess; do
	# .htaccess routes /api/* to index.php; without it every endpoint is a 404 (it was once lost in a CI artifact).
	[[ -f "$required" ]] || { echo "dist/ is incomplete: $required is missing — run npm run build first" >&2; exit 1; }
done

# Keep the FTP password out of argv (visible in `ps`): pass it via a temporary netrc.
netrc="$(mktemp)"
# The API config holds the DB password: it exists in dist/ only during the upload.
trap 'rm -f "$netrc" dist/api/config/config.php' EXIT
chmod 600 "$netrc"
scripts/write-api-config.sh dist/api/config/config.php
printf 'machine %s login %s password %s\n' "$FTP_HOST" "$FTP_USER" "$FTP_PASSWORD" > "$netrc"

human_size() {
	numfmt --to=iec --suffix=B "$1"
}

pushd dist > /dev/null
mapfile -d '' files < <(find . -type f -print0 | sort -z)
total_bytes=0
uploaded=()
started=$SECONDS

echo "Uploading ${#files[@]} files to ftp://$FTP_HOST/$FTP_REMOTE_DIR"
# shortcut: the host's TLS cert is issued for its hostname, not the IP, so it is not verified (-k); still encrypted.
for file in "${files[@]}"; do
	path="${file#./}"
	bytes=$(stat -c %s "$file")
	printf '  ↑ %-45s %8s\n' "$path" "$(human_size "$bytes")"
	curl --silent --show-error --fail -k --ssl-reqd --ftp-create-dirs --netrc-file "$netrc" \
		--retry 3 --retry-delay 5 --connect-timeout 30 --max-time 300 \
		-T "$file" "ftp://$FTP_HOST/$FTP_REMOTE_DIR/$path"
	total_bytes=$((total_bytes + bytes))
	uploaded+=("| \`$path\` | $(human_size "$bytes") |")
done
elapsed=$((SECONDS - started))
popd > /dev/null

echo "Uploaded ${#files[@]} files ($(human_size "$total_bytes")) in ${elapsed}s"

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
	{
		echo "### 🚀 Uploaded ${#files[@]} files ($(human_size "$total_bytes")) in ${elapsed}s"
		echo
		echo "| File | Size |"
		echo "|---|---|"
		printf '%s\n' "${uploaded[@]}"
		echo
	} >> "$GITHUB_STEP_SUMMARY"
fi

# No SSH on the host, so migrations run through the token-protected API endpoint.
echo "Running database migrations…"
migration_response="$(curl --silent --show-error --fail-with-body --retry 2 --max-time 60 -X POST \
	-H "Authorization: Bearer $MIGRATION_TOKEN" -A "blazingsun-deploy" "$SITE_URL/api/migrations")" || {
	echo "Migrations failed: $migration_response" >&2
	exit 1
}
echo "Migrations: $migration_response"

echo "Checking API health…"
health_response="$(curl --silent --show-error --fail-with-body --retry 2 --max-time 30 -A "blazingsun-deploy" "$SITE_URL/api/health")" || {
	echo "Health check failed: $health_response" >&2
	exit 1
}
echo "Health: $health_response"

if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
	{
		echo "### 🐘 PHP API"
		echo "- Migrations: \`$migration_response\`"
		echo "- Health: \`$health_response\`"
		echo
	} >> "$GITHUB_STEP_SUMMARY"
fi
