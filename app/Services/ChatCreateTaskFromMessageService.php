<?php

namespace App\Services;

use App\Events\TeamChatMessageCreated;
use App\Models\BoardColumn;
use App\Models\ChatTaskLink;
use App\Models\Client;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\User;
use App\Support\TaskBoardDefaults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatCreateTaskFromMessageService
{
    public function __construct(
        private readonly TeamChatMemberService $teamChatMembers,
        private readonly ChatMentionService $chatMentions,
        private readonly ChatReadReceiptService $readReceipts,
        private readonly ChatUnreadService $chatUnread,
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatTaskCardEnricher $taskCards,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{task: array<string, mixed>, message: array<string, mixed>}
     */
    public function create(User $actor, array $data): array
    {
        $context = (string) ($data['context'] ?? '');
        $team = Team::query()->where('slug', (string) ($data['team_slug'] ?? ''))->first();
        if (! $team) {
            throw ValidationException::withMessages(['team_slug' => 'الفريق غير موجود.']);
        }

        $this->assertCanAccessContext($actor, $context, $data);

        $sourceMessage = $this->resolveSourceMessage($context, $data);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' && $sourceMessage) {
            $title = $this->titleFromMessageBody((string) ($sourceMessage['body'] ?? ''));
        }
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'عنوان المهمة مطلوب.']);
        }

        $assigneeIds = $this->resolveAssigneeIds($data, $sourceMessage, $actor);
        $description = $this->buildDescription($data, $sourceMessage, $context);

        $columnId = $this->defaultColumnIdForTeam($team);
        $task = $this->createTask($team, $columnId, $title, $description, $data, $assigneeIds);
        $announcement = $this->postAnnouncement($actor, $context, $data, $task, $title);

        ChatTaskLink::query()->create([
            'task_id' => $task->id,
            'message_type' => $announcement['message_type'],
            'message_id' => $announcement['message_id'],
            'created_by_user_id' => $actor->id,
        ]);

        $messagePayload = $announcement['payload'];
        $messagePayload['task_card'] = $this->taskCardPayload($task);

        return [
            'task' => $this->taskPayload($task),
            'message' => $messagePayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCanAccessContext(User $actor, string $context, array $data): void
    {
        match ($context) {
            'team' => abort_unless(
                $this->teamChatMembers->userCanAccessTeam($actor, (int) ($data['team_id'] ?? 0)),
                403,
            ),
            'private_room' => abort_unless(
                ($room = PrivateChatRoom::query()->find((int) ($data['private_room_id'] ?? 0)))
                    && $room->userIsMember($actor),
                403,
            ),
            'direct' => abort_unless(
                ($conversation = DirectConversation::query()->find((int) ($data['direct_conversation_id'] ?? 0)))
                    && $conversation->userParticipates($actor),
                403,
            ),
            default => throw ValidationException::withMessages(['context' => 'سياق الدردشة غير صالح.']),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{body?: string, mentions?: array<int, mixed>}|null
     */
    private function resolveSourceMessage(string $context, array $data): ?array
    {
        $messageId = (int) ($data['source_message_id'] ?? 0);
        if ($messageId <= 0) {
            return null;
        }

        return match ($context) {
            'team' => TeamChatMessage::query()
                ->where('team_id', (int) $data['team_id'])
                ->whereKey($messageId)
                ->with(['mentions.user:id,name,username'])
                ->first()
                ?->toChatArray(),
            'private_room' => PrivateChatMessage::query()
                ->where('private_chat_room_id', (int) $data['private_room_id'])
                ->whereKey($messageId)
                ->with(['mentions.user:id,name,username'])
                ->first()
                ?->toChatArray(),
            'direct' => DirectMessage::query()
                ->where('direct_conversation_id', (int) $data['direct_conversation_id'])
                ->whereKey($messageId)
                ->with(['mentions.user:id,name,username'])
                ->first()
                ?->toChatArray(),
            default => null,
        };
    }

    private function titleFromMessageBody(string $body): string
    {
        $plain = trim(preg_replace('/@\[[\d]+\]|@[a-zA-Z0-9][a-zA-Z0-9._-]{1,31}/u', '', $body) ?? $body);
        $plain = trim(preg_replace('/\s+/u', ' ', $plain));

        return Str::limit($plain, 120, '…');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{body?: string, mentions?: array<int, mixed>}|null  $sourceMessage
     * @return list<int>
     */
    private function resolveAssigneeIds(array $data, ?array $sourceMessage, User $actor): array
    {
        $ids = collect($data['assignee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($sourceMessage) {
            foreach ($sourceMessage['mentions'] ?? [] as $mention) {
                $uid = (int) ($mention['user']['id'] ?? $mention['user_id'] ?? 0);
                if ($uid > 0) {
                    $ids->push($uid);
                }
            }
            $body = (string) ($sourceMessage['body'] ?? '');
            $mentionIds = $this->chatMentions->extractMentionIds($body);
            foreach ($mentionIds as $mentionId) {
                $ids->push($mentionId);
            }
        }

        $ids->push((int) $actor->id);

        return $ids->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{body?: string}|null  $sourceMessage
     */
    private function buildDescription(array $data, ?array $sourceMessage, string $context): ?string
    {
        $parts = [];
        $manual = trim((string) ($data['description'] ?? ''));
        if ($manual !== '') {
            $parts[] = $manual;
        }
        if ($sourceMessage && trim((string) ($sourceMessage['body'] ?? '')) !== '') {
            $parts[] = 'من الدردشة ('.$this->contextLabel($context)."):\n".trim((string) $sourceMessage['body']);
        }

        if ($parts === []) {
            return null;
        }

        return Str::limit(implode("\n\n", $parts), 5000, '');
    }

    private function contextLabel(string $context): string
    {
        return match ($context) {
            'team' => 'فريق',
            'private_room' => 'غرفة خاصة',
            'direct' => 'رسالة مباشرة',
            default => 'دردشة',
        };
    }

    private function defaultColumnIdForTeam(Team $team): int
    {
        $board = TaskBoard::query()->firstOrCreate(
            ['team_id' => $team->id],
            ['name' => 'لوحة '.$team->name],
        );

        foreach (TaskBoardDefaults::COLUMNS as $col) {
            BoardColumn::query()->updateOrCreate(
                [
                    'task_board_id' => $board->id,
                    'name' => $col['name'],
                ],
                ['sort_order' => $col['sort_order']],
            );
        }

        $column = BoardColumn::query()
            ->where('task_board_id', $board->id)
            ->where('name', 'قائمة الانتظار')
            ->first();

        if (! $column) {
            $column = BoardColumn::query()
                ->where('task_board_id', $board->id)
                ->orderBy('sort_order')
                ->first();
        }

        if (! $column) {
            throw ValidationException::withMessages(['team_slug' => 'لوحة المهام غير مهيأة لهذا الفريق.']);
        }

        return (int) $column->id;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeIds
     */
    private function createTask(Team $team, int $columnId, string $title, ?string $description, array $data, array $assigneeIds): Task
    {
        $column = BoardColumn::query()->with('taskBoard')->findOrFail($columnId);
        $max = (int) Task::query()->where('board_column_id', $column->id)->max('position');

        $task = Task::query()->create([
            'task_board_id' => $column->task_board_id,
            'board_column_id' => $column->id,
            'title' => $title,
            'description' => $description,
            'assignee_id' => $assigneeIds[0] ?? null,
            'client_id' => ! empty($data['client_id']) ? (int) $data['client_id'] : null,
            'position' => $max + 1,
        ]);

        $task->assignees()->sync($assigneeIds);
        $this->smartNotifications->notifyTaskAssigned($task->fresh(['assignees']), null);

        return $task->fresh(['client:id,name', 'taskBoard.team:id,slug', 'column:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{message_type: class-string, message_id: int, payload: array<string, mixed>}
     */
    private function postAnnouncement(User $actor, string $context, array $data, Task $task, string $title): array
    {
        $clientName = $task->client?->name;
        $clientSuffix = $clientName ? " — {$clientName}" : '';
        $body = "📋 تم إنشاء مهمة: «{$title}»{$clientSuffix}";

        return match ($context) {
            'team' => $this->postTeamAnnouncement($actor, (int) $data['team_id'], $body),
            'private_room' => $this->postPrivateAnnouncement($actor, (int) $data['private_room_id'], $body),
            'direct' => $this->postDirectAnnouncement($actor, (int) $data['direct_conversation_id'], $body),
            default => throw ValidationException::withMessages(['context' => 'سياق الدردشة غير صالح.']),
        };
    }

    /**
     * @return array{message_type: class-string, message_id: int, payload: array<string, mixed>}
     */
    private function postTeamAnnouncement(User $actor, int $teamId, string $body): array
    {
        $message = TeamChatMessage::query()->create([
            'team_id' => $teamId,
            'user_id' => $actor->id,
            'body' => $body,
        ]);
        $message->load(['user:id,name,avatar_path', 'mentions.user:id,name,username']);
        $this->chatMentions->processTeamMessage($message, $actor->id);
        broadcast(new TeamChatMessageCreated($message));
        $this->smartNotifications->notifyTeamChatMessage($message, $actor->id, []);
        $this->chatUnread->markTeamAsRead(request(), $teamId);

        $payload = $this->readReceipts
            ->enrichTeamMessages(collect([$message]), $teamId, (int) $actor->id)
            ->first() ?? $message->toChatArray();

        return [
            'message_type' => TeamChatMessage::class,
            'message_id' => (int) $message->id,
            'payload' => $payload,
        ];
    }

    /**
     * @return array{message_type: class-string, message_id: int, payload: array<string, mixed>}
     */
    private function postPrivateAnnouncement(User $actor, int $roomId, string $body): array
    {
        $room = PrivateChatRoom::query()->findOrFail($roomId);
        $message = PrivateChatMessage::query()->create([
            'private_chat_room_id' => $roomId,
            'user_id' => $actor->id,
            'body' => $body,
        ]);
        $message->load(['user:id,name,avatar_path', 'mentions.user:id,name,username']);
        $this->chatMentions->processPrivateMessage($message, $room, (int) $actor->id);
        $this->smartNotifications->notifyPrivateRoomMessage($room, $message, (int) $actor->id, []);
        $this->chatUnread->markPrivateRoomAsRead(request(), $roomId);

        $payload = $this->readReceipts
            ->enrichPrivateRoomMessages(collect([$message]), $roomId, (int) $actor->id)
            ->first() ?? $message->toChatArray();

        return [
            'message_type' => PrivateChatMessage::class,
            'message_id' => (int) $message->id,
            'payload' => $payload,
        ];
    }

    /**
     * @return array{message_type: class-string, message_id: int, payload: array<string, mixed>}
     */
    private function postDirectAnnouncement(User $actor, int $conversationId, string $body): array
    {
        $conversation = DirectConversation::query()->findOrFail($conversationId);
        $message = DirectMessage::query()->create([
            'direct_conversation_id' => $conversationId,
            'user_id' => $actor->id,
            'body' => $body,
        ]);
        $message->load(['user:id,name,avatar_path', 'mentions.user:id,name,username']);
        $this->chatMentions->processDirectMessage($message, $conversation, (int) $actor->id);
        $this->smartNotifications->notifyDirectMessage($conversation, $message, (int) $actor->id, []);
        $this->chatUnread->markDirectAsRead(request(), $conversationId);

        $payload = $this->readReceipts
            ->enrichDirectMessages(collect([$message]), $conversationId, (int) $actor->id)
            ->first() ?? $message->toChatArray((int) $actor->id);

        return [
            'message_type' => DirectMessage::class,
            'message_id' => (int) $message->id,
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskCardPayload(Task $task): array
    {
        return [
            'id' => (int) $task->id,
            'title' => (string) $task->title,
            'team_slug' => $task->taskBoard?->team?->slug,
            'board_column_id' => (int) $task->board_column_id,
            'column_name' => $task->column?->name,
            'client_name' => $task->client?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task): array
    {
        return $this->taskCardPayload($task);
    }
}
