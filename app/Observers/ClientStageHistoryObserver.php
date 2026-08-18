<?php

namespace App\Observers;

use App\Jobs\SendClientMilestoneWhatsAppNotificationJob;
use App\Models\ClientStageHistory;

class ClientStageHistoryObserver
{
    public function created(ClientStageHistory $clientStageHistory): void
    {
        SendClientMilestoneWhatsAppNotificationJob::dispatch($clientStageHistory->id)
            ->afterCommit();
    }
}
