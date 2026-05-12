<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeLog extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'started_at',
        'completed_at',
        'duration_seconds',
        'was_overdue',
        'overdue_seconds',
        'from_column_name',
        'to_column_name',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_seconds' => 'integer',
            'overdue_seconds' => 'integer',
            'was_overdue' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
