<?php

namespace App\Http\Middleware;

use App\Http\Controllers\TeamNotebookController;
use App\Models\BoardColumn;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Services\ChatNotificationReadService;
use App\Services\ChatUnreadService;
use App\Services\EmployeePresenceService;
use App\Services\NativePushService;
use App\Services\NavigationVisibilityService;
use App\Support\EffectiveSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $systemNotificationsUnread = 0;
        $chatNotificationsUnread = 0;
        $chatMessagesUnreadTotal = 0;

        if ($user) {
            if (Schema::hasTable('notifications')) {
                $chatEventsRead = app(ChatNotificationReadService::class);
                $systemNotificationsUnread = $chatEventsRead->unreadNonChatNotificationsCount($user);
                $chatNotificationsUnread = $chatEventsRead->unreadChatNotificationsCount($user);
            }
            $chatMessagesUnreadTotal = app(ChatUnreadService::class)->fullUnreadPayload($user)['totalUnreadMessages'];
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
                ] : null,
                'can' => [
                    'manageEmployees' => $user ? Gate::forUser($user)->allows('manage-employees') : false,
                    'deleteRecords' => $user ? $user->isAdmin() : false,
                    'viewAdminHome' => $user ? Gate::forUser($user)->allows('view-admin-home') : false,
                    'viewWarehouse' => $user ? Gate::forUser($user)->allows('view-warehouse') : false,
                    'manageCampaignUpdates' => $user ? Gate::forUser($user)->allows('manage-campaign-updates') : false,
                    'viewClientPortalLink' => $user ? Gate::forUser($user)->allows('view-client-portal-link') : false,
                    'manageSystemSettings' => $user ? Gate::forUser($user)->allows('manage-system-settings') : false,
                    'viewSalesAnalytics' => $user ? $this->canViewSalesAnalytics($user) : false,
                ],
            ],
            'notifications' => [
                'unread_count' => $systemNotificationsUnread,
                'chat_notifications_unread' => $chatNotificationsUnread,
                'chat_messages_unread_total' => $chatMessagesUnreadTotal,
                'webpush_public_key' => config('services.webpush.public_key'),
                'native_fcm_configured' => NativePushService::isConfigured(),
                'firebase_mobile_push_enabled' => EffectiveSettings::firebaseMobilePushEnabled(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'team_notebook' => fn () => TeamNotebookController::sharedPayloadForRequest($request),
            'nav_team_routes' => fn () => app(NavigationVisibilityService::class)->allowedRouteNamesForUser($user),
            'attendance' => fn () => $this->attendancePayload($user),
            'tickets_meta' => fn () => $this->ticketsPayload($user),
        ];
    }

    private function canViewSalesAnalytics($user): bool
    {
        if (! $user) {
            return false;
        }
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        $isHr = method_exists($user, 'isHrManager') ? $user->isHrManager() : false;
        if ($isAdmin || $isHr) {
            return true;
        }

        return (bool) $user->teams()
            ->where('slug', 'sales')
            ->wherePivot('is_lead', true)
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attendancePayload($user): ?array
    {
        if (! $user || ! Schema::hasTable('employee_attendances')) {
            return null;
        }

        try {
            $presence = app(EmployeePresenceService::class);
            $needs = $presence->needsDailyCheckIn($user);

            return [
                'needs_check_in' => $needs,
                'today_active_seconds' => (int) optional($presence->attendanceForToday($user))->active_seconds,
                'open_tasks' => $needs ? $this->openTasksForUser((int) $user->id) : [],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openTasksForUser(int $userId): array
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('board_columns')) {
            return [];
        }

        try {
            $doneColumnIds = BoardColumn::query()
                ->whereIn('name', ['تم', 'مكتمل', 'منجز', 'Done', 'Completed'])
                ->pluck('id')
                ->all();
            $inProgressId = BoardColumn::query()->where('name', 'قيد التنفيذ')->value('id');

            $tasks = Task::query()
                ->select('tasks.*')
                ->with(['column:id,name', 'client:id,name'])
                ->leftJoin('task_assignees', 'task_assignees.task_id', '=', 'tasks.id')
                ->where(function ($q) use ($userId) {
                    $q->where('tasks.assignee_id', $userId)->orWhere('task_assignees.user_id', $userId);
                })
                ->when(Schema::hasColumn('tasks', 'archived_at'), fn ($q) => $q->whereNull('tasks.archived_at'))
                ->when(! empty($doneColumnIds), fn ($q) => $q->whereNotIn('tasks.board_column_id', $doneColumnIds))
                ->distinct()
                ->orderByRaw('CASE WHEN tasks.due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('tasks.due_at')
                ->limit(40)
                ->get();

            return $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'column_name' => $task->column?->name,
                'is_in_progress' => $inProgressId && (int) $task->board_column_id === (int) $inProgressId,
                'due_at' => $task->due_at?->toIso8601String(),
                'is_overdue' => $task->due_at !== null && $task->due_at->isPast(),
                'client_name' => $task->client?->name,
            ])->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ticketsPayload($user): ?array
    {
        if (! $user || ! Schema::hasTable('support_tickets')) {
            return null;
        }

        try {
            $isAdmin = method_exists($user, 'isAdmin') && $user->isAdmin();
            $canTriage = $isAdmin || Gate::forUser($user)->allows('manage-employees');

            $query = SupportTicket::query()->whereNotIn('status', ['resolved', 'closed']);
            if (! $canTriage) {
                $query->where(function ($q) use ($user) {
                    $q->where('reporter_id', $user->id)->orWhere('assignee_id', $user->id);
                });
            }

            return [
                'open_count' => (int) (clone $query)->count(),
                'critical_open_count' => (int) (clone $query)->where('priority', 'critical')->count(),
                'mine_open_count' => (int) SupportTicket::query()
                    ->where('assignee_id', $user->id)
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->count(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
