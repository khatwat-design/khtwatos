<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * صلاحيات العمليات التشغيلية (اجتماعات، فلاتر العملاء على الواجهات).
 */
class OperationalAuthorization
{
    public function canViewClient(User $user, Client $client): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $client->account_manager_id === (int) $user->id
            || (int) $client->campaign_manager_id === (int) $user->id;
    }

    public function canUseMeetingClientFilter(User $user, int $clientId): bool
    {
        $client = Client::query()->find($clientId);

        return $client && $this->canViewClient($user, $client);
    }

    /**
     * من يُسمح له أن يُسند الاجتماع لهذا المضيف (اختيار من القائمة).
     */
    public function canAssignMeetingHost(User $user, int $hostUserId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $hostUserId === (int) $user->id) {
            return true;
        }

        return User::query()->whereKey($hostUserId)->exists();
    }

    public function canViewMeeting(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $meeting->user_id === (int) $user->id) {
            return true;
        }

        $meeting->loadMissing(['participants', 'client']);

        if ($meeting->participants->contains(fn (User $p) => (int) $p->id === (int) $user->id)) {
            return true;
        }

        if ($meeting->client_id && $meeting->client) {
            return $this->canViewClient($user, $meeting->client);
        }

        return false;
    }

    /**
     * @param  array<int, int>  $teamIds
     */
    public function ensureMeetingTeamIdsAllowed(User $user, array $teamIds): void
    {
        $teamIds = array_values(array_filter(array_map('intval', $teamIds)));
        if ($teamIds === []) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $allowed = $user->teams()->whereIn('teams.id', $teamIds)->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        foreach ($teamIds as $tid) {
            if (! in_array($tid, $allowed, true)) {
                throw new HttpException(403, 'لا يُسمح بربط فريق غير مختص به.');
            }
        }
    }

    /**
     * @param  array<int, int>  $participantIds
     */
    public function ensureMeetingParticipantIdsAllowed(User $user, array $participantIds): void
    {
        $participantIds = array_values(array_filter(array_map('intval', $participantIds)));
        if ($participantIds === []) {
            return;
        }

        $count = User::query()->whereIn('id', $participantIds)->count();
        if ($count !== count(array_unique($participantIds))) {
            throw new HttpException(403, 'مشارك غير صالح.');
        }

        // يمكن لاحقاً تقييد المشاركين حسب الفريق؛ حالياً أي مستخدم مسجّل مقبول.
    }
}
