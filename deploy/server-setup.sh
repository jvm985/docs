#!/bin/bash
# Eénmalige serveropstelling voor docs.irishof.cloud
# Uitvoeren als root of met sudo

set -e

APP_DIR="/var/www/docs"
DOMAIN="docs.irishof.cloud"

# --- Vereiste packages ---
apt update
apt install -y nginx php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip unzip git nodejs npm supervisor certbot \
    python3-certbot-nginx

# --- Composer ---
if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# --- App ophalen ---
mkdir -p "$APP_DIR"
git clone git@github.com:jvm985/docs.git "$APP_DIR"
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# --- .env ---
cp "$APP_DIR/.env.example" "$APP_DIR/.env"
# Vul handmatig in: APP_KEY, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI
# sed -i aanpassingen kunnen hier worden toegevoegd

cd "$APP_DIR"
php artisan key:generate

# SQLite database aanmaken
touch "$APP_DIR/database/database.sqlite"
chown www-data:www-data "$APP_DIR/database/database.sqlite"

# --- Dependencies ---
sudo -u www-data composer install --no-interaction --no-dev --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build

# --- Database ---
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache

# --- Nginx ---
cp "$APP_DIR/deploy/nginx.conf" /etc/nginx/sites-available/docs
ln -sf /etc/nginx/sites-available/docs /etc/nginx/sites-enabled/docs
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# --- Let's Encrypt ---
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m admin@irishof.cloud
systemctl reload nginx

# --- Supervisor ---
cp "$APP_DIR/deploy/supervisor.conf" /etc/supervisor/conf.d/docs-worker.conf
supervisorctl reread
supervisorctl update
supervisorctl start docs-worker:*

echo ""
echo "Klaar! App bereikbaar op https://$DOMAIN"
echo "Vergeet .env in te vullen (APP_KEY, GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI)"
