<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    /**
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body): array
    {
        $token = (string) config('services.whatsapp.token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.version', 'v22.0');

        if ($token === '' || $phoneNumberId === '') {
            throw new RuntimeException('WhatsApp Cloud API غير مهيأ في ملف البيئة.');
        }

        $normalizedTo = preg_replace('/[^0-9]/', '', $to) ?: $to;

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedTo,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException((string) ($response->json('error.message') ?: 'فشل إرسال رسالة واتساب.'));
        }

        return $response->json();
    }
}

