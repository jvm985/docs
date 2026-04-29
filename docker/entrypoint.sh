#!/bin/sh
set -e

# Zorg dat de database directory en bestand schrijfbaar zijn voor www-data
chown www-data:www-data /var/www/html/database
chmod 775 /var/www/html/database

if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi
chown www-data:www-data /var/www/html/database/database.sqlite

# Run migrations en cache bij elke start
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
