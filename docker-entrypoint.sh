#!/bin/sh
set -e

# Harde schoonmaak van de gedeelde map
echo "🧹 Purging shared assets..."
rm -rf /var/www/public_shared/FINAL_V11 2>/dev/null || true

# Kopieer de nieuwe build
echo "🚚 Syncing fresh assets (V11)..."
cp -rf /var/www/public/FINAL_V11 /var/www/public_shared/

# Start de standaard PHP-FPM
exec php-fpm
