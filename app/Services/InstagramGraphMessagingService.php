<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramGraphMessagingService
{
    /**
     * إرسال رسالة نصية عبر Instagram Messaging API (حساب أعمال مربوط بصفحة فيسبوك).
     *
     * @return array<string, mixed>
     */
    public function sendText(string $instagramBusinessAccountId, string $pageAccessToken, string $recipientPsid, string $text): array
    {
        $version = (string) config('services.instagram.graph_version', config('services.meta_ads.version', 'v22.0'));
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $version,
            $instagramBusinessAccountId
        );

        $payload = [
            'messaging_type' => 'RESPONSE',
            'recipient' => ['id' => $recipientPsid],
            'message' => ['text' => mb_substr($text, 0, 1000)],
        ];

        $response = Http::timeout(45)
            ->acceptJson()
            ->post($url.'?access_token='.urlencode($pageAccessToken), $payload);

        if (! $response->successful()) {
            $msg = (string) data_get($response->json(), 'error.message', $response->body());

            throw new RuntimeException($msg !== '' ? $msg : 'فشل طلب Instagram Graph API.');
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }
}
