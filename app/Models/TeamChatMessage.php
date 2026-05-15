<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ChatMessagePayload;
use App\Support\UserAvatar;

class TeamChatMessage extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'body',
        'forwarded_from_user_name',
        'forwarded_from_context',
        'edited_at',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatArray(): array
    {
        $this->loadMissing('user:id,name,avatar_path');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'forward' => ChatMessagePayload::forwardMeta($this->forwarded_from_user_name, $this->forwarded_from_context),
            'attachment' => ChatMessagePayload::attachmentPayload(
                $this->attachment_path,
                $this->attachment_name,
                $this->attachment_mime,
                $this->attachment_size,
            ),
            'user' => UserAvatar::chatUser($this->user),
        ];
    }
}
