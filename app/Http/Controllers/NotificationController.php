<?php

namespace App\Http\Controllers;

use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function __construct(private readonly SystemNotificationService $systemNotificationService)
    {
    }

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
            ]);
        }

        // Keep deadline alerts fresh even without page navigation.
        $this->systemNotificationService->syncForUser($user);

        $notifications = $user->notifications()
            ->latest()
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

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => (int) $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! Schema::hasTable('notifications')) {
            return response()->json(['ok' => true, 'unread_count' => 0]);
        }

        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'unread_count' => (int) $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (! Schema::hasTable('notifications')) {
            return response()->json(['ok' => true, 'unread_count' => 0]);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }
}

