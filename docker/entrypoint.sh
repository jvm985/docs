#!/bin/sh
set -e

# Copy public assets to the shared volume so nginx can serve them
cp -r /var/www/html/public-image/* /var/www/html/public/ 2>/dev/null || true

# Make storage and public writable for www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/public
chmod -R 775 /var/www/html/storage

# SQLite database lives in the storage volume (so it survives image rebuilds)
DB_DIR=/var/www/html/storage/app/database
DB_FILE=$DB_DIR/database.sqlite
mkdir -p "$DB_DIR"
[ -f "$DB_FILE" ] || touch "$DB_FILE"
chown -R www-data:www-data "$DB_DIR"

# Symlink the standard location so config defaults still work
mkdir -p /var/www/html/database
rm -f /var/www/html/database/database.sqlite
ln -sf "$DB_FILE" /var/www/html/database/database.sqlite
chown -h www-data:www-data /var/www/html/database/database.sqlite

# Migrate and re-cache on every boot
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link 2>/dev/null || true

exec "$@"
