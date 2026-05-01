<?php

namespace App\Http\Controllers;

use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\OutsideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

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
        $entries = Arr::wrap($request->input('entry', []));

        foreach ($entries as $entry) {
            $changes = Arr::wrap(data_get($entry, 'changes', []));
            foreach ($changes as $change) {
                $value = data_get($change, 'value', []);

                $messages = Arr::wrap(data_get($value, 'messages', []));
                foreach ($messages as $incoming) {
                    $this->storeInboundMessage($incoming);
                }

                $statuses = Arr::wrap(data_get($value, 'statuses', []));
                foreach ($statuses as $status) {
                    $this->applyStatusUpdate($status);
                }
            }
        }

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $incoming
     */
    private function storeInboundMessage(array $incoming): void
    {
        $phone = (string) data_get($incoming, 'from', '');
        if ($phone === '') {
            return;
        }

        $body = (string) data_get($incoming, 'text.body', '');
        $externalId = (string) data_get($incoming, 'id', '');
        $type = (string) data_get($incoming, 'type', 'text');
        $now = now();

        $contact = OutsideContact::query()->firstOrCreate(
            ['phone' => $phone],
            ['channel' => 'whatsapp']
        );

        $conversation = OutsideConversation::query()->firstOrCreate([
            'outside_contact_id' => $contact->id,
        ]);

        OutsideMessage::query()->firstOrCreate(
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

        $conversation->update([
            'status' => 'open',
            'latest_message_preview' => mb_substr($body !== '' ? $body : '[رسالة واردة]', 0, 120),
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_inbound_at' => $now,
            'updated_at' => $now,
        ]);

        $contact->update([
            'last_message_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function applyStatusUpdate(array $status): void
    {
        $externalId = (string) data_get($status, 'id', '');
        if ($externalId === '') {
            return;
        }

        $message = OutsideMessage::query()->where('external_message_id', $externalId)->first();
        if (!$message) {
            return;
        }

        $providerStatus = (string) data_get($status, 'status', 'sent');
        $errors = Arr::wrap(data_get($status, 'errors', []));
        $errorMessage = null;
        if (!empty($errors)) {
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

