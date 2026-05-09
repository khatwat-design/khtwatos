<?php

namespace App\Http\Middleware;

use App\Http\Controllers\TeamNotebookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                ] : null,
                'can' => [
                    'manageEmployees' => $user ? Gate::forUser($user)->allows('manage-employees') : false,
                    'deleteRecords' => $user ? $user->isAdmin() : false,
                    'viewAdminHome' => $user ? Gate::forUser($user)->allows('view-admin-home') : false,
                    'viewWarehouse' => $user ? Gate::forUser($user)->allows('view-warehouse') : false,
                    'manageCampaignUpdates' => $user ? Gate::forUser($user)->allows('manage-campaign-updates') : false,
                    'viewClientPortalLink' => $user ? Gate::forUser($user)->allows('view-client-portal-link') : false,
                ],
            ],
            'notifications' => [
                'unread_count' => ($user && Schema::hasTable('notifications')) ? (int) $user->unreadNotifications()->count() : 0,
                'webpush_public_key' => config('services.webpush.public_key'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'team_notebook' => fn () => TeamNotebookController::sharedPayloadForRequest($request),
        ];
    }
}
