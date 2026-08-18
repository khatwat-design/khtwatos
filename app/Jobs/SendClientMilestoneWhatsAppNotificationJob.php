<?php

namespace App\Jobs;

use App\Services\ClientMilestoneWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendClientMilestoneWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $clientStageHistoryId,
    ) {}

    public function handle(ClientMilestoneWhatsAppService $service): void
    {
        $service->handleStageHistoryId($this->clientStageHistoryId);
    }
}
