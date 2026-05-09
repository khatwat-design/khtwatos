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
