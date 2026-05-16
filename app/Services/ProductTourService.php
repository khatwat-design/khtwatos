<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProductTourProgress;
use App\Support\ProductTourCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class ProductTourService
{
    public function __construct(
        private readonly NavigationVisibilityService $navigationVisibility,
    ) {}

    /**
     * @return array{
     *     persona: string,
     *     persona_label: string,
     *     catalog: list<array<string, mixed>>,
     *     pending_ids: list<string>,
     *     auto_start_id: string|null,
     *     completed_count: int,
     *     total_count: int
     * }
     */
    public function payloadForUser(User $user, Request $request): array
    {
        $profile = $this->userProfile($user);
        $eligible = $this->eligibleTours($user, $profile, $request);
        $progress = $this->progressMap((int) $user->id);

        $catalog = [];
        $pendingIds = [];

        foreach ($eligible as $def) {
            $status = $progress[$def['id']] ?? 'pending';
            if ($status === 'pending') {
                $pendingIds[] = $def['id'];
            }

            $catalog[] = [
                'id' => $def['id'],
                'title' => $def['title'],
                'description' => $def['description'],
                'route_name' => $def['route_name'],
                'route_match' => $def['route_match'],
                'order' => $def['order'],
                'status' => $status,
            ];
        }

        usort($catalog, fn ($a, $b) => $a['order'] <=> $b['order']);

        $autoStartId = $this->resolveAutoStartId($pendingIds, $eligible, $request);

        $completedCount = count(array_filter($catalog, fn ($row) => $row['status'] !== 'pending'));

        return [
            'persona' => $profile['persona'],
            'persona_label' => $profile['persona_label'],
            'catalog' => $catalog,
            'pending_ids' => $pendingIds,
            'auto_start_id' => $autoStartId,
            'completed_count' => $completedCount,
            'total_count' => count($catalog),
        ];
    }

    public function markFinished(User $user, string $tourId, string $status): void
    {
        if (! in_array($status, [UserProductTourProgress::STATUS_COMPLETED, UserProductTourProgress::STATUS_SKIPPED], true)) {
            return;
        }

        if (! ProductTourCatalog::find($tourId)) {
            return;
        }

        if (! Schema::hasTable('user_product_tour_progress')) {
            return;
        }

        UserProductTourProgress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'tour_id' => $tourId,
            ],
            [
                'status' => $status,
                'finished_at' => now(),
            ],
        );
    }

    public function resetAllForUser(User $user): void
    {
        if (! Schema::hasTable('user_product_tour_progress')) {
            return;
        }

        UserProductTourProgress::query()->where('user_id', $user->id)->delete();
    }

    public function restartTour(User $user, string $tourId): void
    {
        if (! ProductTourCatalog::find($tourId) || ! Schema::hasTable('user_product_tour_progress')) {
            return;
        }

        UserProductTourProgress::query()
            ->where('user_id', $user->id)
            ->where('tour_id', $tourId)
            ->delete();
    }

    public function userCanAccessTour(User $user, string $tourId, Request $request): bool
    {
        $def = ProductTourCatalog::find($tourId);
        if (! $def) {
            return false;
        }

        $profile = $this->userProfile($user);

        return $this->tourMatchesUser($def, $user, $profile, $request);
    }

    /**
     * @return array{persona: string, persona_label: string, role: string, team_slugs: list<string>, is_any_lead: bool, is_hr_lead: bool}
     */
    public function userProfile(User $user): array
    {
        $user->loadMissing('teams:id,slug,name');

        $teamSlugs = $user->teams->pluck('slug')->filter()->values()->all();
        $isAnyLead = $user->teams->contains(fn ($t) => (bool) $t->pivot?->is_lead);
        $isHrLead = $user->isHrManager();

        $persona = 'member';
        $personaLabel = 'موظف';

        if ($user->isAdmin()) {
            $persona = 'admin';
            $personaLabel = 'مدير النظام';
        } elseif ($isHrLead) {
            $persona = 'hr_lead';
            $personaLabel = 'قائد الموارد البشرية';
        } elseif ($isAnyLead) {
            $primary = $teamSlugs[0] ?? 'lead';
            $persona = 'lead_'.$primary;
            $personaLabel = 'قائد فريق · '.$this->teamLabel($primary);
        } elseif (in_array('sales', $teamSlugs, true)) {
            $persona = 'sales';
            $personaLabel = 'فريق المبيعات';
        } elseif (in_array('media-buyer', $teamSlugs, true)) {
            $persona = 'media_buyer';
            $personaLabel = 'مدراء الحملات';
        } elseif (in_array('account', $teamSlugs, true)) {
            $persona = 'account';
            $personaLabel = 'مدراء الحسابات';
        } elseif (in_array('writing', $teamSlugs, true)) {
            $persona = 'writing';
            $personaLabel = 'فريق الكتابة';
        } elseif (in_array('accounting', $teamSlugs, true)) {
            $persona = 'accounting';
            $personaLabel = 'المحاسبة';
        }

        return [
            'persona' => $persona,
            'persona_label' => $personaLabel,
            'role' => (string) $user->role,
            'team_slugs' => $teamSlugs,
            'is_any_lead' => $isAnyLead,
            'is_hr_lead' => $isHrLead,
        ];
    }

    /**
     * @param  array{persona: string, persona_label: string, role: string, team_slugs: list<string>, is_any_lead: bool, is_hr_lead: bool}  $profile
     * @return list<array<string, mixed>>
     */
    private function eligibleTours(User $user, array $profile, Request $request): array
    {
        $navAllow = $this->navigationVisibility->allowedRouteNamesForUser($user);

        return array_values(array_filter(
            ProductTourCatalog::definitions(),
            function (array $def) use ($user, $profile, $request, $navAllow): bool {
                if (! $this->tourMatchesUser($def, $user, $profile, $request)) {
                    return false;
                }

                if ($navAllow !== null && $def['route_name'] !== 'home.index' && ! in_array($def['route_name'], $navAllow, true)) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @param  array{persona: string, persona_label: string, role: string, team_slugs: list<string>, is_any_lead: bool, is_hr_lead: bool}  $profile
     */
    private function tourMatchesUser(array $def, User $user, array $profile, Request $request): bool
    {
        if ($def['roles'] !== null && ! in_array($profile['role'], $def['roles'], true)) {
            return false;
        }

        if ($def['team_lead_only'] && ! $profile['is_any_lead'] && ! $user->isAdmin()) {
            return false;
        }

        if ($def['team_slugs'] !== null) {
            $overlap = array_intersect($def['team_slugs'], $profile['team_slugs']);
            if ($overlap === [] && ! $user->isAdmin()) {
                return false;
            }
        }

        if ($def['gate'] !== null && ! Gate::forUser($user)->allows($this->gateToAbility($def['gate']))) {
            return false;
        }

        if ($def['id'] === 'admin-home' && ! Gate::forUser($user)->allows('view-admin-home')) {
            return false;
        }

        if ($def['id'] === 'welcome' && Gate::forUser($user)->allows('view-admin-home')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function progressMap(int $userId): array
    {
        if (! Schema::hasTable('user_product_tour_progress')) {
            return [];
        }

        return UserProductTourProgress::query()
            ->where('user_id', $userId)
            ->pluck('status', 'tour_id')
            ->all();
    }

    /**
     * @param  list<string>  $pendingIds
     * @param  list<array<string, mixed>>  $eligible
     */
    private function resolveAutoStartId(array $pendingIds, array $eligible, Request $request): ?string
    {
        if ($pendingIds === []) {
            return null;
        }

        $routeName = $request->route()?->getName() ?? '';
        if ($routeName === '') {
            return null;
        }

        $ordered = collect($eligible)
            ->filter(fn ($d) => in_array($d['id'], $pendingIds, true))
            ->sortBy('order')
            ->values();

        foreach ($ordered as $def) {
            if ($this->routeMatchesPattern($routeName, $def['route_match'])) {
                return $def['id'];
            }
        }

        return null;
    }

    private function routeMatchesPattern(string $routeName, string $pattern): bool
    {
        if ($pattern === $routeName) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($routeName, $prefix);
        }

        return false;
    }

    private function gateToAbility(string $gate): string
    {
        return match ($gate) {
            'viewSalesAnalytics' => 'view-sales-analytics',
            'viewWarehouse' => 'view-warehouse',
            'manageEmployees' => 'manage-employees',
            'manageSystemSettings' => 'manage-system-settings',
            'viewAdminHome' => 'view-admin-home',
            default => $gate,
        };
    }

    private function teamLabel(string $slug): string
    {
        return match ($slug) {
            'writing' => 'الكتابة',
            'media-buyer' => 'الحملات',
            'account' => 'الحسابات',
            'sales' => 'المبيعات',
            'hr' => 'الموارد البشرية',
            'accounting' => 'المحاسبة',
            default => $slug,
        };
    }
}
