#!/bin/sh
set -e

# Sync het VOLLEDIGE public directory naar het gedeelde volume
echo "🚚 Deep syncing public directory to shared volume..."
cp -rf /var/www/public/* /var/www/public_shared/

# Start de standaard PHP-FPM
exec php-fpm
