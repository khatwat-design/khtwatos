<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'task_board_id',
        'board_column_id',
        'title',
        'description',
        'assignee_id',
        'client_id',
        'position',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_at' => 'datetime',
        ];
    }

    public function taskBoard(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withTimestamps();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->orderByDesc('created_at');
    }
}
