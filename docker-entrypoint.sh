#!/bin/sh
set -e

# Sync het VOLLEDIGE public directory naar het gedeelde volume
# Dit dwingt Nginx om de nieuwste HTML (index.php), Favicons én Vite builds te zien
echo "🚚 Deep syncing public directory to shared volume..."
cp -rf /var/www/public/* /var/www/public_shared/

# Start de standaard PHP-FPM
exec php-fpm
