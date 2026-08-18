<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Support\UserAvatar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TeamChatMemberService
{
    public function userCanAccessTeam(?User $user, int $teamId): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! Schema::hasTable('team_chat_members')) {
            return $user->teams()->where('teams.id', $teamId)->exists();
        }

        return in_array((int) $user->id, $this->memberIdsForTeam($teamId), true);
    }

    /**
     * @return list<int>
     */
    public function memberIdsForTeam(int $teamId): array
    {
        if (! Schema::hasTable('team_chat_members')) {
            return $this->pivotMemberIdsForTeam($teamId);
        }

        $explicit = $this->explicitMemberIdsForTeam($teamId);
        if ($explicit !== []) {
            return $explicit;
        }

        return $this->defaultMemberIdsForTeam($teamId);
    }

    /**
     * @return list<int>
     */
    private function explicitMemberIdsForTeam(int $teamId): array
    {
        return DB::table('team_chat_members')
            ->where('team_id', $teamId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function pivotMemberIdsForTeam(int $teamId): array
    {
        return Team::query()
            ->find($teamId)
            ?->users()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];
    }

    /**
     * @return list<int>
     */
    private function defaultMemberIdsForTeam(int $teamId): array
    {
        $team = Team::query()->find($teamId);

        if ($team && $team->slug === 'khatwat') {
            return User::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->pivotMemberIdsForTeam($teamId);
    }

    /**
     * @return list<int>
     */
    private function configuredTeamIds(): array
    {
        return DB::table('team_chat_members')
            ->distinct()
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, avatar_url: string|null}>
     */
    public function membersPayloadForTeam(int $teamId): array
    {
        $ids = $this->memberIdsForTeam($teamId);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => UserAvatar::url($u),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function syncMembers(Team $team, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id) => $id > 0)));

        if (! Schema::hasTable('team_chat_members')) {
            return;
        }

        DB::table('team_chat_members')->where('team_id', $team->id)->delete();

        $now = now();
        foreach ($userIds as $userId) {
            DB::table('team_chat_members')->insert([
                'team_id' => $team->id,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function syncFromTeamPivot(Team $team): void
    {
        if (! Schema::hasTable('team_chat_members') || ! Schema::hasTable('team_user')) {
            return;
        }

        $ids = $team->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $this->syncMembers($team, $ids);
    }

    /**
     * @return list<int>
     */
    public function accessibleTeamIdsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isAdmin()) {
            return Team::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! Schema::hasTable('team_chat_members')) {
            return $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        }

        $accessible = DB::table('team_chat_members')
            ->where('user_id', $user->id)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $configured = $this->configuredTeamIds();

        foreach (Team::query()->whereNotIn('id', $configured)->pluck('id') as $teamId) {
            $teamId = (int) $teamId;
            if (in_array($teamId, $accessible, true)) {
                continue;
            }
            if (in_array((int) $user->id, $this->defaultMemberIdsForTeam($teamId), true)) {
                $accessible[] = $teamId;
            }
        }

        return array_values(array_unique($accessible));
    }
}
