#!/usr/bin/env bash
set -euo pipefail

# Usage:
# APP_DIR=/var/www/khtwatos BRANCH=main bash deploy/vps-deploy.sh

APP_DIR="${APP_DIR:-/var/www/khtwatos}"
BRANCH="${BRANCH:-main}"

echo "Deploying branch ${BRANCH} in ${APP_DIR}"
cd "${APP_DIR}"

php artisan down || true

git fetch origin "${BRANCH}"
git reset --hard "origin/${BRANCH}"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if command -v npm >/dev/null 2>&1; then
  npm ci --no-audit --no-fund
  npm run build
else
  echo "npm not found: skipping frontend build."
fi

php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan storage:link || true

php artisan up
echo "Deployment completed successfully."
