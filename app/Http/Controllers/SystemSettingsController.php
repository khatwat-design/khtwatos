<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\EffectiveSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $definitions = EffectiveSettings::definitions();
        $items = [];

        foreach ($definitions as $key => $meta) {
            $locked = (bool) ($meta['locked'] ?? false);
            $stored = SystemSetting::boolean($key, true);
            $effective = match ($key) {
                'firebase_mobile_push_enabled' => EffectiveSettings::firebaseMobilePushEnabled(),
                'whatsapp_milestone_notifications_enabled' => EffectiveSettings::whatsappMilestoneNotificationsEnabled(),
                'portal_daily_sales_reminder_enabled' => EffectiveSettings::portalDailySalesReminderEnabled(),
                'goods_daily_sales_reminders_enabled' => EffectiveSettings::goodsDailySalesRemindersEnabled(),
                'client_reports_daily_enabled' => EffectiveSettings::clientDailyReportsEnabled(),
                'client_reports_weekly_enabled' => EffectiveSettings::clientWeeklyReportsEnabled(),
                default => false,
            };

            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'help' => $meta['help'],
                'locked' => $locked,
                'lock_hint' => $meta['lock_hint'] ?? null,
                'value' => $locked ? $effective : $stored,
                'effective' => $effective,
            ];
        }

        return Inertia::render('Settings/Index', [
            'items' => $items,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $definitions = EffectiveSettings::definitions();
        $rules = [];

        foreach ($definitions as $key => $meta) {
            if (! ($meta['locked'] ?? false)) {
                $rules[$key] = ['required', 'boolean'];
            }
        }

        $data = $request->validate($rules);
        $userId = $request->user()?->id;

        foreach ($data as $key => $bool) {
            SystemSetting::put($key, $bool ? '1' : '0', $userId);
        }

        return redirect()->route('settings.index')->with('success', 'تم حفظ الإعدادات.');
    }
}
