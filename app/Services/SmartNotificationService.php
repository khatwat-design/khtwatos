<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCampaignUpdate;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\Meeting;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\User;
use App\Notifications\SystemEventNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SmartNotificationService
{
    public function __construct(private readonly WebPushService $webPushService) {}

    /**
     * @param  array<int, int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function notifyUsers(array $userIds, array $payload, ?int $actorId = null): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->when($actorId, fn (Collection $c) => $c->reject(fn ($id) => $id === (int) $actorId))
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->each(fn (User $user) => $user->notify(new SystemEventNotification($payload)));

        $this->webPushService->sendToUsers($ids->all(), $payload);
    }

    public function notifyTaskAssigned(Task $task, ?int $actorId = null): void
    {
        $assigneeIds = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (empty($assigneeIds) && $task->assignee_id) {
            $assigneeIds = [(int) $task->assignee_id];
        }

        $this->notifyUsers($assigneeIds, [
            'title' => 'مهمة جديدة تم تعيينها لك',
            'body' => $task->title,
            'severity' => 'info',
            'category' => 'tasks',
            'link' => route('tasks.index'),
            'meta' => ['task_id' => $task->id],
        ], $actorId);
    }

    public function notifyTaskMoved(Task $task, string $toColumnName, ?int $actorId = null): void
    {
        $ids = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (empty($ids) && $task->assignee_id) {
            $ids = [(int) $task->assignee_id];
        }
        $this->notifyUsers($ids, [
            'title' => 'تم تحديث حالة مهمة',
            'body' => "{$task->title} → {$toColumnName}",
            'severity' => $toColumnName === 'تم' ? 'success' : 'info',
            'category' => 'tasks',
            'link' => route('tasks.index'),
            'meta' => ['task_id' => $task->id, 'to_column' => $toColumnName],
        ], $actorId);
    }

    public function notifyMeetingCreated(Meeting $meeting, ?int $actorId = null): void
    {
        $participantIds = $meeting->participants()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if (empty($participantIds) && $meeting->user_id) {
            $participantIds = [(int) $meeting->user_id];
        }
        $this->notifyUsers($participantIds, [
            'title' => 'اجتماع جديد',
            'body' => $meeting->title,
            'severity' => 'info',
            'category' => 'meetings',
            'link' => route('meetings.index'),
            'meta' => ['meeting_id' => $meeting->id],
        ], $actorId);
    }

    public function notifyMeetingCompleted(Meeting $meeting, ?int $actorId = null): void
    {
        $participantIds = $meeting->participants()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $this->notifyUsers($participantIds, [
            'title' => 'تم إنهاء اجتماع',
            'body' => $meeting->title,
            'severity' => 'success',
            'category' => 'meetings',
            'link' => route('meetings.index'),
            'meta' => ['meeting_id' => $meeting->id],
        ], $actorId);
    }

    public function notifyClientStageChanged(Client $client, string $stageLabel, ?int $actorId = null): void
    {
        $targets = [
            (int) ($client->account_manager_id ?? 0),
            (int) ($client->campaign_manager_id ?? 0),
        ];
        $this->notifyUsers($targets, [
            'title' => 'تحديث مرحلة عميل',
            'body' => "{$client->name} انتقل إلى {$stageLabel}",
            'severity' => 'info',
            'category' => 'clients',
            'link' => route('clients.show', $client),
            'meta' => ['client_id' => $client->id],
        ], $actorId);
    }

    public function notifyWarehouseUpdated(ClientCampaignUpdate $update, ?int $actorId = null): void
    {
        $team = Team::query()->where('slug', 'media-buyer')->first();
        $ids = $team
            ? $team->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $this->notifyUsers($ids, [
            'title' => 'تحديث جديد في المخزن',
            'body' => ($update->client?->name ? $update->client->name.' - ' : '').'تم تحديث بيانات الحملة',
            'severity' => 'info',
            'category' => 'warehouse',
            'link' => route('warehouse.index', ['client_id' => $update->client_id]),
            'meta' => ['client_id' => $update->client_id],
        ], $actorId);
    }

    public function notifyTeamChatMessage(TeamChatMessage $message, ?int $actorId = null): void
    {
        $team = Team::query()->find($message->team_id);
        if (! $team) {
            return;
        }

        $ids = $team->users()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $senderName = $message->relationLoaded('user') ? ($message->user?->name ?? 'موظف') : (User::query()->find($message->user_id)?->name ?? 'موظف');
        $preview = trim(Str::limit(strip_tags((string) ($message->body ?? '')), 120));

        $this->notifyUsers($ids, [
            'title' => 'رسالة في '.$team->name,
            'body' => trim($senderName.': '.($preview !== '' ? $preview : 'رسالة أو مرفق')),
            'severity' => 'info',
            'category' => 'chat',
            'link' => route('chat.index', ['tab' => 'all', 'team' => $team->slug]),
            'meta' => [
                'chat_kind' => 'team',
                'team_id' => $team->id,
                'team_slug' => $team->slug,
                'preview' => $preview,
                'sender_id' => $message->user_id,
            ],
        ], $actorId);
    }

    public function notifyPrivateRoomMessage(PrivateChatRoom $room, PrivateChatMessage $message, ?int $actorId = null): void
    {
        $ids = $room->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $senderName = $message->relationLoaded('user') ? ($message->user?->name ?? 'موظف') : (User::query()->find($message->user_id)?->name ?? 'موظف');
        $preview = trim(Str::limit(strip_tags((string) ($message->body ?? '')), 120));

        $this->notifyUsers($ids, [
            'title' => 'غرفة: '.$room->name,
            'body' => trim($senderName.': '.($preview !== '' ? $preview : 'رسالة أو مرفق')),
            'severity' => 'info',
            'category' => 'chat',
            'link' => route('chat.index', ['tab' => 'rooms', 'private_room' => $room->id]),
            'meta' => [
                'chat_kind' => 'private_room',
                'private_room_id' => $room->id,
                'preview' => $preview,
                'sender_id' => $message->user_id,
            ],
        ], $actorId);
    }

    public function notifyDirectMessage(DirectConversation $conversation, DirectMessage $message, ?int $actorId = null): void
    {
        $recipientIds = $conversation->users()
            ->where('users.id', '!=', $message->user_id)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($recipientIds)) {
            return;
        }

        $sender = $message->relationLoaded('user') ? $message->user : User::query()->find($message->user_id);
        $senderName = $sender?->name ?? 'موظف';
        $preview = trim(Str::limit(strip_tags((string) ($message->body ?? '')), 120));

        $this->notifyUsers($recipientIds, [
            'title' => 'رسالة من '.$senderName,
            'body' => $preview !== '' ? $preview : 'رسالة أو مرفق',
            'severity' => 'info',
            'category' => 'chat',
            'link' => route('chat.index', ['tab' => 'direct', 'direct' => $conversation->id]),
            'meta' => [
                'chat_kind' => 'direct',
                'direct_conversation_id' => $conversation->id,
                'preview' => $preview,
                'sender_id' => $message->user_id,
            ],
        ], $actorId);
    }
}
