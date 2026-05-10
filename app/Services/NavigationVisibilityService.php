<?php

namespace App\Services;

use App\Models\TeamNavigationGrant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class NavigationVisibilityService
{
    /**
     * @return list<string>|null مسارات مسموح عرضها في القائمة؛ null = لا قيود حسب الفرق (السلوك الافتراضي)
     */
    public function allowedRouteNamesForUser(?User $user): ?array
    {
        if (! $user || $user->isAdmin()) {
            return null;
        }

        if (! Schema::hasTable('team_navigation_grants')) {
            return null;
        }

        $user->loadMissing('teams');

        if ($user->teams->isEmpty()) {
            return null;
        }

        foreach ($user->teams as $team) {
            if (! $this->teamHasRestrictionRows((int) $team->id)) {
                return null;
            }
        }

        if ($user->teams->isEmpty()) {
            return null;
        }

        $allowed = [];

        foreach ($user->teams as $team) {
            $isLead = (bool) ($team->pivot->is_lead ?? false);
            $grants = TeamNavigationGrant::query()
                ->where('team_id', $team->id)
                ->get(['route_name', 'allow_members', 'allow_leads']);

            foreach ($grants as $grant) {
                $okMember = ! $isLead && $grant->allow_members;
                $okLead = $isLead && $grant->allow_leads;
                if ($okMember || $okLead) {
                    $allowed[(string) $grant->route_name] = true;
                }
            }
        }

        $allowed['home.index'] = true;

        return array_keys($allowed);
    }

    private function teamHasRestrictionRows(int $teamId): bool
    {
        return TeamNavigationGrant::query()->where('team_id', $teamId)->exists();
    }
}
