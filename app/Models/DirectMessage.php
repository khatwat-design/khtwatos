<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ChatMessagePayload;
use App\Support\EmployeeCallChatLog;
use App\Support\UserAvatar;

class DirectMessage extends Model
{
    protected $fillable = [
        'direct_conversation_id',
        'user_id',
        'body',
        'forwarded_from_user_name',
        'forwarded_from_context',
        'edited_at',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'employee_call_id',
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
        static::created(function (DirectMessage $message): void {
            $message->conversation()?->touch();
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectConversation::class, 'direct_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employeeCall(): BelongsTo
    {
        return $this->belongsTo(EmployeeCall::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatArray(?int $viewerId = null): array
    {
        $this->loadMissing('user:id,name,avatar_path');

        $payload = [
            'id' => $this->id,
            'kind' => 'message',
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

        if ($this->employee_call_id) {
            $this->loadMissing('employeeCall');
            if ($this->employeeCall) {
                $payload['kind'] = 'call';
                $payload['body'] = '';
                $payload['call'] = EmployeeCallChatLog::payloadForViewer($this->employeeCall, $viewerId);
                $payload['created_at'] = ($this->employeeCall->ended_at ?? $this->created_at)?->toIso8601String();
            }
        }

        return $payload;
    }
}
