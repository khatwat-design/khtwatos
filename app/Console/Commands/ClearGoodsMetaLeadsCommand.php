<?php

namespace App\Console\Commands;

use App\Models\GoodsMetaLead;
use Illuminate\Console\Command;

class ClearGoodsMetaLeadsCommand extends Command
{
    protected $signature = 'goods:meta-leads:clear {--force : تنفيذ الحذف بدون تأكيد}';

    protected $description = 'حذف كل ليدز ميتا المخزّنة في النظام (قبل مزامنة ورقة جديدة)';

    public function handle(): int
    {
        $count = GoodsMetaLead::query()->count();

        if ($count === 0) {
            $this->info('لا توجد ليدز ميتا في النظام.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("سيتم حذف {$count} ليد. متابعة؟", false)) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        GoodsMetaLead::query()->delete();

        $this->info("تم حذف {$count} ليد من جدول ليدز ميتا.");

        return self::SUCCESS;
    }
}
