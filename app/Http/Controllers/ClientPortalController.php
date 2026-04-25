<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDailySale;
use App\Models\ClientDailySaleItem;
use App\Models\ClientPortalNote;
use App\Models\ClientProduct;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientPortalController extends Controller
{
    public function login(): Response
    {
        if ($this->authenticatedClient(request()) !== null) {
            return Inertia::location(route('portal.dashboard'));
        }

        return Inertia::render('Portal/Login', [
            'client' => null,
            'configured' => true,
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $client = Client::query()
            ->where('portal_username', $data['username'])
            ->first(['id', 'portal_password']);

        if (!$client || empty($client->portal_password) || !Hash::check($data['password'], (string) $client->portal_password)) {
            throw ValidationException::withMessages([
                'username' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        $request->session()->put('portal_client_id', (int) $client->id);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('portal_client_id');

        return redirect()->route('portal.login');
    }

    public function dashboard(Request $request): Response|RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $client->load([
            'currentStage:id,key,label',
            'tasks.column:id,name',
            'meetings' => fn ($q) => $q->where('status', 'scheduled')->orderBy('start_at')->limit(8),
            'dailySales' => fn ($q) => $q->with('items')->orderByDesc('sales_date')->limit(14),
            'campaignUpdates' => fn ($q) => $q->orderByDesc('report_date')->limit(10),
            'products' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'portalNotes' => fn ($q) => $q->where('expires_at', '>', now())->orderByDesc('created_at')->limit(8),
        ]);

        $salesByDate = $client->dailySales->keyBy(fn (ClientDailySale $row) => $row->sales_date?->toDateString());
        $analytics = $this->buildPortalAnalytics($client);
        $bookingTeams = $this->buildPortalBookingTeams();

        return Inertia::render('Portal/Dashboard', [
            ...$this->basePortalProps($client),
            'tasks' => $client->tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'column' => $task->column?->name,
            ])->values(),
            'meetings' => $client->meetings->map(fn ($meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'start_at' => $meeting->start_at?->toIso8601String(),
                'status' => $meeting->status,
            ])->values(),
            'daily_sales' => $client->dailySales->map(fn (ClientDailySale $row) => [
                'id' => $row->id,
                'sales_date' => $row->sales_date?->toDateString(),
                'orders_count' => $row->orders_count,
                'revenue' => (float) $row->revenue,
                'notes' => $row->notes,
                'items' => $row->items->map(fn (ClientDailySaleItem $item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => (float) $item->subtotal,
                ])->values(),
            ])->values(),
            'products' => $client->products->map(fn (ClientProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->unit_price,
                'stock_quantity' => $product->stock_quantity,
                'details' => $product->details,
            ])->values(),
            'campaign_updates' => $client->campaignUpdates->map(function ($row) use ($salesByDate) {
                $saleRow = $salesByDate->get($row->report_date?->toDateString());
                $saleRevenue = (float) ($saleRow?->revenue ?? 0);
                $saleOrders = (int) ($saleRow?->orders_count ?? 0);
                $messages = max(0, (int) ($row->messages_count ?? 0));
                $clicks = max(0, (int) ($row->clicks_count ?? 0));
                $spend = (float) ($row->ad_spend ?? 0);

                return [
                    'id' => $row->id,
                    'report_date' => $row->report_date?->toDateString(),
                    'ad_spend' => $spend,
                    'messages_count' => $messages,
                    'clicks_count' => $clicks,
                    'actions_taken' => $row->actions_taken,
                    'sales_revenue' => $saleRevenue,
                    'sales_orders' => $saleOrders,
                    'roas' => $spend > 0 ? round($saleRevenue / $spend, 2) : null,
                    'cost_per_message' => $messages > 0 ? round($spend / $messages, 2) : null,
                    'cost_per_click' => $clicks > 0 ? round($spend / $clicks, 2) : null,
                ];
            })->values(),
            'analytics' => $analytics,
            'product_analytics' => $this->buildProductAnalytics($client),
            'active_notes' => $client->portalNotes->map(fn (ClientPortalNote $note) => [
                'id' => $note->id,
                'note' => $note->note,
                'created_at' => $note->created_at?->toIso8601String(),
                'expires_at' => $note->expires_at?->toIso8601String(),
            ])->values(),
            'booked' => $request->boolean('booked'),
            'booking_teams' => $bookingTeams,
        ]);
    }

    public function storeMeeting(Request $request): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $data = $request->validate([
            'team_slug' => ['required', 'exists:teams,slug'],
            'selection_mode' => ['required', 'in:team,members'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
            'project_name' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $team = Team::query()
            ->where('slug', $data['team_slug'])
            ->where('slug', '!=', 'khatwat')
            ->with(['users' => fn ($q) => $q->where('is_bookable', true)->orderBy('name')])
            ->firstOrFail();

        $teamMembers = $team->users->values();
        if ($teamMembers->isEmpty()) {
            throw ValidationException::withMessages([
                'team_slug' => 'هذا القسم لا يحتوي موظفين متاحين للحجز.',
            ]);
        }

        $participantIds = collect($data['participant_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($data['selection_mode'] === 'team') {
            $selectedUsers = $teamMembers;
        } else {
            if ($participantIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'participant_ids' => 'اختر موظفًا واحدًا على الأقل.',
                ]);
            }
            $selectedUsers = $teamMembers
                ->whereIn('id', $participantIds->all())
                ->values();
        }

        if ($selectedUsers->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'الاختيارات غير صالحة لهذا القسم.',
            ]);
        }

        $start = Carbon::parse($data['start_at']);
        $end = (clone $start)->addHour();

        $selectedUserIds = $selectedUsers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $busyByUser = $this->buildBusyByUser($selectedUserIds, $start->copy()->subDays(1), $end->copy()->addDays(1));

        foreach ($selectedUsers as $user) {
            if (!$this->isWithinAvailability($user, $start, $end)) {
                throw ValidationException::withMessages([
                    'start_at' => 'الموعد خارج أوقات توفر بعض الموظفين المحددين.',
                ]);
            }

            $busy = $busyByUser[$user->id] ?? collect();
            $conflict = $busy->first(function (Meeting $meeting) use ($start, $end) {
                $meetingEnd = $meeting->end_at ? $meeting->end_at->copy() : $meeting->start_at->copy()->addHour();
                return $meeting->start_at->lt($end) && $meetingEnd->gt($start);
            });

            if ($conflict) {
                throw ValidationException::withMessages([
                    'start_at' => 'هذا الوقت غير متاح لكل الموظفين المختارين. اختر وقتاً آخر.',
                ]);
            }
        }

        $host = $selectedUsers
            ->sortByDesc(fn (User $user) => (bool) ($user->pivot?->is_lead))
            ->first();

        $meeting = Meeting::query()->create([
            'source' => 'internal',
            'external_id' => null,
            'title' => $data['project_name'],
            'start_at' => $start,
            'end_at' => $end,
            'invitee_name' => $client->name,
            'invitee_email' => $client->email,
            'reason' => $data['reason'] ?? null,
            'status' => 'scheduled',
            'user_id' => $host?->id,
            'client_id' => $client->id,
            'raw_payload' => null,
        ]);

        $meeting->participants()->sync($selectedUserIds);

        return redirect()->route('portal.dashboard', ['booked' => 1]);
    }

    public function products(Request $request): Response|RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $client->load([
            'products' => fn ($q) => $q->orderBy('name'),
        ]);

        return Inertia::render('Portal/Products', [
            ...$this->basePortalProps($client),
            'products' => $client->products->map(fn (ClientProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->unit_price,
                'stock_quantity' => $product->stock_quantity,
                'details' => $product->details,
                'is_active' => (bool) $product->is_active,
            ])->values(),
        ]);
    }

    public function profile(Request $request): Response|RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        return Inertia::render('Portal/Profile', [
            ...$this->basePortalProps($client),
            'profile' => [
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'portal_username' => $client->portal_username,
            ],
        ]);
    }

    public function storeDailySales(Request $request): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $data = $request->validate([
            'sales_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:client_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'submitted_by_email' => ['nullable', 'email', 'max:255'],
        ]);

        $items = collect($data['items'])
            ->map(fn (array $item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->groupBy('product_id')
            ->map(fn ($rows, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => $rows->sum('quantity'),
            ])
            ->values();

        $products = $client->products()
            ->whereIn('id', $items->pluck('product_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $invalidProduct = $items->first(fn (array $item) => !$products->has($item['product_id']));
        if ($invalidProduct) {
            throw ValidationException::withMessages([
                'items' => 'أحد المنتجات المحددة غير متاح لهذا العميل.',
            ]);
        }

        $hasPositive = $items->contains(fn (array $item) => $item['quantity'] > 0);
        if (!$hasPositive) {
            throw ValidationException::withMessages([
                'items' => 'أدخل كمية مبيعات على الأقل لمنتج واحد.',
            ]);
        }

        DB::transaction(function () use ($client, $data, $items, $products): void {
            $sale = ClientDailySale::query()
                ->where('client_id', $client->id)
                ->whereDate('sales_date', $data['sales_date'])
                ->with('items')
                ->first();

            if (!$sale) {
                $sale = ClientDailySale::query()->create([
                    'client_id' => $client->id,
                    'sales_date' => $data['sales_date'],
                    'orders_count' => 0,
                    'revenue' => 0,
                    'notes' => null,
                    'source' => 'portal',
                ]);
                $sale->setRelation('items', collect());
            }

            $oldQtyByProduct = $sale->items
                ->groupBy('client_product_id')
                ->map(fn ($rows) => (int) $rows->sum('quantity'));

            $newQtyByProduct = $items
                ->mapWithKeys(fn (array $item) => [$item['product_id'] => (int) $item['quantity']]);

            foreach ($newQtyByProduct as $productId => $newQty) {
                $product = $products->get((int) $productId);
                if (!$product || $product->stock_quantity === null) {
                    continue;
                }

                $oldQty = (int) ($oldQtyByProduct[$productId] ?? 0);
                $availableForThisSale = (int) $product->stock_quantity + $oldQty;

                if ($newQty > $availableForThisSale) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المطلوبة أكبر من المخزون المتاح للمنتج: {$product->name}.",
                    ]);
                }

                $product->update([
                    'stock_quantity' => $availableForThisSale - $newQty,
                ]);
            }

            $sale->items()->delete();
            $ordersCount = 0;
            $revenue = 0.0;

            foreach ($items as $item) {
                if ($item['quantity'] <= 0) {
                    continue;
                }

                $product = $products->get($item['product_id']);
                $unitPrice = (float) $product->unit_price;
                $subtotal = round($item['quantity'] * $unitPrice, 2);

                $sale->items()->create([
                    'client_product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ]);

                $ordersCount += $item['quantity'];
                $revenue += $subtotal;
            }

            $sale->update([
                'orders_count' => $ordersCount,
                'revenue' => round($revenue, 2),
                'notes' => $data['notes'] ?? null,
                'source' => 'portal',
                'submitted_by_name' => $data['submitted_by_name'] ?? null,
                'submitted_by_email' => $data['submitted_by_email'] ?? null,
            ]);
        });

        return redirect()
            ->route('portal.dashboard')
            ->with('success', 'تم حفظ المبيعات اليومية بنجاح.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);

        ClientProduct::query()->create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'details' => $data['details'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('portal.products.index');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        ClientPortalNote::query()->create([
            'client_id' => $client->id,
            'note' => $data['note'],
            'expires_at' => now()->addDay(),
        ]);

        return redirect()->route('portal.dashboard');
    }

    public function updateProduct(Request $request, ClientProduct $clientProduct): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        if ((int) $clientProduct->client_id !== (int) $client->id) {
            abort(403, 'لا يمكنك تعديل مخزون منتج لا يخصك.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'details' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $clientProduct->update($data);

        return redirect()->route('portal.products.index');
    }

    public function destroyProduct(Request $request, ClientProduct $clientProduct): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        if ((int) $clientProduct->client_id !== (int) $client->id) {
            abort(403, 'لا يمكنك حذف منتج لا يخصك.');
        }

        $clientProduct->delete();

        return redirect()->route('portal.products.index');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $client = $this->authenticatedClient($request);
        if (!$client) {
            return redirect()->route('portal.login');
        }

        $section = (string) $request->input('section');

        if ($section === 'account') {
            $data = $request->validate([
                'portal_username' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('clients', 'portal_username')->ignore($client->id),
                ],
                'portal_password' => ['nullable', 'string', 'min:6', 'max:255', 'confirmed'],
            ]);

            $payload = [
                'portal_username' => $data['portal_username'],
            ];
            if (!empty($data['portal_password'])) {
                $payload['portal_password'] = Hash::make($data['portal_password']);
            }

            $client->update($payload);
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'company' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:64'],
            ]);

            $client->update($data);
        }

        return redirect()->route('portal.profile');
    }

    private function authenticatedClient(Request $request): ?Client
    {
        $id = $request->session()->get('portal_client_id');
        if (!$id) {
            return null;
        }

        return Client::query()->find((int) $id);
    }

    private function basePortalProps(Client $client): array
    {
        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'current_stage' => $client->currentStage?->label,
                'notes' => $client->notes,
            ],
        ];
    }

    private function buildPortalBookingTeams(): array
    {
        $teams = Team::query()
            ->where('slug', '!=', 'khatwat')
            ->with(['users' => fn ($q) => $q->where('is_bookable', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        $bookableUsers = $teams
            ->flatMap(fn (Team $team) => $team->users)
            ->unique('id')
            ->values();

        $from = now()->startOfDay();
        $to = now()->addDays(21)->endOfDay();
        $busyByUser = $this->buildBusyByUser(
            $bookableUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $from,
            $to,
        );

        return $teams->map(fn (Team $team) => [
            'name' => $team->name,
            'slug' => $team->slug,
            'members' => $team->users
                ->values()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_lead' => (bool) ($user->pivot?->is_lead),
                    'slots' => $this->buildAvailableSlots(
                        $user,
                        $busyByUser[$user->id] ?? collect(),
                    ),
                ]),
        ])->values()->all();
    }

    private function buildPortalAnalytics(Client $client): array
    {
        $today = now()->toDateString();
        $from = now()->subDays(6)->toDateString();
        $monthFrom = now()->subDays(29)->toDateString();

        $todaySales = ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', $today)
            ->first();
        $todayCampaign = $client->campaignUpdates
            ->first(fn ($row) => $row->report_date?->toDateString() === $today);

        $weekRevenue = (float) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $from)
            ->sum('revenue');
        $weekSpend = (float) $client->campaignUpdates
            ->filter(fn ($row) => $row->report_date?->toDateString() >= $from)
            ->sum('ad_spend');
        $weekMessages = (int) $client->campaignUpdates
            ->filter(fn ($row) => $row->report_date?->toDateString() >= $from)
            ->sum('messages_count');
        $weekOrders = (int) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $from)
            ->sum('orders_count');
        $monthRevenue = (float) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $monthFrom)
            ->sum('revenue');
        $monthSpend = (float) $client->campaignUpdates
            ->filter(fn ($row) => $row->report_date?->toDateString() >= $monthFrom)
            ->sum('ad_spend');
        $monthMessages = (int) $client->campaignUpdates
            ->filter(fn ($row) => $row->report_date?->toDateString() >= $monthFrom)
            ->sum('messages_count');
        $monthOrders = (int) ClientDailySale::query()
            ->where('client_id', $client->id)
            ->whereDate('sales_date', '>=', $monthFrom)
            ->sum('orders_count');

        return [
            'today' => [
                'revenue' => (float) ($todaySales?->revenue ?? 0),
                'orders' => (int) ($todaySales?->orders_count ?? 0),
                'ad_spend' => (float) ($todayCampaign?->ad_spend ?? 0),
                'messages' => (int) ($todayCampaign?->messages_count ?? 0),
                'roas' => ((float) ($todayCampaign?->ad_spend ?? 0)) > 0
                    ? round((float) ($todaySales?->revenue ?? 0) / (float) $todayCampaign->ad_spend, 2)
                    : null,
            ],
            'week' => [
                'revenue' => $weekRevenue,
                'ad_spend' => $weekSpend,
                'messages' => $weekMessages,
                'orders' => $weekOrders,
                'roas' => $weekSpend > 0 ? round($weekRevenue / $weekSpend, 2) : null,
                'cost_per_message' => $weekMessages > 0 ? round($weekSpend / $weekMessages, 2) : null,
                'conversion_rate' => $weekMessages > 0 ? round(($weekOrders / $weekMessages) * 100, 2) : null,
                'cac' => $weekOrders > 0 ? round($weekSpend / $weekOrders, 2) : null,
                'aov' => $weekOrders > 0 ? round($weekRevenue / $weekOrders, 2) : null,
            ],
            'month' => [
                'revenue' => $monthRevenue,
                'ad_spend' => $monthSpend,
                'messages' => $monthMessages,
                'orders' => $monthOrders,
                'roas' => $monthSpend > 0 ? round($monthRevenue / $monthSpend, 2) : null,
                'conversion_rate' => $monthMessages > 0 ? round(($monthOrders / $monthMessages) * 100, 2) : null,
                'cac' => $monthOrders > 0 ? round($monthSpend / $monthOrders, 2) : null,
                'aov' => $monthOrders > 0 ? round($monthRevenue / $monthOrders, 2) : null,
            ],
        ];
    }

    private function buildProductAnalytics(Client $client): array
    {
        $from = now()->subDays(29)->toDateString();

        $products = $client->products()->where('is_active', true)->get(['id', 'name', 'unit_price']);
        $items = ClientDailySaleItem::query()
            ->whereIn('client_product_id', $products->pluck('id'))
            ->whereHas('dailySale', fn ($q) => $q->whereDate('sales_date', '>=', $from))
            ->get();

        $totalRevenue = (float) $items->sum('subtotal');
        $totalQty = (int) $items->sum('quantity');
        $monthSpend = (float) $client->campaignUpdates()
            ->whereDate('report_date', '>=', $from)
            ->sum('ad_spend');
        $monthMessages = (int) $client->campaignUpdates()
            ->whereDate('report_date', '>=', $from)
            ->sum('messages_count');

        return $products->map(function (ClientProduct $product) use ($items, $totalRevenue, $monthSpend, $monthMessages) {
            $productItems = $items->where('client_product_id', $product->id);
            $revenue = (float) $productItems->sum('subtotal');
            $qty = (int) $productItems->sum('quantity');
            $share = $totalRevenue > 0 ? ($revenue / $totalRevenue) : 0;
            $allocatedSpend = round($monthSpend * $share, 2);
            $allocatedMessages = (int) round($monthMessages * $share);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->unit_price,
                'sold_quantity_30d' => $qty,
                'revenue_30d' => $revenue,
                'revenue_share_percent' => round($share * 100, 2),
                'estimated_spend_30d' => $allocatedSpend,
                'estimated_messages_30d' => $allocatedMessages,
                'estimated_roas_30d' => $allocatedSpend > 0 ? round($revenue / $allocatedSpend, 2) : null,
                'estimated_cac_30d' => $qty > 0 ? round($allocatedSpend / $qty, 2) : null,
                'estimated_conversion_rate_30d' => $allocatedMessages > 0 ? round(($qty / $allocatedMessages) * 100, 2) : null,
            ];
        })->values()->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, Meeting> $busyMeetings
     * @return array<int, array<string, string>>
     */
    private function buildAvailableSlots(User $user, \Illuminate\Support\Collection $busyMeetings): array
    {
        $slots = [];

        for ($offset = 0; $offset <= 21; $offset++) {
            $date = now()->addDays($offset);
            $window = $this->dayWindow($user, (int) $date->dayOfWeek);
            if (!$window) {
                continue;
            }

            $cursor = Carbon::parse($date->format('Y-m-d') . ' ' . $window['start']);
            $endOfDay = Carbon::parse($date->format('Y-m-d') . ' ' . $window['end']);

            while ($cursor->copy()->addHour()->lte($endOfDay)) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addHour();
                $cursor->addHour();

                if ($slotStart->lt(now()->addMinutes(15))) {
                    continue;
                }

                $conflict = $busyMeetings->first(function (Meeting $meeting) use ($slotStart, $slotEnd) {
                    $meetingEnd = $meeting->end_at ? $meeting->end_at->copy() : $meeting->start_at->copy()->addHour();
                    return $meeting->start_at->lt($slotEnd) && $meetingEnd->gt($slotStart);
                });

                if ($conflict) {
                    continue;
                }

                $slots[] = [
                    'value' => $slotStart->toIso8601String(),
                    'label' => $slotStart->locale('ar')->translatedFormat('D j M - h:i A'),
                ];
            }

            if (count($slots) >= 30) {
                break;
            }
        }

        return $slots;
    }

    private function isWithinAvailability(User $user, Carbon $start, Carbon $end): bool
    {
        $window = $this->dayWindow($user, (int) $start->dayOfWeek);
        if (!$window) {
            return false;
        }

        $windowStart = Carbon::parse($start->format('Y-m-d') . ' ' . $window['start']);
        $windowEnd = Carbon::parse($start->format('Y-m-d') . ' ' . $window['end']);

        return $start->gte($windowStart) && $end->lte($windowEnd);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedSchedule(User $user): array
    {
        $raw = is_array($user->availability_schedule) ? $user->availability_schedule : [];
        $days = $user->availability_days ?: [0, 1, 2, 3, 4];
        $defaultStart = $user->availability_start_time ? substr($user->availability_start_time, 0, 5) : '09:00';
        $defaultEnd = $user->availability_end_time ? substr($user->availability_end_time, 0, 5) : '17:00';
        $schedule = [];

        for ($day = 0; $day <= 6; $day++) {
            $entry = Arr::get($raw, (string) $day, []);
            $enabled = array_key_exists('enabled', $entry)
                ? (bool) $entry['enabled']
                : in_array($day, $days, true);

            $schedule[] = [
                'day' => $day,
                'enabled' => $enabled,
                'start' => $enabled ? substr((string) ($entry['start'] ?? $defaultStart), 0, 5) : null,
                'end' => $enabled ? substr((string) ($entry['end'] ?? $defaultEnd), 0, 5) : null,
            ];
        }

        return $schedule;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private function dayWindow(User $user, int $day): ?array
    {
        $schedule = collect($this->normalizedSchedule($user));
        $entry = $schedule->first(fn (array $row) => (int) $row['day'] === $day && (bool) $row['enabled']);
        if (!$entry) {
            return null;
        }

        $start = $entry['start'] ?? null;
        $end = $entry['end'] ?? null;
        if (!$start || !$end || $start >= $end) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, Collection<int, Meeting>>
     */
    private function buildBusyByUser(array $userIds, Carbon $from, Carbon $to): array
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        $meetings = Meeting::query()
            ->with('participants:id')
            ->where('status', 'scheduled')
            ->where('start_at', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->where('end_at', '>=', $from)
                    ->orWhereNull('end_at');
            })
            ->where(function ($q) use ($ids) {
                $q->whereIn('user_id', $ids)
                    ->orWhereHas('participants', fn ($q2) => $q2->whereIn('users.id', $ids));
            })
            ->get();

        $busyByUser = [];
        foreach ($ids as $id) {
            $busyByUser[$id] = collect();
        }

        foreach ($meetings as $meeting) {
            $memberIds = collect([$meeting->user_id])
                ->merge($meeting->participants->pluck('id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique();

            foreach ($memberIds as $id) {
                if (isset($busyByUser[$id])) {
                    $busyByUser[$id]->push($meeting);
                }
            }
        }

        return $busyByUser;
    }
}
