<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMentionRead extends Model
{
    public const CONTEXT_TEAM = 'team';

    public const CONTEXT_PRIVATE_ROOM = 'private_room';

    public const CONTEXT_DIRECT = 'direct';

    protected $fillable = [
        'user_id',
        'context_type',
        'context_id',
        'last_ack_message_id',
    ];

    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'last_ack_message_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
