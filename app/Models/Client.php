<?php

namespace App\Models;

use Illuminate\Support\Str;
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
        'campaign_manager_id',
        'notes',
        'portal_token',
        'portal_username',
        'portal_password',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            if (!$client->portal_token) {
                $client->portal_token = Str::random(48);
            }
        });
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'current_pipeline_stage_id');
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function campaignManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'campaign_manager_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(ClientStageHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
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

    public function dailySales(): HasMany
    {
        return $this->hasMany(ClientDailySale::class)->orderByDesc('sales_date');
    }

    public function campaignUpdates(): HasMany
    {
        return $this->hasMany(ClientCampaignUpdate::class)->orderByDesc('report_date');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ClientProduct::class)->orderBy('name');
    }

    public function portalNotes(): HasMany
    {
        return $this->hasMany(ClientPortalNote::class)->orderByDesc('created_at');
    }
}
