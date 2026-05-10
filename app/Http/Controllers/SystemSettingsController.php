<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\TeamNavigationGrant;
use App\Services\DatabaseBackupService;
use App\Support\EffectiveSettings;
use App\Support\NavigationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->can('manage-system-settings'), 403);

        $definitions = EffectiveSettings::definitions();
        $flatItems = [];

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

            $flatItems[] = [
                'key' => $key,
                'category' => (string) ($meta['category'] ?? 'other'),
                'label' => $meta['label'],
                'help' => $meta['help'],
                'locked' => $locked,
                'lock_hint' => $meta['lock_hint'] ?? null,
                'value' => $locked ? $effective : $stored,
                'effective' => $effective,
            ];
        }

        $buckets = [];
        foreach ($flatItems as $item) {
            $cat = $item['category'];
            $buckets[$cat] ??= [];
            $buckets[$cat][] = $item;
        }

        $categoryMeta = EffectiveSettings::toggleCategoryMeta();
        $toggleSections = [];

        foreach (EffectiveSettings::toggleCategoryOrder() as $categoryId) {
            if (empty($buckets[$categoryId])) {
                continue;
            }

            $meta = $categoryMeta[$categoryId] ?? ['title' => $categoryId, 'description' => ''];
            $toggleSections[] = [
                'id' => $categoryId,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'items' => $buckets[$categoryId],
            ];
        }

        foreach ($buckets as $categoryId => $items) {
            if (in_array($categoryId, EffectiveSettings::toggleCategoryOrder(), true)) {
                continue;
            }
            $meta = $categoryMeta[$categoryId] ?? ['title' => $categoryId, 'description' => ''];
            $toggleSections[] = [
                'id' => $categoryId,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'items' => $items,
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

        $teamsWithNavRules = 0;
        if (Schema::hasTable('team_navigation_grants')) {
            $teamsWithNavRules = Team::query()->whereHas('navigationGrants')->count();
        }

        $stats = [
            'teams_total' => $teams->count(),
            'teams_with_nav_rules' => $teamsWithNavRules,
            'toggles_effective_on' => collect($flatItems)->where('effective', true)->count(),
            'toggles_locked' => collect($flatItems)->where('locked', true)->count(),
            'toggle_definitions_total' => count($flatItems),
        ];

        $appMeta = [
            'name' => (string) config('app.name'),
            'url' => (string) config('app.url'),
            'timezone' => (string) config('app.timezone'),
            'locale' => (string) config('app.locale'),
            'environment' => app()->environment(),
            'session_lifetime_minutes' => (int) config('session.lifetime'),
            'php_version' => PHP_VERSION,
        ];

        $scheduleHints = [
            [
                'label' => 'تذكير مبيعات البضاعة',
                'command' => 'goods:send-daily-sales-reminders',
                'schedule' => 'يومياً '.(string) config('services.goods.daily_sales_reminder_at', '09:00').' ('.config('app.timezone').')',
            ],
            [
                'label' => 'تذكير مبيعات بوابة العميل',
                'command' => 'portal:send-daily-sales-reminders',
                'schedule' => 'يومياً '.(string) config('services.portal.daily_sales_reminder_at', '18:00').' ('.config('app.timezone').')',
            ],
            [
                'label' => 'التقرير اليومي للعملاء',
                'command' => 'portal:send-daily-client-reports',
                'schedule' => 'يومياً '.(string) config('services.client_reports.daily_at', '19:30').' ('.config('app.timezone').')',
            ],
            [
                'label' => 'التقرير الأسبوعي للعملاء',
                'command' => 'portal:send-weekly-client-reports',
                'schedule' => 'أسبوعياً يوم '.(string) config('services.client_reports.weekly_at', '10:00').' ('.config('app.timezone').')',
            ],
            [
                'label' => 'نسخ احتياطي لقاعدة البيانات',
                'command' => 'db:backup',
                'schedule' => config('database_backup.schedule_enabled')
                    ? 'يومياً '.(string) config('database_backup.schedule_at', '03:30').' ('.config('app.timezone').')'
                    : 'معطّل حالياً — لفعّل الجدولة اضبط BACKUP_SCHEDULE_ENABLED=true في .env',
            ],
        ];

        return Inertia::render('Settings/Index', [
            'toggle_sections' => $toggleSections,
            'stats' => $stats,
            'app_meta' => $appMeta,
            'schedule_hints' => $scheduleHints,
            'nav_sections' => NavigationCatalog::sectionsForUi(),
            'teams_navigation' => $teamsNavigation,
            'database_backup' => app(DatabaseBackupService::class)->inertiaPayload(),
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
