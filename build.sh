#!/usr/bin/env bash
# Builds dist/warranty-sales-demo-<version>.zip (the folder inside the zip is the plugin folder).
set -euo pipefail
cd "$(dirname "$0")"
ver=$(sed -n "s/.*define('TWD_VERSION', '\([^']*\)').*/\1/p" warranty-sales-demo/warranty-sales-demo.php)
mkdir -p dist
rm -f dist/*.zip
zip -qr -X "dist/warranty-sales-demo-$ver.zip" warranty-sales-demo -x '*.DS_Store'
echo "dist/warranty-sales-demo-$ver.zip"
