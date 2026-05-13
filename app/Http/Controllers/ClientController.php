<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\ClientCampaignUpdate;
use App\Models\ClientDailySale;
use App\Models\ClientMetaIntegration;
use App\Models\ClientProduct;
use App\Models\ClientStageHistory;
use App\Models\OutsideContact;
use App\Models\OutsideConversation;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use App\Services\CampaignDecisionEngineService;
use App\Services\ClientMetaConnectionService;
use App\Services\ClientWorkflowAutomationService;
use App\Services\SmartNotificationService;
use App\Support\SocialProfileUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientWorkflowAutomationService $workflowAutomation,
        private readonly SmartNotificationService $smartNotifications,
        private readonly ClientMetaConnectionService $clientMetaConnection,
        private readonly CampaignDecisionEngineService $campaignDecisionEngine,
    ) {}

    public function index(Request $request): Response
    {
        $stages = $this->ensurePipelineStages();
        $stageId = $request->filled('stage_id') ? (int) $request->query('stage_id') : null;

        $clients = Client::query()
            ->with(['currentStage', 'accountManager:id,name', 'campaignManager:id,name'])
            ->withCount([
                'tasks as open_tasks_count' => fn ($q) => $q->whereHas('column', fn ($c) => $c->where('name', '!=', 'تم')),
            ])
            ->when($stageId, fn ($q) => $q->where('current_pipeline_stage_id', $stageId))
            ->orderBy('name')
            ->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'company' => $c->company,
                'email' => $c->email,
                'logo_url' => $c->logo_path ? Storage::disk('public')->url($c->logo_path) : null,
                'current_stage' => $c->currentStage ? [
                    'key' => $c->currentStage->key,
                    'label' => $c->currentStage->label,
                ] : null,
                'account_manager' => $c->accountManager ? ['id' => $c->accountManager->id, 'name' => $c->accountManager->name] : null,
                'campaign_manager' => $c->campaignManager ? ['id' => $c->campaignManager->id, 'name' => $c->campaignManager->name] : null,
                'open_tasks_count' => (int) $c->open_tasks_count,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]),
            'stages' => $stages,
            'accountManagers' => User::orderBy('name')->get(['id', 'name']),
            'campaignManagers' => $this->campaignManagers(),
            'filters' => [
                'stage_id' => $stageId,
            ],
        ]);
    }

    public function create(): Response
    {
        $stages = $this->ensurePipelineStages();

        return Inertia::render('Clients/Create', [
            'stages' => $stages,
            'accountManagers' => User::orderBy('name')->get(['id', 'name']),
            'campaignManagers' => $this->campaignManagers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedClient($request);
        $stageId = $data['current_pipeline_stage_id'];

        $client = DB::transaction(function () use ($data, $stageId, $request) {
            $client = Client::query()->create([
                ...$data,
                'portal_token' => Str::random(48),
            ]);
            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $stageId,
                'user_id' => $request->user()->id,
                'note' => 'Created',
            ]);

            return $client;
        });

        if (Schema::hasTable('client_meta_integrations')) {
            $this->syncClientMetaPageFields(
                $client->id,
                isset($data['facebook_page']) ? trim((string) $data['facebook_page']) : '',
                isset($data['instagram_page']) ? trim((string) $data['instagram_page']) : '',
            );
        }

        $initialStage = PipelineStage::query()->find($stageId);
        if ($initialStage) {
            $this->workflowAutomation->handleClientStageEntered($client->fresh(), $initialStage->key, $request->user()?->id);
        }

        return redirect()->route('clients.show', $client);
    }

    public function show(Request $request, Client $client): Response
    {
        $client->load([
            'currentStage',
            'accountManager:id,name',
            'campaignManager:id,name',
            'stageHistories.stage',
            'stageHistories.user:id,name',
            'tasks.assignee:id,name',
            'tasks.assignees:id,name',
            'tasks.column:id,name',
            'meetings' => fn ($q) => $q->with('host:id,name')->orderByDesc('start_at')->limit(20),
            'attachments.user:id,name',
            'dailySales.submittedBy:id,name',
            'dailySales.items',
            'campaignUpdates.updatedBy:id,name',
            'products',
            'portalNotes' => fn ($q) => $q->where('expires_at', '>', now())->orderByDesc('created_at')->limit(12),
        ]);

        $tasksByTeam = Task::query()
            ->where('client_id', $client->id)
            ->with(['assignee:id,name', 'assignees:id,name', 'taskBoard.team:id,name'])
            ->get()
            ->groupBy(fn (Task $t) => $t->taskBoard?->team?->name ?? 'General');

        $metrics = [
            'open_tasks' => Task::query()->where('client_id', $client->id)->whereHas('column', fn ($q) => $q->where('name', '!=', 'تم'))->count(),
            'upcoming_meetings' => $client->meetings()->where('start_at', '>=', now())->where('status', 'scheduled')->count(),
            'days_since_update' => (int) $client->updated_at->diffInDays(now()),
        ];

        $metaIntegration = null;
        if (Schema::hasTable('client_meta_integrations')) {
            $metaIntegration = ClientMetaIntegration::query()
                ->where('client_id', $client->id)
                ->first();
        }

        $outsideThreadsPayload = [
            'whatsapp' => null,
            'instagram' => null,
            'messenger' => null,
        ];
        if (Schema::hasTable('outside_contacts') && Schema::hasTable('outside_conversations')) {
            $outsideContacts = OutsideContact::query()
                ->where('client_id', $client->id)
                ->orderByDesc('last_message_at')
                ->get(['id', 'channel', 'last_message_at']);

            foreach ($outsideContacts as $contact) {
                $channel = match ($contact->channel) {
                    'instagram' => 'instagram',
                    'messenger' => 'messenger',
                    default => 'whatsapp',
                };
                if ($outsideThreadsPayload[$channel] !== null) {
                    continue;
                }
                $conversationId = OutsideConversation::query()
                    ->where('outside_contact_id', $contact->id)
                    ->value('id');

                $outsideThreadsPayload[$channel] = [
                    'conversation_id' => $conversationId ? (int) $conversationId : null,
                    'contact_id' => (int) $contact->id,
                    'last_message_at' => $contact->last_message_at?->toIso8601String(),
                ];
            }
        }

        $campaignDecisionPayload = $this->campaignDecisionEngine->buildPayload(
            $client,
            $client->campaignUpdates,
            $client->dailySales,
        );

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'notes' => $client->notes,
                'logo_url' => $client->logo_path ? Storage::disk('public')->url($client->logo_path) : null,
                'current_stage' => $client->currentStage ? [
                    'id' => $client->currentStage->id,
                    'key' => $client->currentStage->key,
                    'label' => $client->currentStage->label,
                ] : null,
                'account_manager' => $client->accountManager ? ['id' => $client->accountManager->id, 'name' => $client->accountManager->name] : null,
                'campaign_manager' => $client->campaignManager ? ['id' => $client->campaignManager->id, 'name' => $client->campaignManager->name] : null,
                'updated_at' => $client->updated_at->toIso8601String(),
                'subscription' => $this->clientSubscriptionPayload($client),
            ],
            'history' => $client->stageHistories->map(fn (ClientStageHistory $h) => [
                'id' => $h->id,
                'stage' => $h->stage->label,
                'note' => $h->note,
                'user' => $h->user?->name,
                'at' => $h->created_at->toIso8601String(),
            ]),
            'tasks' => $client->tasks->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'column' => $t->column?->name,
                'assignee' => $t->assignee?->name,
                'assignees' => $t->assignees->pluck('name')->values(),
            ]),
            'tasks_by_team' => $tasksByTeam->map(fn ($group, $team) => [
                'team' => $team,
                'tasks' => $group->map(fn (Task $t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'assignee' => $t->assignee?->name,
                    'assignees' => $t->assignees->pluck('name')->values(),
                    'column' => $t->column?->name,
                    'description' => $t->description,
                    'team' => $t->taskBoard?->team?->name,
                ])->values(),
            ])->values(),
            'meetings' => $client->meetings->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'start_at' => $m->start_at->toIso8601String(),
                'invitee_name' => $m->invitee_name,
                'reason' => $m->reason,
                'source' => $m->source,
                'host' => $m->host?->name,
            ]),
            'attachments' => $client->attachments->map(fn (ClientAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
                'url' => $this->attachmentPublicUrl($attachment),
                'is_link' => $this->isReferenceLinkAttachment($attachment),
                'is_image' => is_string($attachment->mime) && str_starts_with($attachment->mime, 'image/'),
                'uploaded_by' => $attachment->user ? [
                    'id' => $attachment->user->id,
                    'name' => $attachment->user->name,
                ] : null,
                'created_at' => $attachment->created_at->toIso8601String(),
            ])->values(),
            'task_status_history' => TaskStatusHistory::query()
                ->where('client_id', $client->id)
                ->with(['task:id,title', 'changedBy:id,name'])
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (TaskStatusHistory $row) => [
                    'id' => $row->id,
                    'task_title' => $row->task?->title ?? 'مهمة',
                    'from' => $row->from_column_name,
                    'to' => $row->to_column_name,
                    'by' => $row->changedBy?->name,
                    'at' => $row->created_at->toIso8601String(),
                ]),
            'stages' => PipelineStage::orderBy('sort_order')->get(['id', 'key', 'label']),
            'metrics' => $metrics,
            'portal' => $this->canViewPortalLink($request->user(), $client) ? [
                'url' => route('portal.login'),
                'username' => $client->portal_username,
                'has_credentials' => ! empty($client->portal_username) && ! empty($client->portal_password),
            ] : null,
            'daily_sales' => $client->dailySales
                ->take(15)
                ->map(fn (ClientDailySale $row) => [
                    'id' => $row->id,
                    'sales_date' => $row->sales_date?->toDateString(),
                    'orders_count' => $row->orders_count,
                    'revenue' => (float) $row->revenue,
                    'notes' => $row->notes,
                    'source' => $row->source,
                    'submitted_by' => $row->submittedBy?->name ?: $row->submitted_by_name,
                    'items' => $row->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'unit_price' => (float) $item->unit_price,
                        'quantity' => $item->quantity,
                        'subtotal' => (float) $item->subtotal,
                    ])->values(),
                ])->values(),
            'campaign_updates' => $client->campaignUpdates
                ->take(15)
                ->map(fn (ClientCampaignUpdate $row) => [
                    'id' => $row->id,
                    'report_date' => $row->report_date?->toDateString(),
                    'ad_spend' => (float) $row->ad_spend,
                    'messages_count' => $row->messages_count,
                    'clicks_count' => $row->clicks_count,
                    'leads_count' => $row->leads_count,
                    'purchases_count' => $row->purchases_count,
                    'campaign_revenue' => $row->campaign_revenue !== null ? (float) $row->campaign_revenue : null,
                    'roas' => $row->roas !== null ? (float) $row->roas : null,
                    'cpa' => $row->cpa !== null ? (float) $row->cpa : null,
                    'cvr' => $row->cvr !== null ? (float) $row->cvr : null,
                    'summary' => $row->summary,
                    'actions_taken' => $row->actions_taken,
                    'updated_by' => $row->updatedBy?->name,
                ])->values(),
            'products' => $client->products->map(fn (ClientProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => (float) $product->unit_price,
                'stock_quantity' => $product->stock_quantity,
                'details' => $product->details,
                'is_active' => (bool) $product->is_active,
            ])->values(),
            'portal_notes' => $client->portalNotes->map(fn ($note) => [
                'id' => $note->id,
                'note' => $note->note,
                'expires_at' => $note->expires_at?->toIso8601String(),
                'created_at' => $note->created_at?->toIso8601String(),
            ])->values(),
            'meta_profile' => array_merge(
                [
                    'facebook_page' => $metaIntegration?->meta_page_id,
                    'instagram_page' => $metaIntegration?->meta_instagram_account_id,
                    'facebook_page_url' => SocialProfileUrl::facebook($metaIntegration?->meta_page_id),
                    'instagram_page_url' => SocialProfileUrl::instagram($metaIntegration?->meta_instagram_account_id),
                    'ad_account_id' => $metaIntegration?->ad_account_id,
                    'outside_instagram_threads' => Schema::hasTable('outside_contacts')
                        ? OutsideContact::query()
                            ->where('client_id', $client->id)
                            ->where('channel', 'instagram')
                            ->count()
                        : 0,
                    'outside_messenger_threads' => Schema::hasTable('outside_contacts')
                        ? OutsideContact::query()
                            ->where('client_id', $client->id)
                            ->where('channel', 'messenger')
                            ->count()
                        : 0,
                ],
                $this->clientMetaConnection->connectionSummary($client),
            ),
            'outside_threads' => $outsideThreadsPayload,
            'accountManagers' => User::orderBy('name')->get(['id', 'name']),
            'campaignManagers' => $this->campaignManagers(),
            'can_manage_subscription' => $this->canManageClientSubscription($request->user()),
            'campaign_decision_engine' => $campaignDecisionPayload,
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'campaign_manager_id' => ['nullable', 'exists:users,id'],
            'facebook_page' => ['nullable', 'string', 'max:2000'],
            'instagram_page' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $client->update([
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'account_manager_id' => $data['account_manager_id'] ?? null,
            'campaign_manager_id' => $data['campaign_manager_id'] ?? null,
        ]);

        $oldLogoPath = $client->logo_path;
        if (($data['remove_logo'] ?? false) && $client->logo_path) {
            Storage::disk('public')->delete($client->logo_path);
            $client->logo_path = null;
        }
        if ($request->hasFile('logo')) {
            $newPath = $request->file('logo')->store('client-logos', 'public');
            $client->logo_path = $newPath;
            if ($oldLogoPath && $oldLogoPath !== $newPath) {
                Storage::disk('public')->delete($oldLogoPath);
            }
        }
        $client->save();

        if (Schema::hasTable('client_meta_integrations')) {
            $this->syncClientMetaPageFields(
                $client->id,
                isset($data['facebook_page']) ? trim((string) $data['facebook_page']) : '',
                isset($data['instagram_page']) ? trim((string) $data['instagram_page']) : '',
            );
        }

        return redirect()->route('clients.show', $client);
    }

    public function updateStage(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'current_pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ((int) $client->current_pipeline_stage_id === (int) $data['current_pipeline_stage_id']) {
            return redirect()->route('clients.show', $client);
        }

        $targetStage = PipelineStage::query()->findOrFail($data['current_pipeline_stage_id']);
        $previousStageKey = $client->currentStage?->key;

        DB::transaction(function () use ($client, $data, $request): void {
            $client->update(['current_pipeline_stage_id' => $data['current_pipeline_stage_id']]);
            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $data['current_pipeline_stage_id'],
                'user_id' => $request->user()->id,
                'note' => $data['note'] ?? null,
            ]);
        });

        if ($previousStageKey !== $targetStage->key) {
            $this->workflowAutomation->handleClientStageEntered($client->fresh(), $targetStage->key, $request->user()?->id);
            $this->smartNotifications->notifyClientStageChanged($client->fresh(), $targetStage->label, $request->user()?->id);
        }

        return redirect()->route('clients.show', $client);
    }

    public function activateSubscription(Request $request, Client $client): RedirectResponse
    {
        if (! $this->canManageClientSubscription($request->user())) {
            abort(403, 'هذه العملية متاحة للمحاسب أو مدير النظام.');
        }
        if (! $this->hasClientSubscriptionColumns()) {
            throw ValidationException::withMessages([
                'subscription' => 'يرجى تشغيل migrate أولاً لتفعيل الاشتراك.',
            ]);
        }

        $startAt = now();
        $base = $client->subscription_ends_at && $client->subscription_ends_at->isFuture()
            ? $client->subscription_ends_at->copy()
            : $startAt->copy();
        $endAt = $base->addDays(30);

        $client->update([
            'subscription_started_at' => $startAt,
            'subscription_ends_at' => $endAt,
            'subscription_activated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'تم تفعيل الاشتراك لمدة 30 يوم بنجاح.');
    }

    public function updateSubscription(Request $request, Client $client): RedirectResponse
    {
        if (! $this->canManageClientSubscription($request->user())) {
            abort(403, 'هذه العملية متاحة للمحاسب أو مدير النظام.');
        }
        if (! $this->hasClientSubscriptionColumns()) {
            throw ValidationException::withMessages([
                'subscription' => 'يرجى تشغيل migrate أولاً لتفعيل الاشتراك.',
            ]);
        }

        $data = $request->validate([
            'subscription_started_at' => ['required', 'date'],
            'subscription_ends_at' => ['required', 'date', 'after:subscription_started_at'],
        ]);

        $client->update([
            'subscription_started_at' => $data['subscription_started_at'],
            'subscription_ends_at' => $data['subscription_ends_at'],
            'subscription_activated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'تم تحديث تواريخ الاشتراك بنجاح.');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'هذه العملية متاحة لمدير النظام فقط.');
        }

        foreach ($client->attachments()->get(['path']) as $attachment) {
            if ($attachment->path) {
                Storage::disk('public')->delete($attachment->path);
            }
        }
        $client->delete();

        return redirect()->route('clients.index');
    }

    public function addAttachment(Request $request, Client $client)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $data['file'];
        $path = $file->store('client-attachments', 'public');

        $payload = [
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
        if ($this->hasAttachmentUrlColumn()) {
            $payload['url'] = null;
        }

        $attachment = ClientAttachment::query()->create($payload)->load('user:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'attachment' => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'url' => $this->attachmentPublicUrl($attachment),
                    'is_link' => $this->isReferenceLinkAttachment($attachment),
                    'is_image' => is_string($attachment->mime) && str_starts_with($attachment->mime, 'image/'),
                    'uploaded_by' => $attachment->user ? [
                        'id' => $attachment->user->id,
                        'name' => $attachment->user->name,
                    ] : null,
                    'created_at' => $attachment->created_at->toIso8601String(),
                ],
            ]);
        }

        return redirect()->back();
    }

    public function addReferenceLink(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2000'],
        ]);

        $url = trim((string) $data['url']);
        $title = trim((string) ($data['title'] ?? ''));
        $host = parse_url($url, PHP_URL_HOST);
        $name = $title !== '' ? $title : ($host ?: $url);

        $payload = [
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'path' => $this->hasAttachmentUrlColumn() ? '' : $url,
            'name' => $name,
            'mime' => 'link/url',
            'size' => null,
        ];
        if ($this->hasAttachmentUrlColumn()) {
            $payload['url'] = $url;
        }

        ClientAttachment::query()->create($payload);

        return redirect()->back();
    }

    public function deleteAttachment(Request $request, ClientAttachment $clientAttachment): RedirectResponse
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && (int) $clientAttachment->user_id !== (int) $user->id)) {
            abort(403, 'لا تملك صلاحية حذف هذا المرفق.');
        }

        if (
            $clientAttachment->path
            && ! $this->isReferenceLinkAttachment($clientAttachment)
            && ! str_starts_with((string) $clientAttachment->path, 'http')
        ) {
            Storage::disk('public')->delete($clientAttachment->path);
        }
        $clientAttachment->delete();

        return redirect()->back();
    }

    public function storeProduct(Request $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        $isAssignedAccountManager = (int) $client->account_manager_id === (int) $user?->id;
        if (! $user || (! $user->isAdmin() && ! $isAssignedAccountManager && ! Gate::forUser($user)->allows('view-client-portal-link'))) {
            abort(403, 'لا تملك صلاحية إدارة منتجات هذا العميل.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'details' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ClientProduct::query()->create([
            'client_id' => $client->id,
            'name' => $data['name'],
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'details' => $data['details'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);

        return redirect()->back();
    }

    public function updatePortalCredentials(Request $request, Client $client): RedirectResponse
    {
        $user = $request->user();
        if (! $this->canViewPortalLink($user, $client)) {
            abort(403, 'لا تملك صلاحية إدارة دخول بوابة العميل.');
        }

        $data = $request->validate([
            'portal_username' => [
                'required',
                'string',
                'max:120',
                Rule::unique('clients', 'portal_username')->ignore($client->id),
            ],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        if (empty($client->portal_password) && empty($data['portal_password'])) {
            throw ValidationException::withMessages([
                'portal_password' => 'أدخل كلمة سر للبوابة.',
            ]);
        }

        $payload = [
            'portal_username' => $data['portal_username'],
        ];

        if (! empty($data['portal_password'])) {
            $payload['portal_password'] = Hash::make($data['portal_password']);
        }

        $client->update($payload);

        return redirect()->back();
    }

    public function destroyProduct(Request $request, ClientProduct $clientProduct): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $isAssignedAccountManager = (int) $clientProduct->client?->account_manager_id === (int) $user->id;
        if (! $user->isAdmin() && ! $isAssignedAccountManager) {
            abort(403, 'لا تملك صلاحية حذف المنتج.');
        }

        $clientProduct->delete();

        return redirect()->back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedClient(Request $request): array
    {
        $this->ensurePipelineStages();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'account_manager_id' => ['nullable', 'exists:users,id'],
            'campaign_manager_id' => ['nullable', 'exists:users,id'],
            'current_pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'facebook_page' => ['nullable', 'string', 'max:2000'],
            'instagram_page' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function syncClientMetaPageFields(int $clientId, string $facebookPage, string $instagramPage): void
    {
        $pageId = $facebookPage === '' ? null : $facebookPage;
        $igId = $instagramPage === '' ? null : ltrim($instagramPage, '@');

        $existing = ClientMetaIntegration::query()->where('client_id', $clientId)->first();

        if ($pageId === null && $igId === null && ! $existing) {
            return;
        }

        ClientMetaIntegration::query()->updateOrCreate(
            ['client_id' => $clientId],
            [
                'meta_page_id' => $pageId,
                'meta_instagram_account_id' => $igId,
                'is_active' => true,
            ]
        );
    }

    private function campaignManagers()
    {
        return User::query()
            ->whereHas('teams', fn ($q) => $q->where('slug', 'media-buyer'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, PipelineStage>
     */
    private function ensurePipelineStages()
    {
        $defaults = [
            ['key' => 'lead', 'label' => 'عميل محتمل', 'sort_order' => 10],
            ['key' => 'sales_meeting', 'label' => 'اجتماع مبيعات', 'sort_order' => 20],
            ['key' => 'brief_meeting', 'label' => 'اجتماع البريف', 'sort_order' => 30],
            ['key' => 'analysis', 'label' => 'تحليل', 'sort_order' => 40],
            ['key' => 'analysis_delivered', 'label' => 'تم تسليم التحليل', 'sort_order' => 50],
            ['key' => 'strategy', 'label' => 'الاستراتيجية', 'sort_order' => 60],
            ['key' => 'strategy_delivered', 'label' => 'تم تسليم الاستراتيجية', 'sort_order' => 70],
            ['key' => 'payment', 'label' => 'دفع', 'sort_order' => 80],
            ['key' => 'content_production', 'label' => 'كتابة المحتوى الاعلاني', 'sort_order' => 90],
            ['key' => 'content_delivered', 'label' => 'تم تسليم المحتوى الاعلاني', 'sort_order' => 100],
            ['key' => 'campaign_launch', 'label' => 'إطلاق الحملات الاعلانية', 'sort_order' => 110],
            ['key' => 'campaign_launched', 'label' => 'تم اطلاق الحملات الاعلانية', 'sort_order' => 120],
            ['key' => 'campaign_analysis', 'label' => 'تحليل الحملة الاعلانية', 'sort_order' => 130],
            ['key' => 'optimization', 'label' => 'التحسين', 'sort_order' => 140],
            ['key' => 'paused', 'label' => 'متوقف', 'sort_order' => 150],
        ];

        foreach ($defaults as $stage) {
            PipelineStage::query()->updateOrCreate(['key' => $stage['key']], $stage);
        }

        return PipelineStage::query()
            ->whereIn('key', collect($defaults)->pluck('key'))
            ->orderBy('sort_order')
            ->get(['id', 'key', 'label']);
    }

    private function canViewPortalLink(?User $user, Client $client): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) $client->account_manager_id === (int) $user->id) {
            return true;
        }

        return Gate::forUser($user)->allows('view-client-portal-link');
    }

    private function canManageClientSubscription(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->teams()->where('slug', 'accounting')->exists();
    }

    private function hasClientSubscriptionColumns(): bool
    {
        return Schema::hasColumns('clients', [
            'subscription_started_at',
            'subscription_ends_at',
            'subscription_activated_by',
        ]);
    }

    private function clientSubscriptionPayload(Client $client): array
    {
        if (! $this->hasClientSubscriptionColumns()) {
            return [
                'is_active' => false,
                'started_at' => null,
                'ends_at' => null,
            ];
        }

        return [
            'is_active' => $client->subscription_ends_at ? $client->subscription_ends_at->isFuture() : false,
            'started_at' => $client->subscription_started_at?->toIso8601String(),
            'ends_at' => $client->subscription_ends_at?->toIso8601String(),
        ];
    }

    private function hasAttachmentUrlColumn(): bool
    {
        return Schema::hasColumn('client_attachments', 'url');
    }

    private function isReferenceLinkAttachment(ClientAttachment $attachment): bool
    {
        if ($this->hasAttachmentUrlColumn()) {
            return ! empty($attachment->url) || $attachment->mime === 'link/url';
        }

        return $attachment->mime === 'link/url' || str_starts_with((string) $attachment->path, 'http');
    }

    private function attachmentPublicUrl(ClientAttachment $attachment): string
    {
        if ($this->hasAttachmentUrlColumn() && ! empty($attachment->url)) {
            return (string) $attachment->url;
        }
        if ($this->isReferenceLinkAttachment($attachment)) {
            return (string) $attachment->path;
        }

        return Storage::disk('public')->url($attachment->path);
    }
}
