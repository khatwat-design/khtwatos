#!/usr/bin/env bash
# إصلاح 403 على /home — تشغيل على السيرفر كـ root أو sudo
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/khtwatos}"
NGINX_SITE="${NGINX_SITE:-/etc/nginx/sites-available/khtwatos}"

echo "==> Check stray public/home"
if [[ -d "${APP_DIR}/public/home" ]] && [[ ! -f "${APP_DIR}/public/home/index.php" ]]; then
  echo "Removing ${APP_DIR}/public/home"
  rm -rf "${APP_DIR}/public/home"
else
  echo "No stray public/home (OK)"
fi

echo "==> Patch nginx try_files if needed"
if [[ -f "${NGINX_SITE}" ]] && grep -q 'try_files \$uri \$uri/' "${NGINX_SITE}"; then
  sed -i.bak 's|try_files \$uri \$uri/ /index.php?\$query_string;|try_files $uri /index.php?$query_string;|g' "${NGINX_SITE}"
  nginx -t
  systemctl reload nginx
  echo "nginx reloaded."
else
  echo "Update ${NGINX_SITE} manually: use try_files \$uri /index.php?$query_string;"
  echo "WARNING: do NOT copy deploy/nginx-khtwatos.conf over production — it has no HTTPS."
  echo "If https:// times out, run: sudo bash deploy/fix-restore-https.sh"
fi

echo "Done. Test: curl -I https://os.kharijm.com/home"
