<?php

namespace App\Console\Commands;

use App\Services\EmployeePresenceService;
use Illuminate\Console\Command;

class CloseStaleAttendanceSessionsCommand extends Command
{
    protected $signature = 'attendance:close-stale';

    protected $description = 'إغلاق جلسات نشاط الموظفين الخاملة وحفظ ثوانيها الفعلية في سجل الحضور.';

    public function handle(EmployeePresenceService $presence): int
    {
        $count = $presence->autoCloseStaleSessions();
        $this->info("تم إغلاق {$count} جلسة خاملة.");

        return self::SUCCESS;
    }
}
