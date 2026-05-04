<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientStageHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Portal-only presentation of client journey — does not alter workflow or stages.
 */
class PortalProgressTimelineService
{
    /** @var list<string> */
    private const STEP_KEYS = ['lead', 'analysis', 'strategy', 'content', 'campaign', 'report', 'optimization'];

    /**
     * @param  Collection<int, ClientStageHistory>  $histories  chronological ASC
     * @return array<string, mixed>
     */
    public function build(Client $client, Collection $histories): array
    {
        $currentKey = $client->currentStage?->key;
        $isPaused = $currentKey === 'paused';
        if ($isPaused) {
            $priorStageKey = $histories->reverse()->values()->first(function (ClientStageHistory $row): bool {
                $k = $row->stage?->key;

                return is_string($k) && $k !== '' && $k !== 'paused';
            })?->stage?->key;
            $bucketIndex = $this->bucketIndexForStageKey(is_string($priorStageKey) ? $priorStageKey : null) ?? 0;
        } else {
            $bucketIndex = $this->bucketIndexForStageKey($currentKey) ?? 0;
        }

        $enteredAt = [];
        $completedAt = [];

        foreach ($histories as $row) {
            $stageKey = $row->stage?->key;
            if (! is_string($stageKey) || $stageKey === '' || $stageKey === 'paused') {
                continue;
            }
            $b = $this->bucketIndexForStageKey($stageKey);
            if ($b === null) {
                continue;
            }
            $at = $row->created_at;
            if (! isset($enteredAt[$b]) && $at) {
                $enteredAt[$b] = $at->copy();
            }
        }

        for ($i = 0; $i < 7; $i++) {
            $completedAt[$i] = null;
            foreach ($histories as $row) {
                $stageKey = $row->stage?->key;
                if (! is_string($stageKey) || $stageKey === '' || $stageKey === 'paused') {
                    continue;
                }
                $b = $this->bucketIndexForStageKey($stageKey);
                if ($b === null) {
                    continue;
                }
                if ($b > $i && $row->created_at) {
                    $completedAt[$i] = $row->created_at->copy();
                    break;
                }
            }
        }

        $this->applyPauseCompletions($histories, $completedAt);

        $steps = [];
        foreach (self::STEP_KEYS as $idx => $stepKey) {
            $meta = $this->stepMeta($stepKey);
            $status = $this->stepStatus($idx, $bucketIndex);

            $steps[] = [
                'key' => $stepKey,
                'title_ar' => $meta['title_ar'],
                'title_en' => $meta['title_en'],
                'description_ar' => $meta['description_ar'],
                'status' => $status,
                'entered_at' => isset($enteredAt[$idx]) ? $enteredAt[$idx]->toIso8601String() : null,
                'completed_at' => isset($completedAt[$idx]) && $completedAt[$idx] ? $completedAt[$idx]->toIso8601String() : null,
            ];
        }

        return [
            'steps' => $steps,
            'current_bucket_index' => $bucketIndex,
            'current_stage_key' => $currentKey,
            'is_paused' => $isPaused,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * When workflow jumps to `paused`, complete the prior substantive bucket if nothing newer exists.
     *
     * @param  array<int, Carbon|null>  $completedAt
     */
    private function applyPauseCompletions(Collection $histories, array &$completedAt): void
    {
        $ordered = $histories->values();
        for ($hi = 0; $hi < $ordered->count(); $hi++) {
            $row = $ordered[$hi];
            if ($row->stage?->key !== 'paused' || ! $row->created_at) {
                continue;
            }
            for ($hj = $hi - 1; $hj >= 0; $hj--) {
                $pk = $ordered[$hj]->stage?->key;
                if (! is_string($pk) || $pk === 'paused') {
                    continue;
                }
                $b = $this->bucketIndexForStageKey($pk);
                if ($b === null) {
                    break;
                }
                if ($completedAt[$b] === null) {
                    $completedAt[$b] = $row->created_at->copy();
                }
                break;
            }
        }
    }

    private function stepStatus(int $stepIndex, int $currentBucket): string
    {
        if ($currentBucket > $stepIndex) {
            return 'completed';
        }
        if ($currentBucket === $stepIndex) {
            return 'current';
        }

        return 'upcoming';
    }

    /**
     * Maps granular CRM stages → 7 journey steps (same order as product requirement).
     */
    private function bucketIndexForStageKey(?string $key): ?int
    {
        if ($key === null || $key === '') {
            return null;
        }

        return match (true) {
            in_array($key, ['lead', 'sales_meeting', 'brief_meeting'], true) => 0,
            in_array($key, ['analysis', 'analysis_delivered'], true) => 1,
            in_array($key, ['strategy', 'strategy_delivered', 'payment'], true) => 2,
            in_array($key, ['content_production', 'content_delivered'], true) => 3,
            in_array($key, ['campaign_launch', 'campaign_launched'], true) => 4,
            $key === 'campaign_analysis' => 5,
            $key === 'optimization' => 6,
            default => null,
        };
    }

    /**
     * @return array{title_ar: string, title_en: string, description_ar: string}
     */
    private function stepMeta(string $stepKey): array
    {
        return match ($stepKey) {
            'lead' => [
                'title_ar' => 'عميل محتمل',
                'title_en' => 'Lead',
                'description_ar' => 'التعارف، الاجتماعات الأولى، وجمع البريف قبل بدء العمل الفني.',
            ],
            'analysis' => [
                'title_ar' => 'تحليل',
                'title_en' => 'Analysis',
                'description_ar' => 'دراسة السوق، الجمهور، والمنافسين لتأسيس قرارات مدروسة.',
            ],
            'strategy' => [
                'title_ar' => 'استراتيجية',
                'title_en' => 'Strategy',
                'description_ar' => 'بناء خطة التوجيه، القنوات، والرسائل قبل الإنتاج والإطلاق.',
            ],
            'content' => [
                'title_ar' => 'محتوى',
                'title_en' => 'Content',
                'description_ar' => 'إعداد المواد الإبداعية والنصوص والمرئيات وفق الخطة المعتمدة.',
            ],
            'campaign' => [
                'title_ar' => 'حملة',
                'title_en' => 'Campaign',
                'description_ar' => 'إطلاق الحملات الإعلانية ومراقبة الأداء الأولي على المنصات.',
            ],
            'report' => [
                'title_ar' => 'تقرير',
                'title_en' => 'Report',
                'description_ar' => 'تلخيص النتائج ومؤشرات الأداء ومناقشة ما تم إنجازه.',
            ],
            'optimization' => [
                'title_ar' => 'تحسين',
                'title_en' => 'Optimization',
                'description_ar' => 'ضبط مستمر للاستهداف، الإبداع، والميزانية بناءً على البيانات.',
            ],
            default => [
                'title_ar' => $stepKey,
                'title_en' => $stepKey,
                'description_ar' => '',
            ],
        };
    }
}
