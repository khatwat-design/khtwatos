<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskReassignment;
use App\Models\TaskTimeLog;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TasksSyncAssigneesFromHistoryCommand extends Command
{
    protected $signature = 'tasks:sync-assignees-from-history
                            {--dry-run : عرض التغييرات دون الكتابة في قاعدة البيانات}
                            {--with-time-logs : توحيد سجلات task_time_logs مع المكلّف النهائي (إنجاز المهمة)}
                            {--fix-writing-dual : تصحيح مهام فريق الكتابة التي لا تزال فيها مكلّفان (مدير افتراضي + موظف) بدون سجل إعادة تعيين}';

    protected $description = 'مزامنة assignee_id و task_assignees مع آخر إعادة تعيين، وإصلاح بيانات المهام القديمة لعدم ضياع حق الموظفين في التحليلات';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $withTimeLogs = (bool) $this->option('with-time-logs');
        $fixWritingDual = (bool) $this->option('fix-writing-dual');

        if ($dry) {
            $this->warn('تشغيل تجريبي — لن تُحفظ أي تعديلات.');
        }

        $fromReassignments = $this->syncFromLatestReassignments($dry);
        $this->info("من سجلّات إعادة التعيين: عُدّل {$fromReassignments} مهمة.");

        if ($withTimeLogs && ! $dry) {
            $logs = $this->consolidateTaskTimeLogsForReassignedTasks();
            $this->info("سجلات الإنجاز: عُدّل {$logs} سجلًا (مهام لها إعادة تعيين).");
        } elseif ($withTimeLogs && $dry) {
            $this->comment('تجاهل --with-time-logs في وضع dry-run (شغّل بلا --dry-run لتطبيق توحيد السجلات).');
        }

        if ($fixWritingDual) {
            $dual = $this->fixStaleWritingTeamDualAssignees($dry);
            $this->info("فريق الكتابة (مكلّفان بدون سجل إعادة تعيين): عُدّل {$dual} مهمة.");
        }

        return self::SUCCESS;
    }

    /**
     * يبقي سجل إنجاز واحد لكل مهمة ويضبط user_id على المكلّف النهائي.
     */
    private function consolidateSingleTaskTimeLog(int $taskId, int $workerId): int
    {
        if (! Schema::hasTable('task_time_logs')) {
            return 0;
        }

        $logIds = TaskTimeLog::query()
            ->where('task_id', $taskId)
            ->orderBy('id')
            ->pluck('id');

        if ($logIds->isEmpty()) {
            return 0;
        }

        $keepId = (int) $logIds->first();
        if ($logIds->count() > 1) {
            TaskTimeLog::query()
                ->where('task_id', $taskId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        return TaskTimeLog::query()
            ->where('task_id', $taskId)
            ->where('id', $keepId)
            ->update([
                'user_id' => $workerId,
            ]);
    }

    /**
     * آخر سجل إعادة تعيين لكل مهمة يحدد المكلّف الفعلي.
     */
    private function syncFromLatestReassignments(bool $dry): int
    {
        $latest = DB::table('task_reassignments as tr')
            ->joinSub(
                DB::table('task_reassignments')
                    ->selectRaw('task_id, MAX(id) as max_id')
                    ->groupBy('task_id'),
                'x',
                function ($join) {
                    $join->on('tr.task_id', '=', 'x.task_id')
                        ->on('tr.id', '=', 'x.max_id');
                }
            )
            ->pluck('tr.assigned_to_id', 'tr.task_id');

        if ($latest->isEmpty()) {
            return 0;
        }

        $updated = 0;

        foreach ($latest as $taskId => $assigneeId) {
            $taskId = (int) $taskId;
            $assigneeId = (int) $assigneeId;

            if ($taskId <= 0 || $assigneeId <= 0) {
                continue;
            }

            /** @var Task|null $task */
            $task = Task::query()->find($taskId);
            if (! $task) {
                continue;
            }

            $currentAssignees = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $target = [$assigneeId];
            $needsSync = $currentAssignees !== $target || (int) $task->assignee_id !== $assigneeId;

            if (! $needsSync) {
                continue;
            }

            if (! $dry) {
                $task->assignees()->sync($target);
                $task->assignee_id = $assigneeId;
                $task->save();
            }

            $updated++;
            if ($this->output->isVerbose()) {
                $this->line("Task #{$taskId} → user #{$assigneeId}");
            }
        }

        return $updated;
    }

    /**
     * إبقاء سجل إنجاز واحد لكل مهمة وربطه بالمكلّف وفق آخر إعادة تعيين.
     */
    private function consolidateTaskTimeLogsForReassignedTasks(): int
    {
        $latest = DB::table('task_reassignments as tr')
            ->joinSub(
                DB::table('task_reassignments')
                    ->selectRaw('task_id, MAX(id) as max_id')
                    ->groupBy('task_id'),
                'x',
                function ($join) {
                    $join->on('tr.task_id', '=', 'x.task_id')
                        ->on('tr.id', '=', 'x.max_id');
                }
            )
            ->pluck('tr.assigned_to_id', 'tr.task_id');

        if (! Schema::hasTable('task_time_logs')) {
            $this->warn('جدول task_time_logs غير موجود — تخطّي توحيد السجلات.');

            return 0;
        }

        if ($latest->isEmpty()) {
            return 0;
        }

        $affectedRows = 0;

        foreach ($latest as $taskId => $workerId) {
            $affectedRows += $this->consolidateSingleTaskTimeLog((int) $taskId, (int) $workerId);
        }

        return $affectedRows;
    }

    /**
     * مهام لوح الكتابة ما زالت فيها مدير الفريق + موظف في pivot — نفضّل الموظف غير lead.
     */
    private function fixStaleWritingTeamDualAssignees(bool $dry): int
    {
        $writingTeam = Team::query()->where('slug', 'writing')->first();
        if (! $writingTeam) {
            $this->warn('لا يوجد فريق بمعرف writing — تخطّي --fix-writing-dual.');

            return 0;
        }

        $boardIds = TaskBoard::query()
            ->where('team_id', $writingTeam->id)
            ->pluck('id');

        if ($boardIds->isEmpty()) {
            return 0;
        }

        $dualTaskIds = DB::table('task_assignees')
            ->select('task_id')
            ->groupBy('task_id')
            ->havingRaw('COUNT(*) = 2')
            ->pluck('task_id');

        $updated = 0;

        foreach ($dualTaskIds as $taskId) {
            $taskId = (int) $taskId;

            if (TaskReassignment::query()->where('task_id', $taskId)->exists()) {
                continue;
            }

            /** @var Task|null $task */
            $task = Task::query()
                ->whereIn('task_board_id', $boardIds)
                ->find($taskId);

            if (! $task) {
                continue;
            }

            $userIds = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->values()->all();
            if (count($userIds) !== 2) {
                continue;
            }

            [$a, $b] = $userIds;

            $aLead = (bool) DB::table('team_user')
                ->where('team_id', $writingTeam->id)
                ->where('user_id', $a)
                ->value('is_lead');
            $bLead = (bool) DB::table('team_user')
                ->where('team_id', $writingTeam->id)
                ->where('user_id', $b)
                ->value('is_lead');

            $workerId = null;
            if ($aLead && ! $bLead) {
                $workerId = $b;
            } elseif ($bLead && ! $aLead) {
                $workerId = $a;
            }

            if ($workerId === null) {
                continue;
            }

            if (! $dry) {
                $task->assignees()->sync([$workerId]);
                $task->assignee_id = $workerId;
                $task->save();
                $this->consolidateSingleTaskTimeLog($taskId, $workerId);
            }

            $updated++;
            if ($this->output->isVerbose()) {
                $this->line("Writing dual fix Task #{$taskId} → user #{$workerId}");
            }
        }

        return $updated;
    }
}
