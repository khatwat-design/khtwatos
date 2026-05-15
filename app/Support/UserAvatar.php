<?php

namespace App\Support;

use App\Models\User;

final class UserAvatar
{
    public static function url(?User $user): ?string
    {
        if (! $user?->avatar_path) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $user->avatar_path), '/');
    }

    /**
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    public static function chatUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => self::url($user),
        ];
    }
}
