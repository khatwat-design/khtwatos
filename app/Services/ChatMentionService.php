<?php

namespace App\Services;

use App\Models\ChatMessageMention;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatMentionService
{
    public function __construct(
        private readonly TeamChatMemberService $teamChatMembers,
    ) {}

    /**
     * @return list<string>
     */
    public function extractUsernames(string $body): array
    {
        if ($body === '') {
            return [];
        }

        preg_match_all('/@([a-zA-Z0-9][a-zA-Z0-9._-]{1,31})/u', $body, $matches);

        return array_values(array_unique(array_map(
            fn (string $username) => Str::lower($username),
            $matches[1] ?? [],
        )));
    }

    /**
     * @return list<int>
     */
    public function extractMentionIds(string $body): array
    {
        if ($body === '') {
            return [];
        }

        preg_match_all('/@\[(\d+)\]/', $body, $matches);

        return array_values(array_unique(array_map(
            fn (string $id) => (int) $id,
            $matches[1] ?? [],
        )));
    }

    /**
     * @param  iterable<int, User|array{id: int, username?: string|null}>  $allowedUsers
     * @return list<int>
     */
    public function resolveMentionedUserIds(string $body, iterable $allowedUsers): array
    {
        $allowedIds = [];
        $byUsername = [];

        foreach ($allowedUsers as $user) {
            if ($user instanceof User) {
                $id = (int) $user->id;
                $allowedIds[$id] = true;
                $username = Str::lower(trim((string) $user->username));
                if ($username !== '') {
                    $byUsername[$username] = $id;
                }
                continue;
            }

            $id = (int) ($user['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $allowedIds[$id] = true;
            $username = Str::lower(trim((string) ($user['username'] ?? '')));
            if ($username !== '') {
                $byUsername[$username] = $id;
            }
        }

        $ids = [];
        foreach ($this->extractUsernames($body) as $username) {
            if (isset($byUsername[$username])) {
                $ids[] = $byUsername[$username];
            }
        }

        foreach ($this->extractMentionIds($body) as $id) {
            if (isset($allowedIds[$id])) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $userIds
     */
    public function syncMentions(Model $message, array $userIds): void
    {
        $message->mentions()->delete();

        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id) => $id > 0)));
        if ($userIds === []) {
            return;
        }

        $now = now();
        foreach ($userIds as $userId) {
            $message->mentions()->create([
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function mentionPayloadForMessage(Model $message): array
    {
        $message->loadMissing(['mentions.user:id,name,username']);

        return $message->mentions
            ->map(fn (ChatMessageMention $mention) => [
                'id' => (int) $mention->user_id,
                'name' => (string) ($mention->user?->name ?? ''),
                'username' => (string) ($mention->user?->username ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function mentionableUsersForTeam(int $teamId): array
    {
        $ids = $this->teamChatMembers->memberIdsForTeam($teamId);
        if ($ids === []) {
            return [];
        }

        return $this->usersPayload($ids);
    }

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function mentionableUsersForPrivateRoom(int $roomId): array
    {
        $room = PrivateChatRoom::query()->find($roomId);
        if (! $room) {
            return [];
        }

        return $this->usersPayload(
            $room->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    public function mentionableUsersForDirectConversation(int $conversationId): array
    {
        $conversation = DirectConversation::query()->find($conversationId);
        if (! $conversation) {
            return [];
        }

        return $this->usersPayload(
            $conversation->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    /**
     * @return list<int>
     */
    public function processTeamMessage(TeamChatMessage $message, ?int $actorId = null): array
    {
        $allowed = $this->mentionableUsersForTeam((int) $message->team_id);
        $mentionedIds = $this->resolveMentionedUserIds((string) $message->body, $allowed);
        $this->syncMentions($message, $mentionedIds);
        $this->notifyMentionedUsers($message, $mentionedIds, $actorId);

        return $mentionedIds;
    }

    /**
     * @return list<int>
     */
    public function processPrivateMessage(PrivateChatMessage $message, PrivateChatRoom $room, ?int $actorId = null): array
    {
        $allowed = $this->mentionableUsersForPrivateRoom((int) $room->id);
        $mentionedIds = $this->resolveMentionedUserIds((string) $message->body, $allowed);
        $this->syncMentions($message, $mentionedIds);
        $this->notifyMentionedUsers($message, $mentionedIds, $actorId, $room->name, 'private_room', [
            'private_room_id' => $room->id,
        ]);

        return $mentionedIds;
    }

    /**
     * @return list<int>
     */
    public function processDirectMessage(DirectMessage $message, DirectConversation $conversation, ?int $actorId = null): array
    {
        $allowed = $this->mentionableUsersForDirectConversation((int) $conversation->id);
        $mentionedIds = $this->resolveMentionedUserIds((string) $message->body, $allowed);
        $this->syncMentions($message, $mentionedIds);
        $this->notifyMentionedUsers($message, $mentionedIds, $actorId, null, 'direct', [
            'direct_conversation_id' => $conversation->id,
        ]);

        return $mentionedIds;
    }

    public function resyncForEditedMessage(Model $message): void
    {
        if ($message instanceof TeamChatMessage) {
            $allowed = $this->mentionableUsersForTeam((int) $message->team_id);
        } elseif ($message instanceof PrivateChatMessage) {
            $allowed = $this->mentionableUsersForPrivateRoom((int) $message->private_chat_room_id);
        } elseif ($message instanceof DirectMessage) {
            $allowed = $this->mentionableUsersForDirectConversation((int) $message->direct_conversation_id);
        } else {
            return;
        }

        $mentionedIds = $this->resolveMentionedUserIds((string) $message->body, $allowed);
        $this->syncMentions($message, $mentionedIds);
    }

    /**
     * @param  list<int>  $mentionedIds
     * @param  array<string, mixed>  $metaExtra
     */
    private function notifyMentionedUsers(
        Model $message,
        array $mentionedIds,
        ?int $actorId,
        ?string $contextLabel = null,
        ?string $chatKind = null,
        array $metaExtra = [],
    ): void {
        if ($mentionedIds === []) {
            return;
        }

        $message->loadMissing('user:id,name');
        $senderName = $message->user?->name ?? 'موظف';
        $preview = trim(Str::limit(strip_tags((string) ($message->body ?? '')), 120));

        $link = match (true) {
            $message instanceof TeamChatMessage => route('chat.index', [
                'tab' => 'all',
                'team' => Team::query()->whereKey($message->team_id)->value('slug'),
            ]),
            $message instanceof PrivateChatMessage => route('chat.index', [
                'tab' => 'rooms',
                'private_room' => $message->private_chat_room_id,
            ]),
            $message instanceof DirectMessage => route('chat.index', [
                'tab' => 'direct',
                'direct' => $message->direct_conversation_id,
            ]),
            default => route('chat.index'),
        };

        $title = $contextLabel
            ? 'تم ذكرك في '.$contextLabel
            : 'تم ذكرك في محادثة';

        if ($message instanceof TeamChatMessage) {
            $team = Team::query()->find($message->team_id);
            $teamName = $team?->name === 'خطوات' ? 'خارج المخزون' : ($team?->name ?? 'غرفة');
            $title = 'تم ذكرك في '.$teamName;
            $chatKind = 'team';
            $metaExtra = array_merge($metaExtra, [
                'team_id' => $message->team_id,
                'team_slug' => $team?->slug,
            ]);
        }

        if ($message instanceof DirectMessage) {
            $title = 'تم ذكرك في رسالة خاصة';
        }

        app(SmartNotificationService::class)->notifyUsers($mentionedIds, [
            'title' => $title,
            'body' => trim($senderName.': '.($preview !== '' ? $preview : 'رسالة')),
            'severity' => 'info',
            'category' => 'chat_mention',
            'link' => $link,
            'meta' => array_merge([
                'chat_kind' => $chatKind,
                'mention' => true,
                'sender_id' => $message->user_id,
            ], $metaExtra),
        ], $actorId);
    }

    /**
     * @param  list<int>  $userIds
     * @return list<array{id: int, name: string, username: string}>
     */
    private function usersPayload(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => (string) ($user->username ?? ''),
            ])
            ->values()
            ->all();
    }
}
