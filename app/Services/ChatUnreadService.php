<?php

namespace App\Services;

use App\Models\DirectChatRead;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRead;
use App\Models\PrivateChatRoom;
use App\Models\TeamChatMessage;
use App\Models\TeamChatRead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChatUnreadService
{
    public function __construct(
        private readonly TeamChatMemberService $teamChatMembers,
    ) {}

    /**
     * عدّ رسائل الفريق غير المقروءة لكل فريق (معرف الفريق => العدد).
     *
     * @return array<int, int>
     */
    public function teamUnreadMap(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $teamIds = $this->teamChatMembers->accessibleTeamIdsForUser($user);
        $readMap = TeamChatRead::query()
            ->where('user_id', $user->id)
            ->pluck('last_read_message_id', 'team_id');

        $result = [];
        foreach ($teamIds as $teamId) {
            $team = (object) ['id' => $teamId];
            $lastRead = (int) ($readMap[$team->id] ?? 0);
            $result[(int) $team->id] = (int) TeamChatMessage::query()
                ->where('team_id', $team->id)
                ->where('id', '>', $lastRead)
                ->count();
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    public function privateRoomUnreadMap(?User $user): array
    {
        if (! $user || ! Schema::hasTable('private_chat_reads')) {
            return [];
        }

        $roomIds = PrivateChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $readMap = PrivateChatRead::query()
            ->where('user_id', $user->id)
            ->pluck('last_read_message_id', 'private_chat_room_id');

        $result = [];
        foreach ($roomIds as $rid) {
            $rid = (int) $rid;
            $lastRead = (int) ($readMap[$rid] ?? 0);
            $result[$rid] = (int) PrivateChatMessage::query()
                ->where('private_chat_room_id', $rid)
                ->where('id', '>', $lastRead)
                ->count();
        }

        return $result;
    }

    /**
     * @return array<int, int> conversation_id => count
     */
    public function directUnreadMap(?User $user): array
    {
        if (! $user || ! Schema::hasTable('direct_chat_reads')) {
            return [];
        }

        $conversationIds = DirectConversation::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $readMap = DirectChatRead::query()
            ->where('user_id', $user->id)
            ->pluck('last_read_message_id', 'direct_conversation_id');

        $result = [];
        foreach ($conversationIds as $cid) {
            $cid = (int) $cid;
            $lastRead = (int) ($readMap[$cid] ?? 0);
            $result[$cid] = (int) DirectMessage::query()
                ->where('direct_conversation_id', $cid)
                ->where('id', '>', $lastRead)
                ->count();
        }

        return $result;
    }

    /**
     * @return array{
     *     unreadCounts: array<int, int>,
     *     privateRoomUnreadCounts: array<int, int>,
     *     directUnreadCounts: array<int, int>,
     *     totalUnreadMessages: int
     * }
     */
    public function fullUnreadPayload(?User $user): array
    {
        $teams = $this->teamUnreadMap($user);
        $privateRooms = $this->privateRoomUnreadMap($user);
        $direct = $this->directUnreadMap($user);

        $total = array_sum($teams) + array_sum($privateRooms) + array_sum($direct);

        return [
            'unreadCounts' => $teams,
            'privateRoomUnreadCounts' => $privateRooms,
            'directUnreadCounts' => $direct,
            'totalUnreadMessages' => $total,
        ];
    }

    public function markTeamAsRead(Request $request, int $teamId): void
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return;
        }

        $lastId = (int) TeamChatMessage::query()->where('team_id', $teamId)->max('id');

        TeamChatRead::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'user_id' => $userId,
            ],
            [
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
            ],
        );
    }

    public function markPrivateRoomAsRead(Request $request, int $roomId): void
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return;
        }

        $lastId = (int) PrivateChatMessage::query()->where('private_chat_room_id', $roomId)->max('id');

        PrivateChatRead::query()->updateOrCreate(
            [
                'private_chat_room_id' => $roomId,
                'user_id' => $userId,
            ],
            [
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
            ],
        );
    }

    public function markDirectAsRead(Request $request, int $conversationId): void
    {
        $userId = $request->user()?->id;
        if (! $userId) {
            return;
        }

        $lastId = (int) DirectMessage::query()->where('direct_conversation_id', $conversationId)->max('id');

        DirectChatRead::query()->updateOrCreate(
            [
                'direct_conversation_id' => $conversationId,
                'user_id' => $userId,
            ],
            [
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
            ],
        );
    }
}
