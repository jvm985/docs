#!/bin/sh
set -e

# Copy public assets to the shared volume so nginx can serve them
cp -r /var/www/html/public-image/* /var/www/html/public/ 2>/dev/null || true

# Make storage and public writable for www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/public
find /var/www/html/storage -type d -exec chmod 775 {} +
find /var/www/html/storage -type f -exec chmod 664 {} +

# Shared R library (bind-mounted from host); ensure www-data can write
if [ -d /opt/r-site-library ]; then
    chown -R www-data:www-data /opt/r-site-library 2>/dev/null || true
    chmod 775 /opt/r-site-library 2>/dev/null || true
fi

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
