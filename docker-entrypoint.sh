#!/bin/sh
set -e

# Verwijder oude build bestanden om vervuiling en cache-fouten te voorkomen
echo "🧹 Cleaning old assets..."
rm -rf /var/www/public_shared/build/*

# Kopieer de verse build bestanden uit de image naar het volume
echo "🚚 Syncing fresh assets..."
cp -rf /var/www/public/build/* /var/www/public_shared/build/ 2>/dev/null || true

# Start de standaard PHP-FPM
exec php-fpm
