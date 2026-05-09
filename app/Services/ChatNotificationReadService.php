<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemEventNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChatNotificationReadService
{
    public function markReadForActiveThread(?User $user, string $viewKind, ?array $selectedTeam, ?array $selectedPrivateRoom, ?array $selectedDirect): void
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return;
        }

        $notifications = $user->unreadNotifications()
            ->where('type', SystemEventNotification::class)
            ->get();

        foreach ($notifications as $notification) {
            $data = $notification->data;
            if (($data['category'] ?? '') !== 'chat') {
                continue;
            }
            $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

            $matched = match ($viewKind) {
                'team' => $selectedTeam !== null
                    && ($meta['chat_kind'] ?? '') === 'team'
                    && isset($meta['team_id'])
                    && (int) $meta['team_id'] === (int) ($selectedTeam['id'] ?? 0),
                'private_room' => $selectedPrivateRoom !== null
                    && ($meta['chat_kind'] ?? '') === 'private_room'
                    && isset($meta['private_room_id'])
                    && (int) $meta['private_room_id'] === (int) ($selectedPrivateRoom['id'] ?? 0),
                'direct' => $selectedDirect !== null
                    && ($meta['chat_kind'] ?? '') === 'direct'
                    && isset($meta['direct_conversation_id'])
                    && (int) $meta['direct_conversation_id'] === (int) ($selectedDirect['id'] ?? 0),
                default => false,
            };

            if ($matched) {
                $notification->markAsRead();
            }
        }
    }

    /**
     * عدد إشعارات الدردشة في الجدول غير المقروءة (نوع قاعدة الإشعارات).
     */
    public function unreadChatNotificationsCount(?User $user): int
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return $user->unreadNotifications()
            ->where('type', SystemEventNotification::class)
            ->where('data->category', 'chat')
            ->count();
    }

    /**
     * عدد الإشعارات غير الدردشة (للجرس العام في الشريط العلوي).
     */
    public function unreadNonChatNotificationsCount(?User $user): int
    {
        if (! $user || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return $user->unreadNotifications()
            ->where(function ($q): void {
                $q->where('data->category', '<>', 'chat')
                    ->orWhereNull('data->category');
            })
            ->count();
    }

    /**
     * تجهيز صف واحد للعرض في لوحة إشعارات الدردشة.
     *
     * @return array<string, mixed>
     */
    public static function mapNotificationForFeed(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        return [
            'id' => $notification->id,
            'title' => (string) ($data['title'] ?? 'رسالة'),
            'body' => (string) ($data['body'] ?? ''),
            'link' => $data['link'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'chat_kind' => (string) ($meta['chat_kind'] ?? ''),
            'preview' => Str::limit(strip_tags((string) ($meta['preview'] ?? $data['body'] ?? '')), 120),
        ];
    }
}
