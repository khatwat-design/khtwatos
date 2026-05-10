<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:sync-smart')->everyMinute()->withoutOverlapping();
Schedule::command('meta:sync-campaigns --days=7')->everyMinute()->withoutOverlapping();
Schedule::command('goods:send-weekly-survey --limit=200')
    ->weeklyOn(6, '10:00')
    ->withoutOverlapping();

Schedule::command('goods:send-daily-sales-reminders --limit=200')
    ->dailyAt((string) config('services.goods.daily_sales_reminder_at', '09:00'))
    ->withoutOverlapping();

Schedule::command('portal:send-daily-sales-reminders --limit=400')
    ->dailyAt((string) config('services.portal.daily_sales_reminder_at', '18:00'))
    ->withoutOverlapping();

Schedule::command('portal:send-daily-client-reports --limit=400')
    ->dailyAt((string) config('services.client_reports.daily_at', '19:30'))
    ->withoutOverlapping();

Schedule::command('portal:send-weekly-client-reports --limit=400')
    ->weeklyOn(
        (int) config('services.client_reports.weekly_on', 0),
        (string) config('services.client_reports.weekly_at', '10:00')
    )
    ->withoutOverlapping();

if (config('database_backup.schedule_enabled')) {
    $hours = (int) config('database_backup.schedule_every_hours', 2);
    $overlapMinutes = max(15, (int) config('database_backup.schedule_overlap_minutes', 90));

    $backupSchedule = Schedule::command('db:backup')->withoutOverlapping($overlapMinutes);

    if ($hours <= 0 || $hours >= 24) {
        $backupSchedule->dailyAt((string) config('database_backup.schedule_at', '03:30'));
    } else {
        $backupSchedule->cron(sprintf('0 */%d * * *', $hours));
    }
}
