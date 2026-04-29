<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * @param array<int, int> $userIds
     * @param array<string, mixed> $payload
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        $publicKey = (string) config('services.webpush.public_key');
        $privateKey = (string) config('services.webpush.private_key');
        $subject = (string) config('services.webpush.subject');

        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return;
        }

        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $ids->all())
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $body = json_encode([
            'title' => (string) ($payload['title'] ?? 'إشعار جديد'),
            'body' => (string) ($payload['body'] ?? ''),
            'link' => $payload['link'] ?? null,
            'severity' => $payload['severity'] ?? 'info',
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification($this->toSubscription($subscription), $body ?: '{}');
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                PushSubscription::query()
                    ->where('endpoint', (string) $report->getRequest()->getUri())
                    ->update(['last_used_at' => now()]);
                continue;
            }

            $endpoint = (string) $report->getRequest()->getUri();
            PushSubscription::query()->where('endpoint', $endpoint)->delete();
            Log::warning('Web push failed', ['endpoint' => $endpoint, 'reason' => $report->getReason()]);
        }
    }

    private function toSubscription(PushSubscription $subscription): Subscription
    {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
        ]);
    }
}

