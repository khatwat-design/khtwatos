<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientAutomatedReportLog;
use App\Models\ClientPortalNote;
use App\Models\OutsideContact;
use App\Support\EffectiveSettings;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ClientAutomatedReportDeliveryService
{
    public function __construct(
        private readonly ClientAutomatedReportComposer $composer,
        private readonly WhatsAppCloudService $whatsApp,
    ) {}

    public function deliverDaily(Client $client): void
    {
        if (! EffectiveSettings::clientDailyReportsEnabled()) {
            return;
        }

        if (! Schema::hasTable('client_automated_report_logs')) {
            return;
        }

        $payload = $this->composer->composeDaily($client);
        $this->deliver($client, 'daily', $payload['text'], $payload['period_key']);
    }

    public function deliverWeekly(Client $client): void
    {
        if (! EffectiveSettings::clientWeeklyReportsEnabled()) {
            return;
        }

        if (! Schema::hasTable('client_automated_report_logs')) {
            return;
        }

        $payload = $this->composer->composeWeekly($client);
        $this->deliver($client, 'weekly', $payload['text'], $payload['period_key']);
    }

    private function deliver(Client $client, string $reportType, string $body, string $periodKey): void
    {
        if (ClientAutomatedReportLog::query()
            ->where('client_id', $client->id)
            ->where('report_type', $reportType)
            ->where('period_key', $periodKey)
            ->where('status', 'sent')
            ->exists()) {
            return;
        }

        $channel = strtolower(trim((string) config('services.client_reports.delivery', 'both')));
        if (! in_array($channel, ['both', 'whatsapp', 'portal'], true)) {
            $channel = 'both';
        }

        $waOk = false;
        $portalOk = false;
        $providerId = null;
        $portalNoteId = null;
        $errors = [];

        $phone = $this->resolveRecipientPhone($client);

        if (($channel === 'both' || $channel === 'whatsapp') && $phone) {
            try {
                $resp = $this->whatsApp->sendText($phone, $body);
                $waOk = true;
                $providerId = is_string(data_get($resp, 'messages.0.id'))
                    ? (string) data_get($resp, 'messages.0.id')
                    : null;
            } catch (RuntimeException $e) {
                $errors[] = 'wa: '.$e->getMessage();
            } catch (Throwable $e) {
                $errors[] = 'wa: '.$e->getMessage();
            }
        } elseif ($channel === 'both' || $channel === 'whatsapp') {
            $errors[] = 'wa: no_phone';
        }

        if ($channel === 'both' || $channel === 'portal') {
            if (! Schema::hasTable('client_portal_notes')) {
                $errors[] = 'portal: missing_table';
            } elseif (! $client->portal_username) {
                $errors[] = 'portal: no_username';
            } else {
                $note = ClientPortalNote::query()->create([
                    'client_id' => $client->id,
                    'note' => mb_substr($body, 0, 1900),
                    'expires_at' => now()->addDays($reportType === 'weekly' ? 21 : 14),
                ]);
                $portalOk = true;
                $portalNoteId = $note->id;
            }
        }

        $status = ($waOk || $portalOk) ? 'sent' : 'failed';
        $skipReason = null;
        if ($status === 'failed' && $errors !== []) {
            $skipReason = 'delivery_failed';
        }

        ClientAutomatedReportLog::query()->create([
            'client_id' => $client->id,
            'report_type' => $reportType,
            'period_key' => $periodKey,
            'body' => mb_substr($body, 0, 4000),
            'delivery' => $channel,
            'status' => $status,
            'skip_reason' => $skipReason,
            'portal_note_id' => $portalNoteId,
            'provider_message_id' => $providerId,
            'error_message' => $errors !== [] ? implode(' | ', $errors) : null,
        ]);
    }

    private function resolveRecipientPhone(Client $client): ?string
    {
        $digits = '';

        if (Schema::hasTable('outside_contacts')) {
            $wa = OutsideContact::query()
                ->where('client_id', $client->id)
                ->where('channel', 'whatsapp')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->value('phone');
            $digits = $this->normalizePhoneDigits($wa);
        }

        if ($digits === '') {
            $digits = $this->normalizePhoneDigits($client->phone);
        }

        return $digits !== '' ? $digits : null;
    }

    private function normalizePhoneDigits(?string $phone): string
    {
        $p = preg_replace('/\D+/', '', (string) $phone);

        return $p ?: '';
    }
}
