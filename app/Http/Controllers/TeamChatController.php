<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\TeamChatRead;
use App\Models\TeamChatTypingState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $this->markTeamAsRead($request, $selectedTeam->id);
        $unreadCounts = $this->buildUnreadCounts($request);

        return Inertia::render('Chat/Index', [
            'teams' => $teams,
            'selectedTeam' => [
                'id' => $selectedTeam->id,
                'name' => $selectedTeam->name,
                'slug' => $selectedTeam->slug,
            ],
            'messages' => $messages->map(fn (TeamChatMessage $msg) => $this->mapMessage($msg)),
            'unreadCounts' => $unreadCounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['nullable', 'string', 'max:4000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:10240', 'required_without:body'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('chat-attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getMimeType();
            $attachmentSize = $file->getSize();
        }

        TeamChatMessage::query()->create([
            'team_id' => $data['team_id'],
            'user_id' => $request->user()->id,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $this->markTeamAsRead($request, (int) $data['team_id']);

        return redirect()->back();
    }

    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = TeamChatMessage::query()
            ->with('user:id,name')
            ->where('team_id', (int) $data['team_id'])
            ->orderBy('id');

        if (!empty($data['after_id'])) {
            $query->where('id', '>', (int) $data['after_id']);
        } else {
            $query->latest()->limit(150);
        }

        $rows = $query->get();
        if (empty($data['after_id'])) {
            $rows = $rows->reverse()->values();
        }
        $this->markTeamAsRead($request, (int) $data['team_id']);

        return response()->json([
            'messages' => $rows->map(fn (TeamChatMessage $msg) => $this->mapMessage($msg))->values(),
            'unreadCounts' => $this->buildUnreadCounts($request),
        ]);
    }

    public function update(Request $request, TeamChatMessage $teamChatMessage): RedirectResponse
    {
        $this->ensureCanModify($request, $teamChatMessage);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $teamChatMessage->update([
            'body' => trim($data['body']),
            'edited_at' => now(),
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, TeamChatMessage $teamChatMessage): RedirectResponse
    {
        $this->ensureCanModify($request, $teamChatMessage);
        if ($teamChatMessage->attachment_path) {
            Storage::disk('public')->delete($teamChatMessage->attachment_path);
        }
        $teamChatMessage->delete();

        return redirect()->back();
    }

    public function typingUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        TeamChatTypingState::query()->updateOrCreate(
            [
                'team_id' => (int) $data['team_id'],
                'user_id' => $request->user()->id,
            ],
            [
                'updated_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function typingUsers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        $users = TeamChatTypingState::query()
            ->with('user:id,name')
            ->where('team_id', (int) $data['team_id'])
            ->where('updated_at', '>=', now()->subSeconds(8))
            ->where('user_id', '!=', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (TeamChatTypingState $state) => [
                'id' => $state->user?->id,
                'name' => $state->user?->name,
            ])
            ->filter(fn (array $row) => !empty($row['id']))
            ->values();

        return response()->json([
            'users' => $users,
        ]);
    }

    private function ensureTeams()
    {
        $defaults = [
            ['name' => 'خطوات', 'slug' => 'khatwat', 'sort_order' => 5],
            ['name' => 'فريق الكتابة', 'slug' => 'writing', 'sort_order' => 10],
            ['name' => 'مدراء الحملات', 'slug' => 'media-buyer', 'sort_order' => 20],
            ['name' => 'مدراء الحسابات', 'slug' => 'account', 'sort_order' => 30],
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

    private function ensureCanModify(Request $request, TeamChatMessage $message): void
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ($user->isAdmin() || (int) $message->user_id === (int) $user->id) {
            return;
        }

        abort(403, 'لا تملك صلاحية تعديل/حذف هذه الرسالة.');
    }

    private function markTeamAsRead(Request $request, int $teamId): void
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return;
        }

        $lastId = (int) TeamChatMessage::query()
            ->where('team_id', $teamId)
            ->max('id');

        TeamChatRead::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'user_id' => $userId,
            ],
            [
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, int>
     */
    private function buildUnreadCounts(Request $request): array
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return [];
        }

        $teams = Team::query()->get(['id']);
        $readMap = TeamChatRead::query()
            ->where('user_id', $userId)
            ->pluck('last_read_message_id', 'team_id');

        $result = [];
        foreach ($teams as $team) {
            $lastRead = (int) ($readMap[$team->id] ?? 0);
            $count = TeamChatMessage::query()
                ->where('team_id', $team->id)
                ->where('id', '>', $lastRead)
                ->count();

            $result[(int) $team->id] = (int) $count;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMessage(TeamChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'body' => $msg->body,
            'created_at' => $msg->created_at->toIso8601String(),
            'edited_at' => $msg->edited_at?->toIso8601String(),
            'attachment' => $msg->attachment_path ? [
                'url' => Storage::disk('public')->url($msg->attachment_path),
                'name' => $msg->attachment_name,
                'mime' => $msg->attachment_mime,
                'size' => $msg->attachment_size,
                'is_image' => is_string($msg->attachment_mime) && str_starts_with($msg->attachment_mime, 'image/'),
            ] : null,
            'user' => $msg->user ? [
                'id' => $msg->user->id,
                'name' => $msg->user->name,
            ] : null,
        ];
    }
}

