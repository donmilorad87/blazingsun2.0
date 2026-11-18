#!/usr/bin/env bash
# Writes the PHP API config (a PHP file returning an array) from environment variables.
# Used by the deploy (GitHub secrets) and the API smoke test. var_export() escapes every value,
# so passwords with quotes or $ cannot break or inject PHP code.
# Usage: DB_HOST=… DB_PORT=… DB_NAME=… DB_USER=… DB_PASSWORD=… MIGRATION_TOKEN=… scripts/write-api-config.sh <output-file>
set -euo pipefail

output="${1:?usage: write-api-config.sh <output-file>}"
: "${DB_HOST:?missing}" "${DB_PORT:?missing}" "${DB_NAME:?missing}" "${DB_USER:?missing}" "${DB_PASSWORD:?missing}"
export MIGRATION_TOKEN="${MIGRATION_TOKEN:-}"

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
	];
	echo "<?php\n\n// Generated at deploy time from GitHub secrets. Do not edit or commit.\nreturn " . var_export($config, true) . ";\n";
' > "$output")
php -l "$output" > /dev/null
echo "Wrote API config to $output"
