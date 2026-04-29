<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineAlertNotification;
use Illuminate\Support\Facades\Schema;

class SystemNotificationService
{
    public function syncForUser(User $user): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $now = now();
        $soonThreshold = $now->copy()->addHours(24);

        $tasks = Task::query()
            ->whereNull('archived_at')
            ->whereNotNull('due_at')
            ->whereHas('column', fn ($q) => $q->whereNotIn('name', ['تم', 'done', 'completed']))
            ->where(function ($q) use ($user): void {
                $q->where('assignee_id', $user->id)
                    ->orWhereHas('assignees', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->with(['client:id,name', 'column:id,name'])
            ->get(['id', 'title', 'assignee_id', 'due_at', 'client_id', 'board_column_id']);

        foreach ($tasks as $task) {
            if (! $task->due_at) {
                continue;
            }

            $severity = null;
            if ($task->due_at->lt($now)) {
                $severity = 'overdue';
            } elseif ($task->due_at->lte($soonThreshold)) {
                $severity = 'due_soon';
            } elseif ($task->due_at->isSameDay($now)) {
                $severity = 'due_today';
            }

            if (! $severity) {
                continue;
            }

            $exists = $user->notifications()
                ->where('type', TaskDeadlineAlertNotification::class)
                ->where('data->task_id', $task->id)
                ->where('data->severity', $severity)
                ->where('created_at', '>=', $now->copy()->subHours(12))
                ->exists();

            if (! $exists) {
                $user->notify(new TaskDeadlineAlertNotification($task, $severity));
            }
        }
    }
}

