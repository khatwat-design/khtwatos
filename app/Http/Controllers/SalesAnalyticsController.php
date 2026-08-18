<?php

namespace App\Http\Controllers;

use App\Services\GoodsMetaLeadAnalyticsService;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesAnalyticsController extends Controller
{
    public function __construct(private readonly SalesAnalyticsService $sales) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        // Admins/HR/Sales leads فقط — لمنع تسريب التحليلات لعموم البائعين.
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        $isHr = method_exists($user, 'isHrManager') ? $user->isHrManager() : false;
        $isSalesLead = $user->teams()
            ->where('slug', 'sales')
            ->wherePivot('is_lead', true)
            ->exists();

        abort_unless($isAdmin || $isHr || $isSalesLead, 403);

        $rangeDays = max(7, min(180, (int) $request->query('range', 30)));
        $data = $this->sales->build($rangeDays);

        return Inertia::render('Sales/Analytics', $data + [
            'analytics_range_days' => $rangeDays,
            'meta_leads' => app(GoodsMetaLeadAnalyticsService::class)->salesDashboard($rangeDays),
        ]);
    }
}
