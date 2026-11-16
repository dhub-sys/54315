#!/usr/bin/env bash
# Builds dist/warranty-manager-demo-<version>.zip (the folder inside the zip is the plugin folder).
set -euo pipefail
cd "$(dirname "$0")"
ver=$(sed -n "s/.*define('TW_VERSION', '\([^']*\)').*/\1/p" warranty-manager-demo/thermic-warranty.php)
mkdir -p dist
rm -f "dist/warranty-manager-demo-$ver.zip"
zip -qr -X "dist/warranty-manager-demo-$ver.zip" warranty-manager-demo -x '*.DS_Store'
echo "dist/warranty-manager-demo-$ver.zip"
