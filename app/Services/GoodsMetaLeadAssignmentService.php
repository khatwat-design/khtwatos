<?php

namespace App\Services;

use App\Models\GoodsMetaLead;
use App\Models\User;
use Illuminate\Support\Collection;

class GoodsMetaLeadAssignmentService
{
    /**
     * @return list<array{id: int, name: string, weight: int}>
     */
    public function assignees(): array
    {
        $config = config('goods.meta_leads_assignees', []);
        $resolved = [];

        foreach ($config as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $username = trim((string) ($row['username'] ?? ''));
            $weight = (int) ($row['weight'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $user = $this->resolveAssigneeUser($name, $username);
            if (! $user) {
                continue;
            }

            $resolved[] = [
                'id' => (int) $user->id,
                'name' => $user->name,
                'weight' => $weight,
            ];
        }

        return $resolved;
    }

    public function isAssignee(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return in_array($userId, array_column($this->assignees(), 'id'), true);
    }

    public function pickOwnerUserId(): ?int
    {
        $assignees = $this->assignees();
        if ($assignees === []) {
            return null;
        }

        $ids = array_column($assignees, 'id');
        $counts = GoodsMetaLead::query()
            ->whereIn('owner_user_id', $ids)
            ->selectRaw('owner_user_id, COUNT(*) as aggregate')
            ->groupBy('owner_user_id')
            ->pluck('aggregate', 'owner_user_id');

        $total = (int) $counts->sum() + 1;
        $weightSum = array_sum(array_column($assignees, 'weight')) ?: 100;

        $bestId = $assignees[0]['id'];
        $bestDeficit = -INF;

        foreach ($assignees as $assignee) {
            $current = (int) ($counts[$assignee['id']] ?? 0);
            $target = ($assignee['weight'] / $weightSum) * $total;
            $deficit = $target - $current;
            if ($deficit > $bestDeficit) {
                $bestDeficit = $deficit;
                $bestId = $assignee['id'];
            }
        }

        return $bestId;
    }

    /**
     * @return list<array{id: int, name: string, weight: int, leads_today: int, upcoming_calls: int, total_leads: int}>
     */
    public function assigneeFilterStats(): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return collect($this->assignees())->map(function (array $assignee) use ($todayStart, $todayEnd) {
            $base = GoodsMetaLead::query()->where('owner_user_id', $assignee['id']);

            return [
                ...$assignee,
                'leads_today' => (clone $base)->where(function ($q) use ($todayStart, $todayEnd) {
                    $q->whereBetween('lead_created_at', [$todayStart, $todayEnd])
                        ->orWhereBetween('created_at', [$todayStart, $todayEnd]);
                })->count(),
                'upcoming_calls' => (clone $base)
                    ->whereNotNull('next_call_at')
                    ->where('next_call_at', '>=', now())
                    ->count(),
                'total_leads' => (clone $base)->count(),
            ];
        })->values()->all();
    }

    /**
     * @return array{leads_today: int, upcoming_calls: int}
     */
    public function staffDashboardCounts(int $userId): array
    {
        if (! $this->isAssignee($userId)) {
            return ['leads_today' => 0, 'upcoming_calls' => 0];
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $base = GoodsMetaLead::query()->where('owner_user_id', $userId);

        return [
            'leads_today' => (clone $base)->where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('lead_created_at', [$todayStart, $todayEnd])
                    ->orWhereBetween('created_at', [$todayStart, $todayEnd]);
            })->count(),
            'upcoming_calls' => (clone $base)
                ->whereNotNull('next_call_at')
                ->where('next_call_at', '>=', now())
                ->count(),
        ];
    }

    /** @return Collection<int, User> */
    public function assigneeUsers(): Collection
    {
        $ids = array_column($this->assignees(), 'id');
        if ($ids === []) {
            return collect();
        }

        return User::query()->whereIn('id', $ids)->orderBy('name')->get();
    }

    private function resolveAssigneeUser(string $name, string $username): ?User
    {
        if ($name !== '') {
            $byName = User::query()->where('name', $name)->first();
            if ($byName) {
                return $byName;
            }

            $byName = User::query()->where('name', 'like', '%'.$name.'%')->first();
            if ($byName) {
                return $byName;
            }
        }

        if ($username !== '') {
            $normalized = ltrim($username, '@');

            return User::query()
                ->where(function ($q) use ($normalized, $username) {
                    $q->where('username', $normalized)
                        ->orWhere('username', $username);
                })
                ->first();
        }

        return null;
    }
}
