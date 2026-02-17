#!/bin/bash
# Description: Prepares the project for FTP deployment by cleaning up unnecessary files.

set -e

echo "=== 1. Running Composer for Production ==="
composer install --no-dev --optimize-autoloader

echo "=== 2. Cleaning up Vendor folder ==="
# Remove common heavy and unnecessary folders in vendor
find vendor -type d -name "tests" -exec rm -rf {} +
find vendor -type d -name "Test" -exec rm -rf {} +
find vendor -type d -name "docs" -exec rm -rf {} +
find vendor -type d -name ".git" -exec rm -rf {} +
find vendor -type f -name "*.md" -delete
find vendor -type f -name "phpunit.xml*" -delete
find vendor -type f -name ".gitignore" -delete

echo "=== 3. Cleaning up local tmp files ==="
rm -rf tmp/*

echo "=== 4. Removing web-migration tools (safety check) ==="
# We assume the user will upload them if needed, but we don't want them in the 'clean' repo
# Actually, the user asked for them, so maybe we leave them but warn.
echo "NOTE: Remember to upload public_html/web_migrate.php only when you need to run migrations."

echo "=== Done! Project is ready for FTP upload. ==="
echo "Tip: Use 'lftp' or 'rsync' for faster differential uploads if your host supports them."
