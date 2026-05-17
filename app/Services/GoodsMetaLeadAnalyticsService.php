<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GoodsMetaLeadAnalyticsService
{
    public function __construct(
        private readonly GoodsMetaLeadAssignmentService $assignment,
    ) {}
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

    /**
     * تحليلات ليدز ميتا لصفحة تحليلات المبيعات.
     *
     * @return array<string, mixed>
     */
    public function salesDashboard(int $rangeDays): array
    {
        $rangeDays = max(7, min(180, $rangeDays));
        $from = CarbonImmutable::now()->subDays($rangeDays - 1)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        $assignees = $this->assignment->assignees();
        $reps = [];

        foreach ($assignees as $assignee) {
            $base = GoodsMetaLead::query()->where('owner_user_id', $assignee['id']);
            $inRange = (clone $base)->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            });

            $byStatus = (clone $inRange)->get(['workflow_status'])
                ->groupBy('workflow_status')
                ->map->count()
                ->all();

            $won = (int) ($byStatus[GoodsMetaLead::WORKFLOW_WON] ?? 0);
            $lost = (int) ($byStatus[GoodsMetaLead::WORKFLOW_LOST] ?? 0);
            $rejected = (int) ($byStatus[GoodsMetaLead::WORKFLOW_REJECTED] ?? 0);
            $closed = $won + $lost + $rejected;

            $reps[] = [
                'id' => $assignee['id'],
                'name' => $assignee['name'],
                'weight' => $assignee['weight'],
                'leads_in_range' => $inRange->count(),
                'leads_total' => (clone $base)->count(),
                'leads_today' => (clone $base)->where(function ($q) {
                    $q->whereDate('lead_created_at', today())
                        ->orWhereDate('created_at', today());
                })->count(),
                'upcoming_calls' => (clone $base)
                    ->whereNotNull('next_call_at')
                    ->where('next_call_at', '>=', now())
                    ->count(),
                'calls_scheduled_in_range' => (clone $base)
                    ->whereNotNull('next_call_at')
                    ->whereBetween('next_call_at', [$from, $to])
                    ->count(),
                'won' => $won,
                'following' => (int) ($byStatus[GoodsMetaLead::WORKFLOW_FOLLOWING] ?? 0),
                'potential' => (int) ($byStatus[GoodsMetaLead::WORKFLOW_POTENTIAL] ?? 0),
                'conversion_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0,
                'by_status' => $byStatus,
            ];
        }

        $allInRange = GoodsMetaLead::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            });

        $teamByStatus = (clone $allInRange)->get(['workflow_status'])
            ->groupBy('workflow_status')
            ->map->count()
            ->all();

        return [
            'range_days' => $rangeDays,
            'reps' => $reps,
            'team_totals' => [
                'leads_in_range' => $allInRange->count(),
                'upcoming_calls' => GoodsMetaLead::query()
                    ->whereNotNull('next_call_at')
                    ->where('next_call_at', '>=', now())
                    ->count(),
                'won' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_WON] ?? 0),
                'following' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_FOLLOWING] ?? 0),
                'potential' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_POTENTIAL] ?? 0),
            ],
            'by_campaign' => GoodsMetaLead::query()
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('lead_created_at', [$from, $to])
                        ->orWhereBetween('created_at', [$from, $to]);
                })
                ->whereNotNull('campaign_name')
                ->selectRaw('campaign_name, COUNT(*) as aggregate')
                ->groupBy('campaign_name')
                ->orderByDesc('aggregate')
                ->limit(8)
                ->pluck('aggregate', 'campaign_name')
                ->all(),
        ];
    }
}
