<?php

namespace App\Operational\Integrations\Contracts;

use App\Models\ClientMetaIntegration;
use Carbon\Carbon;

/**
 * Port for Meta Ads campaign insights sync — keeps warehouse/business callers
 * decoupled from HTTP/token details inside {@see \App\Services\MetaCampaignSyncService}.
 */
interface MetaCampaignInsightsSynchronizer
{
    /**
     * @return array{inserted: int, updated: int, days: int, error?: string}
     */
    public function syncIntegration(
        ClientMetaIntegration $integration,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $actorUserId = null,
        ?string $accessToken = null
    ): array;
}
