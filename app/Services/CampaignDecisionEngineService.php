<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only decision layer on top of stored Meta + sales aggregates.
 * Does not sync or mutate analytics rows — only interprets them for the UI.
 */
class CampaignDecisionEngineService
{
    private const int MAX_SNAPSHOT_DAYS = 14;

    private const float MIN_SPEND_FOR_SIGNAL = 8.0;

    private const float ROAS_BAD = 1.85;

    private const float ROAS_WARN = 2.75;

    private const float ROAS_STRONG = 3.8;

    /**
     * @param  Collection<int, ClientCampaignUpdate>  $campaignUpdates
     * @param  Collection<int, ClientDailySale>  $dailySales
     * @return array<string, mixed>
     */
    public function buildPayload(Client $client, Collection $campaignUpdates, Collection $dailySales): array
    {
        $updates = $campaignUpdates
            ->filter(fn (ClientCampaignUpdate $u) => $u->report_date !== null)
            ->sortByDesc(fn (ClientCampaignUpdate $u) => $u->report_date->toDateString())
            ->values();

        if ($updates->isEmpty()) {
            return $this->emptyPayload($client, $dailySales);
        }

        $byDate = $updates->keyBy(fn (ClientCampaignUpdate $u) => $u->report_date->toDateString());

        $campaigns = [];
        foreach ($updates->take(self::MAX_SNAPSHOT_DAYS) as $row) {
            $dateStr = $row->report_date->toDateString();
            $prev = $this->rowForPreviousDay($byDate, $row->report_date);
            $metrics = $this->computeMetrics($row, $prev);
            $health = $this->computeHealth($row, $metrics);
            $priority = $this->computePriority($health, $row, $metrics);
            $actions = $this->buildActions($row, $metrics, $health, $priority);

            $campaigns[] = [
                'id' => $row->id,
                'report_date' => $dateStr,
                'label' => 'أداء إعلاني — '.$dateStr,
                'health' => $health,
                'priority' => $priority,
                'priority_score' => $this->priorityScore($priority, (float) $row->ad_spend, $health),
                'trend' => [
                    'roas' => $metrics['roas_trend'],
                    'engagement' => $metrics['engagement_trend'],
                ],
                'metrics' => [
                    'roas' => $metrics['roas'],
                    'cpa' => $metrics['cpa'],
                    /** Proxy for “low CTR” when impressions are not stored: clicks per spend unit */
                    'engagement_index' => $metrics['engagement_index'],
                    'engagement_note' => 'نقرات لكل وحدة إنفاق (بديل بسيط عن CTR)',
                    'ad_spend' => round((float) $row->ad_spend, 2),
                    'clicks_count' => (int) $row->clicks_count,
                    'messages_count' => (int) $row->messages_count,
                    'cvr' => $metrics['cvr'],
                ],
                'actions' => $actions,
            ];
        }

        usort($campaigns, function (array $a, array $b): int {
            if ($a['priority_score'] !== $b['priority_score']) {
                return $b['priority_score'] <=> $a['priority_score'];
            }

            return strcmp((string) $b['report_date'], (string) $a['report_date']);
        });

        $latest = $updates->first();
        $prevLatest = $this->rowForPreviousDay($byDate, $latest->report_date);

        return [
            'enabled' => true,
            'client_id' => $client->id,
            'daily_insight' => $this->buildDailyInsight($latest, $prevLatest, $dailySales),
            'profit' => $this->buildProfitSnapshot($latest, $dailySales),
            'campaigns' => array_values($campaigns),
            'recommended_actions' => $this->flattenRecommendedActions($campaigns),
        ];
    }

    /**
     * @param  Collection<int, ClientDailySale>  $dailySales
     * @return array<string, mixed>
     */
    private function emptyPayload(Client $client, Collection $dailySales): array
    {
        $sales = $dailySales
            ->filter(fn (ClientDailySale $s) => $s->sales_date !== null)
            ->sortByDesc(fn (ClientDailySale $s) => $s->sales_date->toDateString())
            ->values();

        $insight = [
            'headline' => 'لا توجد بيانات حملات مسجلة بعد.',
            'vs_yesterday' => null,
            'key_issue' => null,
            'key_opportunity' => null,
        ];

        if ($sales->count() >= 2) {
            $today = $sales->get(0);
            $yesterday = $sales->get(1);
            $insight = $this->buildSalesOnlyInsight($today, $yesterday);
        }

        return [
            'enabled' => false,
            'client_id' => $client->id,
            'daily_insight' => $insight,
            'profit' => [
                'has_sales' => false,
                'report_date' => null,
                'revenue' => null,
                'ad_spend' => null,
                'estimated_profit' => null,
                'note' => 'أضف مزامنة ميتا أو تحديثات حملات لعرض الربحية والقرارات.',
            ],
            'campaigns' => [],
            'recommended_actions' => $sales->isNotEmpty()
                ? ['سجّل بيانات الحملات (ميتا) لربط الأداء الإعلاني بالمبيعات واتخاذ قرارات أوضح.']
                : [],
        ];
    }

