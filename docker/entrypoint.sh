#!/bin/sh
set -e

# ─── Phase 1: root setup ───

# Copy public assets to the shared volume so nginx can serve them
cp -r /var/www/html/public-image/* /var/www/html/public/ 2>/dev/null || true

# Make storage + public + bootstrap/cache writable for www-data
chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/public \
    /var/www/html/bootstrap/cache
find /var/www/html/storage -type d -exec chmod 775 {} +
find /var/www/html/storage -type f -exec chmod 664 {} +
find /var/www/html/bootstrap/cache -type d -exec chmod 775 {} +

# Shared R library (bind-mounted from host); ensure www-data can write
if [ -d /opt/r-site-library ]; then
    chown -R www-data:www-data /opt/r-site-library 2>/dev/null || true
    chmod 775 /opt/r-site-library 2>/dev/null || true
fi

# SQLite DB lives in the storage volume (survives image rebuilds)
DB_DIR=/var/www/html/storage/app/database
DB_FILE=$DB_DIR/database.sqlite
mkdir -p "$DB_DIR"
[ -f "$DB_FILE" ] || touch "$DB_FILE"
chown -R www-data:www-data "$DB_DIR"

# Symlink standard location → storage volume
mkdir -p /var/www/html/database
chown www-data:www-data /var/www/html/database
rm -f /var/www/html/database/database.sqlite
ln -sf "$DB_FILE" /var/www/html/database/database.sqlite
chown -h www-data:www-data /var/www/html/database/database.sqlite

# ─── Phase 2: drop to www-data, finish boot, hand off ───

gosu www-data php artisan migrate --force
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
gosu www-data php artisan event:cache
gosu www-data php artisan storage:link 2>/dev/null || true

# php-fpm master + workers (or queue:work for the worker service) as www-data
exec gosu www-data "$@"
