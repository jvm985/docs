#!/bin/sh
set -e

# Kopieer public assets van image naar volume (zodat nginx ze kan serveren)
cp -r /var/www/html/public-image/* /var/www/html/public/ 2>/dev/null || true

# Zorg dat storage en database schrijfbaar zijn voor www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/database /var/www/html/public
chmod -R 775 /var/www/html/storage /var/www/html/database

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
php artisan storage:link 2>/dev/null || true

exec "$@"
