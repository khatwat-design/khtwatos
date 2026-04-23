<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'current_pipeline_stage_id',
        'account_manager_id',
        'notes',
    ];

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'current_pipeline_stage_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(ClientStageHistory::class)->orderByDesc('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function taskStatusHistories(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class)->orderByDesc('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClientAttachment::class)->orderByDesc('created_at');
    }
}
