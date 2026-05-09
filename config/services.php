<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'calendly' => [
        'webhook_signing_key' => env('CALENDLY_WEBHOOK_SIGNING_KEY'),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@os.kharijm.com'),
    ],

    'meta_ads' => [
        'app_id' => env('META_ADS_APP_ID'),
        'app_secret' => env('META_ADS_APP_SECRET'),
        'redirect_uri' => env('META_ADS_REDIRECT_URI'),
        'portal_redirect_uri' => env('META_ADS_PORTAL_REDIRECT_URI'),
        'profile_redirect_uri' => env('META_ADS_PROFILE_REDIRECT_URI'),
        'access_token' => env('META_ADS_ACCESS_TOKEN'),
        'version' => env('META_ADS_GRAPH_VERSION', 'v22.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram (قسم الخارج — Webhook + Graph Messaging)
    |--------------------------------------------------------------------------
    |
    | التحقق من الويب هوك: نفس مسار Laravel ‎/outside/webhook‎ لـ WhatsApp و Instagram.
    | إن تركت INSTAGRAM_* فارغة تُستخدم قيم META_ADS_* (نفس تطبيق ميتا غالباً).
    |
    */
    'instagram' => [
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN', env('WHATSAPP_WEBHOOK_VERIFY_TOKEN')),
        /** معرّف يظهر في Meta → Instagram (أو نفس META_ADS_APP_ID) */
        'app_id' => env('INSTAGRAM_APP_ID', env('META_ADS_APP_ID')),
        /** المفتاح السري لمنتج Instagram في لوحة المطورين إن اختلف عن تطبيق فيسبوك الرئيسي */
        'app_secret' => env('INSTAGRAM_APP_SECRET', env('META_ADS_APP_SECRET')),
        'graph_version' => env('INSTAGRAM_GRAPH_VERSION', env('META_ADS_GRAPH_VERSION', 'v22.0')),
        /**
         * رمز وصول طويل من مدير الأعمال → مستخدم النظام → إنشاء رمز (صلاحيات Instagram messaging).
         * إن وُجد يُستخدم لإرسال DM بدل توكن بوابة العميل.
         */
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        /**
         * معرّف Instagram Business Account لاستدعاء ‎POST /{ig-id}/messages‎ (نفس اللي في الويب هوك entry.id عادةً).
         * إن وُجد مع access_token يُفضَّل على قيمة التكامل للعميل.
         */
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Messenger (قسم الخارج — Webhook object: page + Send API)
    |--------------------------------------------------------------------------
    |
    | نفس مسار التحقق /outside/webhook — في Meta اشترك في Page + subscriptions للرسائل.
    | الإرسال: POST /{page-id}/messages برمز صفحة يملك pages_messaging.
    |
    */
    'messenger' => [
        'webhook_verify_token' => env('MESSENGER_WEBHOOK_VERIFY_TOKEN', env('WHATSAPP_WEBHOOK_VERIFY_TOKEN')),
        'graph_version' => env('MESSENGER_GRAPH_VERSION', env('META_ADS_GRAPH_VERSION', 'v22.0')),
        'page_id' => env('MESSENGER_PAGE_ID'),
        'access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),
        /**
         * قالب معتمد في Meta لإرسال بيانات دخول الموظفين لمن لم يتواصل مع رقم الواتساب خلال 24 ساعة.
         * نص القالب يجب أن يحتوي متغيرات الجسم {{1}} … {{5}} بالترتيب: الاسم، اسم المستخدم، كلمة المرور، رابط الدخول، الدور.
         */
        'employee_credentials_template' => env('WHATSAPP_EMPLOYEE_CREDENTIALS_TEMPLATE'),
        'employee_credentials_template_lang' => env('WHATSAPP_EMPLOYEE_CREDENTIALS_LANG', 'ar'),
        /** إشعارات مراحل العميل (لا تُعدّل منطق سير العمل — تُفعّل من مراقب ClientStageHistory) */
        'milestone_notifications_enabled' => env('WHATSAPP_MILESTONE_NOTIFICATIONS', true),
        /** أقل فاصل بالساعات بين أي رسالتين أوتوماتيكيتين لنفس العميل لتقليل الإزعاج */
        'milestone_min_hours_between' => (float) env('WHATSAPP_MILESTONE_MIN_HOURS', 6),
    ],

    'goods' => [
        /** وقت إرسال تذكير المبيعات اليومي (توقيت التطبيق APP_TIMEZONE) */
        'daily_sales_reminder_at' => env('GOODS_DAILY_SALES_REMINDER_AT', '09:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | بوابة العميل — تذكير المبيعات اليومية (واتساب)
    |--------------------------------------------------------------------------
    */
    'portal' => [
        'daily_sales_reminder_enabled' => env('CLIENT_PORTAL_DAILY_SALES_REMINDER', true),
        /** وقت التذكير إن لم تُسجَّل مبيعات اليوم (APP_TIMEZONE) */
        'daily_sales_reminder_at' => env('CLIENT_PORTAL_DAILY_SALES_REMINDER_AT', '18:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | تقارير العميل الأوتوماتيكية (قراءة فقط من مبيعات/حملات — لا تغيّر منطق التحليلات)
    |--------------------------------------------------------------------------
    */
    'client_reports' => [
        'enabled_daily' => env('CLIENT_DAILY_REPORTS', true),
        'enabled_weekly' => env('CLIENT_WEEKLY_REPORTS', true),
        /** both | whatsapp | portal */
        'delivery' => env('CLIENT_REPORTS_DELIVERY', 'both'),
        'daily_at' => env('CLIENT_DAILY_REPORT_AT', '19:30'),
        'weekly_at' => env('CLIENT_WEEKLY_REPORT_AT', '10:00'),
        /** 0 = الأحد … 6 = السبت (Carbon weeklyOn) */
        'weekly_on' => (int) env('CLIENT_WEEKLY_REPORT_DAY', 0),
    ],

];
