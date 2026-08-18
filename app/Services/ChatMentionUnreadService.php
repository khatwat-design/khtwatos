<?php

namespace App\Services;

use App\Models\ChatMentionRead;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\TeamChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatMentionUnreadService
{
    public function __construct(
        private readonly TeamChatMemberService $teamChatMembers,
    ) {}

    /**
     * @return array{
     *     teamMentionUnreadCounts: array<int, int>,
     *     privateRoomMentionUnreadCounts: array<int, int>,
     *     directMentionUnreadCounts: array<int, int>
     * }
     */
    public function summaryPayload(?User $user): array
    {
        if (! $user || ! Schema::hasTable('chat_message_mentions')) {
            return [
                'teamMentionUnreadCounts' => [],
                'privateRoomMentionUnreadCounts' => [],
                'directMentionUnreadCounts' => [],
            ];
        }

        return [
            'teamMentionUnreadCounts' => $this->teamMentionUnreadMap($user),
            'privateRoomMentionUnreadCounts' => $this->privateRoomMentionUnreadMap($user),
            'directMentionUnreadCounts' => $this->directMentionUnreadMap($user),
        ];
    }

    /**
     * @return array{count: int, message_ids: list<int>, first_message_id: int|null}
     */
    public function inboxForContext(?User $user, string $contextType, int $contextId): array
    {
        if (! $user || $contextId <= 0 || ! Schema::hasTable('chat_message_mentions')) {
            return ['count' => 0, 'message_ids' => [], 'first_message_id' => null];
        }

        $lastAck = $this->lastAckMessageId((int) $user->id, $contextType, $contextId);
        $messageIds = $this->unreadMentionMessageIds((int) $user->id, $contextType, $contextId, $lastAck);

        return [
            'count' => count($messageIds),
            'message_ids' => $messageIds,
            'first_message_id' => $messageIds[0] ?? null,
        ];
    }

    public function acknowledge(?User $user, string $contextType, int $contextId, int $upToMessageId): void
    {
        if (! $user || $contextId <= 0 || $upToMessageId <= 0 || ! Schema::hasTable('chat_mention_reads')) {
            return;
        }

        $row = ChatMentionRead::query()->firstOrNew([
            'user_id' => $user->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
        ]);

        $current = (int) ($row->last_ack_message_id ?? 0);
        $row->last_ack_message_id = max($current, $upToMessageId);
        $row->save();
    }

    public function messageMentionsCurrentUser(array $message, ?int $viewerId): bool
    {
        if (! $viewerId) {
            return false;
        }

        foreach ($message['mentions'] ?? [] as $mention) {
            if ((int) ($mention['id'] ?? 0) === $viewerId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    public function teamMentionUnreadMap(User $user): array
    {
        $ackMap = $this->ackMapForUser((int) $user->id, ChatMentionRead::CONTEXT_TEAM);
        $result = [];

        foreach ($this->teamChatMembers->accessibleTeamIdsForUser($user) as $teamId) {
            $count = count($this->unreadMentionMessageIds(
                (int) $user->id,
                ChatMentionRead::CONTEXT_TEAM,
                $teamId,
                $ackMap[$teamId] ?? 0,
            ));
            if ($count > 0) {
                $result[$teamId] = $count;
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    public function privateRoomMentionUnreadMap(User $user): array
    {
        if (! Schema::hasTable('private_chat_room_user')) {
            return [];
        }

        $ackMap = $this->ackMapForUser((int) $user->id, ChatMentionRead::CONTEXT_PRIVATE_ROOM);
        $roomIds = DB::table('private_chat_room_user')
            ->where('user_id', $user->id)
            ->pluck('private_chat_room_id');

        $result = [];
        foreach ($roomIds as $roomId) {
            $roomId = (int) $roomId;
            $count = count($this->unreadMentionMessageIds(
                (int) $user->id,
                ChatMentionRead::CONTEXT_PRIVATE_ROOM,
                $roomId,
                $ackMap[$roomId] ?? 0,
            ));
            if ($count > 0) {
                $result[$roomId] = $count;
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    public function directMentionUnreadMap(User $user): array
    {
        if (! Schema::hasTable('direct_conversation_user')) {
            return [];
        }

        $ackMap = $this->ackMapForUser((int) $user->id, ChatMentionRead::CONTEXT_DIRECT);
        $conversationIds = DB::table('direct_conversation_user')
            ->where('user_id', $user->id)
            ->pluck('direct_conversation_id');

        $result = [];
        foreach ($conversationIds as $conversationId) {
            $conversationId = (int) $conversationId;
            $count = count($this->unreadMentionMessageIds(
                (int) $user->id,
                ChatMentionRead::CONTEXT_DIRECT,
                $conversationId,
                $ackMap[$conversationId] ?? 0,
            ));
            if ($count > 0) {
                $result[$conversationId] = $count;
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function ackMapForUser(int $userId, string $contextType): array
    {
        if (! Schema::hasTable('chat_mention_reads')) {
            return [];
        }

        return ChatMentionRead::query()
            ->where('user_id', $userId)
            ->where('context_type', $contextType)
            ->pluck('last_ack_message_id', 'context_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function lastAckMessageId(int $userId, string $contextType, int $contextId): int
    {
        if (! Schema::hasTable('chat_mention_reads')) {
            return 0;
        }

        return (int) (ChatMentionRead::query()
            ->where('user_id', $userId)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->value('last_ack_message_id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function unreadMentionMessageIds(int $userId, string $contextType, int $contextId, int $lastAck): array
    {
        $query = DB::table('chat_message_mentions')
            ->where('chat_message_mentions.user_id', $userId);

        if ($contextType === ChatMentionRead::CONTEXT_TEAM) {
            $query
                ->join('team_chat_messages', function ($join) {
                    $join->on('team_chat_messages.id', '=', 'chat_message_mentions.mentionable_id')
                        ->where('chat_message_mentions.mentionable_type', TeamChatMessage::class);
                })
                ->where('team_chat_messages.team_id', $contextId)
                ->where('team_chat_messages.id', '>', $lastAck)
                ->orderBy('team_chat_messages.id')
                ->select('team_chat_messages.id');
        } elseif ($contextType === ChatMentionRead::CONTEXT_PRIVATE_ROOM) {
            $query
                ->join('private_chat_messages', function ($join) {
                    $join->on('private_chat_messages.id', '=', 'chat_message_mentions.mentionable_id')
                        ->where('chat_message_mentions.mentionable_type', PrivateChatMessage::class);
                })
                ->where('private_chat_messages.private_chat_room_id', $contextId)
                ->where('private_chat_messages.id', '>', $lastAck)
                ->orderBy('private_chat_messages.id')
                ->select('private_chat_messages.id');
        } else {
            $query
                ->join('direct_messages', function ($join) {
                    $join->on('direct_messages.id', '=', 'chat_message_mentions.mentionable_id')
                        ->where('chat_message_mentions.mentionable_type', DirectMessage::class);
                })
                ->where('direct_messages.direct_conversation_id', $contextId)
                ->where('direct_messages.id', '>', $lastAck)
                ->orderBy('direct_messages.id')
                ->select('direct_messages.id');
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
