#!/usr/bin/env bash
# Copies the runtime PHP API into dist/api/ (deployed to public_html/api/).
# Only runtime files: no tests, vendor/ or dev config. config/config.php is written later, at deploy time.
set -euo pipefail
cd "$(dirname "$0")/.."

target=dist/api
rm -rf "$target"
mkdir -p "$target"
cp server/public/index.php server/public/page.php server/public/.htaccess "$target/"
cp -r server/src server/routes server/migrations server/templates server/content "$target/"
echo "Packaged PHP API into $target ($(find "$target" -type f | wc -l) files)"
