<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientStageHistory;
use App\Models\ClientWhatsappMilestoneLog;
use App\Models\OutsideContact;
use App\Support\EffectiveSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Listens to pipeline history (ClientStageHistory) only — does not own workflow rules.
 */
class ClientMilestoneWhatsAppService
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsApp,
    ) {}

    public function handleStageHistoryId(int $clientStageHistoryId): void
    {
        if (! $this->isEnabled() || ! Schema::hasTable('client_whatsapp_milestone_logs')) {
            return;
        }

        $history = ClientStageHistory::query()
            ->with(['stage:id,key,label', 'client'])
            ->find($clientStageHistoryId);

        if (! $history || ! $history->client) {
            return;
        }

        $stageKey = $history->stage?->key;
        if (! is_string($stageKey) || $stageKey === '') {
            return;
        }

        $milestoneKey = $this->milestoneKeyForStage($stageKey);
        if ($milestoneKey === null) {
            return;
        }

        $client = $history->client;

        if ($this->hasSuccessfulNotificationForMilestone($client->id, $milestoneKey)) {
            $this->writeLog($client->id, $milestoneKey, $stageKey, null, null, 'skipped', 'duplicate_milestone');

            return;
        }

        if ($this->isRateLimited($client->id)) {
            $this->writeLog($client->id, $milestoneKey, $stageKey, null, null, 'skipped', 'rate_limited');

            return;
        }

        $phone = $this->resolveRecipientPhone($client);
        if ($phone === null || $phone === '') {
            $this->writeLog($client->id, $milestoneKey, $stageKey, null, null, 'skipped', 'no_phone');

            return;
        }

        $body = $this->buildMessage($milestoneKey, $client);

        try {
            $response = $this->whatsApp->sendText($phone, $body);
            $msgId = $this->extractMessageId($response);
            $this->writeLog($client->id, $milestoneKey, $stageKey, $phone, $body, 'sent', null, $msgId);

            Log::info('whatsapp.milestone.sent', [
                'client_id' => $client->id,
                'milestone_key' => $milestoneKey,
                'pipeline_stage_key' => $stageKey,
                'to' => $phone,
                'message_id' => $msgId,
            ]);
        } catch (RuntimeException $e) {
            $this->writeLog($client->id, $milestoneKey, $stageKey, $phone, $body, 'failed', null, null, $e->getMessage());

            Log::warning('whatsapp.milestone.failed', [
                'client_id' => $client->id,
                'milestone_key' => $milestoneKey,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $this->writeLog($client->id, $milestoneKey, $stageKey, $phone, $body, 'failed', null, null, $e->getMessage());

            Log::error('whatsapp.milestone.exception', [
                'client_id' => $client->id,
                'milestone_key' => $milestoneKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return EffectiveSettings::whatsappMilestoneNotificationsEnabled();
    }

    private function milestoneKeyForStage(string $pipelineStageKey): ?string
    {
        return match ($pipelineStageKey) {
            'analysis_delivered' => 'analysis_completed',
            'strategy_delivered' => 'strategy_completed',
            'content_delivered' => 'content_ready',
            'campaign_launched' => 'campaign_launched',
            'optimization' => 'report_ready',
            default => null,
        };
    }

    private function buildMessage(string $milestoneKey, Client $client): string
    {
        $name = trim((string) $client->name) ?: 'عميلنا الكريم';
        $portalUrl = url('/portal/login');

        return match ($milestoneKey) {
            'analysis_completed' => "مرحبًا {$name}،\n"
                ."تم إكمال مرحلة التحليل لمشروعك. يمكنك متابعة التفاصيل والمستجدات في بوابة العميل:\n"
                ."{$portalUrl}",
            'strategy_completed' => "مرحبًا {$name}،\n"
                ."استراتيجية الحملة جاهزة للاطلاع. يمكنك مراجعتها في بوابة العميل:\n"
                ."{$portalUrl}",
            'content_ready' => "مرحبًا {$name}،\n"
                ."محتوى الحملة الإعلانية جاهز. يمكنك الاطلاع عليه من بوابة العميل:\n"
                ."{$portalUrl}",
            'campaign_launched' => "مرحبًا {$name}،\n"
                ."تم إطلاق حملاتك الإعلانية. يمكنك متابعة الأداء من بوابة العميل:\n"
                ."{$portalUrl}",
            'report_ready' => "مرحبًا {$name}،\n"
                ."تقرير أداء الحملة جاهز للاطلاع. يمكنك مراجعته في بوابة العميل:\n"
                ."{$portalUrl}",
            default => "مرحبًا {$name}،\nلديك تحديث جديد في بوابة العميل:\n{$portalUrl}",
        };
    }

    private function resolveRecipientPhone(Client $client): ?string
    {
        $digits = null;

        if (Schema::hasTable('outside_contacts')) {
            $waPhone = OutsideContact::query()
                ->where('client_id', $client->id)
                ->where('channel', 'whatsapp')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->value('phone');

            $digits = $this->normalizePhoneDigits($waPhone);
        }

        if ($digits === null || $digits === '') {
            $digits = $this->normalizePhoneDigits($client->phone);
        }

        return $digits !== '' ? $digits : null;
    }

    private function normalizePhoneDigits(?string $phone): string
    {
        $p = preg_replace('/\D+/', '', (string) $phone);

        return $p ?: '';
    }

    private function hasSuccessfulNotificationForMilestone(int $clientId, string $milestoneKey): bool
    {
        return ClientWhatsappMilestoneLog::query()
            ->where('client_id', $clientId)
            ->where('milestone_key', $milestoneKey)
            ->where('status', 'sent')
            ->exists();
    }

    private function isRateLimited(int $clientId): bool
    {
        $minHours = (float) config('services.whatsapp.milestone_min_hours_between', 6);
        if ($minHours <= 0) {
            return false;
        }

        $lastSent = ClientWhatsappMilestoneLog::query()
            ->where('client_id', $clientId)
            ->where('status', 'sent')
            ->orderByDesc('created_at')
            ->first();

        if (! $lastSent || ! $lastSent->created_at) {
            return false;
        }

        return $lastSent->created_at->gt(now()->subHours($minHours));
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function extractMessageId(?array $response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        $messages = $response['messages'] ?? null;
        if (! is_array($messages) || ! isset($messages[0]['id'])) {
            return null;
        }

        return is_string($messages[0]['id']) ? $messages[0]['id'] : null;
    }

    private function writeLog(
        int $clientId,
        string $milestoneKey,
        string $pipelineStageKey,
        ?string $phone,
        ?string $body,
        string $status,
        ?string $skipReason,
        ?string $providerMessageId = null,
        ?string $errorMessage = null,
    ): void {
        ClientWhatsappMilestoneLog::query()->create([
            'client_id' => $clientId,
            'milestone_key' => $milestoneKey,
            'pipeline_stage_key' => $pipelineStageKey,
            'recipient_phone' => $phone,
            'message_body' => $body,
            'status' => $status,
            'skip_reason' => $skipReason,
            'provider_message_id' => $providerMessageId,
            'error_message' => $errorMessage,
        ]);
    }
}
