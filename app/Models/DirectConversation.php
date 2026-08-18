<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectConversation extends Model
{
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'direct_conversation_user', 'direct_conversation_id', 'user_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    public static function findOrCreateBetween(User $a, User $b): self
    {
        $aId = min($a->id, $b->id);
        $bId = max($a->id, $b->id);

        $candidates = self::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $aId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $bId))
            ->withCount('users')
            ->get();

        foreach ($candidates as $conversation) {
            if ((int) $conversation->users_count === 2) {
                return $conversation;
            }
        }

        $conversation = self::query()->create();
        $conversation->users()->attach([$aId, $bId]);

        return $conversation;
    }

    public function otherUser(User $current): ?User
    {
        return $this->users()->where('users.id', '!=', $current->id)->first();
    }

    public function userParticipates(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->users()->where('users.id', $user->id)->exists();
    }
}
