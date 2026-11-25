#!/usr/bin/env bash
# Writes the PHP API config (a PHP file returning an array) from environment variables.
# Used by the deploy (GitHub secrets) and the API smoke test. var_export() escapes every value,
# so passwords with quotes or $ cannot break or inject PHP code.
# Usage: DB_HOST=… DB_PORT=… DB_NAME=… DB_USER=… DB_PASSWORD=… MIGRATION_TOKEN=… \
#        [SITE_URL=…] [ADMIN_USERNAME=… ADMIN_PASSWORD=…] scripts/write-api-config.sh <output-file>
# ADMIN_PASSWORD is the plain password: only its hash (password_hash) is written, the password never reaches the server.
set -euo pipefail

output="${1:?usage: write-api-config.sh <output-file>}"
: "${DB_HOST:?missing}" "${DB_PORT:?missing}" "${DB_NAME:?missing}" "${DB_USER:?missing}" "${DB_PASSWORD:?missing}"
export MIGRATION_TOKEN="${MIGRATION_TOKEN:-}" SITE_URL="${SITE_URL:-https://blazingsun.space}"
export ADMIN_USERNAME="${ADMIN_USERNAME:-}" ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
if [[ -n "$ADMIN_USERNAME" && ${#ADMIN_PASSWORD} -lt 12 ]]; then
	echo "ADMIN_PASSWORD must be at least 12 characters when ADMIN_USERNAME is set" >&2
	exit 1
fi

mkdir -p "$(dirname "$output")"
(umask 077 && php -r '
	$config = [
		"db" => [
			"host" => getenv("DB_HOST"),
			"port" => getenv("DB_PORT"),
			"name" => getenv("DB_NAME"),
			"user" => getenv("DB_USER"),
			"password" => getenv("DB_PASSWORD"),
		],
		"migration_token" => getenv("MIGRATION_TOKEN"),
		"site_url" => getenv("SITE_URL"),
		"admin" => [
			"username" => getenv("ADMIN_USERNAME"),
			"password_hash" => getenv("ADMIN_PASSWORD") === "" ? "" : password_hash(getenv("ADMIN_PASSWORD"), defined("PASSWORD_ARGON2ID") ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT),
		],
	];
	echo "<?php\n\n// Generated at deploy time from GitHub secrets. Do not edit or commit.\nreturn " . var_export($config, true) . ";\n";
' > "$output")
php -l "$output" > /dev/null
echo "Wrote API config to $output"
