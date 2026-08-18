<?php

namespace App\Services;

use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use App\Models\ClientMetaIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MetaCampaignSyncService
{
    public function __construct(
        private readonly ClientMetaConnectionService $metaConnection,
    ) {}

    public function syncIntegration(
        ClientMetaIntegration $integration,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $actorUserId = null,
        ?string $accessToken = null
    ): array {
        $token = (string) ($accessToken ?: config('services.meta_ads.access_token'));
        if ($token === '') {
            $integration->update(['last_error' => 'Missing META_ADS_ACCESS_TOKEN']);

            return ['inserted' => 0, 'updated' => 0, 'days' => 0, 'error' => 'META_ADS_ACCESS_TOKEN غير مضبوط.'];
        }

        $accountId = preg_replace('/\D+/', '', (string) $integration->ad_account_id);
        if (! $accountId) {
            $integration->update(['last_error' => 'Invalid ad account id']);

            return ['inserted' => 0, 'updated' => 0, 'days' => 0, 'error' => 'رقم الحساب الإعلاني غير صالح.'];
        }

        $version = (string) config('services.meta_ads.version', 'v22.0');
        $baseUrl = "https://graph.facebook.com/{$version}";
        $url = "{$baseUrl}/act_{$accountId}/insights";

        $response = Http::timeout(40)->get($url, [
            'access_token' => $token,
            'level' => 'account',
            'time_increment' => 1,
            'fields' => 'date_start,date_stop,spend,clicks,actions',
            'time_range' => json_encode([
                'since' => $fromDate->toDateString(),
                'until' => $toDate->toDateString(),
            ], JSON_UNESCAPED_SLASHES),
        ]);

        if ($response->failed()) {
            $errorBody = Str::limit((string) $response->body(), 900);
            $integration->update(['last_error' => $errorBody]);

            $this->metaConnection->recordMetaApiFailure(
                (int) $integration->client_id,
                'campaign_insights_sync',
                $response->status(),
                $response->json(),
                (string) $response->body(),
            );

            return ['inserted' => 0, 'updated' => 0, 'days' => 0, 'error' => "Meta API error ({$response->status()})"];
        }

        $rows = collect((array) $response->json('data', []));
        $byDate = $rows->groupBy(function (array $row) {
            return (string) ($row['date_start'] ?? '');
        });

        $inserted = 0;
        $updated = 0;

        foreach ($byDate as $date => $items) {
            if (! $date) {
                continue;
            }

            $spend = (float) $items->sum(fn (array $row) => (float) ($row['spend'] ?? 0));
            $clicks = (int) round($items->sum(fn (array $row) => (float) ($row['clicks'] ?? 0)));

            $messages = 0;
            $leads = 0;
            $purchases = 0;

            foreach ($items as $item) {
                foreach ((array) ($item['actions'] ?? []) as $action) {
                    $type = (string) ($action['action_type'] ?? '');
                    $value = (int) round((float) ($action['value'] ?? 0));

                    if (str_contains($type, 'messaging') || str_contains($type, 'onsite_conversion.messaging')) {
                        $messages += $value;
                    }
                    if ($type === 'lead' || str_contains($type, 'lead')) {
                        $leads += $value;
                    }
                    if ($type === 'purchase' || str_contains($type, 'purchase')) {
                        $purchases += $value;
                    }
                }
            }

            $sales = ClientDailySale::query()
                ->where('client_id', $integration->client_id)
                ->whereDate('sales_date', $date)
                ->first();

            $revenue = (float) ($sales?->revenue ?? 0);
            $orders = (int) ($sales?->orders_count ?? 0);

            $existing = ClientCampaignUpdate::query()
                ->where('client_id', $integration->client_id)
                ->whereDate('report_date', $date)
                ->first();

            $payload = [
                'ad_spend' => round($spend, 2),
                'messages_count' => $messages,
                'clicks_count' => $clicks,
                'leads_count' => $leads > 0 ? $leads : $messages,
                'purchases_count' => $purchases > 0 ? $purchases : $orders,
                'campaign_revenue' => $revenue,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : null,
                'cpa' => $orders > 0 ? round($spend / $orders, 2) : null,
                'cvr' => $messages > 0 ? round(($orders / $messages) * 100, 2) : null,
                'updated_by_user_id' => $actorUserId,
            ];

            if (Schema::hasColumns('client_campaign_updates', ['data_source', 'source_ref', 'fetched_at'])) {
                $payload['data_source'] = 'meta';
                $payload['source_ref'] = "act_{$accountId}";
                $payload['fetched_at'] = now();
            }

            ClientCampaignUpdate::query()->updateOrCreate(
                [
                    'client_id' => $integration->client_id,
                    'report_date' => $date,
                ],
                $payload
            );

            if ($existing) {
                $updated++;
            } else {
                $inserted++;
            }
        }

        $integration->update([
            'last_synced_at' => now(),
            'last_error' => null,
        ]);

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'days' => $byDate->count(),
            'error' => null,
        ];
    }
}
