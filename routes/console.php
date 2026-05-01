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
