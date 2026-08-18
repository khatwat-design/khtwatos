<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\GoodsCustomer;
use App\Models\GoodsCustomerStatusHistory;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;

class OutsideGoodsClientBridgeService
{
    /** @var list<string> */
    public const OUTSIDE_FUNNEL_STATUSES = ['new', 'potential', 'unlikely', 'qualified'];

    public function __construct(
        private readonly ClientWorkflowAutomationService $workflow,
        private readonly OutsideWhatsappInboundService $inboundHints,
    ) {}

    public function afterOutsideConversationSaved(OutsideConversation $conversation, ?int $actorId): void
    {
        $conversation->loadMissing('contact');
        $status = (string) $conversation->status;

        if ($status === 'closed') {
            $goods = $this->goodsForContactId((int) $conversation->outside_contact_id);
            if ($goods && ! $goods->client_id) {
                $this->applyGoodsStatus($goods, 'paused', 'مزامنة من الخارج: محادثة مغلقة', $actorId);
            }

            return;
        }

        if (! in_array($status, self::OUTSIDE_FUNNEL_STATUSES, true)) {
            return;
        }

        $goods = $this->ensureGoodsForConversation($conversation);
        if (! $goods) {
            return;
        }

        if ($goods->client_id) {
            return;
        }

        if ($goods->status !== $status) {
            $this->applyGoodsStatus($goods, $status, 'مزامنة من قسم الخارج', $actorId);
        }

        if ($status === 'potential') {
            $this->promoteGoodsCustomerToClient($goods->fresh(), $actorId);
        }
    }

    public function afterGoodsCustomerStatusSaved(GoodsCustomer $goodsCustomer, ?int $actorId): void
    {
        if (! $goodsCustomer->outside_contact_id) {
            if ($goodsCustomer->status === 'potential' && ! $goodsCustomer->client_id) {
                $this->promoteGoodsCustomerToClient($goodsCustomer->fresh(), $actorId);
            }

            return;
        }

        $goodsCustomer->loadMissing('contact');
        $status = (string) $goodsCustomer->status;

        $outsideStatus = $this->mapGoodsStatusToOutside($status);
        if ($outsideStatus !== null) {
            $conv = OutsideConversation::query()->firstOrCreate([
                'outside_contact_id' => $goodsCustomer->outside_contact_id,
            ]);
            if ((string) $conv->status !== $outsideStatus) {
                $conv->update(['status' => $outsideStatus]);
            }
        }

        if ($status === 'potential' && ! $goodsCustomer->client_id) {
            $this->promoteGoodsCustomerToClient($goodsCustomer->fresh(), $actorId);
        }
    }

    public function promoteGoodsCustomerToClient(GoodsCustomer $goodsCustomer, ?int $actorId): ?Client
    {
        if ($goodsCustomer->client_id) {
            return Client::query()->find($goodsCustomer->client_id);
        }

        if ($goodsCustomer->status !== 'potential') {
            return null;
        }

        return DB::transaction(function () use ($goodsCustomer, $actorId): ?Client {
            $goodsCustomer->refresh();

            if ($goodsCustomer->client_id) {
                return Client::query()->find($goodsCustomer->client_id);
            }

            if ($goodsCustomer->status !== 'potential') {
                return null;
            }

            $stage = PipelineStage::query()->firstOrCreate(
                ['key' => 'lead'],
                ['label' => 'عميل محتمل', 'sort_order' => 10]
            );

            $client = Client::query()->create([
                'name' => $goodsCustomer->name,
                'phone' => $goodsCustomer->phone,
                'company' => $goodsCustomer->company,
                'current_pipeline_stage_id' => $stage->id,
            ]);

            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $stage->id,
                'user_id' => $actorId,
                'note' => 'إنشاء تلقائي من البضاعة/الخارج (عميل محتمل)',
            ]);

            $this->workflow->handleClientStageEntered($client, 'lead', $actorId);

            $fromStatus = $goodsCustomer->status;
            $goodsCustomer->update([
                'client_id' => $client->id,
                'status' => 'active',
                'confirmed_at' => $goodsCustomer->confirmed_at ?? now(),
            ]);

            GoodsCustomerStatusHistory::query()->create([
                'goods_customer_id' => $goodsCustomer->id,
                'from_status' => $fromStatus,
                'to_status' => 'active',
                'note' => 'ترقية إلى عميل في نظام العملاء (مرحلة عميل محتمل في المبيعات)',
                'user_id' => $actorId,
            ]);

            $conv = OutsideConversation::query()->where('outside_contact_id', $goodsCustomer->outside_contact_id)->first();
            if ($conv) {
                $conv->update(['status' => 'qualified']);
            }

            return $client;
        });
    }

    private function ensureGoodsForConversation(OutsideConversation $conversation): ?GoodsCustomer
    {
        $contact = $conversation->contact;
        if (! $contact instanceof OutsideContact) {
            return null;
        }

        if (data_get($contact->meta, 'employee_user_id')) {
            return null;
        }

        $existing = GoodsCustomer::query()->where('outside_contact_id', $contact->id)->first();
        if ($existing) {
            return $existing;
        }

        $preview = (string) ($conversation->latest_message_preview ?? '');
        $profileName = $contact->channel === 'whatsapp'
            ? data_get($contact->meta, 'whatsapp_profile_name')
            : null;

        $this->inboundHints->ensureGoodsCustomerForNewInbound($contact, is_string($profileName) ? $profileName : null, $preview);

        return GoodsCustomer::query()->where('outside_contact_id', $contact->id)->first();
    }

    private function goodsForContactId(int $contactId): ?GoodsCustomer
    {
        return GoodsCustomer::query()->where('outside_contact_id', $contactId)->first();
    }

    private function applyGoodsStatus(GoodsCustomer $goods, string $toStatus, string $note, ?int $actorId): void
    {
        $from = $goods->status;
        if ($from === $toStatus) {
            return;
        }

        $goods->update([
            'status' => $toStatus,
            'confirmed_at' => $toStatus !== 'new' ? ($goods->confirmed_at ?? now()) : null,
        ]);

        GoodsCustomerStatusHistory::query()->create([
            'goods_customer_id' => $goods->id,
            'from_status' => $from,
            'to_status' => $toStatus,
            'note' => $note,
            'user_id' => $actorId,
        ]);
    }

    private function mapGoodsStatusToOutside(string $goodsStatus): ?string
    {
        return match ($goodsStatus) {
            'new' => 'new',
            'potential' => 'potential',
            'unlikely' => 'unlikely',
            'qualified' => 'qualified',
            'active' => 'qualified',
            'paused' => 'closed',
            'lost' => 'unlikely',
            default => null,
        };
    }
}
