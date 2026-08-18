<?php

namespace App\Console\Commands;

use App\Models\GoodsMetaLead;
use App\Services\SmartNotificationService;
use Illuminate\Console\Command;

class SendGoodsMetaLeadCallRemindersCommand extends Command
{
    protected $signature = 'goods:meta-leads:call-reminders';

    protected $description = 'تذكير الموظف المسؤول قبل موعد المكالمة بيوم (24 ساعة)';

    public function __construct(
        private readonly SmartNotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $windowEnd = now()->addDay();
        $leads = GoodsMetaLead::query()
            ->with('owner:id,name')
            ->whereNotNull('owner_user_id')
            ->whereNotNull('next_call_at')
            ->whereNull('call_reminder_sent_at')
            ->where('next_call_at', '>', now())
            ->where('next_call_at', '<=', $windowEnd)
            ->get();

        $sent = 0;

        foreach ($leads as $lead) {
            $ownerId = (int) $lead->owner_user_id;
            if ($ownerId <= 0) {
                continue;
            }

            $when = $lead->next_call_at?->timezone(config('app.timezone'))->format('Y-m-d H:i');
            $this->notifications->notifyUsers([$ownerId], [
                'title' => 'تذكير: مكالمة ليد ميتا غداً',
                'body' => trim(($lead->full_name ?: 'عميل').' — الموعد: '.$when),
                'severity' => 'warning',
                'category' => 'general',
                'link' => route('goods.index', [
                    'tab' => 'meta_leads',
                    'meta_owner' => $ownerId,
                    'meta_view' => 'upcoming_calls',
                ]),
                'meta' => ['goods_meta_lead_id' => $lead->id],
            ]);

            $lead->call_reminder_sent_at = now();
            $lead->save();
            $sent++;
        }

        $this->info("Sent {$sent} call reminder(s).");

        return self::SUCCESS;
    }
}
