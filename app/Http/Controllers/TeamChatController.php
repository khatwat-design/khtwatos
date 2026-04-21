<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamChatController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = $this->ensureTeams();
        $selectedSlug = $request->query('team');
        $selectedTeam = $selectedSlug
            ? $teams->firstWhere('slug', $selectedSlug)
            : $teams->first();

        $messages = TeamChatMessage::query()
            ->with('user:id,name')
            ->where('team_id', $selectedTeam->id)
            ->latest()
            ->limit(150)
            ->get()
            ->reverse()
            ->values();

        return Inertia::render('Chat/Index', [
            'teams' => $teams,
            'selectedTeam' => [
                'id' => $selectedTeam->id,
                'name' => $selectedTeam->name,
                'slug' => $selectedTeam->slug,
            ],
            'messages' => $messages->map(fn (TeamChatMessage $msg) => [
                'id' => $msg->id,
                'body' => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
                'user' => $msg->user ? [
                    'id' => $msg->user->id,
                    'name' => $msg->user->name,
                ] : null,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        TeamChatMessage::query()->create([
            'team_id' => $data['team_id'],
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        return redirect()->back();
    }

    private function ensureTeams()
    {
        $defaults = [
            ['name' => 'الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'الميديا باير', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'الأكاونت', 'slug' => 'account', 'sort_order' => 30],
            ['name' => 'المبيعات', 'slug' => 'sales', 'sort_order' => 40],
            ['name' => 'الموارد البشرية', 'slug' => 'hr', 'sort_order' => 50],
            ['name' => 'المحاسبة', 'slug' => 'accounting', 'sort_order' => 60],
        ];

        foreach ($defaults as $team) {
            Team::query()->updateOrCreate(
                ['slug' => $team['slug']],
                [
                    'name' => $team['name'],
                    'sort_order' => $team['sort_order'],
                ],
            );
        }

        return Team::query()->orderBy('sort_order')->get(['id', 'name', 'slug']);
    }
}

