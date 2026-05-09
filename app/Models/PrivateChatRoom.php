<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivateChatRoom extends Model
{
    protected $fillable = [
        'name',
        'creator_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'private_chat_room_user', 'private_chat_room_id', 'user_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PrivateChatMessage::class);
    }

    public function userIsMember(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->members()->where('users.id', $user->id)->exists();
    }
}
