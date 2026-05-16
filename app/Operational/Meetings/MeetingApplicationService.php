<?php

namespace App\Operational\Meetings;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MeetingApplicationService
{
    public function archiveCompletedMeetings(): void
    {
        // إبقاء تلقائي اختياري — لا نُدْرِج سياسة أرشفة تلقائية حتى لا نُدهش المستخدمين.
    }

    /**
     * @param  Builder<Meeting>  $query
     */
    public function applyVisibilityAndFilters(
        Builder $query,
        Request $request,
        User $user,
        bool $hasArchiveColumn,
        bool $includeArchived,
    ): void {
        if (! $user->isAdmin()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', function (Builder $p) use ($user) {
                        $p->where('users.id', $user->id);
                    })
                    ->orWhereHas('client', function (Builder $c) use ($user) {
                        $c->where('account_manager_id', $user->id)
                            ->orWhere('campaign_manager_id', $user->id);
                    });
            });
        }

        if ($hasArchiveColumn && ! $includeArchived) {
            $query->whereNull('archived_at');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        $status = $request->query('status');
        if (is_string($status) && in_array($status, ['scheduled', 'completed', 'canceled', 'postponed'], true)) {
            $query->where('status', $status);
        }

        $scope = $request->query('scope');
        if ($scope === 'internal') {
            $query->whereNull('client_id');
        } elseif ($scope === 'client') {
            $query->whereNotNull('client_id');
        }
    }

    /**
     * إحصائيات البطاقات — مع نفس صلاحيات الرؤية، بدون فلتر الحالة في الطلب
     * حتى تبقى الأرقام شاملة في نافذة التحليلات.
     *
     * @return array<string, int>
     */
    public function listStats(Request $request, bool $hasArchiveColumn, User $user): array
    {
        $includeArchived = $request->boolean('include_archived');

        $visible = Meeting::query();
        if (! $user->isAdmin()) {
            $visible->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('participants', function (Builder $p) use ($user) {
                        $p->where('users.id', $user->id);
                    })
                    ->orWhereHas('client', function (Builder $c) use ($user) {
                        $c->where('account_manager_id', $user->id)
                            ->orWhere('campaign_manager_id', $user->id);
                    });
            });
        }

        $withoutArchivedToggle = clone $visible;
        if ($hasArchiveColumn && ! $includeArchived) {
            $withoutArchivedToggle->whereNull('archived_at');
        }

        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = Carbon::now()->endOfDay();

        return [
            'today' => (clone $withoutArchivedToggle)->whereBetween('start_at', [$startOfDay, $endOfDay])->count(),
            'scheduled' => (clone $withoutArchivedToggle)->where('status', 'scheduled')->count(),
            'completed' => (clone $withoutArchivedToggle)->where('status', 'completed')->count(),
            'archived' => $hasArchiveColumn
                ? (clone $visible)->whereNotNull('archived_at')->count()
                : 0,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function hostsForPicker(User $user): array
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, label: string}>
     */
    public function clientsForPicker(User $user): array
    {
        $q = Client::query()->orderBy('name');

        if (! $user->isAdmin()) {
            $q->where(function (Builder $c) use ($user) {
                $c->where('account_manager_id', $user->id)
                    ->orWhere('campaign_manager_id', $user->id);
            });
        }

        return $q->get(['id', 'name'])
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'label' => $c->name.($c->company ? ' — '.$c->company : ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<mixed>  $ids
     * @return array<int, int>
     */
    public function normalizeIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function syncParticipants(Meeting $meeting, array $data): void
    {
        $ids = $this->normalizeIds($data['participant_ids'] ?? []);
        $meeting->participants()->sync($ids);
    }
}
