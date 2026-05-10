<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * قيم فعلية تجمع إعدادات .env مع تجاوزات لوحة الإعدادات (جدول system_settings).
 */
class EffectiveSettings
{
    public static function firebaseMobilePushEnabled(): bool
    {
        if (! (bool) config('services.firebase.mobile_push_enabled', false)) {
            return false;
        }

        return SystemSetting::boolean('firebase_mobile_push_enabled', true);
    }

    public static function whatsappMilestoneNotificationsEnabled(): bool
    {
        if (! (bool) config('services.whatsapp.milestone_notifications_enabled', true)) {
            return false;
        }

        return SystemSetting::boolean('whatsapp_milestone_notifications_enabled', true);
    }

    public static function portalDailySalesReminderEnabled(): bool
    {
        if (! (bool) config('services.portal.daily_sales_reminder_enabled', true)) {
            return false;
        }

        return SystemSetting::boolean('portal_daily_sales_reminder_enabled', true);
    }

    public static function goodsDailySalesRemindersEnabled(): bool
    {
        return SystemSetting::boolean('goods_daily_sales_reminders_enabled', true);
    }

    public static function clientDailyReportsEnabled(): bool
    {
        if (! (bool) config('services.client_reports.enabled_daily', true)) {
            return false;
        }

        return SystemSetting::boolean('client_reports_daily_enabled', true);
    }

    public static function clientWeeklyReportsEnabled(): bool
    {
        if (! (bool) config('services.client_reports.enabled_weekly', true)) {
            return false;
        }

        return SystemSetting::boolean('client_reports_weekly_enabled', true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'firebase_mobile_push_enabled' => [
                'label' => 'إشعارات الجوال (Firebase)',
                'help' => 'تسجيل أجهزة أندرويد/آيفون وإرسال FCM عند توفر المفتاح على الخادم. يتطلب تفعيل FIREBASE_MOBILE_PUSH_ENABLED في البيئة.',
                'locked' => ! (bool) config('services.firebase.mobile_push_enabled', false),
                'lock_hint' => 'معطّل من ملف البيئة (.env): عيّن FIREBASE_MOBILE_PUSH_ENABLED=true بعد إعداد google-services.json.',
            ],
            'whatsapp_milestone_notifications_enabled' => [
                'label' => 'واتساب — إشعارات مراحل العميل',
                'help' => 'رسائل أوتوماتيكية عند تقدّم العميل في مسار العمل.',
                'locked' => ! (bool) config('services.whatsapp.milestone_notifications_enabled', true),
                'lock_hint' => 'معطّل من البيئة: WHATSAPP_MILESTONE_NOTIFICATIONS',
            ],
            'portal_daily_sales_reminder_enabled' => [
                'label' => 'تذكير مبيعات اليوم (بوابة العميل)',
                'help' => 'أمر portal:send-daily-sales-reminders',
                'locked' => ! (bool) config('services.portal.daily_sales_reminder_enabled', true),
                'lock_hint' => 'معطّل من البيئة: CLIENT_PORTAL_DAILY_SALES_REMINDER',
            ],
            'goods_daily_sales_reminders_enabled' => [
                'label' => 'تذكير مبيعات اليوم (قسم البضاعة)',
                'help' => 'أمر goods:send-daily-sales-reminders',
                'locked' => false,
                'lock_hint' => null,
            ],
            'client_reports_daily_enabled' => [
                'label' => 'التقرير اليومي للعملاء',
                'help' => 'أمر portal:send-daily-client-reports',
                'locked' => ! (bool) config('services.client_reports.enabled_daily', true),
                'lock_hint' => 'معطّل من البيئة: CLIENT_DAILY_REPORTS',
            ],
            'client_reports_weekly_enabled' => [
                'label' => 'التقرير الأسبوعي للعملاء',
                'help' => 'أمر portal:send-weekly-client-reports',
                'locked' => ! (bool) config('services.client_reports.enabled_weekly', true),
                'lock_hint' => 'معطّل من البيئة: CLIENT_WEEKLY_REPORTS',
            ],
        ];
    }
}
