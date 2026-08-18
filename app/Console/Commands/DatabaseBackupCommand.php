<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Create a system backup under storage/app/backups (full tar.gz bundle + optional encryption, or database-only)';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $name = $backups->createBackup();
            $this->info('Backup created: '.$name);

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
