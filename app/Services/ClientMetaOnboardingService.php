<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientMetaIntegration;
use App\Models\ClientMetaMediaBuyerMapping;
use App\Models\MetaOAuthToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ClientMetaOnboardingService
{
    public function scanAndSetup(Client $client, string $accessToken): array
    {
        $issues = collect();
        $actions = collect();
        $checks = [
            'business' => false,
            'ad_account' => false,
            'page' => false,
            'instagram_linked' => false,
        ];

        $version = (string) config('services.meta_ads.version', 'v22.0');
        $base = "https://graph.facebook.com/{$version}";

        $businessId = $this->resolveBusinessId($base, $accessToken);
        if (!$businessId) {
            $issues->push([
                'key' => 'missing_business',
                'title' => 'نحتاج خطوة بسيطة منك',
                'message' => 'لم يتم العثور على Business Manager.',
                'fix_url' => 'https://business.facebook.com/overview',
                'fix_label' => 'إنشاء/تجهيز حساب أعمال',
            ]);
        } else {
            $checks['business'] = true;
        }

        $adAccountId = $this->resolveAdAccountId($base, $accessToken, $businessId);
        if (!$adAccountId) {
            $issues->push([
                'key' => 'missing_ad_account',
                'title' => 'نحتاج خطوة بسيطة منك',
                'message' => 'لم يتم العثور على حساب إعلاني متاح.',
                'fix_url' => $businessId ? "https://business.facebook.com/settings/ad-accounts?business_id={$businessId}" : 'https://business.facebook.com/settings/ad-accounts',
                'fix_label' => 'إنشاء/إضافة حساب إعلاني',
            ]);
        } else {
            $checks['ad_account'] = true;
        }

        $pageId = $this->resolvePageId($base, $accessToken, $businessId);
        if (!$pageId) {
            $issues->push([
                'key' => 'missing_page',
                'title' => 'نحتاج خطوة بسيطة منك',
                'message' => 'لم يتم العثور على صفحة فيسبوك مرتبطة.',
                'fix_url' => $businessId ? "https://business.facebook.com/settings/pages?business_id={$businessId}" : 'https://business.facebook.com/settings/pages',
                'fix_label' => 'إضافة/ربط صفحة فيسبوك',
            ]);
        } else {
            $checks['page'] = true;
        }

        $instagramId = null;
        if ($pageId) {
            $instagramId = $this->resolveInstagramIdFromPage($base, $accessToken, $pageId);
            if (!$instagramId) {
                $issues->push([
                    'key' => 'missing_instagram_link',
                    'title' => 'نحتاج خطوة بسيطة منك',
                    'message' => 'صفحة فيسبوك غير مربوطة بحساب Instagram Business.',
                    'fix_url' => "https://www.facebook.com/{$pageId}/settings/?tab=instagram",
                    'fix_label' => 'ربط Instagram بالصفحة',
                ]);
            } else {
                $checks['instagram_linked'] = true;
            }
        }

        $integration = null;
        if (Schema::hasTable('client_meta_integrations') && $adAccountId) {
            $normalizedAdAccountId = preg_replace('/\D+/', '', $adAccountId);
            $integration = ClientMetaIntegration::query()
                ->where('client_id', $client->id)
                ->orWhere('ad_account_id', $normalizedAdAccountId)
                ->first();

            if (! $integration) {
                $integration = new ClientMetaIntegration();
            }

            $integration->fill([
                'client_id' => $client->id,
                'ad_account_id' => $normalizedAdAccountId,
                'meta_business_id' => $businessId,
                'meta_page_id' => $pageId,
                'meta_instagram_account_id' => $instagramId,
                'is_active' => true,
                'setup_status' => $issues->isEmpty() ? 'completed' : 'needs_attention',
                'last_error' => $issues->isEmpty() ? null : 'Needs manual fix steps',
                'last_scan_payload' => ['checks' => $checks, 'issues' => $issues->values()->all()],
            ]);
            $integration->save();
            $actions->push('تم تحديث ربط العميل مع الحساب الإعلاني.');
        }

        $assignmentResult = $this->assignMediaBuyers($base, $accessToken, $client, $adAccountId);
        foreach ($assignmentResult['actions'] as $action) {
            $actions->push($action);
        }
        foreach ($assignmentResult['issues'] as $issue) {
            $issues->push($issue);
        }

        return [
            'checks' => $checks,
            'actions' => $actions->values()->all(),
            'issues' => $issues->values()->all(),
            'integration_id' => $integration?->id,
            'completed' => $issues->isEmpty(),
        ];
    }

    private function resolveBusinessId(string $base, string $token): ?string
    {
        $resp = Http::timeout(25)->get("{$base}/me/businesses", [
            'access_token' => $token,
            'fields' => 'id,name',
            'limit' => 1,
        ]);

        if (!$resp->ok()) {
            return null;
        }

        return (string) data_get($resp->json(), 'data.0.id', '');
    }

    private function resolveAdAccountId(string $base, string $token, ?string $businessId): ?string
    {
        if ($businessId) {
            $owned = Http::timeout(25)->get("{$base}/{$businessId}/owned_ad_accounts", [
                'access_token' => $token,
                'fields' => 'id,account_id,name',
                'limit' => 1,
            ]);
            if ($owned->ok() && data_get($owned->json(), 'data.0.account_id')) {
                return (string) data_get($owned->json(), 'data.0.account_id');
            }
        }

        $resp = Http::timeout(25)->get("{$base}/me/adaccounts", [
            'access_token' => $token,
            'fields' => 'id,account_id,name',
            'limit' => 1,
        ]);
        if (!$resp->ok()) {
            return null;
        }

        return (string) (data_get($resp->json(), 'data.0.account_id') ?: data_get($resp->json(), 'data.0.id', ''));
    }

    private function resolvePageId(string $base, string $token, ?string $businessId): ?string
    {
        if ($businessId) {
            $owned = Http::timeout(25)->get("{$base}/{$businessId}/owned_pages", [
                'access_token' => $token,
                'fields' => 'id,name',
                'limit' => 1,
            ]);
            if ($owned->ok() && data_get($owned->json(), 'data.0.id')) {
                return (string) data_get($owned->json(), 'data.0.id');
            }
        }

        $resp = Http::timeout(25)->get("{$base}/me/accounts", [
            'access_token' => $token,
            'fields' => 'id,name',
            'limit' => 1,
        ]);
        if (!$resp->ok()) {
            return null;
        }

        return (string) data_get($resp->json(), 'data.0.id', '');
    }

    private function resolveInstagramIdFromPage(string $base, string $token, string $pageId): ?string
    {
        $resp = Http::timeout(25)->get("{$base}/{$pageId}", [
            'access_token' => $token,
            'fields' => 'instagram_business_account{id,username},connected_instagram_account{id,username}',
        ]);
        if (!$resp->ok()) {
            return null;
        }

        return (string) (data_get($resp->json(), 'instagram_business_account.id')
            ?: data_get($resp->json(), 'connected_instagram_account.id')
            ?: '');
    }

    private function assignMediaBuyers(string $base, string $token, Client $client, ?string $adAccountId): array
    {
        $actions = collect();
        $issues = collect();
        if (!$adAccountId) {
            return ['actions' => $actions, 'issues' => $issues];
        }

        if (Schema::hasTable('client_meta_media_buyer_mappings')) {
            $this->syncDefaultMappingsFromTeam($client);
            $mappings = ClientMetaMediaBuyerMapping::query()
                ->with('user:id,name,email')
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->get();
        } else {
            $mappings = collect();
        }

        if ($mappings->isEmpty()) {
            $issues->push([
                'key' => 'missing_media_buyer_mapping',
                'title' => 'نحتاج خطوة بسيطة منك',
                'message' => 'لا توجد خرائط Media Buyer لهذا العميل داخل النظام.',
                'fix_url' => null,
                'fix_label' => 'اطلب من الإدارة إضافة ربط media buyer',
            ]);

            return ['actions' => $actions, 'issues' => $issues];
        }

        foreach ($mappings as $mapping) {
            $principalId = $mapping->meta_business_user_id ?: $mapping->meta_system_user_id;
            if (!$principalId && $mapping->user_id && Schema::hasTable('meta_oauth_tokens')) {
                $buyerToken = MetaOAuthToken::query()
                    ->where('user_id', $mapping->user_id)
                    ->first();

                if ($buyerToken?->meta_user_id) {
                    $principalId = (string) $buyerToken->meta_user_id;
                    $mapping->update([
                        'meta_business_user_id' => $mapping->meta_business_user_id ?: $principalId,
                    ]);
                    $actions->push("تم استخدام مصادقة الميديا باير {$mapping->user?->name} لالتقاط Meta User ID.");
                }
            }

            if (!$principalId) {
                $issues->push([
                    'key' => "missing_business_user_{$mapping->id}",
                    'title' => 'نحتاج خطوة بسيطة منك',
                    'message' => "الميديا باير {$mapping->user?->name} يحتاج مصادقة Meta من ملفه الشخصي أو business_user_id/system_user_id.",
                    'fix_url' => null,
                    'fix_label' => 'طلب مصادقة الميديا باير',
                ]);
                continue;
            }

            $assign = Http::timeout(25)->asForm()->post("{$base}/act_{$adAccountId}/assigned_users", [
                'access_token' => $token,
                'user' => $principalId,
                'tasks' => '["MANAGE","ADVERTISE","ANALYZE"]',
            ]);

            if ($assign->ok()) {
                $actions->push("تم منح صلاحيات للميديا باير {$mapping->user?->name}.");
            } else {
                $issues->push([
                    'key' => "assign_failed_{$mapping->id}",
                    'title' => 'نحتاج خطوة بسيطة منك',
                    'message' => "تعذر منح الصلاحية للميديا باير {$mapping->user?->name}.",
                    'fix_url' => 'https://business.facebook.com/settings/people',
                    'fix_label' => 'منح الصلاحية يدويًا مرة واحدة',
                ]);
            }
        }

        return ['actions' => $actions, 'issues' => $issues];
    }

    private function syncDefaultMappingsFromTeam(Client $client): void
    {
        $team = Team::query()->where('slug', 'media-buyer')->first();
        if (!$team) {
            return;
        }

        $userIds = $team->users()->pluck('users.id');
        User::query()->whereIn('id', $userIds)->get(['id'])->each(function (User $user) use ($client): void {
            ClientMetaMediaBuyerMapping::query()->firstOrCreate(
                ['client_id' => $client->id, 'user_id' => $user->id],
                ['role' => 'media_buyer', 'is_active' => true]
            );
        });
    }
}
