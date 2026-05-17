<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\OutsideConversation;
use App\Models\PipelineStage;
use App\Models\StaffPersonalTodo;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\GoodsMetaLeadAssignmentService;
use App\Support\OutsideConversationMetrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->can('view-admin-home')) {
            return $this->renderAdminDashboard();
        }

        return $this->renderStaffDashboard($user);
    }

    private function renderAdminDashboard(): Response
    {
        $now = now();
        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        $stages = PipelineStage::query()
            ->orderBy('sort_order')
            ->get(['id', 'key', 'label']);

        $stageCounts = Client::query()
            ->selectRaw('current_pipeline_stage_id, COUNT(*) as total')
            ->groupBy('current_pipeline_stage_id')
            ->pluck('total', 'current_pipeline_stage_id');

        $clientsByStage = $stages->map(fn (PipelineStage $stage) => [
            'id' => $stage->id,
            'key' => $stage->key,
            'label' => $stage->label,
            'count' => (int) ($stageCounts[$stage->id] ?? 0),
        ])->values();

        $columnCounts = Task::query()
            ->selectRaw('board_column_id, COUNT(*) as total')
            ->groupBy('board_column_id')
            ->pluck('total', 'board_column_id');

        $columns = BoardColumn::query()
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $tasksByColumn = $columns->map(fn (BoardColumn $column) => [
            'id' => $column->id,
            'name' => $column->name,
            'count' => (int) ($columnCounts[$column->id] ?? 0),
        ])->values();

        $meetingStatusCounts = Meeting::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $teams = Team::query()
            ->where('slug', '!=', 'khatwat')
            ->orderBy('sort_order')
            ->get(['id', 'name']);
        $employeesByTeam = $teams->map(function (Team $team) {
            $all = $team->users()->count();
            $leads = $team->users()->wherePivot('is_lead', true)->count();

            return [
                'id' => $team->id,
                'name' => $team->name,
                'employees' => (int) $all,
                'leads' => (int) $leads,
            ];
        })->values();

        $campaignManagers = User::query()
            ->whereHas('teams', fn ($q) => $q->where('slug', 'media-buyer'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $clientsByCampaignManager = Client::query()
            ->selectRaw('campaign_manager_id, COUNT(*) as total')
            ->whereNotNull('campaign_manager_id')
            ->groupBy('campaign_manager_id')
            ->pluck('total', 'campaign_manager_id');

        return Inertia::render('Home/Index', [
            'dashboard_mode' => 'admin',
            'staff' => null,
            'staff_personal_todos' => [],
            'outside_metrics' => OutsideConversationMetrics::summary(),
            'cards' => [
                'clients_total' => Client::query()
                    ->where(function ($q) {
                        $q->whereNull('current_pipeline_stage_id')
                            ->orWhereHas('currentStage', fn ($q2) => $q2->where('key', '!=', 'lead'));
                    })
                    ->count(),
                'clients_leads' => Client::query()
                    ->whereHas('currentStage', fn ($q) => $q->where('key', 'lead'))
                    ->count(),
                'tasks_total' => Task::query()->count(),
                'tasks_overdue' => Task::query()
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $now)
                    ->whereHas('column', fn ($q) => $q->where('name', '!=', 'تم'))
                    ->count(),
                'meetings_total' => Meeting::query()->count(),
                'meetings_upcoming' => Meeting::query()
                    ->where('status', 'scheduled')
                    ->where('start_at', '>=', $now)
                    ->count(),
                'employees_total' => User::query()->count(),
                'employees_bookable' => User::query()->where('is_bookable', true)->count(),
            ],
            'clientsByStage' => $clientsByStage,
            'tasksByColumn' => $tasksByColumn,
            'meetings' => [
                'scheduled' => (int) ($meetingStatusCounts['scheduled'] ?? 0),
                'completed' => (int) ($meetingStatusCounts['completed'] ?? 0),
                'canceled' => (int) ($meetingStatusCounts['canceled'] ?? 0),
                'internal' => Meeting::query()->whereNull('client_id')->count(),
                'client' => Meeting::query()->whereNotNull('client_id')->count(),
                'today' => Meeting::query()->whereBetween('start_at', [$todayStart, $todayEnd])->count(),
            ],
            'employees' => [
                'admins' => User::query()->where('role', 'admin')->count(),
                'leads' => User::query()->where('role', 'lead')->count(),
                'members' => User::query()->where('role', 'member')->count(),
                'byTeam' => $employeesByTeam,
                'campaignManagers' => $campaignManagers->map(fn (User $manager) => [
                    'id' => $manager->id,
                    'name' => $manager->name,
                    'clients' => (int) ($clientsByCampaignManager[$manager->id] ?? 0),
                ])->values(),
            ],
        ]);
    }

    private function renderStaffDashboard(User $user): Response
    {
        $now = now();

        $user->load([
            'teams' => fn ($q) => $q->orderBy('teams.sort_order'),
        ]);

        $assignedTasksQuery = Task::query()
            ->whereNull('archived_at')
            ->where(function ($q) use ($user) {
                $q->where('assignee_id', $user->id)
                    ->orWhereHas('assignees', fn ($q2) => $q2->where('users.id', $user->id));
            });

        $openTasksQuery = (clone $assignedTasksQuery)->whereHas(
            'column',
            fn ($q) => $q->whereNotIn('name', $this->doneTaskColumnNames()),
        );

        $tasksAssigned = (clone $openTasksQuery)->count();

        $tasksOverdue = (clone $openTasksQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        $meetingsUpcoming = Meeting::query()
            ->where('status', 'scheduled')
            ->where('start_at', '>=', $now)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->count();

        $clientsAccount = Client::query()->where('account_manager_id', $user->id)->count();
        $clientsCampaign = Client::query()->where('campaign_manager_id', $user->id)->count();

        $outsideUnreadAssigned = OutsideConversation::query()
            ->whereHas('contact', fn ($q) => $q->where('assigned_user_id', $user->id))
            ->where('unread_count', '>', 0)
            ->count();

        $recentTasks = (clone $openTasksQuery)
            ->with(['column:id,name', 'taskBoard.team:id,slug,name'])
            ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Task $t) use ($now) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'due_at' => $t->due_at?->toIso8601String(),
                    'column_name' => $t->column?->name,
                    'team_slug' => $t->taskBoard?->team?->slug,
                    'team_name' => $t->taskBoard?->team?->name,
                    'is_overdue' => $t->due_at !== null && $t->due_at->lt($now),
                ];
            })
            ->values();

        $firstOverdueTask = (clone $openTasksQuery)
            ->with(['taskBoard.team:id,slug,name'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->first();

        $firstOverduePayload = $firstOverdueTask ? [
            'id' => $firstOverdueTask->id,
            'title' => $firstOverdueTask->title,
            'team_slug' => $firstOverdueTask->taskBoard?->team?->slug,
        ] : null;

        $upcomingMeetings = Meeting::query()
            ->with(['client:id,name'])
            ->where('status', 'scheduled')
            ->where('start_at', '>=', $now)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->orderBy('start_at')
            ->limit(8)
            ->get()
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'start_at' => $m->start_at?->toIso8601String(),
                'client_name' => $m->client?->name,
                'is_host' => (int) $m->user_id === (int) $user->id,
            ])
            ->values();

        $meetingsNext48h = Meeting::query()
            ->where('status', 'scheduled')
            ->whereBetween('start_at', [$now, $now->copy()->addHours(48)])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', fn ($q2) => $q2->where('users.id', $user->id));
            })
            ->count();

        $priorityFollowups = $this->buildStaffPriorityFollowups(
            $user,
            $tasksOverdue,
            $outsideUnreadAssigned,
            $meetingsNext48h,
            $clientsAccount,
            $clientsCampaign,
            $firstOverduePayload,
        );

        $metaLeadCounts = app(GoodsMetaLeadAssignmentService::class)->staffDashboardCounts((int) $user->id);

        $personalTodos = StaffPersonalTodo::query()
            ->where('user_id', $user->id)
            ->orderBy('is_done')
            ->orderByDesc('completed_at')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StaffPersonalTodo $todo) => [
                'id' => $todo->id,
                'title' => $todo->title,
                'is_done' => (bool) $todo->is_done,
                'completed_at' => $todo->completed_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Home/Index', [
            'dashboard_mode' => 'staff',
            'staff_personal_todos' => $personalTodos,
            'staff' => [
                'role_key' => $user->role,
                'role_label' => $this->staffRoleLabelAr($user->role),
                'teams' => $user->teams->map(fn (Team $t) => [
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'is_lead' => (bool) $t->pivot->is_lead,
                ])->values(),
                'cards' => [
                    'tasks_assigned' => $tasksAssigned,
                    'tasks_overdue' => $tasksOverdue,
                    'meetings_upcoming' => $meetingsUpcoming,
                    'clients_account_manager' => $clientsAccount,
                    'clients_campaign_manager' => $clientsCampaign,
                    'outside_unread_assigned' => $outsideUnreadAssigned,
                    'meta_leads_today' => $metaLeadCounts['leads_today'],
                    'meta_calls_upcoming' => $metaLeadCounts['upcoming_calls'],
                ],
                'is_meta_leads_rep' => app(GoodsMetaLeadAssignmentService::class)->isAssignee((int) $user->id),
                'recent_tasks' => $recentTasks,
                'first_overdue_task' => $firstOverduePayload,
                'upcoming_meetings' => $upcomingMeetings,
                'priority_followups' => $priorityFollowups,
            ],
            'outside_metrics' => null,
            'cards' => null,
            'clientsByStage' => [],
            'tasksByColumn' => [],
            'meetings' => null,
            'employees' => null,
        ]);
    }

    private function staffRoleLabelAr(string $role): string
    {
        return match ($role) {
            'admin' => 'مدير النظام',
            'lead' => 'قائد فريق',
            'member' => 'مبدع بفريق',
            default => 'موظف',
        };
    }

    /** @return list<string> */
    private function doneTaskColumnNames(): array
    {
        return ['تم', 'مكتمل', 'منجز', 'Done', 'Completed'];
    }

    /**
     * @return list<array{kind: string, title: string, detail: string, route: string|null, action_label: string|null}>
     */
    private function buildStaffPriorityFollowups(
        User $user,
        int $tasksOverdue,
        int $outsideUnreadAssigned,
        int $meetingsNext48h,
        int $clientsAccount,
        int $clientsCampaign,
        ?array $firstOverdueTask = null,
    ): array {
        $items = [];

        if ($tasksOverdue > 0) {
            $overdueParams = $firstOverdueTask
                ? array_filter([
                    'task' => $firstOverdueTask['id'],
                    'team' => $firstOverdueTask['team_slug'] ?? null,
                ], fn ($v) => $v !== null && $v !== '')
                : [];

            $items[] = [
                'kind' => 'danger',
                'title' => 'مهام متأخرة',
                'detail' => $tasksOverdue === 1
                    ? 'مهمة واحدة تجاوزت الموعد ولم تُغلق بعد.'
                    : "لديك {$tasksOverdue} مهام تجاوزت الموعد ولم تُغلق بعد.",
                'route' => 'tasks.index',
                'route_params' => $overdueParams,
                'action_label' => $firstOverdueTask ? 'فتح المهمة' : 'فتح المهام',
            ];
        }

        if ($outsideUnreadAssigned > 0) {
            $items[] = [
                'kind' => 'warning',
                'title' => 'محادثات في الخارج',
                'detail' => $outsideUnreadAssigned === 1
                    ? 'محادثة واحدة مخصّصة لك وبها رسائل غير مقروءة.'
                    : "{$outsideUnreadAssigned} محادثات موجهة إليك وبها رسائل جديدة.",
                'route' => 'outside.index',
                'action_label' => 'فتح الخارج',
            ];
        }

        if ($meetingsNext48h > 0) {
            $items[] = [
                'kind' => 'info',
                'title' => 'اجتماعات خلال يومين',
                'detail' => $meetingsNext48h === 1
                    ? 'لديك اجتماع واحد مجدول خلال الـ ٤٨ ساعة القادمة.'
                    : "{$meetingsNext48h} اجتماعات مجدولة خلال الـ ٤٨ ساعة القادمة.",
                'route' => 'meetings.index',
                'action_label' => 'جدول الاجتماعات',
            ];
        }

        $teamSlugs = $user->teams->pluck('slug')->all();

        if ($clientsCampaign > 0) {
            $isMediaBuyer = in_array('media-buyer', $teamSlugs, true);
            if ($isMediaBuyer && Gate::forUser($user)->allows('view-warehouse')) {
                $items[] = [
                    'kind' => 'info',
                    'title' => 'عملاء الحملات',
                    'detail' => $clientsCampaign === 1
                        ? 'عميل واحد مرتبط بك كمدير حملات — راجع المخزن ومزامنة ميتا عند الحاجة.'
                        : "{$clientsCampaign} عميلاً تحت مسؤوليتك كمدير حملات — راجع الإنفاق والمزامنة دورياً.",
                    'route' => 'warehouse.index',
                    'action_label' => 'المخزن',
                ];
            } elseif ($isMediaBuyer) {
                $items[] = [
                    'kind' => 'info',
                    'title' => 'عملاء الحملات',
                    'detail' => $clientsCampaign === 1
                        ? 'عميل واحد تحت مسؤوليتك كمدير حملات — راجع الإنفاق والتحديثات من ملف العميل.'
                        : "{$clientsCampaign} عميلاً تحت مسؤوليتك كمدير حملات — راجع الإنفاق والتحديثات.",
                    'route' => 'clients.index',
                    'action_label' => 'قائمة العملاء',
                ];
            } else {
                $items[] = [
                    'kind' => 'info',
                    'title' => 'إشراف على الحملات',
                    'detail' => $clientsCampaign === 1
                        ? 'عميل واحد مرتبط بك كمدير حملات.'
                        : "{$clientsCampaign} عملاء تحت مسؤوليتك كمدير حملات.",
                    'route' => 'clients.index',
                    'action_label' => 'العملاء',
                ];
            }
        }

        if ($clientsAccount > 0) {
            $accountTeam = in_array('account', $teamSlugs, true);
            $items[] = [
                'kind' => 'info',
                'title' => $accountTeam ? 'مسؤولية الحسابات' : 'عملاء تحت إشرافك',
                'detail' => $clientsAccount === 1
                    ? ($accountTeam
                        ? 'عميل واحد تحت مسؤوليتك كمسؤول حساب — تابع المراحل والتواصل.'
                        : 'عميل واحد مربوط بك كمسؤول حساب.')
                    : ($accountTeam
                        ? "{$clientsAccount} عملاء تحت مسؤوليتك كمسؤول حساب — تابع المراحل والتذكيرات."
                        : "{$clientsAccount} عملاء مربوطون بك كمسؤول حساب."),
                'route' => 'clients.index',
                'action_label' => 'قائمة العملاء',
            ];
        }

        $items = array_slice($items, 0, 6);

        if ($items === []) {
            $items[] = [
                'kind' => 'success',
                'title' => 'لا تنبيهات عاجلة',
                'detail' => 'لا مهام متأخرة، ولا رسائل معلّقة في الخارج مخصّصة لك، ولا اجتماعات خلال يومين. يمكنك متابعة عملك من الشريط الجانبي.',
                'route' => null,
                'action_label' => null,
            ];
        }

        return $items;
    }
}
