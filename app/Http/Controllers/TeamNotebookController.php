<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamNotebookPersonal;
use App\Models\TeamNotebookShared;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TeamNotebookController extends Controller
{
    public function updatePersonal(Request $request, Team $team): RedirectResponse
    {
        $this->ensureNotebookTables();
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        TeamNotebookPersonal::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ],
            ['body' => (string) ($data['body'] ?? '')],
        );

        return redirect()->back();
    }

    public function updateShared(Request $request, Team $team): RedirectResponse
    {
        $this->ensureNotebookTables();
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        TeamNotebookShared::query()->updateOrCreate(
            ['team_id' => $team->id],
            [
                'body' => (string) ($data['body'] ?? ''),
                'updated_by' => $user->id,
            ],
        );

        return redirect()->back();
    }

    /**
     * دفتر موسّع لكل الصفحات: يعتمد على آخر فريق زاره الموظف من المهام/الدردشة، أو أول فريق بالترتيب.
     *
     * @return array<string, mixed>|null
     */
    public static function sharedPayloadForRequest(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('team_notebook_personals')) {
            return null;
        }

        $teamId = self::resolveNotebookTeamId($request);
        if (! $teamId) {
            return null;
        }

        $team = Team::query()->find($teamId);

        return self::payloadForTeam($team, $request);
    }

    public static function rememberNotebookTeam(Request $request, Team $team): void
    {
        $request->session()->put('notebook_team_id', (int) $team->id);
    }

    public static function resolveNotebookTeamId(Request $request): ?int
    {
        $sid = $request->session()->get('notebook_team_id');
        if ($sid !== null && $sid !== '' && Team::query()->whereKey((int) $sid)->exists()) {
            return (int) $sid;
        }

        return Team::query()->orderBy('sort_order')->value('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function payloadForTeam(?Team $team, Request $request): ?array
    {
        if (! $team || ! Schema::hasTable('team_notebook_personals')) {
            return null;
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        $personal = TeamNotebookPersonal::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();

        $shared = TeamNotebookShared::query()
            ->where('team_id', $team->id)
            ->with('updatedBy:id,name')
            ->first();

        return [
            'team_id' => $team->id,
            'personal_body' => $personal?->body ?? '',
            'shared_body' => $shared?->body ?? '',
            'shared_meta' => [
                'updated_by_name' => $shared?->updatedBy?->name,
                'updated_at' => $shared?->updated_at?->toIso8601String(),
            ],
        ];
    }

    private function ensureNotebookTables(): void
    {
        if (! Schema::hasTable('team_notebook_personals')) {
            abort(503, 'يرجى تشغيل migrations لدفتر الفريق.');
        }
    }

}
