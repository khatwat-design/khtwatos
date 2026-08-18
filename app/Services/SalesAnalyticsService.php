<?php

namespace App\Services;

use App\Models\GoodsCustomer;
use App\Models\GoodsCustomerStatusHistory;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SalesAnalyticsService
{
    private const DEFAULT_RANGE_DAYS = 30;

    /**
     * تحليلات شاملة لفريق المبيعات: لكل عضو + إجمالي الفريق + قيادة الفريق.
     *
     * @return array<string, mixed>
     */
    public function build(int $rangeDays): array
    {
        $rangeDays = max(7, min(180, $rangeDays));
        $from = CarbonImmutable::now()->subDays($rangeDays - 1)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        $team = Team::query()->where('slug', 'sales')->first();
        $reps = $this->salesReps();

        if ($reps->isEmpty()) {
            return [
                'range_days' => $rangeDays,
                'team' => $team ? ['id' => $team->id, 'name' => $team->name, 'slug' => $team->slug] : null,
                'reps' => [],
                'team_totals' => $this->emptyTotals(),
                'leaderboard' => [],
                'pipeline' => [],
                'channel_mix' => [],
                'recent_status_changes' => [],
            ];
        }

        $userIds = $reps->pluck('id')->all();

        $contactsCreated = $this->contactsCreatedPerRep($userIds, $from, $to);
        $totalAssignedOpen = $this->totalAssignedOpenPerRep($userIds);
        $stale = $this->staleConversationsPerRep($userIds);
        $msgStats = $this->outboundMessageStatsPerRep($userIds, $from, $to);
        $firstResp = $this->firstResponsePerRep($userIds, $from, $to);
        $pipelineByRep = $this->pipelinePerRep($userIds);
        $promotions = $this->goodsClientPromotionsPerRep($userIds, $from, $to);
        $statusChanges = $this->statusChangesPerRep($userIds, $from, $to);
        $channelMixByRep = $this->channelMixPerRep($userIds, $from, $to);
        $intelligenceByRep = $this->intelligenceMixPerRep($userIds);

        $repsOut = [];
        foreach ($reps as $user) {
            $uid = (int) $user->id;
            $msgs = $msgStats[$uid] ?? ['total' => 0, 'failed' => 0, 'days_active' => 0];
            $cc = (int) ($contactsCreated[$uid] ?? 0);
            $tao = (int) ($totalAssignedOpen[$uid] ?? 0);
            $sc = (int) ($stale[$uid] ?? 0);
            $fr = $firstResp[$uid] ?? ['avg_seconds' => 0, 'count' => 0];
            $pipe = $pipelineByRep[$uid] ?? [];
            $promo = $promotions[$uid] ?? ['promoted' => 0, 'qualified' => 0, 'lost' => 0];
            $changes = (int) ($statusChanges[$uid] ?? 0);
            $channels = $channelMixByRep[$uid] ?? [];
            $intel = $intelligenceByRep[$uid] ?? [];

            $failedRate = $msgs['total'] > 0 ? round($msgs['failed'] / $msgs['total'], 3) : null;
            $conversionRate = $cc > 0 ? round($promo['promoted'] / $cc, 3) : null;
            $qualifiedRate = $cc > 0 ? round($promo['qualified'] / $cc, 3) : null;
            $messagesPerContact = $cc > 0 ? round($msgs['total'] / $cc, 1) : 0;
            $stallShare = $tao > 0 ? round($sc / $tao, 3) : 0;

            // نقاط مبيعات (0..100) لمؤشر سريع للأداء — وزن أعلى للجودة (تحويل، التزام، رد سريع).
            $convScore = $conversionRate !== null ? min(1.0, $conversionRate * 2) : 0; // 50% تحويل = كامل
            $respScore = $fr['avg_seconds'] > 0 ? max(0, 1 - min(1.0, $fr['avg_seconds'] / 3600)) : 0; // أقل من ساعة = ممتاز
            $volumeScore = min(1.0, $msgs['total'] / max(20, $rangeDays));
            $stallPenalty = min(1.0, $stallShare * 2);
            $score = (int) round(
                40 * $convScore
                + 25 * $respScore
                + 25 * $volumeScore
                - 10 * $stallPenalty
            );
            $score = max(0, min(100, $score));

            $grade = match (true) {
                $score >= 85 => 'A',
                $score >= 70 => 'B',
                $score >= 55 => 'C',
                $score >= 40 => 'D',
                default => 'E',
            };

            $signals = [];
            if ($conversionRate !== null && $conversionRate >= 0.4) {
                $signals[] = ['code' => 'high_conversion', 'tone' => 'emerald', 'label' => '💎 معدل تحويل عالٍ'];
            }
            if ($fr['avg_seconds'] > 0 && $fr['avg_seconds'] <= 900 && $fr['count'] >= 3) {
                $signals[] = ['code' => 'fast_response', 'tone' => 'sky', 'label' => '⚡ سريع الاستجابة'];
            }
            if ($sc >= 5) {
                $signals[] = ['code' => 'stalled', 'tone' => 'rose', 'label' => "⏸ {$sc} محادثة راكدة"];
            }
            if ($failedRate !== null && $failedRate >= 0.1) {
                $signals[] = ['code' => 'send_failures', 'tone' => 'orange', 'label' => 'نسبة فشل إرسال مرتفعة'];
            }
            if ($promo['lost'] >= 3 && $cc > 0 && $promo['lost'] / max(1, $cc) >= 0.3) {
                $signals[] = ['code' => 'high_lost', 'tone' => 'rose', 'label' => '⚠ نسبة عملاء مفقودين عالية'];
            }
            if ($cc >= 10 && ($conversionRate ?? 0) < 0.1) {
                $signals[] = ['code' => 'low_quality_handling', 'tone' => 'amber', 'label' => 'جودة معالجة منخفضة'];
            }

            $repsOut[] = [
                'id' => $user->id,
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                'is_lead' => (bool) ($user->pivot?->is_lead ?? false),
                'score' => $score,
                'grade' => $grade,
                'signals' => $signals,

                'volume' => [
                    'contacts_new' => $cc,
                    'open_assigned' => $tao,
                    'messages_out' => (int) $msgs['total'],
                    'messages_failed' => (int) $msgs['failed'],
                    'failed_rate' => $failedRate,
                    'days_active' => (int) $msgs['days_active'],
                    'messages_per_contact' => $messagesPerContact,
                    'status_changes' => $changes,
                ],

                'quality' => [
                    'first_response_avg_seconds' => (int) $fr['avg_seconds'],
                    'first_response_count' => (int) $fr['count'],
                    'conversion_rate' => $conversionRate,
                    'qualified_rate' => $qualifiedRate,
                    'stalled_conversations' => $sc,
                    'stall_share' => $stallShare,
                    'promoted_to_client' => (int) $promo['promoted'],
                    'qualified' => (int) $promo['qualified'],
                    'lost' => (int) $promo['lost'],
                ],

                'pipeline' => $pipe,
                'channel_mix' => $channels,
                'ai_classification' => $intel,
            ];
        }

        usort($repsOut, fn ($a, $b) => $b['score'] <=> $a['score']);

        $teamTotals = $this->aggregateTotals($repsOut);

        return [
            'range_days' => $rangeDays,
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ] : null,
            'reps' => $repsOut,
            'team_totals' => $teamTotals,
            'leaderboard' => $this->buildLeaderboards($repsOut),
            'pipeline' => $this->aggregatePipeline($repsOut),
            'channel_mix' => $this->aggregateChannelMix($repsOut),
            'recent_status_changes' => $this->recentStatusChanges($userIds, 15),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function salesReps(): Collection
    {
        $team = Team::query()->where('slug', 'sales')->first();
        if (! $team) {
            return collect();
        }

        return $team->users()->orderBy('name')->get();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function contactsCreatedPerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_contacts')) {
            return [];
        }

        $rows = OutsideContact::query()
            ->select('assigned_user_id', DB::raw('COUNT(*) as cnt'))
            ->whereIn('assigned_user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('assigned_user_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->assigned_user_id] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function totalAssignedOpenPerRep(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_conversations')) {
            return [];
        }

        $rows = OutsideConversation::query()
            ->join('outside_contacts', 'outside_conversations.outside_contact_id', '=', 'outside_contacts.id')
            ->whereIn('outside_contacts.assigned_user_id', $userIds)
            ->whereNotIn('outside_conversations.status', ['closed'])
            ->groupBy('outside_contacts.assigned_user_id')
            ->select('outside_contacts.assigned_user_id as uid', DB::raw('COUNT(*) as cnt'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * المحادثات الراكدة: عميل ردّ آخر مرة وفات على رد البائع >24 ساعة.
     *
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function staleConversationsPerRep(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_conversations')) {
            return [];
        }

        $threshold = CarbonImmutable::now()->subDay();
        $rows = OutsideConversation::query()
            ->join('outside_contacts', 'outside_conversations.outside_contact_id', '=', 'outside_contacts.id')
            ->whereIn('outside_contacts.assigned_user_id', $userIds)
            ->whereNotIn('outside_conversations.status', ['closed'])
            ->whereNotNull('outside_conversations.last_inbound_at')
            ->where('outside_conversations.last_inbound_at', '<', $threshold)
            ->where(function ($q) {
                $q->whereNull('outside_conversations.last_outbound_at')
                    ->orWhereColumn('outside_conversations.last_outbound_at', '<', 'outside_conversations.last_inbound_at');
            })
            ->groupBy('outside_contacts.assigned_user_id')
            ->select('outside_contacts.assigned_user_id as uid', DB::raw('COUNT(*) as cnt'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{total:int, failed:int, days_active:int}>
     */
    private function outboundMessageStatsPerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_messages')) {
            return [];
        }

        $rows = OutsideMessage::query()
            ->whereIn('sent_by_user_id', $userIds)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'sent_by_user_id as uid',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN provider_status = 'failed' THEN 1 ELSE 0 END) as failed"),
                DB::raw('COUNT(DISTINCT DATE(created_at)) as days_active'),
            ])
            ->groupBy('sent_by_user_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = [
                'total' => (int) $r->total,
                'failed' => (int) $r->failed,
                'days_active' => (int) $r->days_active,
            ];
        }

        return $out;
    }

    /**
     * متوسط زمن رد البائع على رسالة العميل (Inbound→Outbound) — حساب على مستوى المحادثة.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{avg_seconds:int, count:int}>
     */
    private function firstResponsePerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_conversations')) {
            return [];
        }

        $secondsExpr = DB::getDriverName() === 'sqlite'
            ? '(strftime("%s", outside_conversations.last_outbound_at) - strftime("%s", outside_conversations.last_inbound_at))'
            : 'TIMESTAMPDIFF(SECOND, outside_conversations.last_inbound_at, outside_conversations.last_outbound_at)';

        $rows = OutsideConversation::query()
            ->join('outside_contacts', 'outside_conversations.outside_contact_id', '=', 'outside_contacts.id')
            ->whereIn('outside_contacts.assigned_user_id', $userIds)
            ->whereNotNull('outside_conversations.last_inbound_at')
            ->whereNotNull('outside_conversations.last_outbound_at')
            ->whereColumn('outside_conversations.last_outbound_at', '>=', 'outside_conversations.last_inbound_at')
            ->whereBetween('outside_conversations.last_inbound_at', [$from, $to])
            ->groupBy('outside_contacts.assigned_user_id')
            ->select([
                'outside_contacts.assigned_user_id as uid',
                DB::raw('COUNT(*) as cnt'),
                DB::raw("AVG({$secondsExpr}) as avg_dur"),
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = [
                'count' => (int) $r->cnt,
                'avg_seconds' => max(0, (int) $r->avg_dur),
            ];
        }

        return $out;
    }

    /**
     * توزيع حالات المحادثات لكل بائع.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    private function pipelinePerRep(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_conversations')) {
            return [];
        }

        $rows = OutsideConversation::query()
            ->join('outside_contacts', 'outside_conversations.outside_contact_id', '=', 'outside_contacts.id')
            ->whereIn('outside_contacts.assigned_user_id', $userIds)
            ->groupBy('outside_contacts.assigned_user_id', 'outside_conversations.status')
            ->select([
                'outside_contacts.assigned_user_id as uid',
                'outside_conversations.status as status',
                DB::raw('COUNT(*) as cnt'),
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r->uid;
            $out[$uid] ??= [];
            $out[$uid][(string) $r->status] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * عدد العملاء الذين رُقّوا إلى عميل فعلي + المؤهَّلين + المفقودين.
     *
     * @param  list<int>  $userIds
     * @return array<int, array{promoted:int, qualified:int, lost:int}>
     */
    private function goodsClientPromotionsPerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('goods_customers')) {
            return [];
        }

        $rows = GoodsCustomer::query()
            ->whereIn('owner_user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->select([
                'owner_user_id as uid',
                DB::raw('SUM(CASE WHEN client_id IS NOT NULL THEN 1 ELSE 0 END) as promoted'),
                DB::raw("SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified"),
                DB::raw("SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost"),
            ])
            ->groupBy('owner_user_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = [
                'promoted' => (int) $r->promoted,
                'qualified' => (int) $r->qualified,
                'lost' => (int) $r->lost,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, int>
     */
    private function statusChangesPerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('goods_customer_status_histories')) {
            return [];
        }

        $rows = GoodsCustomerStatusHistory::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('user_id')
            ->select('user_id as uid', DB::raw('COUNT(*) as cnt'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->uid] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    private function channelMixPerRep(array $userIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_messages')) {
            return [];
        }

        $rows = OutsideMessage::query()
            ->whereIn('sent_by_user_id', $userIds)
            ->where('direction', 'outbound')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('sent_by_user_id', 'channel')
            ->select('sent_by_user_id as uid', 'channel', DB::raw('COUNT(*) as cnt'))
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r->uid;
            $out[$uid] ??= [];
            $out[$uid][(string) $r->channel] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * توزيع تصنيفات الذكاء الاصطناعي لمحادثات البائع (lead/existing/support/spam).
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, int>>
     */
    private function intelligenceMixPerRep(array $userIds): array
    {
        if (empty($userIds) || ! Schema::hasTable('outside_conversations')) {
            return [];
        }

        $rows = OutsideConversation::query()
            ->join('outside_contacts', 'outside_conversations.outside_contact_id', '=', 'outside_contacts.id')
            ->whereIn('outside_contacts.assigned_user_id', $userIds)
            ->whereNotNull('outside_conversations.intelligence_classification')
            ->groupBy('outside_contacts.assigned_user_id', 'outside_conversations.intelligence_classification')
            ->select([
                'outside_contacts.assigned_user_id as uid',
                'outside_conversations.intelligence_classification as cls',
                DB::raw('COUNT(*) as cnt'),
            ])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r->uid;
            $out[$uid] ??= [];
            $out[$uid][(string) $r->cls] = (int) $r->cnt;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $reps
     */
    private function aggregateTotals(array $reps): array
    {
        $totals = $this->emptyTotals();
        if (empty($reps)) {
            return $totals;
        }
        $totals['member_count'] = count($reps);

        $convSum = 0;
        $convCount = 0;
        $respSum = 0;
        $respCount = 0;

        foreach ($reps as $r) {
            $totals['contacts_new'] += $r['volume']['contacts_new'];
            $totals['open_assigned'] += $r['volume']['open_assigned'];
            $totals['messages_out'] += $r['volume']['messages_out'];
            $totals['messages_failed'] += $r['volume']['messages_failed'];
            $totals['status_changes'] += $r['volume']['status_changes'];
            $totals['promoted_to_client'] += $r['quality']['promoted_to_client'];
            $totals['qualified'] += $r['quality']['qualified'];
            $totals['lost'] += $r['quality']['lost'];
            $totals['stalled_conversations'] += $r['quality']['stalled_conversations'];
            $totals['score_sum'] += $r['score'];

            if ($r['quality']['conversion_rate'] !== null) {
                $convSum += $r['quality']['conversion_rate'];
                $convCount++;
            }
            if ($r['quality']['first_response_count'] > 0) {
                $respSum += $r['quality']['first_response_avg_seconds'] * $r['quality']['first_response_count'];
                $respCount += $r['quality']['first_response_count'];
            }
        }

        $totals['avg_score'] = $totals['member_count'] > 0
            ? (int) round($totals['score_sum'] / $totals['member_count'])
            : 0;
        $totals['avg_conversion_rate'] = $convCount > 0 ? round($convSum / $convCount, 3) : null;
        $totals['team_first_response_avg_seconds'] = $respCount > 0 ? (int) round($respSum / $respCount) : 0;
        $totals['failed_rate'] = $totals['messages_out'] > 0
            ? round($totals['messages_failed'] / $totals['messages_out'], 3)
            : null;

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'member_count' => 0,
            'contacts_new' => 0,
            'open_assigned' => 0,
            'messages_out' => 0,
            'messages_failed' => 0,
            'failed_rate' => null,
            'status_changes' => 0,
            'promoted_to_client' => 0,
            'qualified' => 0,
            'lost' => 0,
            'stalled_conversations' => 0,
            'score_sum' => 0,
            'avg_score' => 0,
            'avg_conversion_rate' => null,
            'team_first_response_avg_seconds' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reps
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildLeaderboards(array $reps): array
    {
        $byField = fn (callable $getter) => array_values(array_slice(
            tap($reps, function (&$arr) use ($getter): void {
                usort($arr, fn ($a, $b) => $getter($b) <=> $getter($a));
            }),
            0,
            3,
        ));

        return [
            'by_conversion' => $byField(fn ($r) => $r['quality']['conversion_rate'] ?? 0),
            'by_volume' => $byField(fn ($r) => $r['volume']['messages_out']),
            'by_speed' => array_values(array_slice(
                array_filter($reps, fn ($r) => $r['quality']['first_response_count'] > 0),
                0,
                3,
            )),
            'most_stalled' => array_values(array_slice(
                tap($reps, function (&$arr): void {
                    usort($arr, fn ($a, $b) => $b['quality']['stalled_conversations'] <=> $a['quality']['stalled_conversations']);
                }),
                0,
                3,
            )),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $reps
     * @return array<string, int>
     */
    private function aggregatePipeline(array $reps): array
    {
        $totals = [];
        foreach ($reps as $r) {
            foreach (($r['pipeline'] ?? []) as $status => $cnt) {
                $totals[$status] = ($totals[$status] ?? 0) + (int) $cnt;
            }
        }

        return $totals;
    }

    /**
     * @param  list<array<string, mixed>>  $reps
     * @return array<string, int>
     */
    private function aggregateChannelMix(array $reps): array
    {
        $totals = [];
        foreach ($reps as $r) {
            foreach (($r['channel_mix'] ?? []) as $ch => $cnt) {
                $totals[$ch] = ($totals[$ch] ?? 0) + (int) $cnt;
            }
        }

        return $totals;
    }

    /**
     * أحدث تغييرات حالة على عملاء المبيعات — يوفّر "feed" نشاط للمدير.
     *
     * @param  list<int>  $userIds
     * @return list<array<string, mixed>>
     */
    private function recentStatusChanges(array $userIds, int $limit): array
    {
        if (empty($userIds) || ! Schema::hasTable('goods_customer_status_histories')) {
            return [];
        }

        return GoodsCustomerStatusHistory::query()
            ->whereIn('user_id', $userIds)
            ->orderByDesc('created_at')
            ->with(['user:id,name,avatar_path', 'customer:id,name'])
            ->limit($limit)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'created_at' => $h->created_at?->toIso8601String(),
                'from' => $h->from_status,
                'to' => $h->to_status,
                'note' => $h->note,
                'user' => $h->user ? [
                    'id' => $h->user->id,
                    'name' => $h->user->name,
                    'avatar_url' => $h->user->avatar_path ? Storage::disk('public')->url($h->user->avatar_path) : null,
                ] : null,
                'customer' => $h->customer ? [
                    'id' => $h->customer->id,
                    'name' => $h->customer->name,
                ] : null,
            ])
            ->all();
    }
}
