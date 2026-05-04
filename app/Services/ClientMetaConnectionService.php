<?php

namespace App\Services;

use App\Enums\MetaConnectionStatus;
use App\Models\Client;
use App\Models\ClientMetaConnectionLog;
use App\Models\ClientMetaIntegration;
use App\Models\ClientMetaOauthToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClientMetaConnectionService
{
    /** Scopes requested in portal OAuth (must stay aligned with redirect scope string). */
    public const REQUIRED_SCOPES = [
        'ads_read',
        'read_insights',
        'business_management',
        'pages_show_list',
        'pages_messaging',
        'instagram_basic',
        'instagram_manage_messages',
    ];

    private const CONNECTING_TTL_MINUTES = 30;

    private const REFRESH_IF_EXPIRES_WITHIN_DAYS = 14;

    public function markOAuthRedirectStarted(Client $client, bool $isReconnect = false): void
    {
        if (Schema::hasColumn('clients', 'meta_oauth_connecting_at')) {
            $client->forceFill(['meta_oauth_connecting_at' => now()])->save();
        }

        if (Schema::hasTable('client_meta_oauth_tokens')) {
            $existing = ClientMetaOauthToken::query()->where('client_id', $client->id)->first();
            if ($existing) {
                $existing->update([
                    'connection_status' => MetaConnectionStatus::Connecting->value,
                    'oauth_started_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('client_meta_connection_logs')) {
            $this->writeLog(
                $client,
                $isReconnect ? 'oauth_reconnect_started' : 'oauth_connect_started',
                'portal_client',
                null,
                $isReconnect ? 'بدء إعادة ربط Meta' : 'بدء ربط Meta',
                ['client_id' => $client->id, 'is_reconnect' => $isReconnect]
            );
        }
    }

    public function clearOAuthConnectingFlags(Client $client): void
    {
        if (Schema::hasColumn('clients', 'meta_oauth_connecting_at')) {
            $client->forceFill(['meta_oauth_connecting_at' => null])->save();
        }
    }

    /**
     * Clear stale "connecting" markers so UI does not stay stuck after abandoned OAuth.
     */
    public function normalizeStuckConnectingState(Client $client): void
    {
        $cutoff = now()->subMinutes(self::CONNECTING_TTL_MINUTES);

        if (
            Schema::hasColumn('clients', 'meta_oauth_connecting_at')
            && $client->meta_oauth_connecting_at
            && $client->meta_oauth_connecting_at->lt($cutoff)
        ) {
            $this->clearOAuthConnectingFlags($client);
        }

        if (! Schema::hasTable('client_meta_oauth_tokens')) {
            return;
        }

        $row = ClientMetaOauthToken::query()->where('client_id', $client->id)->first();
        if (! $row || $row->connection_status !== MetaConnectionStatus::Connecting->value) {
            return;
        }

        if (! $row->oauth_started_at || $row->oauth_started_at->lt($cutoff)) {
            $next = $this->inferStatusFromTokenRow($row);
            $row->update([
                'connection_status' => $next->value,
                'oauth_started_at' => null,
            ]);
        }
    }

    public function resolveEffectiveStatus(Client $client, ?ClientMetaOauthToken $token): MetaConnectionStatus
    {
        $this->normalizeStuckConnectingState($client);

        $connectingRecent = Schema::hasColumn('clients', 'meta_oauth_connecting_at')
            && $client->meta_oauth_connecting_at
            && $client->meta_oauth_connecting_at->gte(now()->subMinutes(self::CONNECTING_TTL_MINUTES));

        if (! $token) {
            if ($connectingRecent) {
                return MetaConnectionStatus::Connecting;
            }

            return MetaConnectionStatus::NotConnected;
        }

        if ($token->connection_status === MetaConnectionStatus::Connecting->value) {
            return MetaConnectionStatus::Connecting;
        }

        if ($token->access_token === null || $token->access_token === '') {
            return MetaConnectionStatus::NotConnected;
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return MetaConnectionStatus::NeedsReconnect;
        }

        if ($token->connection_status === MetaConnectionStatus::NeedsReconnect->value) {
            return MetaConnectionStatus::NeedsReconnect;
        }

        if ($token->connection_status === MetaConnectionStatus::Error->value) {
            return MetaConnectionStatus::Error;
        }

        $parsed = MetaConnectionStatus::tryFrom((string) ($token->connection_status ?? ''));

        return $parsed ?? $this->inferStatusFromTokenRow($token);
    }

    /**
     * @return array{
     *   connection_status: string,
     *   connected: bool,
     *   user_message: string,
     *   missing_permissions: list<string>,
     *   token_expires_at: ?string,
     *   last_connected_at: ?string,
     *   last_error_at: ?string,
     *   last_error_message: ?string,
     *   meta_user_name: ?string
     * }
     */
    public function connectionSummary(Client $client): array
    {
        if (! Schema::hasTable('client_meta_oauth_tokens')) {
            return [
                'connection_status' => MetaConnectionStatus::NotConnected->value,
                'connected' => false,
                'user_message' => $this->friendlyStatusMessage(MetaConnectionStatus::NotConnected, null),
                'missing_permissions' => [],
                'token_expires_at' => null,
                'last_connected_at' => null,
                'last_error_at' => null,
                'last_error_message' => null,
                'meta_user_name' => null,
            ];
        }

        $this->normalizeStuckConnectingState($client);

        $token = ClientMetaOauthToken::query()->where('client_id', $client->id)->first();
        $effective = $this->resolveEffectiveStatus($client, $token);
        $missingPerms = is_array($token?->missing_permissions) ? $token->missing_permissions : [];

        return [
            'connection_status' => $effective->value,
            'connected' => (bool) ($token?->access_token),
            'user_message' => $this->friendlyStatusMessage($effective, $token),
            'missing_permissions' => $missingPerms,
            'token_expires_at' => $token?->expires_at?->toIso8601String(),
            'last_connected_at' => $token?->last_connected_at?->toIso8601String(),
            'last_error_at' => $token?->last_error_at?->toIso8601String(),
            'last_error_message' => $token?->last_error_message,
            'meta_user_name' => $token?->meta_user_name,
        ];
    }

    /**
     * @param  array<int, array{permission?: string, status?: string}>  $permissionData
     * @return list<string>
     */
    public function grantedPermissionsFromMePermissions(array $permissionData): array
    {
        $granted = [];
        foreach ($permissionData as $row) {
            if (($row['status'] ?? '') === 'granted' && ! empty($row['permission'])) {
                $granted[] = (string) $row['permission'];
            }
        }

        return array_values(array_unique($granted));
    }

    /**
     * @param  list<string>  $granted
     * @return list<string>
     */
    public function missingRequiredScopes(array $granted): array
    {
        $missing = [];
        foreach (self::REQUIRED_SCOPES as $scope) {
            if (! in_array($scope, $granted, true)) {
                $missing[] = $scope;
            }
        }

        return $missing;
    }

    /**
     * Persist token + permission snapshot, run onboarding, set connection_status.
     *
     * @param  array<int, array{permission?: string, status?: string}>  $permissionRows
     * @return array{success: bool, message: string, flash_errors?: array<string, string>}
     */
    public function completePortalOAuth(
        Client $client,
        string $accessToken,
        int $expiresInSeconds,
        array $permissionRows,
        string $metaUserId,
        string $metaUserName,
        ClientMetaOnboardingService $onboarding,
        MetaCampaignSyncService $metaSync,
    ): array {
        if (! Schema::hasTable('client_meta_oauth_tokens')) {
            return ['success' => false, 'message' => 'يرجى تشغيل migrate أولاً لتفعيل OAuth.'];
        }

        $granted = $this->grantedPermissionsFromMePermissions($permissionRows);
        $missing = $this->missingRequiredScopes($granted);

        $expiresAt = $expiresInSeconds > 0 ? now()->addSeconds($expiresInSeconds) : null;

        $row = ClientMetaOauthToken::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'access_token' => $accessToken,
                'token_type' => 'bearer',
                'expires_at' => $expiresAt,
                'meta_user_id' => $metaUserId,
                'meta_user_name' => $metaUserName,
                'scopes' => json_encode($permissionRows, JSON_UNESCAPED_UNICODE),
                'missing_permissions' => $missing,
                'oauth_started_at' => null,
                'last_connected_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
                'last_error_at' => null,
                'last_error_context' => null,
            ]
        );

        $setup = ['completed' => false, 'integration_id' => null, 'issues' => []];
        $setupException = null;
        try {
            $setup = $onboarding->scanAndSetup($client, $accessToken);
        } catch (\Throwable $e) {
            $setupException = $e;
            Log::error('meta_onboarding_failed', [
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);
        }

        $hasScanIssues = ! empty($setup['issues']) || $setupException !== null;
        $status = match (true) {
            $setupException !== null => MetaConnectionStatus::Error,
            count($missing) > 0 || $hasScanIssues => MetaConnectionStatus::PartiallyConnected,
            default => MetaConnectionStatus::Connected,
        };

        $row->update([
            'connection_status' => $status->value,
            'missing_permissions' => $missing,
            'last_error_code' => $setupException ? 'onboarding_exception' : null,
            'last_error_message' => $setupException ? 'تعذر إكمال الإعداد التلقائي. يرجى المحاولة لاحقًا أو التواصل مع الدعم.' : null,
            'last_error_at' => $setupException ? now() : null,
            'last_error_context' => $setupException ? [
                'exception' => $setupException::class,
                'message' => $setupException->getMessage(),
            ] : null,
        ]);

        $this->clearOAuthConnectingFlags($client);

        if (Schema::hasTable('client_meta_connection_logs')) {
            $integration = ClientMetaIntegration::query()->where('client_id', $client->id)->first();
            $this->writeLog(
                $client,
                'oauth_completed',
                'portal_client',
                null,
                'اكتملت مصادقة Meta',
                [
                    'meta_user_id' => $metaUserId,
                    'meta_user_name' => $metaUserName,
                    'connection_status' => $status->value,
                    'missing_permissions' => $missing,
                    'ad_account_id' => $integration?->ad_account_id,
                    'meta_business_id' => $integration?->meta_business_id,
                    'meta_page_id' => $integration?->meta_page_id,
                    'meta_instagram_account_id' => $integration?->meta_instagram_account_id,
                    'setup_completed' => (bool) ($setup['completed'] ?? false),
                ]
            );
        }

        if ($setupException === null && ! empty($setup['integration_id']) && Schema::hasTable('client_meta_integrations')) {
            $integration = ClientMetaIntegration::query()->find($setup['integration_id']);
            if ($integration && $integration->is_active) {
                $to = now();
                $from = now()->subDays(6);
                try {
                    $metaSync->syncIntegration($integration, $from, $to, null, $accessToken);
                } catch (\Throwable $e) {
                    Log::warning('meta_initial_sync_failed', [
                        'client_id' => $client->id,
                        'integration_id' => $integration->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $msg = match ($status) {
            MetaConnectionStatus::Connected => 'تم الربط والإعداد التلقائي بنجاح.',
            MetaConnectionStatus::PartiallyConnected => 'تم الربط مع وجود خطوات أو صلاحيات ناقصة — راجع التفاصيل أدناه.',
            MetaConnectionStatus::Error => 'تم حفظ الربط لكن حدث خطأ أثناء الإعداد التلقائي.',
        };

        return ['success' => true, 'message' => $msg];
    }

    public function markNeedsReconnect(ClientMetaOauthToken $row, string $message, ?string $code = null, array $context = []): void
    {
        $row->update([
            'connection_status' => MetaConnectionStatus::NeedsReconnect->value,
            'last_error_code' => $code,
            'last_error_message' => $message,
            'last_error_at' => now(),
            'last_error_context' => array_merge($context, ['marked_at' => now()->toIso8601String()]),
        ]);

        if (Schema::hasTable('client_meta_connection_logs')) {
            $this->writeLog(
                $row->client,
                'token_needs_reconnect',
                'system',
                null,
                $message,
                array_merge($context, ['code' => $code])
            );
        }

        Log::warning('meta_client_token_needs_reconnect', [
            'client_id' => $row->client_id,
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ]);
    }

    public function recordMetaApiFailure(int $clientId, string $action, int $httpStatus, ?array $bodyJson, ?string $rawBody = null): void
    {
        $row = ClientMetaOauthToken::query()->where('client_id', $clientId)->first();
        $metaCode = data_get($bodyJson, 'error.code');
        $metaSub = data_get($bodyJson, 'error.error_subcode');
        $metaMsg = (string) data_get($bodyJson, 'error.message', '');

        $context = [
            'action' => $action,
            'http_status' => $httpStatus,
            'meta_code' => $metaCode,
            'meta_subcode' => $metaSub,
            'meta_message' => $metaMsg,
            'body_excerpt' => $rawBody ? mb_substr($rawBody, 0, 500) : null,
        ];

        Log::warning('meta_api_error', array_merge(['client_id' => $clientId], $context));

        $clientForLog = $row?->client ?? Client::query()->find($clientId);
        if ($clientForLog && Schema::hasTable('client_meta_connection_logs')) {
            $this->writeLog($clientForLog, 'meta_api_error', 'system', null, 'خطأ Meta API', $context);
        }

        if (! $row) {
            return;
        }

        if ($this->isOAuthFatalCode($metaCode, $httpStatus)) {
            $this->markNeedsReconnect(
                $row,
                $metaMsg !== ''
                    ? $metaMsg
                    : 'انتهت صلاحية الربط أو أصبح غير صالح. يرجى إعادة الربط من البوابة.',
                is_scalar($metaCode) ? (string) $metaCode : 'oauth_fatal',
                $context
            );
        }
    }

    public function refreshLongLivedTokenIfNeeded(ClientMetaOauthToken $row): bool
    {
        if ($row->access_token === null || $row->access_token === '') {
            return false;
        }

        if ($row->connection_status === MetaConnectionStatus::NeedsReconnect->value) {
            return false;
        }

        $expiresAt = $row->expires_at;
        $shouldTry = $expiresAt === null
            || $expiresAt->isPast()
            || $expiresAt->lte(now()->addDays(self::REFRESH_IF_EXPIRES_WITHIN_DAYS));

        if (! $shouldTry) {
            return true;
        }

        $appId = (string) config('services.meta_ads.app_id');
        $appSecret = (string) config('services.meta_ads.app_secret');
        $version = (string) config('services.meta_ads.version', 'v22.0');
        if ($appId === '' || $appSecret === '') {
            return $expiresAt === null || $expiresAt->isFuture();
        }

        $response = Http::timeout(40)->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => (string) $row->access_token,
        ]);

        if (! $response->ok() || ! $response->json('access_token')) {
            if ($expiresAt && $expiresAt->isPast()) {
                $this->markNeedsReconnect(
                    $row,
                    'تعذر تجديد رمز Meta. يرجى إعادة الربط.',
                    'refresh_failed',
                    ['http_status' => $response->status(), 'body_excerpt' => mb_substr((string) $response->body(), 0, 400)]
                );
            }

            return false;
        }

        $newToken = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 0);
        $row->update([
            'access_token' => $newToken,
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : $row->expires_at,
            'last_token_refresh_at' => now(),
        ]);

        if (Schema::hasTable('client_meta_connection_logs')) {
            $this->writeLog($row->client, 'token_refreshed', 'system', null, 'تم تجديد رمز Meta', [
                'client_id' => $row->client_id,
                'expires_in' => $expiresIn,
            ]);
        }

        return true;
    }

    public function getAccessTokenForClient(int $clientId): ?string
    {
        if (! Schema::hasTable('client_meta_oauth_tokens')) {
            return null;
        }

        $row = ClientMetaOauthToken::query()->where('client_id', $clientId)->first();
        if (! $row || $row->access_token === null || $row->access_token === '') {
            return null;
        }

        if ($row->connection_status === MetaConnectionStatus::NeedsReconnect->value) {
            return null;
        }

        if (! $this->refreshLongLivedTokenIfNeeded($row)) {
            $row->refresh();

            return ($row->expires_at && $row->expires_at->isPast()) ? null : (string) $row->access_token;
        }

        $row->refresh();

        if ($row->expires_at && $row->expires_at->isPast()) {
            $this->markNeedsReconnect($row, 'انتهت صلاحية رمز Meta.', 'expired', []);

            return null;
        }

        return (string) $row->access_token;
    }

    public function portalMetaSetupPayload(Client $client): array
    {
        $summary = $this->connectionSummary($client);

        if (! Schema::hasTable('client_meta_integrations')) {
            return array_merge(['enabled' => false], $summary);
        }

        $integration = ClientMetaIntegration::query()
            ->where('client_id', $client->id)
            ->first();

        return array_merge($summary, [
            'enabled' => true,
            'ad_account_id' => $integration?->ad_account_id,
            'meta_business_id' => $integration?->meta_business_id,
            'meta_page_id' => $integration?->meta_page_id,
            'meta_instagram_account_id' => $integration?->meta_instagram_account_id,
            'setup_status' => $integration?->setup_status,
            'issues' => (array) ($integration?->last_scan_payload['issues'] ?? []),
        ]);
    }

    private function friendlyStatusMessage(MetaConnectionStatus $status, ?ClientMetaOauthToken $token): string
    {
        return match ($status) {
            MetaConnectionStatus::NotConnected => 'لم يتم ربط Meta بعد.',
            MetaConnectionStatus::Connecting => 'جاري إكمال ربط Meta… إذا أُغلقت النافذة، انتظر قليلًا أو أعد المحاولة.',
            MetaConnectionStatus::Connected => 'الاتصال سليم وجميع الصلاحيات المطلوبة متوفرة.',
            MetaConnectionStatus::PartiallyConnected => 'الاتصال يعمل لكن توجد صلاحيات أو إعدادات ناقصة — راجع التفاصيل.',
            MetaConnectionStatus::Error => $token?->last_error_message ?: 'حدث خطأ أثناء الإعداد. حاول مرة أخرى أو تواصل مع الدعم.',
            MetaConnectionStatus::NeedsReconnect => $token?->last_error_message ?: 'انتهت صلاحية الربط — يرجى إعادة الربط.',
        };
    }

    private function inferStatusFromTokenRow(ClientMetaOauthToken $row): MetaConnectionStatus
    {
        if ($row->access_token === null || $row->access_token === '') {
            return MetaConnectionStatus::NotConnected;
        }

        if ($row->expires_at && $row->expires_at->isPast()) {
            return MetaConnectionStatus::NeedsReconnect;
        }

        $missing = is_array($row->missing_permissions) ? $row->missing_permissions : [];
        if (count($missing) > 0) {
            return MetaConnectionStatus::PartiallyConnected;
        }

        return MetaConnectionStatus::Connected;
    }

    private function isOAuthFatalCode(mixed $metaCode, int $httpStatus): bool
    {
        if ($httpStatus === 401) {
            return true;
        }

        $code = is_numeric($metaCode) ? (int) $metaCode : null;
        if ($code === null) {
            return false;
        }

        // 190 invalid OAuth / expired, 102 session key invalid
        return in_array($code, [190, 102, 463], true);
    }

    private function writeLog(Client $client, string $event, string $actorType, ?int $userId, ?string $message, array $context): void
    {
        ClientMetaConnectionLog::query()->create([
            'client_id' => $client->id,
            'event' => $event,
            'actor_type' => $actorType,
            'user_id' => $userId,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
