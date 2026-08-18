<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutsideConversation extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    protected $fillable = [
        'outside_contact_id',
        'status',
        'latest_message_preview',
        'unread_count',
        'last_inbound_at',
        'last_outbound_at',
        'intelligence_classification',
        'intelligence_classification_at',
        'intelligence_summary',
        'intelligence_summary_at',
        'intelligence_summary_inbound_count',
        'intelligence_suggested_replies',
        'intelligence_suggested_at',
        'intelligence_routing',
        'intelligence_client_context',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'intelligence_classification_at' => 'datetime',
        'intelligence_summary_at' => 'datetime',
        'intelligence_suggested_replies' => 'array',
        'intelligence_suggested_at' => 'datetime',
        'intelligence_routing' => 'array',
        'intelligence_client_context' => 'array',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OutsideContact::class, 'outside_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutsideMessage::class)->orderByDesc('created_at');
    }
}
