#!/usr/bin/env bash
#
# Deploy script for docs.irishof.cloud
# Runs on the production server in /opt/irishof/3-docs
#
# Usage: sudo ./deploy.sh
#
# Steps:
#   1. Pull latest code from main
#   2. Rebuild containers
#   3. Wait for app container to be ready
#   4. Run migrations (--force)
#   5. Clear view/config/route caches
#   6. Run the feature test suite (uses sqlite :memory: per phpunit.xml — never touches prod DB)
#   7. Curl-smoke test the public /login page
#
set -euo pipefail

APP_DIR="/opt/irishof/3-docs"
SERVICE="app"

cd "$APP_DIR"

step() { printf '\n\033[1;36m═══ %s ═══\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✓\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

step "Pulling latest from main"
git pull origin main

step "Building and starting containers"
docker compose up -d --build

step "Waiting for app container"
for i in $(seq 1 60); do
    if docker compose exec -T "$SERVICE" php -v >/dev/null 2>&1; then
        ok "app container ready"
        break
    fi
    sleep 1
    if [ "$i" -eq 60 ]; then
        fail "app container did not become ready in 60s"
    fi
done

step "Running migrations"
docker compose exec -T "$SERVICE" php artisan migrate --force

step "Clearing caches"
docker compose exec -T "$SERVICE" php artisan view:clear
docker compose exec -T "$SERVICE" php artisan config:clear
docker compose exec -T "$SERVICE" php artisan route:clear

step "Running feature test suite (ephemeral container, file-based sqlite)"
# Production image is built with --no-dev, so pest/collision aren't available.
# Spin up an ephemeral container, install dev deps, run tests, then drop it.
if docker compose run --rm --no-deps -T \
        --entrypoint sh \
        "$SERVICE" -c "composer install --no-interaction --prefer-dist --quiet 2>&1 | tail -3 && vendor/bin/pest --compact --testsuite=Feature"; then
    ok "feature tests passed"
else
    fail "feature tests failed"
fi

step "Smoke test"
HTTP_CODE=$(curl -sk -o /dev/null -w '%{http_code}' https://docs.irishof.cloud/login || echo "0")
if [ "$HTTP_CODE" = "200" ]; then
    ok "/login responded HTTP 200"
else
    fail "/login responded HTTP $HTTP_CODE"
fi

step "Pruning dangling images"
docker image prune -f >/dev/null || true

printf '\n\033[1;32m✅ Deploy klaar\033[0m\n'
