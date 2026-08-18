#!/usr/bin/env bash
# استعادة HTTPS بعد استبدال خطأ لملف nginx (منفذ 443 لا يستجيب)
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/khtwatos}"
NGINX_SITE="${NGINX_SITE:-/etc/nginx/sites-available/khtwatos}"
DOMAIN="${DOMAIN:-os.kharijm.com}"
REPO_EXAMPLE="${APP_DIR}/deploy/nginx-khtwatos-ssl.conf.example"

echo "==> Diagnostics"
systemctl is-active nginx || true
ss -tlnp | grep -E ':80|:443' || true

if [[ -f "${NGINX_SITE}.bak" ]]; then
  echo "==> Restoring nginx from ${NGINX_SITE}.bak"
  cp -a "${NGINX_SITE}.bak" "${NGINX_SITE}"
elif [[ -f /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ]]; then
  echo "==> Cert exists; applying SSL example from repo"
  if [[ ! -f "${REPO_EXAMPLE}" ]]; then
    echo "Missing ${REPO_EXAMPLE}. Run: cd ${APP_DIR} && git pull origin main"
    exit 1
  fi
  cp -a "${REPO_EXAMPLE}" "${NGINX_SITE}"
else
  echo "No backup and no Let's Encrypt cert for ${DOMAIN}."
  echo "Try: certbot --nginx -d ${DOMAIN}"
  exit 1
fi

echo "==> Remove stray public/home if any"
if [[ -d "${APP_DIR}/public/home" ]] && [[ ! -f "${APP_DIR}/public/home/index.php" ]]; then
  rm -rf "${APP_DIR}/public/home"
fi

echo "==> Ensure try_files (no \$uri/)"
if grep -q 'try_files \$uri \$uri/' "${NGINX_SITE}"; then
  sed -i.bak2 's|try_files \$uri \$uri/ /index.php?\$query_string;|try_files $uri /index.php?$query_string;|g' "${NGINX_SITE}"
fi

nginx -t
systemctl reload nginx

echo "==> Local checks"
curl -sI --connect-timeout 5 "https://${DOMAIN}/" | head -5 || true
curl -sI --connect-timeout 5 "http://${DOMAIN}/" | head -5 || true

echo "Done. Open https://${DOMAIN}/ in browser."
