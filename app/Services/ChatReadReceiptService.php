<?php

namespace App\Services;

use App\Models\DirectChatRead;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRead;
use App\Models\PrivateChatRoom;
use App\Models\TeamChatMessage;
use App\Models\TeamChatRead;
use Illuminate\Support\Collection;

class ChatReadReceiptService
{
    public function __construct(
        private readonly ChatTaskCardEnricher $taskCards,
    ) {}

    /**
     * @param  Collection<int, TeamChatMessage|array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    public function enrichTeamMessages(Collection $messages, int $teamId, int $viewerId): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $readMap = TeamChatRead::query()
            ->where('team_id', $teamId)
            ->where('user_id', '!=', $viewerId)
            ->pluck('last_read_message_id', 'user_id');

        $recipientCount = $readMap->count();

        $mapped = $messages->map(function ($msg) use ($readMap, $recipientCount, $viewerId) {
            $arr = $msg instanceof TeamChatMessage ? $msg->toChatArray() : $msg;
            $senderId = (int) ($arr['user']['id'] ?? 0);

            if ($senderId !== $viewerId || ! empty($arr['is_pending'])) {
                $arr['read_receipt'] = null;

                return $arr;
            }

            $arr['read_receipt'] = $this->buildReceipt((int) $arr['id'], $readMap, $recipientCount, 'team');

            return $arr;
        });

        return $this->taskCards->enrichForModel($mapped, TeamChatMessage::class);
    }

    /**
     * @param  Collection<int, PrivateChatMessage|array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    public function enrichPrivateRoomMessages(Collection $messages, int $roomId, int $viewerId): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $room = PrivateChatRoom::query()->find($roomId);
        $memberIds = $room
            ? $room->members()->where('users.id', '!=', $viewerId)->pluck('users.id')
            : collect();

        $readMap = PrivateChatRead::query()
            ->where('private_chat_room_id', $roomId)
            ->whereIn('user_id', $memberIds)
            ->pluck('last_read_message_id', 'user_id');

        $recipientCount = max($memberIds->count(), $readMap->count());

        $mapped = $messages->map(function ($msg) use ($readMap, $recipientCount, $viewerId) {
            $arr = $msg instanceof PrivateChatMessage ? $msg->toChatArray() : $msg;
            $senderId = (int) ($arr['user']['id'] ?? 0);

            if ($senderId !== $viewerId || ! empty($arr['is_pending'])) {
                $arr['read_receipt'] = null;

                return $arr;
            }

            $arr['read_receipt'] = $this->buildReceipt((int) $arr['id'], $readMap, $recipientCount, 'group');

            return $arr;
        });

        return $this->taskCards->enrichForModel($mapped, PrivateChatMessage::class);
    }

    /**
     * @param  Collection<int, DirectMessage|array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    public function enrichDirectMessages(Collection $messages, int $conversationId, int $viewerId): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $peerReadId = (int) (DirectChatRead::query()
            ->where('direct_conversation_id', $conversationId)
            ->where('user_id', '!=', $viewerId)
            ->value('last_read_message_id') ?? 0);

        return $messages->map(function ($msg) use ($peerReadId, $viewerId) {
            $arr = $msg instanceof DirectMessage ? $msg->toChatArray($viewerId) : $msg;
            $senderId = (int) ($arr['user']['id'] ?? 0);

            if (($arr['kind'] ?? '') === 'call') {
                $arr['read_receipt'] = null;

                return $arr;
            }

            if ($senderId !== $viewerId || ! empty($arr['is_pending'])) {
                $arr['read_receipt'] = null;

                return $arr;
            }

            $messageId = (int) $arr['id'];
            $read = $peerReadId >= $messageId;

            $arr['read_receipt'] = [
                'status' => $read ? 'read' : 'sent',
                'read_count' => $read ? 1 : 0,
                'total_recipients' => 1,
                'label' => $read ? 'تمت القراءة' : 'تم الإرسال',
            ];

            return $arr;
        });

        return $this->taskCards->enrichForModel($mapped, DirectMessage::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function teamReceiptsPayload(int $teamId, int $viewerId, ?int $afterMessageId = null): ?array
    {
        $query = TeamChatMessage::query()
            ->where('team_id', $teamId)
            ->where('user_id', $viewerId)
            ->orderByDesc('id')
            ->limit(80);

        if ($afterMessageId) {
            $query->where('id', '>', $afterMessageId);
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            return null;
        }

        $enriched = $this->enrichTeamMessages($messages, $teamId, $viewerId);

        return [
            'receipts' => $enriched
                ->filter(fn (array $row) => ! empty($row['read_receipt']))
                ->mapWithKeys(fn (array $row) => [(int) $row['id'] => $row['read_receipt']])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int|string, int>  $readMap  user_id => last_read_message_id
     * @return array{status: string, read_count: int, total_recipients: int, label: string}
     */
    private function buildReceipt(int $messageId, Collection $readMap, int $recipientCount, string $mode): array
    {
        $readCount = (int) $readMap->filter(fn ($lastId) => (int) $lastId >= $messageId)->count();

        if ($recipientCount <= 0) {
            return [
                'status' => 'sent',
                'read_count' => 0,
                'total_recipients' => 0,
                'label' => 'تم الإرسال',
            ];
        }

        if ($readCount >= $recipientCount) {
            return [
                'status' => 'read',
                'read_count' => $readCount,
                'total_recipients' => $recipientCount,
                'label' => $mode === 'team' ? 'تمت القراءة من الفريق' : 'تمت القراءة من الجميع',
            ];
        }

        if ($readCount > 0) {
            return [
                'status' => 'partial',
                'read_count' => $readCount,
                'total_recipients' => $recipientCount,
                'label' => "مقروءة من {$readCount}/{$recipientCount}",
            ];
        }

        return [
            'status' => 'sent',
            'read_count' => 0,
            'total_recipients' => $recipientCount,
            'label' => 'تم الإرسال',
        ];
    }
}
