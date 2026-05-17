<?php

namespace App\Console\Commands;

use App\Models\GoodsMetaLead;
use App\Services\GoodsMetaLeadAssignmentService;
use Illuminate\Console\Command;

class AssignGoodsMetaLeadsCommand extends Command
{
    protected $signature = 'goods:meta-leads:assign-unassigned {--force : تنفيذ بدون تأكيد}';

    protected $description = 'توزيع الليدز بدون مسؤول بين نبراس (60%) وحسين علي (40%)';

    public function __construct(
        private readonly GoodsMetaLeadAssignmentService $assignment,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = GoodsMetaLead::query()->whereNull('owner_user_id')->count();
        if ($count === 0) {
            $this->info('لا توجد ليدز بدون مسؤول.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("توزيع {$count} ليد؟", false)) {
            return self::SUCCESS;
        }

        $assigned = 0;
        GoodsMetaLead::query()
            ->whereNull('owner_user_id')
            ->orderBy('id')
            ->each(function (GoodsMetaLead $lead) use (&$assigned) {
                $ownerId = $this->assignment->pickOwnerUserId();
                if (! $ownerId) {
                    return false;
                }
                $lead->owner_user_id = $ownerId;
                $lead->assigned_at = now();
                $lead->save();
                $assigned++;
            });

        $this->info("تم تعيين {$assigned} ليد.");

        return self::SUCCESS;
    }
}
