<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsMetaLead extends Model
{
    public const WORKFLOW_NEW = 'new';

    public const WORKFLOW_FOLLOWING = 'following';

    public const WORKFLOW_POTENTIAL = 'potential';

    public const WORKFLOW_UNLIKELY = 'unlikely';

    public const WORKFLOW_QUALIFIED = 'qualified';

    public const WORKFLOW_WON = 'won';

    public const WORKFLOW_LOST = 'lost';

    public const WORKFLOW_REJECTED = 'rejected';

    protected $fillable = [
        'meta_lead_id',
        'lead_created_at',
        'full_name',
        'phone',
        'phone_normalized',
        'platform',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
        'form_id',
        'form_name',
        'is_organic',
        'meta_lead_status',
        'monthly_orders_answer',
        'goal_answer',
        'team_notes',
        'probability_label',
        'reason_label',
        'outcome_label',
        'workflow_status',
        'owner_user_id',
        'goods_customer_id',
        'first_contact_date',
        'last_contact_date',
        'next_contact_date',
        'form_answers',
        'raw_row',
        'sheet_synced_at',
        'sheet_row_number',
    ];

    protected function casts(): array
    {
        return [
            'lead_created_at' => 'datetime',
            'is_organic' => 'boolean',
            'first_contact_date' => 'date',
            'last_contact_date' => 'date',
            'next_contact_date' => 'date',
            'form_answers' => 'array',
            'raw_row' => 'array',
            'sheet_synced_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function goodsCustomer(): BelongsTo
    {
        return $this->belongsTo(GoodsCustomer::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(GoodsMetaLeadStatusHistory::class);
    }
}
