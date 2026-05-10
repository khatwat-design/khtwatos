<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\TeamNavigationGrant;
use App\Support\EffectiveSettings;
use App\Support\NavigationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $definitions = EffectiveSettings::definitions();
        $items = [];

        foreach ($definitions as $key => $meta) {
            $locked = (bool) ($meta['locked'] ?? false);
            $stored = SystemSetting::boolean($key, true);
            $effective = match ($key) {
                'firebase_mobile_push_enabled' => EffectiveSettings::firebaseMobilePushEnabled(),
                'whatsapp_milestone_notifications_enabled' => EffectiveSettings::whatsappMilestoneNotificationsEnabled(),
                'portal_daily_sales_reminder_enabled' => EffectiveSettings::portalDailySalesReminderEnabled(),
                'goods_daily_sales_reminders_enabled' => EffectiveSettings::goodsDailySalesRemindersEnabled(),
                'client_reports_daily_enabled' => EffectiveSettings::clientDailyReportsEnabled(),
                'client_reports_weekly_enabled' => EffectiveSettings::clientWeeklyReportsEnabled(),
                default => false,
            };

            $items[] = [
                'key' => $key,
                'label' => $meta['label'],
                'help' => $meta['help'],
                'locked' => $locked,
                'lock_hint' => $meta['lock_hint'] ?? null,
                'value' => $locked ? $effective : $stored,
                'effective' => $effective,
            ];
        }

        $teams = Team::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'slug']);
        $grantRows = TeamNavigationGrant::query()
            ->whereIn('team_id', $teams->pluck('id'))
            ->get();

        $teamsNavigation = $teams->map(function (Team $team) use ($grantRows) {
            $rows = $grantRows->where('team_id', $team->id)->keyBy('route_name');
            $routes = [];
            foreach (NavigationCatalog::routeNames() as $routeName) {
                $g = $rows->get($routeName);
                $routes[$routeName] = [
                    'allow_members' => $g ? (bool) $g->allow_members : false,
                    'allow_leads' => $g ? (bool) $g->allow_leads : false,
                ];
            }

            return [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'routes' => $routes,
            ];
        })->values()->all();

        $navCatalog = [];
        foreach (NavigationCatalog::items() as $routeName => $label) {
            $navCatalog[] = [
                'route_name' => $routeName,
                'label' => $label,
            ];
        }

        return Inertia::render('Settings/Index', [
            'items' => $items,
            'nav_catalog' => $navCatalog,
            'teams_navigation' => $teamsNavigation,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $definitions = EffectiveSettings::definitions();
        $rules = [];

        foreach ($definitions as $key => $meta) {
            if (! ($meta['locked'] ?? false)) {
                $rules[$key] = ['required', 'boolean'];
            }
        }

        $data = $request->validate($rules);
        $userId = $request->user()?->id;

        foreach ($data as $key => $bool) {
            SystemSetting::put($key, $bool ? '1' : '0', $userId);
        }

        return redirect()->route('settings.index')->with('success', 'تم حفظ الإعدادات.');
    }

    public function updateTeamNavigation(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $routeNames = NavigationCatalog::routeNames();

        $data = $request->validate([
            'teams' => ['required', 'array'],
            'teams.*.team_id' => ['required', 'integer', 'exists:teams,id'],
            'teams.*.routes' => ['required', 'array'],
            'teams.*.routes.*.route_name' => ['required', 'string', Rule::in($routeNames)],
            'teams.*.routes.*.allow_members' => ['required', 'boolean'],
            'teams.*.routes.*.allow_leads' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['teams'] as $teamPayload) {
                $teamId = (int) $teamPayload['team_id'];
                TeamNavigationGrant::query()->where('team_id', $teamId)->delete();

                foreach ($teamPayload['routes'] as $routePayload) {
                    $allowMembers = (bool) ($routePayload['allow_members'] ?? false);
                    $allowLeads = (bool) ($routePayload['allow_leads'] ?? false);
                    if (! $allowMembers && ! $allowLeads) {
                        continue;
                    }

                    TeamNavigationGrant::query()->create([
                        'team_id' => $teamId,
                        'route_name' => (string) $routePayload['route_name'],
                        'allow_members' => $allowMembers,
                        'allow_leads' => $allowLeads,
                    ]);
                }
            }
        });

        return redirect()->route('settings.index')->with('success', 'تم حفظ ظهور القائمة للفرق.');
    }
}
