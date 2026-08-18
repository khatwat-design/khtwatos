<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DailyOperation;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DailyOperationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $this->resolveUserRole($user);
        $isReportUser = $role === 'media_buyer' || $role === 'account_manager';
        $isToday = true;

        $dateStr = $request->query('date');
        if ($dateStr) {
            $parsed = Carbon::parse($dateStr);
            $isToday = $parsed->isToday();
        }
        $date = $dateStr ? Carbon::parse($dateStr)->toDateString() : Carbon::today()->toDateString();

        $clients = ! $isReportUser ? collect() : $this->resolveClients($user, $role);
        $submissions = ! $isReportUser
            ? collect()
            : DailyOperation::query()
                ->where('user_id', $user->id)
                ->whereDate('report_date', $date)
                ->get()
                ->keyBy('client_id');

        $allSubmitted = $isReportUser && $clients->isNotEmpty()
            && $clients->every(fn ($client) => $submissions->has($client->id) && $submissions->get($client->id)->is_submitted);

        $forceAnalytics = ! $isReportUser || ! $isToday || $allSubmitted;
        $analytics = $forceAnalytics ? $this->buildAnalytics($user, $role, $date) : null;

        return Inertia::render('Operations/Index', [
            'userRole' => $role,
            'clients' => ! $isReportUser
                ? []
                : $clients->values()->map(fn (Client $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'company' => $c->company,
                ]),
            'submissions' => ! $isReportUser
                ? []
                : $submissions->map(fn (DailyOperation $op) => [
                    'client_id' => $op->client_id,
                    'is_submitted' => $op->is_submitted,
                    'spend' => (float) ($op->spend ?? 0),
                    'ctr' => (float) ($op->ctr ?? 0),
                    'client_feedback' => $op->client_feedback,
                    'personal_feedback' => $op->personal_feedback,
                    'orders_count' => (int) ($op->orders_count ?? 0),
                    'revenue' => (float) ($op->revenue ?? 0),
                ])->values(),
            'allSubmitted' => $allSubmitted,
            'analytics' => $analytics,
            'showAnalyticsDefault' => $forceAnalytics,
            'currentDate' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $role = $this->resolveUserRole($user);
        $today = Carbon::today()->toDateString();

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'spend' => ['nullable', 'numeric', 'min:0'],
            'ctr' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'client_feedback' => ['nullable', 'string', 'max:1000'],
            'personal_feedback' => ['nullable', 'string', 'max:1000'],
            'orders_count' => ['nullable', 'integer', 'min:0'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
        ]);

        $client = Client::findOrFail((int) $data['client_id']);

        if (! $this->canAccessClient($user, $client, $role)) {
            abort(403, 'لا تملك صلاحية الوصول لهذا العميل.');
        }

        $entry = DailyOperation::query()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->where('role', $role)
            ->whereDate('report_date', $today)
            ->first();

        if (! $entry) {
            $entry = new DailyOperation();
            $entry->user_id = $user->id;
            $entry->client_id = $client->id;
            $entry->report_date = $today;
            $entry->role = $role;
        }

        $isMediaBuyer = $role === 'admin' || $role === 'media_buyer';
        $isAccountManager = $role === 'admin' || $role === 'account_manager';

        $entry->spend = $isMediaBuyer ? $data['spend'] : null;
        $entry->ctr = $isMediaBuyer ? $data['ctr'] : null;
        $entry->client_feedback = $data['client_feedback'];
        $entry->personal_feedback = $isMediaBuyer ? $data['personal_feedback'] : null;
        $entry->orders_count = $isAccountManager ? $data['orders_count'] : null;
        $entry->revenue = $isAccountManager ? $data['revenue'] : null;
        $entry->is_submitted = true;
        $entry->submitted_at = now();
        $entry->save();

        session()->flash('success', 'تم حفظ البيانات');
        return redirect()->route('operations.index');
    }

    private function resolveUserRole($user): string
    {
        if ($user->isAdmin()) {
            return 'admin';
        }

        $hasMediaBuyerClients = $user->campaignManagedClients()->exists();
        $hasAccountManagerClients = $user->managedClients()->exists();
        $isInMediaBuyerTeam = $user->teams()->where('slug', 'media-buyer')->exists();
        $isInAccountTeam = $user->teams()->where('slug', 'account')->exists();

        if ($hasMediaBuyerClients && $isInMediaBuyerTeam) {
            return 'media_buyer';
        }

        if ($hasAccountManagerClients && $isInAccountTeam) {
            return 'account_manager';
        }

        if ($isInMediaBuyerTeam) {
            return 'media_buyer';
        }

        if ($isInAccountTeam) {
            return 'account_manager';
        }

        return 'viewer';
    }

    private function resolveClients($user, string $role)
    {
        if ($role === 'admin') {
            return Client::query()->where('is_active', true)->orderBy('name')->get();
        }

        if ($role === 'media_buyer') {
            return $user->campaignManagedClients()->where('is_active', true)->orderBy('name')->get();
        }

        if ($role === 'account_manager') {
            return $user->managedClients()->where('is_active', true)->orderBy('name')->get();
        }

        return collect();
    }

    private function canAccessClient($user, Client $client, string $role): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'media_buyer') {
            return (int) $client->campaign_manager_id === (int) $user->id;
        }

        if ($role === 'account_manager') {
            return (int) $client->account_manager_id === (int) $user->id;
        }

        return false;
    }

    private function buildAnalytics($user, string $role, string $date): array
    {
        if (! in_array($role, ['media_buyer', 'account_manager'])) {
            $clients = Client::query()->where('is_active', true)->orderBy('name')->get();
            $operations = DailyOperation::query()
                ->whereDate('report_date', $date)
                ->where('is_submitted', true)
                ->get()
                ->groupBy('client_id');
        } else {
            $clients = $this->resolveClients($user, $role);
            $operations = DailyOperation::query()
                ->whereIn('client_id', $clients->pluck('id'))
                ->whereDate('report_date', $date)
                ->where('is_submitted', true)
                ->get()
                ->groupBy('client_id');
        }

        return $clients->map(function (Client $client) use ($operations) {
            $clientOps = $operations->get($client->id, collect());

            $spend = (float) $clientOps->sum('spend');
            $ctr = $clientOps->where('role', 'media_buyer')->avg('ctr');
            $orders = (int) $clientOps->sum('orders_count');
            $revenue = (float) $clientOps->sum('revenue');
            $feedback = $clientOps->pluck('client_feedback')->filter()->first() ?? '—';
            $personal = $clientOps->pluck('personal_feedback')->filter()->first() ?? '—';

            $roas = $spend > 0 && $revenue > 0 ? round($revenue / $spend, 2) : null;
            $cac = $orders > 0 && $spend > 0 ? round($spend / $orders, 2) : null;
            $conversionRate = $spend > 0 && $revenue > 0 ? round(($revenue / $spend) * 100, 2) : null;

            return [
                'client_id' => $client->id,
                'client' => $client->name,
                'spend' => $spend,
                'revenue' => $revenue,
                'orders' => $orders,
                'ctr' => $ctr !== null ? round($ctr, 2) : null,
                'roas' => $roas,
                'conversion_rate' => $conversionRate,
                'cac' => $cac,
                'client_feedback' => $feedback,
                'personal_feedback' => $personal,
            ];
        })->values()->all();
    }
}
