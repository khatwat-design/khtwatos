<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientDailySale;
use App\Models\ClientDailySalesReminderLog;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendClientPortalDailySalesRemindersCommand extends Command
{
    protected $signature = 'portal:send-daily-sales-reminders {--limit=400}';

    protected $description = 'تذكير عملاء البوابة بإدخال مبيعات اليوم عبر واتساب (مرة واحدة يوميًا لكل عميل عند عدم الإدخال)';

    public function __construct(
        private readonly WhatsAppCloudService $whatsAppCloudService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) config('services.portal.daily_sales_reminder_enabled', true)) {
            $this->info('Client portal daily sales reminders are disabled.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('client_daily_sales_reminder_logs') || ! Schema::hasTable('client_daily_sales')) {
            $this->warn('Missing tables; run migrations.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $today = now()->toDateString();

        $clients = Client::query()
            ->whereNotNull('portal_username')
            ->where('portal_username', '!=', '')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($clients as $client) {
            if ($this->clientHasMeaningfulSaleForDate((int) $client->id, $today)) {
                $skipped++;

                continue;
            }

            if ($this->alreadyRemindedToday((int) $client->id, $today)) {
                $this->logResult($client->id, $today, null, null, 'skipped', 'already_reminded_today');
                $skipped++;

                continue;
            }

            $phone = $this->resolveRecipientPhone($client);
            if ($phone === null || $phone === '') {
                $this->logResult($client->id, $today, null, null, 'skipped', 'no_phone');
                $skipped++;

                continue;
            }

            $body = $this->buildFriendlyBody($client);

            $outsideMessageId = null;
            $conversation = null;

            if (
                Schema::hasTable('outside_messages')
                && Schema::hasTable('outside_conversations')
                && Schema::hasTable('outside_contacts')
            ) {
                $contact = OutsideContact::query()
                    ->where('client_id', $client->id)
                    ->where('channel', 'whatsapp')
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->first();

                if ($contact) {
                    $conversation = OutsideConversation::query()->firstOrCreate([
                        'outside_contact_id' => $contact->id,
                    ]);

                    $message = OutsideMessage::query()->create([
                        'outside_conversation_id' => $conversation->id,
                        'channel' => 'whatsapp',
                        'direction' => 'outbound',
                        'message_type' => 'text',
                        'body' => $body,
                        'provider_status' => 'queued',
                        'sent_at' => null,
                    ]);
                    $outsideMessageId = $message->id;
                }
            }

            try {
                $response = $this->whatsAppCloudService->sendText($phone, $body);
                $msgId = is_string(data_get($response, 'messages.0.id'))
                    ? (string) data_get($response, 'messages.0.id')
                    : null;

                if ($outsideMessageId && ($msg = OutsideMessage::query()->find($outsideMessageId))) {
                    $msg->update([
                        'external_message_id' => $msgId,
                        'provider_status' => 'sent',
                        'provider_error' => null,
                        'sent_at' => now(),
                    ]);
                }

                if ($conversation) {
                    $conversation->update([
                        'latest_message_preview' => mb_substr($body, 0, 120),
                        'last_outbound_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->logResult($client->id, $today, $phone, $body, 'sent', null, $msgId, $outsideMessageId);
                $sent++;
            } catch (Throwable $e) {
                if ($outsideMessageId && ($msg = OutsideMessage::query()->find($outsideMessageId))) {
                    $msg->update([
                        'provider_status' => 'failed',
                        'provider_error' => $e->getMessage(),
                        'retry_count' => 1,
                    ]);
                }

                $this->logResult($client->id, $today, $phone, $body, 'failed', null, null, $outsideMessageId, $e->getMessage());
                $failed++;
            }
        }

        $this->info("Portal daily sales reminders: candidates {$clients->count()}, sent {$sent}, skipped {$skipped}, failed {$failed}.");

        return self::SUCCESS;
    }

    private function clientHasMeaningfulSaleForDate(int $clientId, string $date): bool
    {
        return ClientDailySale::query()
            ->where('client_id', $clientId)
            ->whereDate('sales_date', $date)
            ->where(function ($q): void {
                $q->where('orders_count', '>', 0)
                    ->orWhere('revenue', '>', 0);
            })
            ->exists();
    }

    private function alreadyRemindedToday(int $clientId, string $today): bool
    {
        return ClientDailySalesReminderLog::query()
            ->where('client_id', $clientId)
            ->whereDate('reminder_date', $today)
            ->where('status', 'sent')
            ->exists();
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

    private function buildFriendlyBody(Client $client): string
    {
        $name = trim((string) $client->name) ?: 'هناك';
        $portalUrl = url('/portal/login');

        $templates = [
            "مرحبًا {$name} 👋\n"
                ."نذكّرك بلطف: لم نستلم بعد مبيعات اليوم. عندما يتسنّى لك دقيقتين، سجّلها من بوابة العميل — يساعدنا ذلك على متابعة أداء الحملات بدقة.\n"
                ."{$portalUrl}",

            "أهلًا {$name}،\n"
                ."تمرّ علينا نهاية اليوم وما زال بإمكانك إضافة مبيعات اليوم من البوابة. لا تتردد إن احتجت أي مساعدة.\n"
                ."{$portalUrl}",

            "{$name}، تحية ودّية ✨\n"
                ."لو تقدر تدخل مبيعات اليوم على الرابط أدناه، يكون دعمًا كبيرًا لفريقنا. شكرًا لتعاونك الدائم.\n"
                ."{$portalUrl}",

            "مرحبًا {$name}،\n"
                ."تذكير سريع وغير مزعج: مبيعات اليوم لم تُسجّل بعد. يمكنك إدخالها بسهولة من بوابة العميل.\n"
                ."{$portalUrl}",

            "{$name} عزيزنا/عزيزتنا،\n"
                ."نود إكمال صورة الأداء اليومية — أدخل مبيعاتك عندما يناسبك من البوابة، ونحن نقدّر وقتك.\n"
                ."{$portalUrl}",
        ];

        $idx = (int) (now()->dayOfYear % max(1, count($templates)));

        return $templates[$idx];
    }

    private function logResult(
        int $clientId,
        string $reminderDate,
        ?string $phone,
        ?string $body,
        string $status,
        ?string $skipReason,
        ?string $providerMessageId = null,
        ?int $outsideMessageId = null,
        ?string $errorMessage = null,
    ): void {
        ClientDailySalesReminderLog::query()->create([
            'client_id' => $clientId,
            'reminder_date' => $reminderDate,
            'recipient_phone' => $phone,
            'body' => $body,
            'status' => $status,
            'skip_reason' => $skipReason,
            'provider_message_id' => $providerMessageId,
            'outside_message_id' => $outsideMessageId,
            'error_message' => $errorMessage,
        ]);
    }
}
