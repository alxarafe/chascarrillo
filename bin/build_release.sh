#!/bin/bash

set -euo pipefail

VERSION=${1:?"Uso: ./bin/build_release.sh vX.Y.Z"}
OUTPUT_FILE="chascarrillo-deploy-${VERSION}.zip"
VERIFY_DIR=$(mktemp -d)
trap 'rm -rf "$VERIFY_DIR"' EXIT

echo "Preparando paquete de despliegue ${VERSION}"
composer validate --strict
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php scripts/publish_theme_assets.php
php scripts/build_distribution_manifest.php --tag "$VERSION"
php scripts/validate_release.php --tag "$VERSION"

zip -D -r "$OUTPUT_FILE" . \
    -x "*.git*" \
    -x ".htaccess" \
    -x "*/.htaccess" \
    -x "Content" \
    -x "Content/*" \
    -x "storage" \
    -x "storage/*" \
    -x "var" \
    -x "var/*" \
    -x "public_html/uploads" \
    -x "public_html/uploads/*" \
    -x "config.json" \
    -x ".env" \
    -x "tmp/*" \
    -x ".phpunit.cache" \
    -x ".phpunit.cache/*" \
    -x "docs/audit/*" \
    -x "coverage/*" \
    -x "reports/*" \
    -x ".DS_Store" \
    -x "*/.DS_Store" \
    -x "*.log" \
    -x "*.tmp" \
    -x "*.zip" \
    -x "Tests/*" \
    -x "phpunit.xml" \
    -x "phpcs.xml" \
    -x "phpstan.neon" \
    -x "phpstan-bootstrap.php" \
    -x "psalm.xml" \
    -x "psalm-stubs.php" \
    -x ".agents/*" \
    -x ".codex/*" \
    -x ".github/*" \
    -x "$OUTPUT_FILE"

unzip -q "$OUTPUT_FILE" -d "$VERIFY_DIR"
php scripts/validate_release.php "$VERIFY_DIR" --artifact --tag "$VERSION"
echo "Paquete verificado: ${OUTPUT_FILE}"
