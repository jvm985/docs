#!/bin/sh
set -e

# Simpele assets sync voor Nginx
echo "🚚 Syncing assets..."
cp -rf /var/www/public/build/* /var/www/public_shared/build/ 2>/dev/null || true

# Start de standaard PHP-FPM
exec php-fpm
