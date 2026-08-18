<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\MetaOAuthToken;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $metaToken = null;
        if (Schema::hasTable('meta_oauth_tokens')) {
            $metaToken = MetaOAuthToken::query()->where('user_id', $user->id)->first();
        }

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'metaOAuth' => [
                'enabled' => Schema::hasTable('meta_oauth_tokens'),
                'is_media_buyer' => $this->canConnectMetaOAuth($user),
                'connected' => (bool) $metaToken,
                'meta_user_name' => $metaToken?->meta_user_name,
                'meta_user_id' => $metaToken?->meta_user_id,
                'expires_at' => optional($metaToken?->expires_at)?->toDateTimeString(),
            ],
        ]);
    }

    public function redirectMetaOAuth(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canConnectMetaOAuth($user)) {
            abort(403, 'هذه العملية متاحة للميديا باير أو مدير النظام.');
        }
        if (! Schema::hasTable('meta_oauth_tokens')) {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'يرجى تشغيل migrate أولاً لتفعيل OAuth.']);
        }

        $appId = (string) config('services.meta_ads.app_id');
        $redirectUri = $this->resolveProfileMetaRedirectUri();
        $version = (string) config('services.meta_ads.version', 'v22.0');
        if ($appId === '') {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'META_ADS_APP_ID أو META_ADS_REDIRECT_URI غير مضبوط.']);
        }

        $state = Str::random(40);
        $request->session()->put('meta_oauth_profile_state', $state);

        $query = http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'ads_read,read_insights,business_management',
            'response_type' => 'code',
        ]);

        return redirect()->away("https://www.facebook.com/{$version}/dialog/oauth?{$query}");
    }

    public function handleMetaOAuthCallback(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canConnectMetaOAuth($user)) {
            abort(403, 'هذه العملية متاحة للميديا باير أو مدير النظام.');
        }
        if (! Schema::hasTable('meta_oauth_tokens')) {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'يرجى تشغيل migrate أولاً لتفعيل OAuth.']);
        }

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('meta_oauth_profile_state', '');
        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'فشل التحقق الأمني (state mismatch).']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'لم يتم استلام code من Meta.']);
        }

        $appId = (string) config('services.meta_ads.app_id');
        $appSecret = (string) config('services.meta_ads.app_secret');
        $redirectUri = $this->resolveProfileMetaRedirectUri();
        $version = (string) config('services.meta_ads.version', 'v22.0');
        if ($appId === '' || $appSecret === '') {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'بيانات OAuth في .env غير مكتملة.']);
        }

        $tokenResponse = Http::timeout(40)->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        if ($tokenResponse->failed()) {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'فشل الحصول على access token من Meta.']);
        }

        $shortToken = (string) $tokenResponse->json('access_token', '');
        if ($shortToken === '') {
            return redirect()->route('profile.edit')->withErrors(['meta_oauth' => 'Meta لم يُرجع access token صالح.']);
        }

        $longTokenResponse = Http::timeout(40)->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        $finalToken = $shortToken;
        $expiresIn = (int) $tokenResponse->json('expires_in', 0);
        if ($longTokenResponse->ok() && $longTokenResponse->json('access_token')) {
            $finalToken = (string) $longTokenResponse->json('access_token');
            $expiresIn = (int) $longTokenResponse->json('expires_in', $expiresIn);
        }

        $meResponse = Http::timeout(20)->get("https://graph.facebook.com/{$version}/me", [
            'access_token' => $finalToken,
            'fields' => 'id,name',
        ]);
        $grantedResponse = Http::timeout(20)->get("https://graph.facebook.com/{$version}/me/permissions", [
            'access_token' => $finalToken,
        ]);

        MetaOAuthToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_token' => $finalToken,
                'token_type' => 'bearer',
                'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                'meta_user_id' => (string) $meResponse->json('id', ''),
                'meta_user_name' => (string) $meResponse->json('name', ''),
                'scopes' => json_encode($grantedResponse->json('data', []), JSON_UNESCAPED_UNICODE),
            ]
        );

        return redirect()->route('profile.edit')->with('status', 'تم ربط Meta OAuth بنجاح.');
    }

    public function disconnectMetaOAuth(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canConnectMetaOAuth($user)) {
            abort(403, 'هذه العملية متاحة للميديا باير أو مدير النظام.');
        }
        if (Schema::hasTable('meta_oauth_tokens')) {
            MetaOAuthToken::query()->where('user_id', $user->id)->delete();
        }

        return redirect()->route('profile.edit')->with('status', 'تم فصل Meta OAuth.');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $oldAvatarPath = $user->avatar_path;
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);
        unset($data['avatar'], $data['remove_avatar']);

        $user->fill($data);

        if ($removeAvatar && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            $newPath = $request->file('avatar')->store('user-avatars', 'public');
            $user->avatar_path = $newPath;
            if ($oldAvatarPath && $oldAvatarPath !== $newPath) {
                Storage::disk('public')->delete($oldAvatarPath);
            }
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * تحكم في إظهار دفتر الملاحظات الجانبي (المحتوى يبقى محفوظاً في قاعدة البيانات).
     */
    public function updateTeamNotebookVisibility(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'show_team_notebook' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'show_team_notebook' => $data['show_team_notebook'],
        ]);

        return Redirect::back();
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function canConnectMetaOAuth(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->teams()->where('slug', 'media-buyer')->exists();
    }

    private function resolveProfileMetaRedirectUri(): string
    {
        return (string) (
            config('services.meta_ads.profile_redirect_uri')
            ?: config('services.meta_ads.redirect_uri')
            ?: route('profile.meta.oauth.callback')
        );
    }
}
