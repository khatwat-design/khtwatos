<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Services\TeamChatMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamChatMembersController extends Controller
{
    public function __construct(
        private readonly TeamChatMemberService $teamChatMembers,
    ) {}

    public function show(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
            ],
            'members' => $this->teamChatMembers->membersPayloadForTeam((int) $team->id),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ]);

        $ids = collect($data['member_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $this->teamChatMembers->syncMembers($team, $ids);

        return response()->json([
            'ok' => true,
            'members' => $this->teamChatMembers->membersPayloadForTeam((int) $team->id),
        ]);
    }
}
