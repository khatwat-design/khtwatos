<?php

namespace App\Http\Controllers;

use App\Services\ChatNotificationReadService;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function __construct(
        private readonly SystemNotificationService $systemNotificationService,
        private readonly ChatNotificationReadService $chatNotificationRead,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! Schema::hasTable('notifications')) {
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
                'chat_notifications_unread' => 0,
            ]);
        }

        // Keep deadline alerts fresh even without page navigation.
        $this->systemNotificationService->syncForUser($user);

        $scope = $request->query('scope', 'all');
        if (! in_array($scope, ['all', 'system', 'chat'], true)) {
            $scope = 'all';
        }

        $baseQuery = $user->notifications()->latest();

        if ($scope === 'system') {
            $baseQuery->where(function ($q): void {
                $q->whereNull('data->category')
                    ->orWhere('data->category', '<>', 'chat');
            });
        } elseif ($scope === 'chat') {
            $baseQuery->where('data->category', 'chat');
        }

        $notifications = $baseQuery
            ->limit(25)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])
            ->values();

        $systemUnread = $this->chatNotificationRead->unreadNonChatNotificationsCount($user);
        $chatUnread = $this->chatNotificationRead->unreadChatNotificationsCount($user);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => match ($scope) {
                'system' => $systemUnread,
                'chat' => $chatUnread,
                default => (int) $user->unreadNotifications()->count(),
            },
            'chat_notifications_unread' => $chatUnread,
            'system_notifications_unread' => $systemUnread,
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! Schema::hasTable('notifications')) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
                'chat_notifications_unread' => 0,
            ]);
        }

        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => $this->chatNotificationRead->unreadNonChatNotificationsCount($user),
            'chat_notifications_unread' => $this->chatNotificationRead->unreadChatNotificationsCount($user),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! Schema::hasTable('notifications')) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
                'chat_notifications_unread' => 0,
            ]);
        }

        // لا يمسّ إشعارات الدردشة — لها لوحة ومسار مستقلان.
        $user->unreadNotifications()
            ->where(function ($q): void {
                $q->whereNull('data->category')
                    ->orWhere('data->category', '<>', 'chat');
            })
            ->get()
            ->each(fn ($n) => $n->markAsRead());

        return response()->json([
            'ok' => true,
            'unread_count' => $this->chatNotificationRead->unreadNonChatNotificationsCount($user),
            'chat_notifications_unread' => $this->chatNotificationRead->unreadChatNotificationsCount($user),
        ]);
    }
}
