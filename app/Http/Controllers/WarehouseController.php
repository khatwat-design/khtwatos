<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use App\Models\ClientDailySaleItem;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $clientId = $request->filled('client_id') ? (int) $request->query('client_id') : null;
        $days = max(3, min(90, (int) $request->query('days', 7)));
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startDate = $request->filled('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->query('end_date'))->startOfDay() : null;

        if ($startDate && $endDate && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $windowEnd = $endDate ?: $today->copy();
        $windowStart = $startDate ?: $windowEnd->copy()->subDays($days - 1);
        $days = max(1, (int) $windowStart->diffInDays($windowEnd) + 1);

        $clients = Client::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $salesQuery = ClientDailySale::query()
            ->with('client:id,name')
            ->orderByDesc('sales_date')
            ->orderByDesc('updated_at');

        if ($clientId) {
            $salesQuery->where('client_id', $clientId);
        }
        $salesQuery
            ->whereDate('sales_date', '>=', $windowStart->toDateString())
            ->whereDate('sales_date', '<=', $windowEnd->toDateString());

        $campaignQuery = ClientCampaignUpdate::query()
            ->with(['client:id,name', 'updatedBy:id,name'])
            ->orderByDesc('report_date')
            ->orderByDesc('updated_at');

        if ($clientId) {
            $campaignQuery->where('client_id', $clientId);
        }
        $campaignQuery
            ->whereDate('report_date', '>=', $windowStart->toDateString())
            ->whereDate('report_date', '<=', $windowEnd->toDateString());

        $sales = $salesQuery->limit(60)->get();
        $campaignUpdates = $campaignQuery->limit(60)->get();
        $trend = $this->buildDailyTrend($clientId, $windowStart, $windowEnd);
        $analytics = $this->buildAnalyticsCards($clientId, $today, $yesterday);
        $alerts = $this->buildAlerts($clientId, $today);
        $executive = $this->buildExecutiveAnalytics($clientId, $windowStart, $windowEnd);

        return Inertia::render('Warehouse/Index', [
            'filters' => [
                'client_id' => $clientId,
                'days' => $days,
                'start_date' => $windowStart->toDateString(),
                'end_date' => $windowEnd->toDateString(),
            ],
            'clients' => $clients,
            'permissions' => [
                'can_view_warehouse' => Gate::forUser($request->user())->allows('view-warehouse'),
                'can_manage_campaign_updates' => Gate::forUser($request->user())->allows('manage-campaign-updates'),
            ],
            'sales_rows' => $sales->map(fn (ClientDailySale $row) => [
                'id' => $row->id,
                'client' => $row->client?->name,
                'sales_date' => $row->sales_date?->toDateString(),
                'orders_count' => $row->orders_count,
                'revenue' => (float) $row->revenue,
                'source' => $row->source,
                'submitted_by_name' => $row->submitted_by_name,
                'submitted_by_email' => $row->submitted_by_email,
            ])->values(),
            'campaign_rows' => $campaignUpdates->map(fn (ClientCampaignUpdate $row) => [
                'id' => $row->id,
                'client_id' => $row->client_id,
                'client' => $row->client?->name,
                'report_date' => $row->report_date?->toDateString(),
                'ad_spend' => (float) $row->ad_spend,
                'messages_count' => (int) $row->messages_count,
                'clicks_count' => (int) $row->clicks_count,
                'actions_taken' => $row->actions_taken,
                'updated_by' => $row->updatedBy?->name,
            ])->values(),
            'alerts' => $alerts,
            'analytics' => $analytics,
            'daily_trend' => $trend,
            'executive_cards' => $executive['cards'],
            'client_performance' => $executive['client_performance'],
            'product_performance' => $executive['product_performance'],
            'team_performance' => $executive['team_performance'],
            'smart_alerts' => $executive['smart_alerts'],
        ]);
    }

    public function upsertCampaignUpdate(Request $request): RedirectResponse
    {
        if (!Gate::forUser($request->user())->allows('manage-campaign-updates')) {
            abort(403, 'هذه العملية متاحة لمدير النظام أو مدراء الحملات.');
        }

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'ad_spend' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'messages_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'clicks_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'actions_taken' => ['nullable', 'string', 'max:5000'],
        ]);

        $today = Carbon::today()->toDateString();
        $todaySales = ClientDailySale::query()
            ->where('client_id', $data['client_id'])
            ->whereDate('sales_date', $today)
            ->first();

        $todayRevenue = (float) ($todaySales?->revenue ?? 0);
        $todayOrders = (int) ($todaySales?->orders_count ?? 0);
        $adSpend = (float) $data['ad_spend'];
        $messages = (int) $data['messages_count'];
        $clicks = (int) ($data['clicks_count'] ?? 0);

        ClientCampaignUpdate::query()->updateOrCreate(
            [
                'client_id' => $data['client_id'],
                'report_date' => $today,
            ],
            [
                'ad_spend' => $adSpend,
                'messages_count' => $messages,
                'clicks_count' => $clicks,
                'leads_count' => $messages,
                'purchases_count' => $todayOrders,
                'campaign_revenue' => $todayRevenue,
                'roas' => $adSpend > 0 ? round($todayRevenue / $adSpend, 2) : null,
                'cpa' => $todayOrders > 0 ? round($adSpend / $todayOrders, 2) : null,
                'cvr' => $messages > 0 ? round(($todayOrders / $messages) * 100, 2) : null,
                'summary' => null,
                'actions_taken' => $data['actions_taken'] ?? null,
                'updated_by_user_id' => $request->user()?->id,
            ]
        );

        return redirect()->route('warehouse.index', ['client_id' => $data['client_id']]);
    }

    private function buildDailyTrend(?int $clientId, Carbon $windowStart, Carbon $windowEnd): Collection
    {
        $from = $windowStart->toDateString();
        $to = $windowEnd->toDateString();
        $days = (int) $windowStart->diffInDays($windowEnd) + 1;

        $salesByDate = ClientDailySale::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('sales_date', '>=', $from)
            ->whereDate('sales_date', '<=', $to)
            ->selectRaw('sales_date as date, SUM(orders_count) as orders_count, SUM(revenue) as revenue')
            ->groupBy('sales_date')
            ->get()
            ->keyBy('date');

        $campaignByDate = ClientCampaignUpdate::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', '>=', $from)
            ->whereDate('report_date', '<=', $to)
            ->selectRaw('report_date as date, SUM(ad_spend) as ad_spend, SUM(messages_count) as messages_count, SUM(clicks_count) as clicks_count')
            ->groupBy('report_date')
            ->get()
            ->keyBy('date');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($salesByDate, $campaignByDate, $windowEnd) {
                $date = $windowEnd->copy()->subDays($offset)->toDateString();
                $sales = $salesByDate->get($date);
                $campaign = $campaignByDate->get($date);
                $revenue = (float) ($sales->revenue ?? 0);
                $adSpend = (float) ($campaign->ad_spend ?? 0);

                return [
                    'date' => $date,
                    'orders_count' => (int) ($sales->orders_count ?? 0),
                    'revenue' => $revenue,
                    'ad_spend' => $adSpend,
                    'messages_count' => (int) ($campaign->messages_count ?? 0),
                    'clicks_count' => (int) ($campaign->clicks_count ?? 0),
                    'roas_from_sales' => $adSpend > 0 ? round($revenue / $adSpend, 2) : null,
                    'cost_per_message' => ((int) ($campaign->messages_count ?? 0)) > 0
                        ? round($adSpend / (int) $campaign->messages_count, 2)
                        : null,
                ];
            })
            ->values();
    }

    private function buildAnalyticsCards(?int $clientId, Carbon $today, Carbon $yesterday): array
    {
        $todaySalesQuery = ClientDailySale::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('sales_date', $today->toDateString());
        $yesterdaySalesQuery = ClientDailySale::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('sales_date', $yesterday->toDateString());

        $todayCampaignQuery = ClientCampaignUpdate::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', $today->toDateString());
        $yesterdayCampaignQuery = ClientCampaignUpdate::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', $yesterday->toDateString());

        $todayRevenue = (float) $todaySalesQuery->sum('revenue');
        $yesterdayRevenue = (float) $yesterdaySalesQuery->sum('revenue');
        $todayOrders = (int) $todaySalesQuery->sum('orders_count');
        $todaySpend = (float) $todayCampaignQuery->sum('ad_spend');
        $yesterdaySpend = (float) $yesterdayCampaignQuery->sum('ad_spend');
        $todayMessages = (int) $todayCampaignQuery->sum('messages_count');
        $todayClicks = (int) $todayCampaignQuery->sum('clicks_count');

        $todayRoas = $todaySpend > 0 ? round($todayRevenue / $todaySpend, 2) : null;
        $yesterdayRoas = $yesterdaySpend > 0 ? round($yesterdayRevenue / $yesterdaySpend, 2) : null;

        return [
            'today' => [
                'revenue' => $todayRevenue,
                'orders' => $todayOrders,
                'ad_spend' => $todaySpend,
                'messages' => $todayMessages,
                'clicks' => $todayClicks,
                'roas' => $todayRoas,
                'cost_per_message' => $todayMessages > 0 ? round($todaySpend / $todayMessages, 2) : null,
            ],
            'delta' => [
                'revenue' => round($todayRevenue - $yesterdayRevenue, 2),
                'ad_spend' => round($todaySpend - $yesterdaySpend, 2),
                'roas' => $todayRoas !== null && $yesterdayRoas !== null ? round($todayRoas - $yesterdayRoas, 2) : null,
            ],
        ];
    }

    private function buildAlerts(?int $clientId, Carbon $today): Collection
    {
        $alerts = collect();

        $latestUpdates = ClientCampaignUpdate::query()
            ->with('client:id,name')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->orderByDesc('report_date')
            ->get()
            ->groupBy('client_id');

        foreach ($latestUpdates as $rows) {
            $latest = $rows->first();
            $previous = $rows->skip(1)->first();
            if (!$latest || !$previous) {
                continue;
            }

            $latestRevenue = (float) ClientDailySale::query()
                ->where('client_id', $latest->client_id)
                ->whereDate('sales_date', $latest->report_date)
                ->sum('revenue');
            $previousRevenue = (float) ClientDailySale::query()
                ->where('client_id', $previous->client_id)
                ->whereDate('sales_date', $previous->report_date)
                ->sum('revenue');
            $latestRoas = (float) $latest->ad_spend > 0 ? ($latestRevenue / (float) $latest->ad_spend) : null;
            $previousRoas = (float) $previous->ad_spend > 0 ? ($previousRevenue / (float) $previous->ad_spend) : null;

            if (
                $latestRoas !== null
                && $previousRoas !== null
                && $previousRoas > 0
                && $latestRoas <= ($previousRoas * 0.8)
            ) {
                $dropPercent = round((1 - ($latestRoas / $previousRoas)) * 100, 1);
                $alerts->push([
                    'type' => 'critical',
                    'client_id' => $latest->client_id,
                    'client' => $latest->client?->name,
                    'title' => 'هبوط واضح في ROAS',
                    'message' => "انخفض ROAS المحسوب من المبيعات بنسبة {$dropPercent}% مقارنةً بآخر يوم.",
                    'date' => $latest->report_date?->toDateString(),
                ]);
            }
        }

        $activeClientIds = ClientCampaignUpdate::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', '>=', $today->copy()->subDays(7)->toDateString())
            ->distinct()
            ->pluck('client_id');

        if ($activeClientIds->isNotEmpty()) {
            $clientsMissingSales = Client::query()
                ->whereIn('id', $activeClientIds)
                ->where(function ($query) {
                    $query->whereNull('current_pipeline_stage_id')
                        ->orWhereHas('currentStage', fn ($q) => $q->where('key', '!=', 'lead'));
                })
                ->whereDoesntHave('dailySales', fn ($q) => $q->whereDate('sales_date', Carbon::today()->toDateString()))
                ->get(['id', 'name']);

            foreach ($clientsMissingSales as $client) {
                $alerts->push([
                    'type' => 'warning',
                    'client_id' => $client->id,
                    'client' => $client->name,
                    'title' => 'لا يوجد إدخال مبيعات اليوم',
                    'message' => 'لم يتم تسجيل مبيعات يومية لهذا العميل حتى الآن.',
                    'date' => Carbon::today()->toDateString(),
                ]);
            }
        }

        return $alerts
            ->sortBy([
                fn (array $alert) => $alert['type'] === 'critical' ? 0 : 1,
                fn (array $alert) => $alert['client'] ?? '',
            ])
            ->values();
    }

    private function buildExecutiveAnalytics(?int $clientId, Carbon $windowStart, Carbon $windowEnd): array
    {
        $days = (int) $windowStart->diffInDays($windowEnd) + 1;
        $fromWindow = $windowStart->toDateString();
        $toWindow = $windowEnd->toDateString();
        $fromPrevWindow = $windowStart->copy()->subDays($days)->toDateString();
        $toPrevWindow = $windowStart->copy()->subDay()->toDateString();

        $salesWindow = ClientDailySale::query()
            ->with('client:id,name,account_manager_id')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('sales_date', '>=', $fromWindow)
            ->whereDate('sales_date', '<=', $toWindow)
            ->get();

        $campaignWindow = ClientCampaignUpdate::query()
            ->with(['client:id,name,account_manager_id', 'updatedBy:id,name'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', '>=', $fromWindow)
            ->whereDate('report_date', '<=', $toWindow)
            ->get();

        $salesPrevious = ClientDailySale::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('sales_date', '>=', $fromPrevWindow)
            ->whereDate('sales_date', '<=', $toPrevWindow)
            ->get();

        $campaignPrevious = ClientCampaignUpdate::query()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('report_date', '>=', $fromPrevWindow)
            ->whereDate('report_date', '<=', $toPrevWindow)
            ->get();

        $revenueWindow = (float) $salesWindow->sum('revenue');
        $ordersWindow = (int) $salesWindow->sum('orders_count');
        $spendWindow = (float) $campaignWindow->sum('ad_spend');
        $messagesWindow = (int) $campaignWindow->sum('messages_count');
        $clicksWindow = (int) $campaignWindow->sum('clicks_count');

        $revenuePrevious = (float) $salesPrevious->sum('revenue');
        $ordersPrevious = (int) $salesPrevious->sum('orders_count');
        $spendPrevious = (float) $campaignPrevious->sum('ad_spend');
        $messagesPrevious = (int) $campaignPrevious->sum('messages_count');

        $clientPerf = $this->buildClientPerformance($salesWindow, $campaignWindow);
        $productPerf = $this->buildProductPerformance($clientId, $fromWindow, $campaignWindow);
        $teamPerf = $this->buildTeamPerformance($salesWindow, $campaignWindow);
        $smartAlerts = $this->buildSmartAlerts($clientPerf, $productPerf, $windowEnd);

        $roasWindow = $spendWindow > 0 ? round($revenueWindow / $spendWindow, 2) : null;
        $roasPrevious = $spendPrevious > 0 ? round($revenuePrevious / $spendPrevious, 2) : null;

        return [
            'cards' => [
                'window_days' => $days,
                'revenue_window' => round($revenueWindow, 2),
                'orders_window' => $ordersWindow,
                'ad_spend_window' => round($spendWindow, 2),
                'messages_window' => $messagesWindow,
                'clicks_window' => $clicksWindow,
                'roas_window' => $roasWindow,
                'conversion_rate_window' => $messagesWindow > 0 ? round(($ordersWindow / $messagesWindow) * 100, 2) : null,
                'cac_window' => $ordersWindow > 0 ? round($spendWindow / $ordersWindow, 2) : null,
                'aov_window' => $ordersWindow > 0 ? round($revenueWindow / $ordersWindow, 2) : null,
                'revenue_previous_window' => round($revenuePrevious, 2),
                'ad_spend_previous_window' => round($spendPrevious, 2),
                'roas_previous_window' => $roasPrevious,
                'conversion_rate_previous_window' => $messagesPrevious > 0 ? round(($ordersPrevious / $messagesPrevious) * 100, 2) : null,
            ],
            'client_performance' => $clientPerf->values(),
            'product_performance' => $productPerf->values(),
            'team_performance' => $teamPerf->values(),
            'smart_alerts' => $smartAlerts->values(),
        ];
    }

    private function buildClientPerformance(Collection $sales30, Collection $campaign30): Collection
    {
        $salesByClient = $sales30->groupBy('client_id');
        $campaignByClient = $campaign30->groupBy('client_id');

        $clientIds = collect($salesByClient->keys())
            ->merge($campaignByClient->keys())
            ->unique()
            ->values();

        return $clientIds->map(function ($clientId) use ($salesByClient, $campaignByClient) {
            $salesRows = $salesByClient->get($clientId, collect());
            $campaignRows = $campaignByClient->get($clientId, collect());
            $clientName = $salesRows->first()?->client?->name ?? $campaignRows->first()?->client?->name ?? 'عميل';

            $revenue = (float) $salesRows->sum('revenue');
            $orders = (int) $salesRows->sum('orders_count');
            $spend = (float) $campaignRows->sum('ad_spend');
            $messages = (int) $campaignRows->sum('messages_count');
            $clicks = (int) $campaignRows->sum('clicks_count');

            return [
                'client_id' => (int) $clientId,
                'client' => $clientName,
                'revenue' => round($revenue, 2),
                'orders' => $orders,
                'ad_spend' => round($spend, 2),
                'messages' => $messages,
                'clicks' => $clicks,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : null,
                'conversion_rate' => $messages > 0 ? round(($orders / $messages) * 100, 2) : null,
                'cac' => $orders > 0 ? round($spend / $orders, 2) : null,
                'aov' => $orders > 0 ? round($revenue / $orders, 2) : null,
            ];
        })->sortByDesc(fn ($row) => (float) $row['revenue']);
    }

    private function buildProductPerformance(?int $clientId, string $from30, Collection $campaign30): Collection
    {
        $items = ClientDailySaleItem::query()
            ->with(['product:id,name,client_id', 'dailySale:id,client_id,sales_date'])
            ->when($clientId, fn ($q) => $q->whereHas('dailySale', fn ($q2) => $q2->where('client_id', $clientId)))
            ->whereHas('dailySale', fn ($q) => $q->whereDate('sales_date', '>=', $from30))
            ->get();

        $itemsByProduct = $items->groupBy('client_product_id');
        $campaignByClient = $campaign30->groupBy('client_id');

        return $itemsByProduct->map(function ($rows, $productId) use ($campaignByClient) {
            $first = $rows->first();
            $clientId = (int) ($first?->dailySale?->client_id ?? 0);
            $clientCampaign = $campaignByClient->get($clientId, collect());

            $productRevenue = (float) $rows->sum('subtotal');
            $productQty = (int) $rows->sum('quantity');
            $clientRevenue = (float) $rows->pluck('dailySale')
                ->filter()
                ->unique('id')
                ->sum('revenue');
            if ($clientRevenue <= 0) {
                $clientRevenue = $productRevenue;
            }

            $clientSpend = (float) $clientCampaign->sum('ad_spend');
            $clientMessages = (int) $clientCampaign->sum('messages_count');
            $share = $clientRevenue > 0 ? ($productRevenue / $clientRevenue) : 0;
            $allocatedSpend = round($clientSpend * $share, 2);
            $allocatedMessages = (int) round($clientMessages * $share);

            return [
                'product_id' => (int) $productId,
                'client_id' => $clientId,
                'product' => $first?->product?->name ?? $first?->product_name ?? 'منتج',
                'sold_quantity' => $productQty,
                'revenue' => round($productRevenue, 2),
                'estimated_spend' => $allocatedSpend,
                'roas' => $allocatedSpend > 0 ? round($productRevenue / $allocatedSpend, 2) : null,
                'cac' => $productQty > 0 ? round($allocatedSpend / $productQty, 2) : null,
                'conversion_rate' => $allocatedMessages > 0 ? round(($productQty / $allocatedMessages) * 100, 2) : null,
            ];
        })->sortByDesc(fn ($row) => (float) $row['revenue']);
    }

    private function buildTeamPerformance(Collection $sales30, Collection $campaign30): Collection
    {
        $teams = Team::query()
            ->whereIn('slug', ['account', 'media-buyer', 'writing', 'sales'])
            ->with('users:id')
            ->get(['id', 'name', 'slug']);

        $accountManagerRevenue = $sales30
            ->groupBy(fn ($row) => (int) ($row->client?->account_manager_id ?? 0))
            ->map(fn ($rows) => (float) $rows->sum('revenue'));
        $campaignByUpdater = $campaign30
            ->groupBy(fn ($row) => (int) ($row->updated_by_user_id ?? 0));

        return $teams->map(function (Team $team) use ($accountManagerRevenue, $campaignByUpdater) {
            $userIds = $team->users->pluck('id')->map(fn ($id) => (int) $id)->all();

            $teamRevenue = collect($userIds)->sum(fn ($id) => (float) ($accountManagerRevenue[$id] ?? 0));
            $teamCampaignRows = collect($userIds)
                ->flatMap(fn ($id) => $campaignByUpdater->get($id, collect()))
                ->values();
            $teamSpend = (float) $teamCampaignRows->sum('ad_spend');
            $teamMessages = (int) $teamCampaignRows->sum('messages_count');

            return [
                'team' => $team->name,
                'slug' => $team->slug,
                'members' => count($userIds),
                'revenue' => round($teamRevenue, 2),
                'ad_spend' => round($teamSpend, 2),
                'messages' => $teamMessages,
                'roas' => $teamSpend > 0 ? round($teamRevenue / $teamSpend, 2) : null,
            ];
        })->sortByDesc(fn ($row) => (float) $row['revenue']);
    }

    private function buildSmartAlerts(Collection $clientPerformance, Collection $productPerformance, Carbon $today): Collection
    {
        $alerts = collect();

        foreach ($clientPerformance as $row) {
            if (($row['roas'] ?? null) !== null && (float) $row['roas'] < 1.2) {
                $alerts->push([
                    'type' => 'critical',
                    'scope' => 'client',
                    'title' => 'ROAS منخفض',
                    'message' => "العميل {$row['client']} لديه ROAS منخفض ({$row['roas']}).",
                    'date' => $today->toDateString(),
                ]);
            }

            if (($row['conversion_rate'] ?? null) !== null && (float) $row['conversion_rate'] < 5) {
                $alerts->push([
                    'type' => 'warning',
                    'scope' => 'client',
                    'title' => 'تحويل ضعيف',
                    'message' => "معدل التحويل للعميل {$row['client']} منخفض ({$row['conversion_rate']}%).",
                    'date' => $today->toDateString(),
                ]);
            }
        }

        foreach ($productPerformance as $row) {
            if (($row['sold_quantity'] ?? 0) > 0 && ($row['roas'] ?? null) !== null && (float) $row['roas'] < 1) {
                $alerts->push([
                    'type' => 'warning',
                    'scope' => 'product',
                    'title' => 'منتج يحتاج تحسين',
                    'message' => "المنتج {$row['product']} يحقق ROAS منخفض ({$row['roas']}).",
                    'date' => $today->toDateString(),
                ]);
            }
        }

        return $alerts
            ->take(12)
            ->values();
    }
}
