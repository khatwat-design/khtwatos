<?php

namespace App\Operational\ReadModels;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\OutsideConversation;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Staff dashboard operational snapshot — consolidates task/meeting/client/outside
 * counts used by {@see \App\Http\Controllers\HomeController} without embedding query logic in HTTP layer.
 */
final class StaffWorkspaceSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
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

        $tasksAssigned = (clone $assignedTasksQuery)->count();

        $tasksOverdue = (clone $assignedTasksQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereHas('column', fn ($q) => $q->where('name', '!=', 'تم'))
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

        $recentTasks = (clone $assignedTasksQuery)
            ->with(['column:id,name'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'due_at' => $t->due_at?->toIso8601String(),
                'column_name' => $t->column?->name,
            ])
            ->values();

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
        );

        return [
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
            ],
            'recent_tasks' => $recentTasks,
            'upcoming_meetings' => $upcomingMeetings,
            'priority_followups' => $priorityFollowups,
            'workspace_hints' => $this->staffWorkspaceHints($user),
        ];
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

    /**
     * @return list<array{label: string, detail: string, route: string}>
     */
    private function staffWorkspaceHints(User $user): array
    {
        $user->loadMissing('teams');
        $slugs = $user->teams->pluck('slug')->all();
        $hints = [];

        if ($user->isAdmin() || in_array('sales', $slugs, true) || in_array('accounting', $slugs, true)) {
            $hints[] = [
                'label' => 'الخارج والبضاعة',
                'detail' => 'قنوات الرد وجودة المبيعات',
                'route' => route('outside.index'),
            ];
        }

        if (
            ($user->isAdmin() || in_array('media-buyer', $slugs, true) || Gate::forUser($user)->allows('manage-campaign-updates'))
            && Gate::forUser($user)->allows('view-warehouse')
        ) {
            $hints[] = [
                'label' => 'المخزن',
                'detail' => 'مؤشرات الحملات والمخزون',
                'route' => route('warehouse.index'),
            ];
        }

        if (Gate::forUser($user)->allows('manage-employees')) {
            $hints[] = [
                'label' => 'الموظفون والحضور',
                'detail' => 'إدارة الفريق والحالة التشغيلية',
                'route' => route('employees.index'),
            ];
        }

        return $hints;
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
    ): array {
        $items = [];

        if ($tasksOverdue > 0) {
            $items[] = [
                'kind' => 'danger',
                'title' => 'مهام متأخرة',
                'detail' => $tasksOverdue === 1
                    ? 'مهمة واحدة تجاوزت الموعد ولم تُغلق بعد.'
                    : "لديك {$tasksOverdue} مهام تجاوزت الموعد ولم تُغلق بعد.",
                'route' => 'tasks.index',
                'action_label' => 'فتح المهام',
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
