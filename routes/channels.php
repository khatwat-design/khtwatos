<?php

use App\Services\TeamChatMemberService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('team-chat.{teamId}', function ($user, $teamId) {
    if (! $user) {
        return false;
    }

    return app(TeamChatMemberService::class)->userCanAccessTeam($user, (int) $teamId);
});
