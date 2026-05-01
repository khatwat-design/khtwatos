<?php

namespace App\Http\Controllers;

use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OutsideWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $verifyToken = (string) config('services.whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, $token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): JsonResponse
    {
        try {
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

                    $messages = Arr::wrap(data_get($value, 'messages', []));
                    foreach ($messages as $incoming) {
                        if (! is_array($incoming)) {
                            continue;
                        }
                        $this->storeInboundMessage($incoming);
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

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function storeInboundMessage(array $incoming): void
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

        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => $phone],
            ['channel' => 'whatsapp']
        );

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $contact->id,
        ]);

        /** @var OutsideMessage $message */
        $message = OutsideMessage::query()->firstOrCreate(
            ['external_message_id' => $externalId],
            [
                'outside_conversation_id' => $conversation->id,
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

        $conversation->update([
            'status' => 'open',
            'latest_message_preview' => mb_substr($preview, 0, 120),
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_inbound_at' => $now,
            'updated_at' => $now,
        ]);

        $contact->update([
            'last_message_at' => $now,
        ]);

        Log::info('whatsapp.webhook.inbound_stored', [
            'phone' => $phone,
            'type' => $type,
            'conversation_id' => $conversation->id,
        ]);
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
