<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'can' => [
                    'manageEmployees' => $request->user() ? Gate::forUser($request->user())->allows('manage-employees') : false,
                    'deleteRecords' => $request->user() ? $request->user()->isAdmin() : false,
                    'viewAdminHome' => $request->user() ? Gate::forUser($request->user())->allows('view-admin-home') : false,
                    'viewWarehouse' => $request->user() ? Gate::forUser($request->user())->allows('view-warehouse') : false,
                    'manageCampaignUpdates' => $request->user() ? Gate::forUser($request->user())->allows('manage-campaign-updates') : false,
                    'viewClientPortalLink' => $request->user() ? Gate::forUser($request->user())->allows('view-client-portal-link') : false,
                ],
            ],
        ];
    }
}
