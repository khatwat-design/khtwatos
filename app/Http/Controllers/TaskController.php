<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskMessage;
use App\Models\TaskReassignment;
use App\Models\TaskStatusHistory;
use App\Models\Team;
use App\Models\User;
use App\Services\ClientWorkflowAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(private readonly ClientWorkflowAutomationService $workflowAutomation)
    {
    }

    public function index(Request $request): Response
    {
        $teams = $this->ensureTeams();
        $requestedSlug = $request->query('team');
        $team = $requestedSlug
            ? Team::where('slug', $requestedSlug)->first()
            : null;
        if (! $team) {
            $team = $teams->first();
        }

        $clientId = $request->filled('client_id') ? (int) $request->query('client_id') : null;

        $board = null;
        if ($team) {
            $this->ensureBoardStructure($team);
            $team->load([
                'taskBoard.columns' => fn ($q) => $q->orderBy('sort_order'),
                'taskBoard.columns.tasks' => function ($q) use ($clientId) {
                    $q->when($clientId, fn ($q2) => $q2->where('client_id', $clientId))
                        ->with([
                            'assignee:id,name',
                            'assignees:id,name',
                            'client:id,name',
                            'messages.user:id,name',
                            'reassignments.assignedBy:id,name',
                            'reassignments.assignedTo:id,name',
                            'checklistItems.createdBy:id,name',
                            'attachments.user:id,name',
                        ])
                        ->orderBy('position')
                        ->orderBy('id');
                },
            ]);
            $board = $team->taskBoard;
        }

        $filterClient = null;
        if ($clientId) {
            $filterClient = Client::query()->whereKey($clientId)->first(['id', 'name']);
        }

        return Inertia::render('Tasks/Index', [
            'teams' => $teams,
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ] : null,
            'board' => $board ? [
                'id' => $board->id,
                'name' => $board->name,
                'columns' => $board->columns->map(fn (BoardColumn $col) => [
                    'id' => $col->id,
                    'name' => $col->name,
                    'sort_order' => $col->sort_order,
                    'tasks' => $col->tasks->map(fn (Task $t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'description' => $t->description,
                        'position' => $t->position,
                        'due_at' => $t->due_at?->toIso8601String(),
                        'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
                        'assignees' => $t->assignees->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values(),
                        'client' => $t->client ? ['id' => $t->client->id, 'name' => $t->client->name] : null,
                        'messages' => $t->messages->map(fn (TaskMessage $message) => [
                            'id' => $message->id,
                            'body' => $message->body,
                            'created_at' => $message->created_at->toIso8601String(),
                            'user' => $message->user ? [
                                'id' => $message->user->id,
                                'name' => $message->user->name,
                            ] : null,
                        ])->values(),
                        'reassignments' => $t->reassignments->map(fn (TaskReassignment $row) => [
                            'id' => $row->id,
                            'due_at' => $row->due_at?->toIso8601String(),
                            'note' => $row->note,
                            'assigned_by' => $row->assignedBy ? [
                                'id' => $row->assignedBy->id,
                                'name' => $row->assignedBy->name,
                            ] : null,
                            'assigned_to' => $row->assignedTo ? [
                                'id' => $row->assignedTo->id,
                                'name' => $row->assignedTo->name,
                            ] : null,
                        ])->values(),
                        'checklist_items' => $t->checklistItems->map(fn (TaskChecklistItem $item) => [
                            'id' => $item->id,
                            'title' => $item->title,
                            'is_done' => (bool) $item->is_done,
                            'created_by' => $item->createdBy ? [
                                'id' => $item->createdBy->id,
                                'name' => $item->createdBy->name,
                            ] : null,
                        ])->values(),
                        'attachments' => $t->attachments->map(fn (TaskAttachment $attachment) => [
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
                    ]),
                ]),
            ] : null,
            'clients' => Client::query()
                ->whereDoesntHave('currentStage', fn ($q) => $q->where('key', 'lead'))
                ->orderByRaw('COALESCE(name, company, "")')
                ->get(['id', 'name', 'company']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'client_id' => $clientId,
            ],
            'filterClient' => $filterClient ? [
                'id' => $filterClient->id,
                'name' => $filterClient->name,
            ] : null,
        ]);
    }

    private function ensureBoardStructure(Team $team): void
    {
        $board = TaskBoard::query()->firstOrCreate(
            ['team_id' => $team->id],
            ['name' => 'لوحة '.$team->name]
        );

        $columnDefaults = [
            ['name' => 'قائمة الانتظار', 'sort_order' => 10],
            ['name' => 'قيد التنفيذ', 'sort_order' => 20],
            ['name' => 'مراجعة', 'sort_order' => 30],
            ['name' => 'تم', 'sort_order' => 40],
        ];

        DB::transaction(function () use ($board, $columnDefaults): void {
            $defaultNames = collect($columnDefaults)->pluck('name')->all();
            $columnIdsByName = [];

            foreach ($columnDefaults as $col) {
                $column = BoardColumn::query()->updateOrCreate(
                    [
                        'task_board_id' => $board->id,
                        'name' => $col['name'],
                    ],
                    ['sort_order' => $col['sort_order']]
                );
                $columnIdsByName[$col['name']] = $column->id;
            }

            $waitingColumnId = $columnIdsByName['قائمة الانتظار'] ?? null;

            $extraColumns = BoardColumn::query()
                ->where('task_board_id', $board->id)
                ->whereNotIn('name', $defaultNames)
                ->get();

            foreach ($extraColumns as $extraColumn) {
                if ($waitingColumnId) {
                    Task::query()
                        ->where('board_column_id', $extraColumn->id)
                        ->update(['board_column_id' => $waitingColumnId]);
                }
                $extraColumn->delete();
            }
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Team>
     */
    private function ensureTeams()
    {
        $defaults = [
            ['name' => 'فريق الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'مدراء الحملات', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'مدراء الحسابات', 'slug' => 'account', 'sort_order' => 30],
            ['name' => 'المبيعات', 'slug' => 'sales', 'sort_order' => 40],
            ['name' => 'الموارد البشرية', 'slug' => 'hr', 'sort_order' => 50],
            ['name' => 'المحاسبة', 'slug' => 'accounting', 'sort_order' => 60],
        ];

        foreach ($defaults as $team) {
            Team::query()->updateOrCreate(['slug' => $team['slug']], $team);
        }

        return Team::query()
            ->whereIn('slug', collect($defaults)->pluck('slug'))
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'assignee_id' => $request->filled('assignee_id') ? $request->input('assignee_id') : null,
            'assignee_ids' => $this->normalizeAssigneeIds($request->input('assignee_ids', [])),
        ]);

        $data = $request->validate([
            'board_column_id' => ['required', 'exists:board_columns,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
        ]);

        $column = BoardColumn::query()->with('taskBoard')->findOrFail($data['board_column_id']);
        $max = (int) Task::query()
            ->where('board_column_id', $column->id)
            ->max('position');

        $assigneeIds = $this->extractAssigneeIds($data);

        $task = Task::query()->create([
            'task_board_id' => $column->task_board_id,
            'board_column_id' => $column->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assignee_id' => $assigneeIds[0] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'position' => $max + 1,
        ]);

        $task->assignees()->sync($assigneeIds);

        return redirect()->back();
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $request->merge([
            'due_at' => $request->filled('due_at') ? $request->input('due_at') : null,
            'client_id' => $request->filled('client_id') ? $request->input('client_id') : null,
            'assignee_id' => $request->filled('assignee_id') ? $request->input('assignee_id') : null,
            'assignee_ids' => $this->normalizeAssigneeIds($request->input('assignee_ids', [])),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $assigneeIds = $this->extractAssigneeIds($data);
        $data['assignee_id'] = $assigneeIds[0] ?? null;
        unset($data['assignee_ids']);
        $task->update($data);
        $task->assignees()->sync($assigneeIds);

        return redirect()->back();
    }

    public function sync(Request $request, TaskBoard $taskBoard): RedirectResponse
    {
        $data = $request->validate([
            'columns' => ['required', 'array'],
            'columns.*.id' => ['required', 'exists:board_columns,id'],
            'columns.*.task_ids' => ['present', 'array'],
            'columns.*.task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        DB::transaction(function () use ($data, $taskBoard, $request): void {
            $columnNameMap = BoardColumn::query()->pluck('name', 'id');
            $taskIds = collect($data['columns'])
                ->flatMap(fn (array $col) => $col['task_ids'])
                ->unique()
                ->values();

            $tasksById = Task::query()
                ->whereIn('id', $taskIds)
                ->get()
                ->keyBy('id');

            foreach ($data['columns'] as $col) {
                $column = BoardColumn::query()->findOrFail($col['id']);
                if ((int) $column->task_board_id !== (int) $taskBoard->id) {
                    abort(422, 'Invalid column for this board.');
                }
                foreach ($col['task_ids'] as $index => $taskId) {
                    /** @var Task|null $task */
                    $task = $tasksById->get($taskId);
                    if (! $task) {
                        continue;
                    }

                    $fromColumnId = (int) $task->board_column_id;
                    $toColumnId = (int) $column->id;
                    $toColumnName = $columnNameMap[$column->id] ?? $column->name;
                    $didMoveColumn = $fromColumnId !== $toColumnId;

                    if ($didMoveColumn && $task->client_id) {
                        TaskStatusHistory::query()->create([
                            'task_id' => $task->id,
                            'client_id' => $task->client_id,
                            'from_column_id' => $fromColumnId,
                            'to_column_id' => $toColumnId,
                            'from_column_name' => $columnNameMap[$fromColumnId] ?? null,
                            'to_column_name' => $toColumnName,
                            'changed_by_id' => $request->user()?->id,
                        ]);
                    }

                    Task::query()->whereKey($taskId)->update([
                        'board_column_id' => $column->id,
                        'position' => $index,
                    ]);

                    $task->board_column_id = $column->id;

                    if ($didMoveColumn && (int) $task->client_id > 0 && $toColumnName === 'تم') {
                        $this->workflowAutomation->handleTaskMovedToDone($task, $request->user()?->id);
                    }
                }
            }
        });

        return redirect()->back();
    }

    public function addMessage(Request $request, Task $task)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = TaskMessage::query()->create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ])->load('user:id,name');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at->toIso8601String(),
                    'user' => $message->user ? [
                        'id' => $message->user->id,
                        'name' => $message->user->name,
                    ] : null,
                ],
            ]);
        }

        return redirect()->back();
    }

    public function addAttachment(Request $request, Task $task)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $data['file'];
        $path = $file->store('task-attachments', 'public');

        $attachment = TaskAttachment::query()->create([
            'task_id' => $task->id,
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

    public function deleteAttachment(Request $request, TaskAttachment $taskAttachment)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'لا تملك صلاحية حذف هذا المرفق.');
        }

        if ($taskAttachment->path) {
            Storage::disk('public')->delete($taskAttachment->path);
        }
        $taskAttachment->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->ensureAdmin($request);
        foreach ($task->attachments()->get(['path']) as $attachment) {
            if ($attachment->path) {
                Storage::disk('public')->delete($attachment->path);
            }
        }
        $task->delete();

        return redirect()->back();
    }

    public function addReassignment(Request $request, Task $task): RedirectResponse
    {
        $this->ensureCanManageTask($request, $task);

        $data = $request->validate([
            'assigned_to_id' => ['required', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $task->reassignments()->create([
            'assigned_by_id' => $request->user()->id,
            'assigned_to_id' => (int) $data['assigned_to_id'],
            'due_at' => $data['due_at'] ?? null,
            'note' => isset($data['note']) ? trim($data['note']) : null,
        ]);

        $assigneeIds = $task->assignees()->pluck('users.id')->all();
        $assigneeIds[] = (int) $data['assigned_to_id'];
        $assigneeIds = array_values(array_unique(array_map('intval', $assigneeIds)));

        $task->assignees()->sync($assigneeIds);
        $task->assignee_id = $assigneeIds[0] ?? $task->assignee_id;
        if (!empty($data['due_at'])) {
            $task->due_at = $data['due_at'];
        }
        $task->save();

        return redirect()->back();
    }

    public function addChecklistItem(Request $request, Task $task): RedirectResponse
    {
        $this->ensureCanManageTask($request, $task);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'titles' => ['nullable', 'array'],
            'titles.*' => ['string', 'max:500'],
        ]);

        $titles = collect($data['titles'] ?? []);
        if ($titles->isEmpty() && !empty($data['title'])) {
            $titles = collect([$data['title']]);
        }

        $normalized = $titles
            ->map(fn ($title) => trim((string) $title))
            ->filter(fn ($title) => $title !== '')
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return redirect()->back()->withErrors([
                'title' => 'أدخل عنصر checklist واحد على الأقل.',
            ]);
        }

        foreach ($normalized as $title) {
            $task->checklistItems()->create([
                'title' => $title,
                'created_by_id' => $request->user()->id,
            ]);
        }

        return redirect()->back();
    }

    public function toggleChecklistItem(Request $request, TaskChecklistItem $taskChecklistItem): RedirectResponse
    {
        $this->ensureCanManageTask($request, $taskChecklistItem->task);

        $data = $request->validate([
            'is_done' => ['required', 'boolean'],
        ]);

        $taskChecklistItem->update([
            'is_done' => (bool) $data['is_done'],
        ]);

        return redirect()->back();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeAssigneeIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, int>
     */
    private function extractAssigneeIds(array $data): array
    {
        $ids = $data['assignee_ids'] ?? [];
        if (!empty($ids)) {
            return $ids;
        }

        if (!empty($data['assignee_id'])) {
            return [(int) $data['assignee_id']];
        }

        return [];
    }

    private function ensureCanManageTask(Request $request, Task $task): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->role === 'lead') {
            return;
        }

        $teamId = $task->taskBoard?->team_id;
        if (!$teamId) {
            $task->loadMissing('taskBoard');
            $teamId = $task->taskBoard?->team_id;
        }

        if (!$teamId) {
            abort(403, 'لا يمكن إدارة هذه المهمة.');
        }

        $isLeadOfTeam = $user->teams()
            ->where('teams.id', $teamId)
            ->wherePivot('is_lead', true)
            ->exists();

        if (!$isLeadOfTeam) {
            abort(403, 'هذه العملية مسموحة لمدراء الأقسام فقط.');
        }
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'هذه العملية متاحة لمدير النظام فقط.');
        }
    }
}
