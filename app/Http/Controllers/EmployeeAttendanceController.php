<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Services\EmployeePresenceService;
use App\Services\SmartNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeAttendanceController extends Controller
{
    public function __construct(
        private readonly EmployeePresenceService $presence,
        private readonly SmartNotificationService $smartNotifications,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $attendance = $this->presence->attendanceForToday($user);

        return response()->json([
            'needs_check_in' => $this->presence->needsDailyCheckIn($user),
            'attendance' => $attendance ? [
                'work_date' => $attendance->work_date?->toDateString(),
                'status' => $attendance->status,
                'mood' => $attendance->mood,
                'plan_for_today' => $attendance->plan_for_today,
                'note' => $attendance->note,
                'checked_in_at' => $attendance->checked_in_at?->toIso8601String(),
                'checked_out_at' => $attendance->checked_out_at?->toIso8601String(),
                'active_seconds' => (int) $attendance->active_seconds,
            ] : null,
            'open_tasks' => $this->openTasksForUser((int) $user->id),
        ]);
    }

    public function check(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'mood' => ['nullable', 'in:great,good,neutral,tired,low'],
            'note' => ['nullable', 'string', 'max:480'],
            'selected_task_ids' => ['nullable', 'array'],
            'selected_task_ids.*' => ['integer', 'exists:tasks,id'],
        ]);

        $selectedTaskIds = collect($data['selected_task_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $planSummary = $this->buildPlanSummaryFromTasks($selectedTaskIds);

        $attendance = $this->presence->checkIn($user, [
            'mood' => $data['mood'] ?? null,
            'note' => $data['note'] ?? null,
            'plan_for_today' => $planSummary,
        ]);

        $movedTasks = $this->moveTasksToInProgress($selectedTaskIds, (int) $user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'attendance' => [
                    'work_date' => $attendance->work_date?->toDateString(),
                    'status' => $attendance->status,
                    'mood' => $attendance->mood,
                    'plan_for_today' => $attendance->plan_for_today,
                    'note' => $attendance->note,
                    'checked_in_at' => $attendance->checked_in_at?->toIso8601String(),
                    'active_seconds' => (int) $attendance->active_seconds,
                ],
                'moved_tasks_count' => $movedTasks,
            ]);
        }

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openTasksForUser(int $userId): array
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('board_columns')) {
            return [];
        }

        $doneColumnNames = ['تم', 'مكتمل', 'منجز', 'Done', 'Completed'];
        $doneColumnIds = BoardColumn::query()->whereIn('name', $doneColumnNames)->pluck('id')->all();
        $inProgressId = BoardColumn::query()->where('name', 'قيد التنفيذ')->value('id');

        $query = Task::query()
            ->select('tasks.*')
            ->with(['column:id,name', 'client:id,name', 'taskBoard:id,name'])
            ->leftJoin('task_assignees', 'task_assignees.task_id', '=', 'tasks.id')
            ->where(function ($q) use ($userId) {
                $q->where('tasks.assignee_id', $userId)->orWhere('task_assignees.user_id', $userId);
            })
            ->when(Schema::hasColumn('tasks', 'archived_at'), fn ($q) => $q->whereNull('tasks.archived_at'))
            ->when(! empty($doneColumnIds), fn ($q) => $q->whereNotIn('tasks.board_column_id', $doneColumnIds))
            ->distinct()
            ->orderByRaw('CASE WHEN tasks.due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tasks.due_at')
            ->limit(40);

        return $query->get()->map(function (Task $task) use ($inProgressId) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'column_name' => $task->column?->name,
                'is_in_progress' => $inProgressId && (int) $task->board_column_id === (int) $inProgressId,
                'due_at' => $task->due_at?->toIso8601String(),
                'is_overdue' => $task->due_at !== null && $task->due_at->isPast(),
                'client_name' => $task->client?->name,
                'board_name' => $task->taskBoard?->name,
            ];
        })->values()->all();
    }

    /**
     * يبني خلاصة نصية لعرضها في حقل "خطة اليوم" بناءً على المهام المختارة.
     *
     * @param  list<int>  $taskIds
     */
    private function buildPlanSummaryFromTasks(array $taskIds): ?string
    {
        if (empty($taskIds)) {
            return null;
        }

        $titles = Task::query()->whereIn('id', $taskIds)->pluck('title')->all();
        if (empty($titles)) {
            return null;
        }

        $line = collect($titles)
            ->map(fn ($t) => '• '.mb_substr((string) $t, 0, 120))
            ->take(8)
            ->implode("\n");

        return mb_substr($line, 0, 480);
    }

    /**
     * ينقل المهام المختارة إلى عمود "قيد التنفيذ" ضمن لوحاتها.
     *
     * @param  list<int>  $taskIds
     */
    private function moveTasksToInProgress(array $taskIds, int $actorId): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $moved = 0;

        $tasks = Task::query()
            ->whereIn('id', $taskIds)
            ->with('column:id,name,task_board_id')
            ->get();

        foreach ($tasks as $task) {
            if (! $task->task_board_id) {
                continue;
            }

            $inProgress = BoardColumn::query()
                ->where('task_board_id', $task->task_board_id)
                ->where('name', 'قيد التنفيذ')
                ->first();

            if (! $inProgress) {
                continue;
            }

            if ((int) $task->board_column_id === (int) $inProgress->id) {
                continue;
            }

            $fromColumnId = (int) $task->board_column_id;
            $fromColumnName = $task->column?->name;

            DB::transaction(function () use ($task, $inProgress, $fromColumnId, $fromColumnName, $actorId): void {
                if ($task->client_id) {
                    TaskStatusHistory::query()->create([
                        'task_id' => $task->id,
                        'client_id' => $task->client_id,
                        'from_column_id' => $fromColumnId,
                        'to_column_id' => $inProgress->id,
                        'from_column_name' => $fromColumnName,
                        'to_column_name' => $inProgress->name,
                        'changed_by_id' => $actorId,
                    ]);
                }

                $task->board_column_id = $inProgress->id;
                $task->save();
            });

            $moved++;
        }

        return $moved;
    }

    public function checkOut(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $attendance = $this->presence->checkOut($user);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'attendance' => $attendance ? [
                    'checked_out_at' => $attendance->checked_out_at?->toIso8601String(),
                    'active_seconds' => (int) $attendance->active_seconds,
                ] : null,
            ]);
        }

        return back();
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $data = $request->validate([
            'elapsed' => ['required', 'integer', 'min:0', 'max:600'],
        ]);

        $session = $this->presence->recordHeartbeat(
            $user,
            (int) $data['elapsed'],
            (string) $request->userAgent(),
            (string) $request->ip(),
        );

        return response()->json([
            'ok' => true,
            'session' => [
                'id' => $session->id,
                'active_seconds' => (int) $session->active_seconds,
                'started_at' => $session->started_at?->toIso8601String(),
            ],
        ]);
    }
}
