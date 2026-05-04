<?php

namespace App\Http\Controllers;

use App\Models\ClientMetaIntegration;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use App\Services\MessagingIntelligenceService;
use App\Services\OutsideWhatsappInboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OutsideWebhookController extends Controller
{
    public function __construct(
        private readonly OutsideWhatsappInboundService $inboundService,
        private readonly MessagingIntelligenceService $messagingIntelligence,
    ) {}

    public function verify(Request $request)
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $verifyToken = (string) (
            config('services.instagram.webhook_verify_token')
            ?: config('services.whatsapp.webhook_verify_token')
        );

        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        try {
            $object = (string) $request->input('object', '');
            if ($object === 'instagram') {
                return $this->receiveInstagram($request);
            }

            $entries = Arr::wrap($request->input('entry', []));
            Log::info('whatsapp.webhook.payload', [
                'object' => $request->input('object'),
                'entries' => count($entries),
            ]);

            foreach ($entries as $entry) {
                $changes = Arr::wrap(data_get($entry, 'changes', []));
                foreach ($changes as $change) {
                    $value = data_get($change, 'value', []);
                    $configuredPhoneId = (string) config('services.whatsapp.phone_number_id');
                    $payloadPhoneId = (string) data_get($value, 'metadata.phone_number_id', '');
                    if ($configuredPhoneId !== '' && $payloadPhoneId !== '' && $payloadPhoneId !== $configuredPhoneId) {
                        Log::warning('whatsapp.webhook.phone_number_id_mismatch', [
                            'expected' => $configuredPhoneId,
                            'got' => $payloadPhoneId,
                        ]);
                    }

                    $profileMap = $this->inboundService->profileNamesByNormalizedPhone(
                        is_array($value) ? $value : []
                    );

                    $messages = Arr::wrap(data_get($value, 'messages', []));
                    foreach ($messages as $incoming) {
                        if (! is_array($incoming)) {
                            continue;
                        }
                        $this->storeInboundMessage($incoming, $profileMap);
                    }

                    $statuses = Arr::wrap(data_get($value, 'statuses', []));
                    foreach ($statuses as $status) {
                        if (is_array($status)) {
                            $this->applyStatusUpdate($status);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('whatsapp.webhook.failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => 'internal'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    private function receiveInstagram(Request $request): JsonResponse
    {
        try {
            $entries = Arr::wrap($request->input('entry', []));
            Log::info('instagram.webhook.payload', [
                'object' => $request->input('object'),
                'entries' => count($entries),
            ]);

            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $igBusinessAccountId = (string) data_get($entry, 'id', '');
                $clientId = null;
                if ($igBusinessAccountId !== '' && Schema::hasTable('client_meta_integrations')) {
                    $clientId = ClientMetaIntegration::query()
                        ->where('meta_instagram_account_id', $igBusinessAccountId)
                        ->value('client_id');
                }

                foreach (Arr::wrap(data_get($entry, 'messaging', [])) as $messaging) {
                    if (! is_array($messaging)) {
                        continue;
                    }
                    $this->storeInboundInstagramMessage($messaging, $clientId, $igBusinessAccountId);
                }
            }
        } catch (Throwable $e) {
            Log::error('instagram.webhook.failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => 'internal'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $messaging
     */
    private function storeInboundInstagramMessage(array $messaging, ?int $clientId, string $igBusinessAccountId): void
    {
        $msg = data_get($messaging, 'message');
        if (! is_array($msg)) {
            return;
        }

        if (data_get($msg, 'is_echo')) {
            return;
        }

        $psid = (string) data_get($messaging, 'sender.id', '');
        if ($psid === '') {
            Log::warning('instagram.webhook.inbound_skip', ['reason' => 'empty_sender']);

            return;
        }

        $mid = (string) data_get($msg, 'mid', '');
        if ($mid === '') {
            $mid = 'ig-synthetic-'.bin2hex(random_bytes(8));
        }
        $externalId = 'ig:'.$mid;

        [$body, $type] = $this->extractInstagramInboundBody($msg);
        $now = now();

        $syntheticPhone = 'ig:'.$psid;

        /** @var OutsideContact $contact */
        $contact = OutsideContact::query()->firstOrCreate(
            ['instagram_psid' => $psid],
            [
                'channel' => 'instagram',
                'phone' => $syntheticPhone,
                'client_id' => $clientId,
                'name' => null,
            ]
        );

        if ($clientId && (int) $contact->client_id !== (int) $clientId) {
            $contact->update(['client_id' => $clientId]);
        }

        if ($contact->channel !== 'instagram') {
            $contact->update([
                'channel' => 'instagram',
                'instagram_psid' => $psid,
            ]);
        }

        $meta = array_merge(is_array($contact->meta) ? $contact->meta : [], [
            'instagram_ig_account_id' => $igBusinessAccountId,
            'instagram_last_inbound_at' => $now->toIso8601String(),
        ]);
        $contact->update(['meta' => $meta]);

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $contact->id,
        ]);
        $markConversationFresh = $conversation->wasRecentlyCreated || $conversation->status === 'closed';

        /** @var OutsideMessage $message */
        $message = OutsideMessage::query()->firstOrCreate(
            ['external_message_id' => $externalId],
            [
                'outside_conversation_id' => $conversation->id,
                'channel' => 'instagram',
                'direction' => 'inbound',
                'message_type' => $type,
                'body' => $body,
                'payload' => array_merge($messaging, ['_ig_business_account_id' => $igBusinessAccountId]),
                'provider_status' => 'received',
                'sent_at' => $now,
                'delivered_at' => $now,
            ]
        );

        if (! $message->wasRecentlyCreated) {
            Log::info('instagram.webhook.duplicate_delivery', ['external_message_id' => $externalId]);

            return;
        }

        $preview = $body !== '' ? $body : '[رسالة إنستغرام]';

        $conversationPatch = [
            'latest_message_preview' => mb_substr($preview, 0, 120),
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_inbound_at' => $now,
            'updated_at' => $now,
        ];
        if ($markConversationFresh) {
            $conversationPatch['status'] = 'new';
        }
        $conversation->update($conversationPatch);

        $contact->update([
            'last_message_at' => $now,
        ]);

        $this->inboundService->ensureGoodsCustomerForNewInbound($contact, null, $preview);

        Log::info('instagram.webhook.inbound_stored', [
            'psid' => $psid,
            'type' => $type,
            'conversation_id' => $conversation->id,
        ]);

        $this->messagingIntelligence->refreshAfterInbound($conversation->fresh());
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array{0: string, 1: string}
     */
    private function extractInstagramInboundBody(array $msg): array
    {
        $text = data_get($msg, 'text');
        if (is_string($text) && trim($text) !== '') {
            return [trim($text), 'text'];
        }

        if (data_get($msg, 'attachments')) {
            return ['[مرفق]', 'attachment'];
        }

        return ['[رسالة إنستغرام]', 'unknown'];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, string>  $profileNamesByPhone
     */
    private function storeInboundMessage(array $incoming, array $profileNamesByPhone): void
    {
        $phoneRaw = (string) data_get($incoming, 'from', '');
        $phone = preg_replace('/\D+/', '', $phoneRaw) ?: $phoneRaw;
        if ($phone === '') {
            Log::warning('whatsapp.webhook.inbound_skip', ['reason' => 'empty_from', 'keys' => array_keys($incoming)]);

            return;
        }

        [$body, $type] = $this->extractInboundBody($incoming);

        $externalId = (string) data_get($incoming, 'id', '');
        if ($externalId === '') {
            $externalId = 'synthetic-'.bin2hex(random_bytes(8));
        }

        $now = now();

        $profileName = $profileNamesByPhone[$phone] ?? null;

        $createAttrs = ['channel' => 'whatsapp'];
        if (is_string($profileName) && trim($profileName) !== '') {
            $createAttrs['name'] = mb_substr(trim($profileName), 0, 255);
        }

        /** @var OutsideContact $contact */
        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => $phone],
            $createAttrs
        );

        $this->inboundService->enrichOutsideContact($contact, $profileName);

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $contact->id,
        ]);
        $markConversationFresh = $conversation->wasRecentlyCreated || $conversation->status === 'closed';

        /** @var OutsideMessage $message */
        $message = OutsideMessage::query()->firstOrCreate(
            ['external_message_id' => $externalId],
            [
                'outside_conversation_id' => $conversation->id,
                'channel' => 'whatsapp',
                'direction' => 'inbound',
                'message_type' => $type,
                'body' => $body,
                'payload' => $incoming,
                'provider_status' => 'received',
                'sent_at' => $now,
                'delivered_at' => $now,
            ]
        );

        if (! $message->wasRecentlyCreated) {
            Log::info('whatsapp.webhook.duplicate_delivery', ['external_message_id' => $externalId]);

            return;
        }

        $preview = $body !== '' ? $body : '[رسالة واردة]';

        $conversationPatch = [
            'latest_message_preview' => mb_substr($preview, 0, 120),
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_inbound_at' => $now,
            'updated_at' => $now,
        ];
        if ($markConversationFresh) {
            $conversationPatch['status'] = 'new';
        }
        $conversation->update($conversationPatch);

        $contact->update([
            'last_message_at' => $now,
        ]);

        $this->inboundService->ensureGoodsCustomerForNewInbound($contact, $profileName, $preview);

        Log::info('whatsapp.webhook.inbound_stored', [
            'phone' => $phone,
            'type' => $type,
            'conversation_id' => $conversation->id,
        ]);

        $this->messagingIntelligence->refreshAfterInbound($conversation->fresh());
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array{0: string, 1: string}
     */
    private function extractInboundBody(array $incoming): array
    {
        $type = (string) data_get($incoming, 'type', 'text');

        return match ($type) {
            'text' => [(string) data_get($incoming, 'text.body', ''), 'text'],
            'button' => [(string) data_get($incoming, 'button.text', '[زر]'), 'button'],
            'interactive' => [
                (string) (
                    data_get($incoming, 'interactive.button_reply.title')
                    ?: data_get($incoming, 'interactive.list_reply.title')
                    ?: '[تفاعلي]'
                ),
                'interactive',
            ],
            'image' => [(string) (data_get($incoming, 'image.caption') ?: '[صورة]'), 'image'],
            'video' => [(string) (data_get($incoming, 'video.caption') ?: '[فيديو]'), 'video'],
            'audio' => ['[صوت]', 'audio'],
            'document' => [
                (string) (
                    data_get($incoming, 'document.caption')
                        ?: ('[مستند] '.(string) data_get($incoming, 'document.filename', ''))
                ),
                'document',
            ],
            'sticker' => ['[ملصق]', 'sticker'],
            'location' => [
                (string) (data_get($incoming, 'location.name') ?: data_get($incoming, 'location.address') ?: '[موقع]'),
                'location',
            ],
            default => ['[رسالة ('.$type.')]', $type],
        };
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyStatusUpdate(array $status): void
    {
        $externalId = (string) data_get($status, 'id', '');
        if ($externalId === '') {
            return;
        }

        if (str_starts_with($externalId, 'ig:')) {
            return;
        }

        $message = OutsideMessage::query()->where('external_message_id', $externalId)->first();
        if (! $message) {
            return;
        }

        $providerStatus = (string) data_get($status, 'status', 'sent');
        $errors = Arr::wrap(data_get($status, 'errors', []));
        $errorMessage = null;
        if (! empty($errors)) {
            $errorMessage = (string) data_get($errors[0], 'title', data_get($errors[0], 'message', ''));
        }

        $payload = [
            'provider_status' => $providerStatus,
            'provider_error' => $errorMessage ?: null,
        ];

        if ($providerStatus === 'delivered') {
            $payload['delivered_at'] = now();
        }
        if ($providerStatus === 'read') {
            $payload['read_at'] = now();
        }
        if ($providerStatus === 'failed') {
            $payload['retry_count'] = (int) $message->retry_count + 1;
        }

        $message->update($payload);
    }
}
