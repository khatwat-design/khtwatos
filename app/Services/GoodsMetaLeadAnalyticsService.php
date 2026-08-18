<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $moutafaq = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MOUTAFAQ] ?? 0);
        $lamYattasel = (int) ($byStatus[GoodsMetaLead::WORKFLOW_LAM_YATTASEL] ?? 0);
        $mohtamal = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MOHTAMAL] ?? 0);
        $matabaa = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MATAABA] ?? 0);

        $closed = $moutafaq + $lamYattasel + (int) ($byStatus[GoodsMetaLead::WORKFLOW_BARID] ?? 0);
        $conversionRate = $closed > 0 ? round(($moutafaq / $closed) * 100, 1) : 0;

        return [
            'total' => $total,
            'new_count' => (int) ($byStatus[GoodsMetaLead::WORKFLOW_KULL] ?? 0),
            'following_count' => $matabaa,
            'potential_count' => $mohtamal,
            'won_count' => $moutafaq,
            'rejected_count' => $lamYattasel,
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
     * تحليلات ليدز ميتا لصفحة تحليلات المبيعات — شاملة.
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

            $moutafaq = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MOUTAFAQ] ?? 0);
            $barid = (int) ($byStatus[GoodsMetaLead::WORKFLOW_BARID] ?? 0);
            $lamYattasel = (int) ($byStatus[GoodsMetaLead::WORKFLOW_LAM_YATTASEL] ?? 0);
            $sakhin = (int) ($byStatus[GoodsMetaLead::WORKFLOW_SAKHIN] ?? 0);
            $dafae = (int) ($byStatus[GoodsMetaLead::WORKFLOW_DAFEE] ?? 0);
            $matabaa = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MATAABA] ?? 0);
            $mohtamal = (int) ($byStatus[GoodsMetaLead::WORKFLOW_MOHTAMAL] ?? 0);
            $kull = (int) ($byStatus[GoodsMetaLead::WORKFLOW_KULL] ?? 0);
            $closed = $moutafaq + $barid + $lamYattasel;

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
                'won' => $moutafaq,
                'following' => $matabaa,
                'potential' => $mohtamal,
                'hot' => $sakhin,
                'warm' => $dafae,
                'cold' => $barid,
                'not_contacted' => $lamYattasel,
                'new_leads' => $kull,
                'conversion_rate' => $closed > 0 ? round(($moutafaq / $closed) * 100, 1) : 0,
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

        $teamMoutafaq = (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_MOUTAFAQ] ?? 0);
        $teamBarid = (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_BARID] ?? 0);
        $teamLamYattasel = (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_LAM_YATTASEL] ?? 0);
        $teamClosed = $teamMoutafaq + $teamBarid + $teamLamYattasel;

        return [
            'range_days' => $rangeDays,
            'reps' => $reps,
            'team_totals' => [
                'leads_in_range' => $allInRange->count(),
                'upcoming_calls' => GoodsMetaLead::query()
                    ->whereNotNull('next_call_at')
                    ->where('next_call_at', '>=', now())
                    ->count(),
                'won' => $teamMoutafaq,
                'following' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_MATAABA] ?? 0),
                'potential' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_MOHTAMAL] ?? 0),
                'hot' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_SAKHIN] ?? 0),
                'warm' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_DAFEE] ?? 0),
                'cold' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_BARID] ?? 0),
                'not_contacted' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_LAM_YATTASEL] ?? 0),
                'new_leads' => (int) ($teamByStatus[GoodsMetaLead::WORKFLOW_KULL] ?? 0),
                'conversion_rate' => $teamClosed > 0 ? round(($teamMoutafaq / $teamClosed) * 100, 1) : 0,
            ],
            'funnel' => $this->buildFunnel($from, $to),
            'campaign_performance' => $this->campaignPerformance($from, $to),
            'daily_trend' => $this->dailyTrend($from, $to),
            'response_time_distribution' => $this->responseTimeDistribution($from, $to),
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

    /**
     * Conversion Funnel: الكل → تم التواصل → متابعة → محتمل → تم الاتفاق
     */
    private function buildFunnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = GoodsMetaLead::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->get(['workflow_status']);

        $total = $leads->count();
        $contacted = $total - (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_LAM_YATTASEL)->count();
        $matabaa = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_MATAABA)->count();
        $mohtamal = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_MOHTAMAL)->count();
        $sakhin = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_SAKHIN)->count();
        $dafae = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_DAFEE)->count();
        $won = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_MOUTAFAQ)->count();
        $barid = (int) $leads->where('workflow_status', GoodsMetaLead::WORKFLOW_BARID)->count();

        $active = $contacted - $won - $barid;

        return [
            'total' => $total,
            'contacted' => $contacted,
            'contact_rate' => $total > 0 ? round(($contacted / $total) * 100, 1) : 0,
            'active' => max(0, $active),
            'active_rate' => $total > 0 ? round((max(0, $active) / $total) * 100, 1) : 0,
            'hot' => $sakhin,
            'warm' => $dafae,
            'following' => $matabaa,
            'potential' => $mohtamal,
            'won' => $won,
            'won_rate' => $contacted > 0 ? round(($won / $contacted) * 100, 1) : 0,
            'cold' => $barid,
            'not_contacted' => $total - $contacted,
            'steps' => [
                ['label' => 'إجمالي الليدز', 'value' => $total, 'color' => 'slate'],
                ['label' => 'تم التواصل', 'value' => $contacted, 'color' => 'sky'],
                ['label' => 'نشطة (متابعة/محتمل/ساخن/دافئ)', 'value' => max(0, $active), 'color' => 'amber'],
                ['label' => 'تم الاتفاق', 'value' => $won, 'color' => 'emerald'],
            ],
        ];
    }

    /**
     * أداء كل حملة: عدد الليدز + التحويل + التوزيع.
     */
    private function campaignPerformance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = GoodsMetaLead::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->whereNotNull('campaign_name')
            ->get(['campaign_name', 'workflow_status']);

        $grouped = $leads->groupBy('campaign_name');

        $campaigns = [];
        foreach ($grouped as $campaignName => $campaignLeads) {
            $total = $campaignLeads->count();
            $won = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_MOUTAFAQ)->count();
            $lamYattasel = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_LAM_YATTASEL)->count();
            $barid = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_BARID)->count();
            $matabaa = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_MATAABA)->count();
            $mohtamal = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_MOHTAMAL)->count();
            $sakhin = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_SAKHIN)->count();
            $dafae = (int) $campaignLeads->where('workflow_status', GoodsMetaLead::WORKFLOW_DAFEE)->count();
            $closed = $won + $barid + $lamYattasel;

            $campaigns[] = [
                'name' => $campaignName,
                'total' => $total,
                'won' => $won,
                'contacted' => $total - $lamYattasel,
                'contact_rate' => $total > 0 ? round((($total - $lamYattasel) / $total) * 100, 1) : 0,
                'conversion_rate' => $closed > 0 ? round(($won / $closed) * 100, 1) : 0,
                'hot' => $sakhin,
                'warm' => $dafae,
                'following' => $matabaa,
                'potential' => $mohtamal,
                'cold' => $barid,
                'not_contacted' => $lamYattasel,
            ];
        }

        usort($campaigns, fn ($a, $b) => $b['total'] <=> $a['total']);

        return array_slice($campaigns, 0, 10);
    }

    /**
     * الاتجاه اليومي: عدد الليدز + التحويلات لكل يوم.
     */
    private function dailyTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = GoodsMetaLead::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->get(['lead_created_at', 'created_at', 'workflow_status']);

        $days = [];
        $current = $from->copy();
        while ($current->lte($to)) {
            $dayStr = $current->toDateString();
            $days[$dayStr] = [
                'date' => $dayStr,
                'total' => 0,
                'won' => 0,
                'contacted' => 0,
            ];
            $current = $current->addDay();
        }

        foreach ($leads as $lead) {
            $date = $lead->lead_created_at?->toDateString() ?? $lead->created_at?->toDateString();
            if (! $date || ! isset($days[$date])) {
                continue;
            }
            $days[$date]['total']++;
            if ($lead->workflow_status === GoodsMetaLead::WORKFLOW_MOUTAFAQ) {
                $days[$date]['won']++;
            }
            if ($lead->workflow_status !== GoodsMetaLead::WORKFLOW_LAM_YATTASEL) {
                $days[$date]['contacted']++;
            }
        }

        return array_values($days);
    }

    /**
     * توزيع أوقات الاستجابة (للleadز التي تم التواصل معها).
     */
    private function responseTimeDistribution(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $leads = GoodsMetaLead::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('lead_created_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->whereNotNull('first_contact_date')
            ->get(['lead_created_at', 'created_at', 'first_contact_date']);

        $buckets = [
            'same_day' => 0,
            '1_3_days' => 0,
            '4_7_days' => 0,
            '8_plus_days' => 0,
        ];

        foreach ($leads as $lead) {
            $created = $lead->lead_created_at ?? $lead->created_at;
            if (! $created || ! $lead->first_contact_date) {
                continue;
            }
            $diff = $created->diffInDays($lead->first_contact_date);
            if ($diff <= 0) {
                $buckets['same_day']++;
            } elseif ($diff <= 3) {
                $buckets['1_3_days']++;
            } elseif ($diff <= 7) {
                $buckets['4_7_days']++;
            } else {
                $buckets['8_plus_days']++;
            }
        }

        return [
            ['label' => 'نفس اليوم', 'count' => $buckets['same_day'], 'color' => 'emerald'],
            ['label' => '1-3 أيام', 'count' => $buckets['1_3_days'], 'color' => 'sky'],
            ['label' => '4-7 أيام', 'count' => $buckets['4_7_days'], 'color' => 'amber'],
            ['label' => '+8 أيام', 'count' => $buckets['8_plus_days'], 'color' => 'rose'],
        ];
    }
}
