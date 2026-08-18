<?php

namespace App\Console\Commands;

use App\Support\EmployeeCallDiagnostics;
use Illuminate\Console\Command;

class DiagnoseEmployeeCallsCommand extends Command
{
    protected $signature = 'employee-calls:diagnose';

    protected $description = 'فحص إعداد المكالمات (Reverb، قاعدة البيانات، البث)';

    public function handle(): int
    {
        $status = EmployeeCallDiagnostics::serverStatus();

        foreach ($status as $key => $value) {
            if ($key === 'fix_hint') {
                continue;
            }
            $label = is_bool($value) ? ($value ? 'OK' : 'FAIL') : (string) $value;
            $this->line(sprintf('  %-28s %s', $key.':', $label));
        }

        if (! empty($status['fix_hint'])) {
            $this->newLine();
            $this->warn($status['fix_hint']);
        }

        $this->newLine();
        $this->line('سجلات المكالمات: storage/logs/laravel.log (ابحث عن employee_call.)');

        return ($status['healthy'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
