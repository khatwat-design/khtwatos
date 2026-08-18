<?php

return [

    /*
    |--------------------------------------------------------------------------
    | مجلد النسخ داخل storage/app
    |--------------------------------------------------------------------------
    */

    'directory' => env('BACKUP_DIRECTORY', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | وضع النسخ: full = قاعدة + ملفات التخزين المحلية في أرشيف مرتب؛ database_only = ملف SQL فقط
    |--------------------------------------------------------------------------
    */

    'mode' => env('BACKUP_MODE', 'full'),

    /*
    |--------------------------------------------------------------------------
    | في الوضع الكامل: نسخ مجلدات الأقراص المحلية (مرفقات العملاء، المهام، الدردشة، الصور…)
    |--------------------------------------------------------------------------
    */

    'include_public_storage' => filter_var(env('BACKUP_INCLUDE_PUBLIC_STORAGE', true), FILTER_VALIDATE_BOOLEAN),

    'include_private_storage' => filter_var(env('BACKUP_INCLUDE_PRIVATE_STORAGE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | ضغط ملف SQL قبل إدخاله للأرشيف
    |--------------------------------------------------------------------------
    */

    'compress' => filter_var(env('BACKUP_COMPRESS', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | تشفير الأرشيف النهائي بـ OpenSSL (AES-256-CBC + PBKDF2). يُنصح بتفعيله على السيرفر.
    |--------------------------------------------------------------------------
    */

    'encrypt' => filter_var(env('BACKUP_ENCRYPT', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | كلمة المرور للتشفير وفكّه (لا تُخزَّن في الكود — ضعها في .env فقط). طولها ≥ 16.
    |--------------------------------------------------------------------------
    */

    'encryption_password' => env('BACKUP_ENCRYPTION_PASSWORD'),

    'openssl_pbkdf2_iterations' => (int) env('BACKUP_OPENSSL_ITERATIONS', 600000),

    /*
    |--------------------------------------------------------------------------
    | أقصى عدد ملفات نسخ يُحتفظ بها (الأحدث يُبقى، الأقدم يُحذف تلقائياً)
    |--------------------------------------------------------------------------
    */

    'keep_max_files' => (int) env('BACKUP_KEEP_MAX', 30),

    /*
    |--------------------------------------------------------------------------
    | جدولة يومية عبر Laravel Scheduler (يتطلب cron: schedule:run)
    |--------------------------------------------------------------------------
    */

    'schedule_enabled' => filter_var(env('BACKUP_SCHEDULE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | تكرار النسخ التلقائي: عدد الساعات بين كل تشغيل لـ db:backup (مثلاً 2 = كل ساعتين عند الدقيقة 0).
    | ضع 0 أو ≥24 لاستخدام الجدولة اليومية بالوقت schedule_at بدلاً من ذلك.
    |--------------------------------------------------------------------------
    */

    'schedule_every_hours' => (int) env('BACKUP_SCHEDULE_EVERY_HOURS', 2),

    'schedule_at' => env('BACKUP_SCHEDULE_AT', '03:30'),

    /*
    |--------------------------------------------------------------------------
    | مدة قفل withoutOverlapping بالدقائق (منع تداخل تشغيلين إن استغرق النسخ وقتاً أطول).
    |--------------------------------------------------------------------------
    */

    'schedule_overlap_minutes' => (int) env('BACKUP_SCHEDULE_OVERLAP_MINUTES', 90),

    /*
    |--------------------------------------------------------------------------
    | مسارات الأدوات على الخادم (إن لزم)
    |--------------------------------------------------------------------------
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),

    'sqlite3_path' => env('BACKUP_SQLITE3_PATH', 'sqlite3'),

    'tar_path' => env('BACKUP_TAR_PATH', 'tar'),

    'openssl_path' => env('BACKUP_OPENSSL_PATH', 'openssl'),

    /*
    |--------------------------------------------------------------------------
    | رفع النسخ إلى مستودع GitHub خاص (git commit + push). يتطلب git على الخادم ورمز وصول (classic PAT مع repo، أو fine-grained مع Contents: RW).
    |--------------------------------------------------------------------------
    */

    'github_push_enabled' => filter_var(env('BACKUP_GITHUB_PUSH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'github_token' => env('BACKUP_GITHUB_TOKEN'),

    'github_owner' => env('BACKUP_GITHUB_OWNER'),

    'github_repo' => env('BACKUP_GITHUB_REPO'),

    'github_branch' => env('BACKUP_GITHUB_BRANCH', 'main'),

    'github_subpath' => env('BACKUP_GITHUB_SUBPATH', 'backups'),

    'github_workdir' => env('BACKUP_GITHUB_WORKDIR', ''),

    /*
    |--------------------------------------------------------------------------
    | بعد نجاح الرفع إلى GitHub: حذف الملف من storage/app/backups (القائمة والتنزيل من لوحة التحكم لن يعرضا ذلك الملف).
    |--------------------------------------------------------------------------
    */

    'delete_local_after_github_push' => filter_var(env('BACKUP_DELETE_LOCAL_AFTER_GITHUB_PUSH', true), FILTER_VALIDATE_BOOLEAN),

];
