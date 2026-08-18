<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MessengerGraphMessagingService
{
    /**
     * إرسال رسالة نصية عبر Messenger Send API (صفحة فيسبوك مربوطة بالتطبيق).
     *
     * @return array<string, mixed>
     */
    public function sendText(string $pageId, string $pageAccessToken, string $recipientPsid, string $text): array
    {
        $pageAccessToken = $this->normalizeGraphAccessToken($pageAccessToken);

        $version = (string) config('services.messenger.graph_version', config('services.meta_ads.version', 'v22.0'));
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $version,
            trim($pageId)
        );

        $payload = [
            'messaging_type' => 'RESPONSE',
            'recipient' => ['id' => $recipientPsid],
            'message' => ['text' => mb_substr($text, 0, 2000)],
        ];

        $response = Http::timeout(45)
            ->acceptJson()
            ->post($url.'?access_token='.urlencode($pageAccessToken), $payload);

        if (! $response->successful()) {
            $msg = (string) data_get($response->json(), 'error.message', $response->body());

            throw new RuntimeException($msg !== '' ? $msg : 'فشل طلب Messenger Graph API.');
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    private function normalizeGraphAccessToken(string $raw): string
    {
        $t = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $t = trim($t);
        if ($t !== '' && ($t[0] === '"' || $t[0] === "'")) {
            $t = trim($t, " \t\n\r\0\x0B\"'");
        }

        return preg_replace('/\s+/u', '', $t) ?? $t;
    }
}
