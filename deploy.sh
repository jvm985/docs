#!/bin/bash
set -e

REPO="git@github.com:jvm985/docs.git"
SERVER="irishof.cloud"
DEST="/opt/irishof/11-docs"

echo "🚀 Committing and pushing to GitHub..."
git add .
git commit -m "Deploy update $(date)" || true
git push origin main

# Extract secrets from local .env
G_ID=$(grep GOOGLE_CLIENT_ID .env | cut -d= -f2)
G_SECRET=$(grep GOOGLE_CLIENT_SECRET .env | cut -d= -f2)
A_KEY=$(grep APP_KEY .env | cut -d= -f2)

echo "🌐 Deploying to $SERVER..."
ssh $SERVER "mkdir -p $DEST && cd $DEST && \
    if [ ! -d .git ]; then git clone $REPO .; else git pull origin main; fi && \
    sudo GOOGLE_CLIENT_ID='$G_ID' \
         GOOGLE_CLIENT_SECRET='$G_SECRET' \
         APP_KEY='$A_KEY' \
         docker compose up -d --build"

echo "✅ Deployment voltooid!"
echo "App is live op https://docs.irishof.cloud"
