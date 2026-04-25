#!/bin/sh
set -e

# Kopieer de verse build bestanden uit de image naar het volume
# Dit zorgt ervoor dat Nginx altijd de nieuwste JS/CSS ziet
echo "🚚 Syncing assets..."
cp -rf /var/www/public/build/* /var/www/public_shared/build/ 2>/dev/null || true

# Start de standaard PHP-FPM
exec php-fpm
