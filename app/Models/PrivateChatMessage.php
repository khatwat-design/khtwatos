<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PrivateChatMessage extends Model
{
    protected $fillable = [
        'private_chat_room_id',
        'user_id',
        'body',
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

    protected static function booted(): void
    {
        static::created(function (PrivateChatMessage $message): void {
            $message->room()?->touch();
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(PrivateChatRoom::class, 'private_chat_room_id');
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
        $this->loadMissing('user:id,name');

        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'attachment' => $this->attachment_path ? [
                'url' => Storage::disk('public')->url($this->attachment_path),
                'name' => $this->attachment_name,
                'mime' => $this->attachment_mime,
                'size' => $this->attachment_size,
                'is_image' => is_string($this->attachment_mime) && str_starts_with($this->attachment_mime, 'image/'),
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
        ];
    }
}
