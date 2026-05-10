<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Support\EffectiveSettings;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NativePushService
{
    public static function isConfigured(): bool
    {
        $path = self::credentialsPath();

        return $path !== null && is_readable($path);
    }

    public static function projectId(): ?string
    {
        $data = self::credentialsJson();

        return isset($data['project_id']) ? (string) $data['project_id'] : null;
    }

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function sendToUsers(array $userIds, array $payload): void
    {
        if (! EffectiveSettings::firebaseMobilePushEnabled()) {
            return;
        }

        $path = self::credentialsPath();
        $projectId = self::projectId();

        if ($path === null || ! is_readable($path) || $projectId === null || $projectId === '') {
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

        $tokens = DevicePushToken::query()
            ->whereIn('user_id', $ids->all())
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $accessToken = $this->fetchAccessToken($path);
        if ($accessToken === null) {
            return;
        }

        $title = (string) ($payload['title'] ?? 'إشعار جديد');
        $body = (string) ($payload['body'] ?? '');
        $link = $payload['link'] ?? '';
        $severity = (string) ($payload['severity'] ?? 'info');

        foreach ($tokens as $row) {
            $this->sendSingle($projectId, $accessToken, $row, $title, $body, $link, $severity);
        }
    }

    private function sendSingle(
        string $projectId,
        string $accessToken,
        DevicePushToken $row,
        string $title,
        string $body,
        mixed $link,
        string $severity,
    ): void {
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $response = Http::timeout(25)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url, [
                'message' => [
                    'token' => $row->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => [
                        'link' => (string) $link,
                        'severity' => $severity,
                    ],
                    'android' => [
                        'priority' => 'HIGH',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            DevicePushToken::query()->whereKey($row->id)->update(['last_used_at' => now()]);

            return;
        }

        $status = $response->status();
        $bodyJson = $response->json();

        if ($status === 404 || $status === 400 || $status === 403) {
            $code = data_get($bodyJson, 'error.status');
            if (in_array($code, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                DevicePushToken::query()->whereKey($row->id)->delete();
            }
        }

        Log::warning('Native FCM push failed', [
            'user_id' => $row->user_id,
            'status' => $status,
            'body' => $bodyJson,
        ]);
    }

    private function fetchAccessToken(string $credentialsPath): ?string
    {
        try {
            /** @var array<string, mixed> $json */
            $json = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
            $creds = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $json
            );
            $token = $creds->fetchAuthToken();

            return isset($token['access_token']) ? (string) $token['access_token'] : null;
        } catch (\Throwable $e) {
            Log::warning('Firebase auth token failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private static function credentialsPath(): ?string
    {
        $path = (string) config('services.firebase.credentials');
        $path = trim($path);

        return $path !== '' ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function credentialsJson(): array
    {
        $path = self::credentialsPath();
        if ($path === null || ! is_readable($path)) {
            return [];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return $data;
        } catch (\Throwable) {
            return [];
        }
    }
}
