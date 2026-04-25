#!/bin/bash

# Configuration
SERVER="irishof.cloud"
DEST="/opt/irishof/11-docs"
REPO="git@github.com:jvm985/docs.git"

# Extract secrets from local .env
G_ID=$(grep GOOGLE_CLIENT_ID .env | cut -d '=' -f2)
G_SECRET=$(grep GOOGLE_CLIENT_SECRET .env | cut -d '=' -f2)
A_KEY=$(grep APP_KEY .env | cut -d '=' -f2)

echo "🚀 Committing and pushing to GitHub..."
git add .
git commit -m "Deploy update $(date)"
git push origin main

echo "🌐 Deploying to $SERVER..."
ssh $SERVER "mkdir -p $DEST && cd $DEST && \
    if [ ! -d .git ]; then git clone $REPO .; else git pull origin main; fi && \
    sudo GOOGLE_CLIENT_ID='$G_ID' \
         GOOGLE_CLIENT_SECRET='$G_SECRET' \
         APP_KEY='$A_KEY' \
         docker compose down && \
    sudo GOOGLE_CLIENT_ID='$G_ID' \
         GOOGLE_CLIENT_SECRET='$G_SECRET' \
         APP_KEY='$A_KEY' \
         docker compose up -d --build && \
    echo '⏳ Waiting for database...' && sleep 5 && \
    sudo docker exec docs-app php artisan migrate --force && \
    echo '🔍 Running automated compiler audit...' && \
    sudo docker exec -u www-data docs-app php artisan app:audit-compilers"

echo "✅ Deployment en Audit voltooid!"
echo "App is live op https://docs.irishof.cloud"
