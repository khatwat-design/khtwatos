<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCampaignUpdate extends Model
{
    protected $fillable = [
        'client_id',
        'report_date',
        'ad_spend',
        'messages_count',
        'clicks_count',
        'leads_count',
        'purchases_count',
        'campaign_revenue',
        'roas',
        'cpa',
        'cvr',
        'summary',
        'actions_taken',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'messages_count' => 'integer',
            'clicks_count' => 'integer',
            'leads_count' => 'integer',
            'purchases_count' => 'integer',
            'ad_spend' => 'decimal:2',
            'campaign_revenue' => 'decimal:2',
            'roas' => 'decimal:2',
            'cpa' => 'decimal:2',
            'cvr' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
