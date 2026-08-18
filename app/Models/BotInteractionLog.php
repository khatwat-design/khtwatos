<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotInteractionLog extends Model
{
    protected $fillable = [
        'outside_conversation_id',
        'direction',
        'message_body',
        'message_type',
        'ai_context',
        'ai_response',
        'ai_confidence',
        'response_time_ms',
    ];

    protected $casts = [
        'ai_context' => 'array',
        'ai_response' => 'array',
        'ai_confidence' => 'float',
        'response_time_ms' => 'float',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(OutsideConversation::class, 'outside_conversation_id');
    }
}
