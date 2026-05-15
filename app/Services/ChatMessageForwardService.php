<?php

namespace App\Services;

use App\Events\TeamChatMessageCreated;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatMessageForwardService
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatUnreadService $chatUnread,
    ) {}

    /**
     * @return array{redirect_url: string}
     */
    public function forward(
        User $actor,
        string $sourceKind,
        int $sourceMessageId,
        string $targetKind,
        int $targetId,
    ): array {
        if ($sourceKind === $targetKind && $this->sameTarget($sourceKind, $sourceMessageId, $targetId, $actor)) {
            throw ValidationException::withMessages([
                'target_id' => 'لا يمكن تحويل الرسالة إلى نفس المحادثة.',
            ]);
        }

        $source = $this->resolveSourceMessage($sourceKind, $sourceMessageId, $actor);
        $fromUserName = (string) ($source['author_name'] ?? '');
        $fromContext = (string) ($source['context_label'] ?? '');
        $body = (string) ($source['body'] ?? '');
        $attachment = $source['attachment'] ?? null;

        if ($body === '' && $attachment === null) {
            throw ValidationException::withMessages([
                'source_message_id' => 'لا يوجد محتوى قابل للتحويل في هذه الرسالة.',
            ]);
        }

        $newMessage = $this->createTargetMessage(
            $actor,
            $targetKind,
            $targetId,
            $body,
            $attachment,
            $fromUserName,
            $fromContext,
        );

        return [
            'redirect_url' => $this->redirectUrlForMessage($targetKind, $newMessage),
        ];
    }

    /**
     * @return array{body: string, author_name: string, context_label: string, attachment: ?array{path: string, name: ?string, mime: ?string, size: ?int}}
     */
    private function resolveSourceMessage(string $kind, int $messageId, User $actor): array
    {
        return match ($kind) {
            'team' => $this->resolveTeamSource($messageId),
            'private_room' => $this->resolvePrivateSource($messageId, $actor),
            'direct' => $this->resolveDirectSource($messageId, $actor),
            default => throw ValidationException::withMessages(['source_kind' => 'نوع المصدر غير مدعوم.']),
        };
    }

    /**
     * @return array{body: string, author_name: string, context_label: string, attachment: ?array{path: string, name: ?string, mime: ?string, size: ?int}}
     */
    private function resolveTeamSource(int $messageId): array
    {
        $message = TeamChatMessage::query()->with(['user:id,name,avatar_path', 'team:id,name,slug'])->findOrFail($messageId);
        $teamName = (string) ($message->team?->name ?? 'فريق');
        if ($teamName === 'خطوات') {
            $teamName = 'خارج المخزون';
        }

        return [
            'body' => (string) $message->body,
            'author_name' => (string) ($message->user?->name ?? 'عضو'),
            'context_label' => 'دردشة '.$teamName,
            'attachment' => $this->attachmentFromModel($message),
        ];
    }

    /**
     * @return array{body: string, author_name: string, context_label: string, attachment: ?array{path: string, name: ?string, mime: ?string, size: ?int}}
     */
    private function resolvePrivateSource(int $messageId, User $actor): array
    {
        $message = PrivateChatMessage::query()
            ->with(['user:id,name,avatar_path', 'room:id,name'])
            ->findOrFail($messageId);

        $room = $message->room;
        if (! $room || ! $room->userIsMember($actor)) {
            abort(403);
        }

        return [
            'body' => (string) ($message->body ?? ''),
            'author_name' => (string) ($message->user?->name ?? 'عضو'),
            'context_label' => 'غرفة '.(string) ($room->name ?? ''),
            'attachment' => $this->attachmentFromModel($message),
        ];
    }

    /**
     * @return array{body: string, author_name: string, context_label: string, attachment: ?array{path: string, name: ?string, mime: ?string, size: ?int}}
     */
    private function resolveDirectSource(int $messageId, User $actor): array
    {
        $message = DirectMessage::query()
            ->with(['user:id,name,avatar_path', 'conversation.users:id,name,avatar_path'])
            ->findOrFail($messageId);

        $conversation = $message->conversation;
        if (! $conversation || ! $conversation->userParticipates($actor)) {
            abort(403);
        }

        $peer = $conversation->otherUser($actor);

        return [
            'body' => (string) ($message->body ?? ''),
            'author_name' => (string) ($message->user?->name ?? 'عضو'),
            'context_label' => $peer
                ? 'محادثة مع '.(string) $peer->name
                : 'محادثة خاصة',
            'attachment' => $this->attachmentFromModel($message),
        ];
    }

    /**
     * @param  ?array{path: string, name: ?string, mime: ?string, size: ?int}  $attachment
     */
    private function createTargetMessage(
        User $actor,
        string $targetKind,
        int $targetId,
        string $body,
        ?array $attachment,
        string $fromUserName,
        string $fromContext,
    ): TeamChatMessage|PrivateChatMessage|DirectMessage {
        $copied = $this->copyAttachment($attachment);

        return match ($targetKind) {
            'team' => $this->createTeamMessage($actor, $targetId, $body, $copied, $fromUserName, $fromContext),
            'private_room' => $this->createPrivateMessage($actor, $targetId, $body, $copied, $fromUserName, $fromContext),
            'direct' => $this->createDirectMessage($actor, $targetId, $body, $copied, $fromUserName, $fromContext),
            default => throw ValidationException::withMessages(['target_kind' => 'نوع الوجهة غير مدعوم.']),
        };
    }

    /**
     * @param  ?array{path: string, name: ?string, mime: ?string, size: ?int}  $attachment
     * @return array{path: ?string, name: ?string, mime: ?string, size: ?int}
     */
    private function copyAttachment(?array $attachment): array
    {
        if ($attachment === null || empty($attachment['path'])) {
            return [
                'path' => null,
                'name' => null,
                'mime' => null,
                'size' => null,
            ];
        }

        $sourcePath = $attachment['path'];
        if (! Storage::disk('public')->exists($sourcePath)) {
            return [
                'path' => null,
                'name' => $attachment['name'] ?? null,
                'mime' => $attachment['mime'] ?? null,
                'size' => $attachment['size'] ?? null,
            ];
        }

        $basename = basename($sourcePath);
        $dest = 'chat-attachments/forward-'.Str::uuid().'-'.$basename;
        Storage::disk('public')->copy($sourcePath, $dest);

        return [
            'path' => $dest,
            'name' => $attachment['name'] ?? null,
            'mime' => $attachment['mime'] ?? null,
            'size' => $attachment['size'] ?? null,
        ];
    }

    /**
     * @param  array{path: ?string, name: ?string, mime: ?string, size: ?int}  $attachment
     */
    private function createTeamMessage(
        User $actor,
        int $teamId,
        string $body,
        array $attachment,
        string $fromUserName,
        string $fromContext,
    ): TeamChatMessage {
        Team::query()->findOrFail($teamId);

        $message = TeamChatMessage::query()->create([
            'team_id' => $teamId,
            'user_id' => $actor->id,
            'body' => $body,
            'forwarded_from_user_name' => $fromUserName,
            'forwarded_from_context' => $fromContext,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachment_mime' => $attachment['mime'],
            'attachment_size' => $attachment['size'],
        ]);
        $message->load('user:id,name,avatar_path');
        broadcast(new TeamChatMessageCreated($message));
        $this->smartNotifications->notifyTeamChatMessage($message, (int) $actor->id);

        return $message;
    }

    /**
     * @param  array{path: ?string, name: ?string, mime: ?string, size: ?int}  $attachment
     */
    private function createPrivateMessage(
        User $actor,
        int $roomId,
        string $body,
        array $attachment,
        string $fromUserName,
        string $fromContext,
    ): PrivateChatMessage {
        $room = PrivateChatRoom::query()->findOrFail($roomId);
        if (! $room->userIsMember($actor)) {
            abort(403);
        }

        $message = PrivateChatMessage::query()->create([
            'private_chat_room_id' => $roomId,
            'user_id' => $actor->id,
            'body' => $body,
            'forwarded_from_user_name' => $fromUserName,
            'forwarded_from_context' => $fromContext,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachment_mime' => $attachment['mime'],
            'attachment_size' => $attachment['size'],
        ]);
        $message->load('user:id,name,avatar_path');
        $this->smartNotifications->notifyPrivateRoomMessage($room, $message, (int) $actor->id);

        return $message;
    }

    /**
     * @param  array{path: ?string, name: ?string, mime: ?string, size: ?int}  $attachment
     */
    private function createDirectMessage(
        User $actor,
        int $conversationId,
        string $body,
        array $attachment,
        string $fromUserName,
        string $fromContext,
    ): DirectMessage {
        $conversation = DirectConversation::query()->findOrFail($conversationId);
        if (! $conversation->userParticipates($actor)) {
            abort(403);
        }

        $message = DirectMessage::query()->create([
            'direct_conversation_id' => $conversationId,
            'user_id' => $actor->id,
            'body' => $body,
            'forwarded_from_user_name' => $fromUserName,
            'forwarded_from_context' => $fromContext,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachment_mime' => $attachment['mime'],
            'attachment_size' => $attachment['size'],
        ]);
        $message->load('user:id,name,avatar_path');
        $this->smartNotifications->notifyDirectMessage($conversation, $message, (int) $actor->id);

        return $message;
    }

    /**
     * @return ?array{path: string, name: ?string, mime: ?string, size: ?int}
     */
    private function attachmentFromModel(TeamChatMessage|PrivateChatMessage|DirectMessage $message): ?array
    {
        if (! $message->attachment_path) {
            return null;
        }

        return [
            'path' => $message->attachment_path,
            'name' => $message->attachment_name,
            'mime' => $message->attachment_mime,
            'size' => $message->attachment_size,
        ];
    }

    private function sameTarget(string $kind, int $sourceMessageId, int $targetId, User $actor): bool
    {
        return match ($kind) {
            'team' => (int) TeamChatMessage::query()->whereKey($sourceMessageId)->value('team_id') === $targetId,
            'private_room' => (int) PrivateChatMessage::query()->whereKey($sourceMessageId)->value('private_chat_room_id') === $targetId,
            'direct' => (int) DirectMessage::query()->whereKey($sourceMessageId)->value('direct_conversation_id') === $targetId,
            default => false,
        };
    }

    private function redirectUrlForMessage(string $targetKind, TeamChatMessage|PrivateChatMessage|DirectMessage $message): string
    {
        return match ($targetKind) {
            'team' => route('chat.index', [
                'tab' => 'all',
                'team' => Team::query()->whereKey($message->team_id)->value('slug'),
            ]),
            'private_room' => route('chat.index', [
                'tab' => 'rooms',
                'private_room' => $message->private_chat_room_id,
            ]),
            'direct' => route('chat.index', [
                'tab' => 'direct',
                'direct' => $message->direct_conversation_id,
            ]),
            default => route('chat.index'),
        };
    }
}
