<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutsideMessage extends Model
{
    protected $fillable = [
        'outside_conversation_id',
        'channel',
        'direction',
        'message_type',
        'body',
        'payload',
        'external_message_id',
        'provider_status',
        'provider_error',
        'retry_count',
        'sent_by_user_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OutsideConversation::class, 'outside_conversation_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
