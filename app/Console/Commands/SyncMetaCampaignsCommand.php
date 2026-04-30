<?php

namespace App\Console\Commands;

use App\Models\ClientMetaIntegration;
use App\Models\ClientMetaOauthToken;
use App\Services\MetaCampaignSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncMetaCampaignsCommand extends Command
{
    protected $signature = 'meta:sync-campaigns {--client_id=} {--days=2}';

    protected $description = 'Sync campaign metrics from Meta Ads API per client account mapping.';

    public function handle(MetaCampaignSyncService $metaSync): int
    {
        $days = max(1, min(14, (int) $this->option('days')));
        $toDate = Carbon::today();
        $fromDate = $toDate->copy()->subDays($days - 1);
        $clientId = $this->option('client_id');

        $query = ClientMetaIntegration::query()
            ->where('is_active', true);

        if ($clientId) {
            $query->where('client_id', (int) $clientId);
        }

        $integrations = $query->get();
        if ($integrations->isEmpty()) {
            $this->warn('No active client Meta integrations found.');

            return self::SUCCESS;
        }

        foreach ($integrations as $integration) {
            $clientToken = null;
            if (Schema::hasTable('client_meta_oauth_tokens')) {
                $tokenRow = ClientMetaOauthToken::query()
                    ->where('client_id', $integration->client_id)
                    ->first();
                if ($tokenRow && (!$tokenRow->expires_at || $tokenRow->expires_at->isFuture())) {
                    $clientToken = (string) $tokenRow->access_token;
                }
            }
            if (!$clientToken) {
                $this->error("Client #{$integration->client_id}: missing valid client OAuth token.");
                continue;
            }

            $result = $metaSync->syncIntegration($integration, $fromDate, $toDate, null, $clientToken);
            if ($result['error']) {
                $this->error("Client #{$integration->client_id}: {$result['error']}");
                continue;
            }

            $this->info("Client #{$integration->client_id}: {$result['days']} days, +{$result['inserted']} new, {$result['updated']} updated.");
        }

        return self::SUCCESS;
    }
}
