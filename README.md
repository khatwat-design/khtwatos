# Khtwatos - Deployment Guide

هذا المشروع مبني بـ Laravel + Inertia + Vue، وهذا الملف يشرح طريقة نشره على GitHub و VPS مع MySQL.

## 1) تجهيز GitHub

مستودع المشروع:

- `https://github.com/khatwat-design/khtwatos`

إذا كان المشروع غير مربوط بعد:

```bash
git init
git add .
git commit -m "Prepare project for VPS deployment"
git branch -M main
git remote add origin https://github.com/khatwat-design/khtwatos.git
git push -u origin main
```

إذا كان مربوط مسبقاً:

```bash
git add .
git commit -m "Update deployment and production setup"
git push
```

## 2) متطلبات السيرفر (VPS)

- Ubuntu 22.04+ (أو أي Linux مشابه)
- PHP 8.3 مع الامتدادات: `mbstring`, `xml`, `curl`, `zip`, `mysql`, `bcmath`, `ctype`, `tokenizer`, `fileinfo`
- Composer
- Node.js 20+
- Nginx
- MySQL 8+

## 3) إعداد MySQL بشكل قوي

يوجد ملف جاهز:

- `deploy/mysql-init.sql`

نفّذه داخل MySQL بعد تعديل كلمة المرور:

```bash
sudo mysql < deploy/mysql-init.sql
```

المشروع مضبوط على:

- `utf8mb4`
- `utf8mb4_unicode_ci`
- `strict=true` (في `config/database.php`)

## 4) إعداد المشروع على VPS

```bash
cd /var/www
sudo mkdir -p khtwatos
sudo chown -R $USER:$USER khtwatos
cd khtwatos
git clone https://github.com/khatwat-design/khtwatos.git .
cp .env.production.example .env
php artisan key:generate
```

حدّث قيم `.env` الفعلية (الدومين، البريد، كلمات المرور...).

ثم:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

## 5) نشر التحديثات لاحقاً (Deploy)

ملف نشر جاهز:

- `deploy/vps-deploy.sh`

الاستخدام:

```bash
APP_DIR=/var/www/khtwatos BRANCH=main bash deploy/vps-deploy.sh
```

## 6) إعداد Nginx

ملف جاهز:

- `deploy/nginx-khtwatos.conf`

ثم:

```bash
sudo cp deploy/nginx-khtwatos.conf /etc/nginx/sites-available/khtwatos
sudo ln -s /etc/nginx/sites-available/khtwatos /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 7) أوامر ما بعد النشر

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## 8) تشغيل الـ Queue Worker تلقائياً (Supervisor)

ملف جاهز:

- `deploy/supervisor-laravel-worker.conf`

الأوامر:

```bash
sudo cp deploy/supervisor-laravel-worker.conf /etc/supervisor/conf.d/khtwatos-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

> ملاحظة: صفحة تسجيل الدخول لا تحتوي الآن على خيار إنشاء حساب من الواجهة.
