<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'هذه الصفحة لمدير النظام فقط.');
        }

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
}

