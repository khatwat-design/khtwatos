<?php

namespace App\Models;

use App\Support\UserAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCall extends Model
{
    public const TYPE_VOICE = 'voice';

    public const TYPE_VIDEO = 'video';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_MISSED = 'missed';

    public const STATUS_BUSY = 'busy';

    protected $fillable = [
        'caller_id',
        'callee_id',
        'direct_conversation_id',
        'type',
        'offer_sdp',
        'status',
        'answered_at',
        'ended_at',
        'chat_logged_at',
    ];

    protected function casts(): array
    {
        return [
            'offer_sdp' => 'array',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'chat_logged_at' => 'datetime',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DirectConversation::class, 'direct_conversation_id');
    }

    public function isParticipant(User $user): bool
    {
        return (int) $this->caller_id === (int) $user->id
            || (int) $this->callee_id === (int) $user->id;
    }

    public function otherUser(User $user): ?User
    {
        if ((int) $this->caller_id === (int) $user->id) {
            return $this->callee;
        }
        if ((int) $this->callee_id === (int) $user->id) {
            return $this->caller;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(User $viewer): array
    {
        $this->loadMissing(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);
        $peer = $this->otherUser($viewer);
        $isCaller = (int) $this->caller_id === (int) $viewer->id;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'offer_sdp' => $this->offer_sdp,
            'is_caller' => $isCaller,
            'caller' => UserAvatar::chatUser($this->caller),
            'callee' => UserAvatar::chatUser($this->callee),
            'peer' => UserAvatar::chatUser($peer),
            'direct_conversation_id' => $this->direct_conversation_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
