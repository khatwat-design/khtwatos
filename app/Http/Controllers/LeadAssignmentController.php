<?php

namespace App\Http\Controllers;

use App\Models\GoodsMetaLead;
use App\Models\User;
use App\Services\GoodsMetaLeadAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadAssignmentController extends Controller
{
    public function __construct(
        private readonly GoodsMetaLeadAssignmentService $assignment,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        $isHr = method_exists($user, 'isHrManager') ? $user->isHrManager() : false;
        $isSalesLead = $user->teams()
            ->where('slug', 'sales')
            ->wherePivot('is_lead', true)
            ->exists();
        abort_unless($isAdmin || $isHr || $isSalesLead, 403);

        $assignees = $this->assignment->assignees();
        $stats = $this->assignment->assigneeFilterStats();

        $unassigned = GoodsMetaLead::query()->whereNull('owner_user_id')->count();
        $total = GoodsMetaLead::query()->count();

        $salesTeam = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->join('users', 'users.id', '=', 'team_user.user_id')
            ->where('teams.slug', 'sales')
            ->select('users.id', 'users.name', 'users.username')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Sales/LeadAssignment', [
            'assignees' => $assignees,
            'assignee_stats' => $stats,
            'unassigned_count' => $unassigned,
            'total_leads' => $total,
            'sales_team_members' => $salesTeam,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        abort_unless($isAdmin, 403);

        $data = $request->validate([
            'assignees' => 'required|array|min:1',
            'assignees.*.user_id' => 'required|exists:users,id',
            'assignees.*.weight' => 'required|integer|min:1|max:100',
        ]);

        $config = collect($data['assignees'])->map(function (array $a) {
            $user = User::find($a['user_id']);

            return [
                'name' => $user->name,
                'username' => $user->username,
                'weight' => $a['weight'],
            ];
        })->all();

        $currentConfig = config('goods.meta_leads_assignees', []);

        file_put_contents(
            config_path('goods.php'),
            str_replace(
                var_export($currentConfig, true),
                var_export($config, true),
                file_get_contents(config_path('goods.php'))
            )
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث أوزان التوزيع.',
        ]);
    }

    public function redistribute(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role === 'admin');
        abort_unless($isAdmin, 403);

        $unassigned = GoodsMetaLead::query()
            ->whereNull('owner_user_id')
            ->orderBy('lead_created_at')
            ->get();

        $assigned = 0;
        foreach ($unassigned as $lead) {
            $ownerId = $this->assignment->pickOwnerUserId();
            if ($ownerId) {
                $lead->update([
                    'owner_user_id' => $ownerId,
                    'assigned_at' => now(),
                ]);
                $assigned++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "تم توزيع {$assigned} ليدز غير مُعيَّن.",
            'assigned' => $assigned,
        ]);
    }

    public function stats(): JsonResponse
    {
        $assignees = $this->assignment->assignees();
        $stats = $this->assignment->assigneeFilterStats();

        $unassigned = GoodsMetaLead::query()->whereNull('owner_user_id')->count();
        $total = GoodsMetaLead::query()->count();

        return response()->json([
            'assignees' => $assignees,
            'stats' => $stats,
            'unassigned_count' => $unassigned,
            'total_leads' => $total,
        ]);
    }
}
