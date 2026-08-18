<?php

namespace App\Http\Controllers;

use App\Notifications\SystemEventNotification;
use App\Services\ChatNotificationReadService;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class ChatNotificationsController extends Controller
{
    public function __construct(
        private readonly SystemNotificationService $systemNotificationService,
        private readonly ChatNotificationReadService $chatNotificationRead,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
            ]);
        }

        $this->systemNotificationService->syncForUser($user);

        $notifications = $user->notifications()
            ->where('type', SystemEventNotification::class)
            ->where('data->category', 'chat')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $n) => ChatNotificationReadService::mapNotificationForFeed($n))
            ->values();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->chatNotificationRead->unreadChatNotificationsCount($user),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('notifications')) {
            return response()->json(['ok' => true, 'unread_count' => 0]);
        }

        $user->unreadNotifications()
            ->where('type', SystemEventNotification::class)
            ->where('data->category', 'chat')
            ->get()
            ->each(fn (DatabaseNotification $n) => $n->markAsRead());

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}