    private function rowForPreviousDay(Collection $byDate, Carbon $date): ?ClientCampaignUpdate
    {
        $key = $date->copy()->subDay()->toDateString();

        return $byDate->get($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeMetrics(ClientCampaignUpdate $row, ?ClientCampaignUpdate $prev): array
    {
        $spend = (float) $row->ad_spend;
        $roas = $row->roas !== null ? (float) $row->roas : null;
        $prevRoas = $prev && $prev->roas !== null ? (float) $prev->roas : null;
        $engagement = $spend > 0 ? round($row->clicks_count / $spend, 4) : 0.0;
        $prevSpend = $prev ? (float) $prev->ad_spend : 0.0;
        $prevEngagement = ($prev && $prevSpend > 0)
            ? round($prev->clicks_count / $prevSpend, 4)
            : null;

        $roasTrend = $this->trendThreeWay($roas, $prevRoas, 0.06);
        $engTrend = $this->trendThreeWay($engagement, $prevEngagement, 0.08);

        $cvr = $row->cvr !== null ? (float) $row->cvr : null;
        $prevCvr = $prev && $prev->cvr !== null ? (float) $prev->cvr : null;

        return [
            'roas' => $roas,
            'roas_prev' => $prevRoas,
            'roas_trend' => $roasTrend,
            'cpa' => $row->cpa !== null ? (float) $row->cpa : null,
            'cpa_prev' => $prev && $prev->cpa !== null ? (float) $prev->cpa : null,
            'engagement_index' => $engagement,
            'engagement_prev' => $prevEngagement,
            'engagement_trend' => $engTrend,
            'cvr' => $cvr,
            'cvr_prev' => $prevCvr,
        ];
    }

    private function trendThreeWay(?float $current, ?float $previous, float $thresholdPct): string
    {
        if ($current === null || $previous === null || $previous <= 0) {
            return 'flat';
        }
        $delta = ($current - $previous) / $previous;
        if ($delta > $thresholdPct) {
            return 'up';
        }
        if ($delta < -$thresholdPct) {
            return 'down';
        }

        return 'flat';
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function computeHealth(ClientCampaignUpdate $row, array $metrics): string
    {
        $spend = (float) $row->ad_spend;
        if ($spend < self::MIN_SPEND_FOR_SIGNAL) {
            return 'good';
        }

        $roas = $metrics['roas'];
        if ($roas !== null && $roas < self::ROAS_BAD) {
            return 'bad';
        }

        if ($roas !== null && $roas < self::ROAS_WARN) {
            return 'warning';
        }

        if ($metrics['roas_trend'] === 'down' && $metrics['roas_prev'] !== null && $metrics['roas_prev'] >= self::ROAS_WARN) {
            return 'bad';
        }

        if ($metrics['engagement_trend'] === 'down' && $spend >= 25.0 && $metrics['engagement_prev'] !== null) {
            $prev = (float) $metrics['engagement_prev'];
            if ($prev > 0 && $metrics['engagement_index'] < $prev * 0.65) {
                return 'warning';
            }
        }

        if ($metrics['cpa'] !== null && $metrics['cpa_prev'] !== null && $metrics['cpa_prev'] > 0) {
            if ($metrics['cpa'] > $metrics['cpa_prev'] * 1.45 && $spend >= 15.0) {
                return 'warning';
            }
        }

        if ($roas === null && $spend >= 20.0) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function computePriority(string $health, ClientCampaignUpdate $row, array $metrics): string
    {
        $spend = (float) $row->ad_spend;

        if ($health === 'bad') {
            return 'high';
        }

        if ($health === 'warning' && ($spend >= 40.0 || $metrics['roas_trend'] === 'down')) {
            return 'high';
        }

        if ($health === 'warning') {
            return 'medium';
        }

        return 'low';
    }

    private function priorityScore(string $priority, float $spend, string $health): int
    {
        $base = match ($priority) {
            'high' => 300,
            'medium' => 200,
            default => 100,
        };
        $spendBump = (int) min(90, round($spend / 5));

        return $base + $spendBump + ($health === 'bad' ? 50 : 0);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return list<string>
     */
    private function buildActions(
        ClientCampaignUpdate $row,
        array $metrics,
        string $health,
        string $priority,
    ): array {
        $actions = [];
        $spend = (float) $row->ad_spend;
        $roas = $metrics['roas'];

        if ($roas === null && $spend >= 15.0) {
            $actions[] = 'سجّل المبيعات اليومية أو تأكد من المزامنة لحساب ROAS واتخاذ قرار مبني على الربحية.';
        }

        if ($health === 'bad' || ($roas !== null && $roas < self::ROAS_BAD && $spend >= self::MIN_SPEND_FOR_SIGNAL)) {
            $actions[] = 'راجع المجموعات والإعلانات ذات الأداء الأضعف؛ فكّر بتقليل الميزانية أو إيقاف ما لا يحقق هدف التكلفة.';
        }

        if ($metrics['roas_trend'] === 'down' && $metrics['roas_prev'] !== null) {
            $actions[] = 'الاتجاه تنازلي مقارنة باليوم السابق — راجع الاستهداف، العروض، والكريتيف قبل زيادة الإنفاق.';
        }

        if ($metrics['engagement_trend'] === 'down' || ($metrics['engagement_prev'] !== null && $metrics['engagement_index'] < (float) $metrics['engagement_prev'] * 0.7 && $spend >= 20.0)) {
            $actions[] = 'تفاعل منخفض (نقرات مقابل الإنفاق) — جرّب كريتيفًا جديدًا أو زاوية رسالة مختلفة.';
        }

        if ($metrics['cpa'] !== null && $metrics['cpa_prev'] !== null && $metrics['cpa'] > $metrics['cpa_prev'] * 1.25) {
            $actions[] = 'CPA في ارتفاع — راجع صفحة الهبوط، جودة الجمهور، وتسلسل الإعلانات.';
        }

        if ($health === 'good' && $metrics['roas_trend'] === 'up' && $roas !== null && $roas >= self::ROAS_STRONG) {
            $actions[] = 'أداء قوي ومتصاعد: يمكن اختبار رفع الميزانية تدريجيًا (مثل +٢٠٪) على أفضل المجموعات فقط.';
        }

        if ($priority === 'low' && empty($actions) && $spend >= self::MIN_SPEND_FOR_SIGNAL) {
            $actions[] = 'الوضع مستقر — تابع المراقبة اليومية وثبت التغييرات الإيجابية.';
        }

        $out = array_values(array_unique(array_filter($actions)));

        return array_slice($out, 0, 3);
    }

    /**
     * @param  Collection<int, ClientDailySale>  $dailySales
     * @return array<string, mixed>
     */
    private function buildProfitSnapshot(
        ClientCampaignUpdate $latest,
        Collection $dailySales,
    ): array {
        $dateStr = $latest->report_date->toDateString();
        $spend = round((float) $latest->ad_spend, 2);
        $revenueFromRow = $latest->campaign_revenue !== null ? (float) $latest->campaign_revenue : null;

        $sale = $dailySales->first(fn (ClientDailySale $s) => $s->sales_date?->toDateString() === $dateStr);
        $revenue = $revenueFromRow ?? ($sale ? (float) $sale->revenue : null);

        if ($revenue === null && $sale === null) {
            return [
                'has_sales' => false,
                'report_date' => $dateStr,
                'revenue' => null,
                'ad_spend' => $spend,
                'estimated_profit' => null,
                'note' => 'لا توجد مبيعات مربوطة بهذا اليوم لحساب الربح التقديري.',
            ];
        }

        $revenue = $revenue ?? 0.0;

        return [
            'has_sales' => $revenue > 0,
            'report_date' => $dateStr,
            'revenue' => round($revenue, 2),
            'ad_spend' => $spend,
            'estimated_profit' => round($revenue - $spend, 2),
            'note' => null,
        ];
    }

    /**
     * @param  Collection<int, ClientDailySale>  $dailySales
     * @return array<string, mixed>
     */
    private function buildDailyInsight(
        ClientCampaignUpdate $latest,
        ?ClientCampaignUpdate $prev,
        Collection $dailySales,
    ): array {
        $dateStr = $latest->report_date->toDateString();
        $vs = null;
        if ($prev) {
            $vs = [
                'roas_delta_pct' => $this->pctDelta($latest->roas !== null ? (float) $latest->roas : null, $prev->roas !== null ? (float) $prev->roas : null),
                'spend_delta_pct' => $this->pctDelta((float) $latest->ad_spend, (float) $prev->ad_spend),
                'revenue_delta_pct' => $this->pctDelta(
                    $latest->campaign_revenue !== null ? (float) $latest->campaign_revenue : null,
                    $prev->campaign_revenue !== null ? (float) $prev->campaign_revenue : null,
                ),
            ];
        }

        $saleToday = $dailySales->first(fn (ClientDailySale $s) => $s->sales_date?->toDateString() === $dateStr);
        $prevDate = $latest->report_date->copy()->subDay()->toDateString();
        $salePrev = $dailySales->first(fn (ClientDailySale $s) => $s->sales_date?->toDateString() === $prevDate);
        if ($saleToday && $salePrev && $vs !== null) {
            $vs['sales_revenue_delta_pct'] = $this->pctDelta((float) $saleToday->revenue, (float) $salePrev->revenue);
        }

        $issue = null;
        $opp = null;

        if ($latest->roas !== null && (float) $latest->roas < self::ROAS_WARN && (float) $latest->ad_spend >= self::MIN_SPEND_FOR_SIGNAL) {
            $issue = 'ROAS أقل من المستهدف مع إنفاق فعّال — يحتاج مراجعة سريعة.';
        } elseif ($prev && $latest->roas !== null && $prev->roas !== null && (float) $latest->roas < (float) $prev->roas * 0.88) {
            $issue = 'تراجع أداء يومي واضح مقارنة بالأمس.';
        }

        if ($latest->roas !== null && (float) $latest->roas >= self::ROAS_STRONG && $prev && $prev->roas !== null && (float) $latest->roas >= (float) $prev->roas) {
            $opp = 'ROAS قوي ومستقر أو متصاعد — فرصة لتوسيع ما يعمل فقط.';
        } elseif ($latest->roas !== null && (float) $latest->roas >= self::ROAS_WARN && $vs !== null && ($vs['roas_delta_pct'] ?? 0) > 5) {
            $opp = 'تحسن في الكفاءة مقارنة بالأمس.';
        }

        $headline = 'ملخص يوم '.$dateStr.' مقارنة باليوم السابق.';
        if (! $prev) {
            $headline = 'أحدث يوم مسجل: '.$dateStr.' (لا يوجد صف أمس لنفس العميل للمقارنة).';
        }

        return [
            'headline' => $headline,
            'vs_yesterday' => $vs,
            'key_issue' => $issue,
            'key_opportunity' => $opp,
        ];
    }

    private function pctDelta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || abs($previous) < 0.00001) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalesOnlyInsight(ClientDailySale $today, ClientDailySale $yesterday): array
    {
        $revDelta = $this->pctDelta((float) $today->revenue, (float) $yesterday->revenue);

        return [
            'headline' => 'مقارنة مبيعات يوم '.$today->sales_date->toDateString().' باليوم السابق (بدون بيانات حملات).',
            'vs_yesterday' => [
                'sales_revenue_delta_pct' => $revDelta,
            ],
            'key_issue' => $revDelta !== null && $revDelta < -8 ? 'انخفاض ملحوظ في الإيراد اليومي.' : null,
            'key_opportunity' => $revDelta !== null && $revDelta > 8 ? 'نمو في الإيراد — يمكن ربطه بالحملات عند توفر البيانات.' : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $campaigns
     * @return list<string>
     */
    private function flattenRecommendedActions(array $campaigns): array
    {
        $lines = [];
        foreach ($campaigns as $c) {
            if (($c['priority'] ?? '') !== 'high') {
                continue;
            }
            foreach ($c['actions'] ?? [] as $a) {
                $lines[] = '['.$c['report_date'].'] '.$a;
            }
        }
        foreach ($campaigns as $c) {
            if (($c['priority'] ?? '') !== 'medium') {
                continue;
            }
            foreach ($c['actions'] ?? [] as $a) {
                $lines[] = '['.$c['report_date'].'] '.$a;
            }
        }

        $lines = array_values(array_unique($lines));

        return array_slice($lines, 0, 6);
    }
}
