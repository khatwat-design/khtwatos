<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAttachment;
use App\Models\ClientStageHistory;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $stages = $this->ensurePipelineStages();
        $stageId = $request->filled('stage_id') ? (int) $request->query('stage_id') : null;

        $clients = Client::query()
            ->with(['currentStage', 'accountManager:id,name'])
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
                'current_stage' => $c->currentStage ? [
                    'key' => $c->currentStage->key,
                    'label' => $c->currentStage->label,
                ] : null,
                'account_manager' => $c->accountManager ? ['id' => $c->accountManager->id, 'name' => $c->accountManager->name] : null,
                'open_tasks_count' => (int) $c->open_tasks_count,
                'updated_at' => $c->updated_at->toIso8601String(),
            ]),
            'stages' => $stages,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedClient($request);
        $stageId = $data['current_pipeline_stage_id'];

        $client = DB::transaction(function () use ($data, $stageId, $request) {
            $client = Client::query()->create($data);
            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $stageId,
                'user_id' => $request->user()->id,
                'note' => 'Created',
            ]);

            return $client;
        });

        return redirect()->route('clients.show', $client);
    }

    public function show(Client $client): Response
    {
        $client->load([
            'currentStage',
            'accountManager:id,name',
            'stageHistories.stage',
            'stageHistories.user:id,name',
            'tasks.assignee:id,name',
            'tasks.assignees:id,name',
            'tasks.column:id,name',
            'meetings' => fn ($q) => $q->with('host:id,name')->orderByDesc('start_at')->limit(20),
            'attachments.user:id,name',
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

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'notes' => $client->notes,
                'current_stage' => $client->currentStage ? [
                    'id' => $client->currentStage->id,
                    'key' => $client->currentStage->key,
                    'label' => $client->currentStage->label,
                ] : null,
                'account_manager' => $client->accountManager ? ['id' => $client->accountManager->id, 'name' => $client->accountManager->name] : null,
                'updated_at' => $client->updated_at->toIso8601String(),
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
                'url' => Storage::disk('public')->url($attachment->path),
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
            'accountManagers' => User::orderBy('name')->get(['id', 'name']),
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
        ]);

        $client->update($data);

        return redirect()->route('clients.show', $client);
    }

    public function updateStage(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'current_pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($client, $data, $request): void {
            $client->update(['current_pipeline_stage_id' => $data['current_pipeline_stage_id']]);
            ClientStageHistory::query()->create([
                'client_id' => $client->id,
                'pipeline_stage_id' => $data['current_pipeline_stage_id'],
                'user_id' => $request->user()->id,
                'note' => $data['note'] ?? null,
            ]);
        });

        return redirect()->route('clients.show', $client);
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        if (!$request->user()?->isAdmin()) {
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

        $attachment = ClientAttachment::query()->create([
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ])->load('user:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'attachment' => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'mime' => $attachment->mime,
                    'size' => $attachment->size,
                    'url' => Storage::disk('public')->url($attachment->path),
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

    public function deleteAttachment(Request $request, ClientAttachment $clientAttachment): RedirectResponse
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && (int) $clientAttachment->user_id !== (int) $user->id)) {
            abort(403, 'لا تملك صلاحية حذف هذا المرفق.');
        }

        if ($clientAttachment->path) {
            Storage::disk('public')->delete($clientAttachment->path);
        }
        $clientAttachment->delete();

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
            'current_pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, PipelineStage>
     */
    private function ensurePipelineStages()
    {
        $defaults = [
            ['key' => 'lead', 'label' => 'عميل محتمل', 'sort_order' => 10],
            ['key' => 'sales_meeting', 'label' => 'اجتماع مبيعات', 'sort_order' => 20],
            ['key' => 'brief_meeting', 'label' => 'اجتماع البريف', 'sort_order' => 30],
            ['key' => 'analysis', 'label' => 'تحليل', 'sort_order' => 40],
            ['key' => 'analysis_delivered', 'label' => 'تم تسليم التحليل', 'sort_order' => 50],
            ['key' => 'payment', 'label' => 'دفع', 'sort_order' => 60],
            ['key' => 'content_production', 'label' => 'إنتاج المحتوى', 'sort_order' => 70],
            ['key' => 'campaign_launch', 'label' => 'إطلاق الحملة', 'sort_order' => 80],
            ['key' => 'optimization', 'label' => 'تحسين', 'sort_order' => 90],
        ];

        foreach ($defaults as $stage) {
            PipelineStage::query()->updateOrCreate(['key' => $stage['key']], $stage);
        }

        return PipelineStage::query()
            ->whereIn('key', collect($defaults)->pluck('key'))
            ->orderBy('sort_order')
            ->get(['id', 'key', 'label']);
    }
}
