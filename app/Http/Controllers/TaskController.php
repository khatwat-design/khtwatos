<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskStatusHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
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
                        ->with(['assignee:id,name', 'assignees:id,name', 'client:id,name'])
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
                    ]),
                ]),
            ] : null,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
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

        foreach ($columnDefaults as $col) {
            BoardColumn::query()->firstOrCreate(
                [
                    'task_board_id' => $board->id,
                    'name' => $col['name'],
                ],
                ['sort_order' => $col['sort_order']]
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Team>
     */
    private function ensureTeams()
    {
        $defaults = [
            ['name' => 'الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'الميديا باير', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'أكاونت', 'slug' => 'account', 'sort_order' => 30],
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

                    if ((int) $task->board_column_id !== (int) $column->id && $task->client_id) {
                        TaskStatusHistory::query()->create([
                            'task_id' => $task->id,
                            'client_id' => $task->client_id,
                            'from_column_id' => $task->board_column_id,
                            'to_column_id' => $column->id,
                            'from_column_name' => $columnNameMap[$task->board_column_id] ?? null,
                            'to_column_name' => $columnNameMap[$column->id] ?? $column->name,
                            'changed_by_id' => $request->user()?->id,
                        ]);
                    }

                    Task::query()->whereKey($taskId)->update([
                        'board_column_id' => $column->id,
                        'position' => $index,
                    ]);

                    $task->board_column_id = $column->id;
                }
            }
        });

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
}
