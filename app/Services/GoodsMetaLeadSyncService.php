<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use App\Models\GoodsMetaLeadStatusHistory;
use App\Support\IraqiPhone;
use Illuminate\Support\Facades\DB;

class GoodsMetaLeadSyncService
{
    public function __construct(
        private readonly GoodsMetaLeadSheetMapper $mapper,
        private readonly GoodsMetaLeadAssignmentService $assignment,
        private readonly SmartNotificationService $notifications,
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
        $mapped['has_whatsapp'] = IraqiPhone::isLikelyMobile($mapped['phone'] ?? null);
        $mapped['sheet_synced_at'] = now();

        return DB::transaction(function () use ($mapped, $actorUserId) {
            /** @var GoodsMetaLead|null $existing */
            $existing = GoodsMetaLead::query()
                ->where('meta_lead_id', $mapped['meta_lead_id'])
                ->first();

            if (! $existing) {
                if (empty($mapped['owner_user_id'])) {
                    $ownerId = $this->assignment->pickOwnerUserId();
                    if ($ownerId) {
                        $mapped['owner_user_id'] = $ownerId;
                        $mapped['assigned_at'] = now();
                    }
                }

                $lead = GoodsMetaLead::query()->create($mapped);
                GoodsMetaLeadStatusHistory::query()->create([
                    'goods_meta_lead_id' => $lead->id,
                    'from_status' => null,
                    'to_status' => $lead->workflow_status,
                    'note' => 'استيراد من Google Sheet',
                    'user_id' => $actorUserId,
                ]);

                if ($lead->owner_user_id) {
                    $this->notifyNewAssignment($lead);
                }

                return $lead;
            }

            $this->applySheetFieldsToExistingLead($existing, $mapped);

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

    /**
     * تحديث بيانات الشيت دون مسح الحالة أو المسؤول أو موعد المكالمة المُدخل من النظام.
     *
     * @param  array<string, mixed>  $mapped
     */
    private function applySheetFieldsToExistingLead(GoodsMetaLead $existing, array $mapped): void
    {
        unset(
            $mapped['workflow_status'],
            $mapped['owner_user_id'],
            $mapped['assigned_at'],
            $mapped['has_whatsapp'],
        );

        if ($existing->workflow_status_managed_at !== null) {
            unset($mapped['probability_label'], $mapped['outcome_label']);
        }

        if ($existing->next_call_at !== null) {
            unset($mapped['next_call_at'], $mapped['call_reminder_sent_at']);
        }

        $existing->fill($mapped);
        $existing->save();
    }

    private function notifyNewAssignment(GoodsMetaLead $lead): void
    {
        $ownerId = (int) ($lead->owner_user_id ?? 0);
        if ($ownerId <= 0) {
            return;
        }

        $this->notifications->notifyUsers([$ownerId], [
            'title' => 'ليد ميتا جديد',
            'body' => trim(($lead->full_name ?: 'عميل').' — '.($lead->campaign_name ?: 'حملة ميتا')),
            'severity' => 'info',
            'category' => 'general',
            'link' => route('goods.index', ['tab' => 'meta_leads', 'meta_owner' => $ownerId]),
            'meta' => ['goods_meta_lead_id' => $lead->id],
        ]);
    }
}
