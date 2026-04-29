<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDeadlineAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly string $severity
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'task_deadline',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'severity' => $this->severity,
            'client_name' => $this->task->client?->name,
            'column_name' => $this->task->column?->name,
        ];
    }
}

