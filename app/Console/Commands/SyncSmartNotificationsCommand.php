<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Console\Command;

class SyncSmartNotificationsCommand extends Command
{
    protected $signature = 'notifications:sync-smart';

    protected $description = 'Sync smart deadline notifications for all users';

    public function handle(SystemNotificationService $systemNotificationService): int
    {
        User::query()->select(['id'])->chunkById(200, function ($users) use ($systemNotificationService): void {
            foreach ($users as $user) {
                $systemNotificationService->syncForUser($user);
            }
        });

        $this->info('Smart notifications synced.');

        return self::SUCCESS;
    }
}

