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
    ],

    'goods' => [
        /** وقت إرسال تذكير المبيعات اليومي (توقيت التطبيق APP_TIMEZONE) */
        'daily_sales_reminder_at' => env('GOODS_DAILY_SALES_REMINDER_AT', '09:00'),
    ],

];
