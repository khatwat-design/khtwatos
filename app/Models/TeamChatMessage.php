<?php

namespace App\Models;

use App\Support\ChatMessagePayload;
use App\Support\ChatStickerCatalog;
use App\Support\UserAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamChatMessage extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'reply_to_message_id',
        'sticker_key',
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

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatArray(): array
    {
        $this->loadMissing(['user:id,name,avatar_path', 'replyTo.user:id,name']);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'sticker' => ChatStickerCatalog::stickerPayload($this->sticker_key),
            'reply' => ChatMessagePayload::replyPreview($this->replyTo),
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
