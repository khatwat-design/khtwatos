<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    /**
     * إرسال بيانات دخول الموظف: نص حر داخل نافذة 24س، أو قالب معتمد إن وُجد في الإعدادات.
     *
     * @return array<string, mixed>
     */
    public function sendEmployeeCredentials(
        string $to,
        string $fullBodyFallback,
        string $displayName,
        string $username,
        string $password,
        string $loginUrl,
        string $roleLabel,
    ): array {
        $template = trim((string) config('services.whatsapp.employee_credentials_template'));
        if ($template === '') {
            return $this->sendText($to, $fullBodyFallback);
        }

        $lang = trim((string) config('services.whatsapp.employee_credentials_template_lang', 'ar')) ?: 'ar';

        return $this->sendTemplate(
            $to,
            $template,
            $lang,
            [
                ['type' => 'text', 'text' => $this->clipTemplateParam($displayName)],
                ['type' => 'text', 'text' => $this->clipTemplateParam($username)],
                ['type' => 'text', 'text' => $this->clipTemplateParam($password)],
                ['type' => 'text', 'text' => $this->clipTemplateParam($loginUrl)],
                ['type' => 'text', 'text' => $this->clipTemplateParam($roleLabel)],
            ]
        );
    }

    /**
     * @param  array<int, array{type: string, text: string}>  $bodyParameters
     * @return array<string, mixed>
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $bodyParameters): array
    {
        $token = (string) config('services.whatsapp.token');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $version = (string) config('services.whatsapp.version', 'v22.0');

        if ($token === '' || $phoneNumberId === '') {
            throw new RuntimeException('WhatsApp Cloud API غير مهيأ في ملف البيئة.');
        }

        $normalizedTo = preg_replace('/[^0-9]/', '', $to) ?: $to;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $bodyParameters,
                    ],
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload);

        return $this->decodeSuccessfulResponse($response);
    }

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

        return $this->decodeSuccessfulResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSuccessfulResponse(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        if (! $response->successful()) {
            $msg = (string) (data_get($json, 'error.error_user_msg')
                ?: data_get($json, 'error.message')
                ?: $response->body());

            throw new RuntimeException('فشل إرسال واتساب: '.$msg);
        }

        if (isset($json['error'])) {
            throw new RuntimeException('فشل إرسال واتساب: '.(string) data_get($json, 'error.message', json_encode($json)));
        }

        return $json;
    }

    private function clipTemplateParam(string $value): string
    {
        $v = trim($value);

        return mb_substr($v, 0, 1024);
    }
}
