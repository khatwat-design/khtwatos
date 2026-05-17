<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use App\Models\GoodsMetaLeadStatusHistory;
use Illuminate\Support\Facades\DB;

class GoodsMetaLeadSyncService
{
    public function __construct(
        private readonly GoodsMetaLeadSheetMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function upsertFromSheetRow(array $row, ?int $actorUserId = null): ?GoodsMetaLead
    {
        $mapped = $this->mapper->mapRow($row);
        if ($mapped === []) {
            return null;
        }

        $mapped['phone_normalized'] = $this->mapper->normalizePhone($mapped['phone'] ?? null);
        $mapped['sheet_synced_at'] = now();

        return DB::transaction(function () use ($mapped, $actorUserId) {
            /** @var GoodsMetaLead|null $existing */
            $existing = GoodsMetaLead::query()
                ->where('meta_lead_id', $mapped['meta_lead_id'])
                ->first();

            if (! $existing) {
                $lead = GoodsMetaLead::query()->create($mapped);
                GoodsMetaLeadStatusHistory::query()->create([
                    'goods_meta_lead_id' => $lead->id,
                    'from_status' => null,
                    'to_status' => $lead->workflow_status,
                    'note' => 'استيراد من Google Sheet',
                    'user_id' => $actorUserId,
                ]);

                return $lead;
            }

            $previousStatus = $existing->workflow_status;
            $existing->fill($mapped);
            $existing->save();

            if ($previousStatus !== $existing->workflow_status) {
                GoodsMetaLeadStatusHistory::query()->create([
                    'goods_meta_lead_id' => $existing->id,
                    'from_status' => $previousStatus,
                    'to_status' => $existing->workflow_status,
                    'note' => 'تحديث من Google Sheet',
                    'user_id' => $actorUserId,
                ]);
            }

            return $existing->fresh();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function upsertMany(array $rows, ?int $actorUserId = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $stats['skipped']++;
                continue;
            }

            $mapped = $this->mapper->mapRow($row);
            $metaLeadId = $mapped['meta_lead_id'] ?? null;
            $before = is_string($metaLeadId) && $metaLeadId !== ''
                ? GoodsMetaLead::query()->where('meta_lead_id', $metaLeadId)->exists()
                : false;
            $lead = $this->upsertFromSheetRow($row, $actorUserId);
            if (! $lead) {
                $stats['skipped']++;
                continue;
            }
            if ($before) {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }
        }

        return $stats;
    }
}
