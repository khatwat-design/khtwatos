#!/usr/bin/env bash
set -euo pipefail

# الرجوع لنسخة سابقة على السيرفر (قبل آخر نشر).
# مثال — قبل جولات التدريب:
#   ROLLBACK_COMMIT=14307ba APP_DIR=/var/www/khtwatos bash deploy/vps-rollback.sh
#
# Usage:
#   ROLLBACK_COMMIT=<sha> APP_DIR=/var/www/khtwatos bash deploy/vps-rollback.sh

APP_DIR="${APP_DIR:-/var/www/khtwatos}"
ROLLBACK_COMMIT="${ROLLBACK_COMMIT:?Set ROLLBACK_COMMIT to the git SHA to restore (e.g. 14307ba)}"

echo "Rolling back ${APP_DIR} to commit ${ROLLBACK_COMMIT}"
cd "${APP_DIR}"

php artisan down || true

git fetch origin
git reset --hard "${ROLLBACK_COMMIT}"

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

if command -v npm >/dev/null 2>&1; then
  npm ci --no-audit --no-fund
  npm run build
else
  echo "npm not found: skipping frontend build."
fi

# اختياري: تراجع آخر migration إن وُجد (مثلاً جدول الجولات)
php artisan migrate:rollback --force --step=1 2>/dev/null || true

if [[ -d public/home ]] && [[ ! -f public/home/index.php ]]; then
  rm -rf public/home
fi

php artisan optimize:clear
php artisan optimize
php artisan storage:link || true

php artisan up
echo "Rollback to ${ROLLBACK_COMMIT} completed."
