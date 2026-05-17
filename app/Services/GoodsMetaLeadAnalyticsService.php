<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use Illuminate\Support\Collection;

class GoodsMetaLeadAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $leads = GoodsMetaLead::query()->get([
            'id',
            'workflow_status',
            'campaign_name',
            'platform',
            'owner_user_id',
            'lead_created_at',
            'outcome_label',
        ]);

        $byStatus = $leads->groupBy('workflow_status')->map->count()->all();
        $byCampaign = $leads->groupBy(fn ($l) => $l->campaign_name ?: '—')->map->count()->sortDesc()->take(8)->all();
        $byPlatform = $leads->groupBy(fn ($l) => $l->platform ?: '—')->map->count()->all();

        $total = $leads->count();
        $won = (int) ($byStatus[GoodsMetaLead::WORKFLOW_WON] ?? 0);
        $rejected = (int) ($byStatus[GoodsMetaLead::WORKFLOW_REJECTED] ?? 0);
        $potential = (int) ($byStatus[GoodsMetaLead::WORKFLOW_POTENTIAL] ?? 0);
        $following = (int) ($byStatus[GoodsMetaLead::WORKFLOW_FOLLOWING] ?? 0);

        $closed = $won + $rejected + (int) ($byStatus[GoodsMetaLead::WORKFLOW_LOST] ?? 0);
        $conversionRate = $closed > 0 ? round(($won / $closed) * 100, 1) : 0;

        return [
            'total' => $total,
            'new_count' => (int) ($byStatus[GoodsMetaLead::WORKFLOW_NEW] ?? 0),
            'following_count' => $following,
            'potential_count' => $potential,
            'won_count' => $won,
            'rejected_count' => $rejected,
            'conversion_rate' => $conversionRate,
            'by_status' => $byStatus,
            'by_campaign' => $byCampaign,
            'by_platform' => $byPlatform,
            'last_7_days' => $this->countLastDays($leads, 7),
        ];
    }

    /**
     * @param  Collection<int, GoodsMetaLead>  $leads
     */
    private function countLastDays(Collection $leads, int $days): int
    {
        $since = now()->subDays($days)->startOfDay();

        return $leads->filter(fn (GoodsMetaLead $l) => $l->lead_created_at && $l->lead_created_at->gte($since))->count();
    }
}
