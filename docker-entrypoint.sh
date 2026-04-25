#!/bin/sh
set -e

# Verwijder oude build bestanden om vervuiling en cache-fouten te voorkomen
echo "🧹 Cleaning shared assets..."
rm -rf /var/www/public_shared/build*

# Kopieer de verse build bestanden uit de image naar het volume
echo "🚚 Syncing fresh assets (V8)..."
cp -rf /var/www/public/build_v8 /var/www/public_shared/

# Start de standaard PHP-FPM
exec php-fpm
