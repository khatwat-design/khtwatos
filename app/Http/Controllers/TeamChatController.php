<?php

namespace App\Http\Controllers;

use App\Events\TeamChatMessageCreated;
use App\Events\TeamChatMessageDeleted;
use App\Events\TeamChatMessageUpdated;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\Team;
use App\Models\TeamChatMessage;
use App\Models\TeamChatTypingState;
use App\Models\User;
use App\Services\ChatNotificationReadService;
use App\Services\ChatUnreadService;
use App\Services\SmartNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TeamChatController extends Controller
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatUnreadService $chatUnread,
        private readonly ChatNotificationReadService $chatNotificationRead,
    ) {}

    public function index(Request $request): Response
    {
        $teams = $this->ensureTeams();
        $user = $request->user();

        $tab = $request->query('tab', 'all');
        if (! in_array($tab, ['all', 'rooms', 'direct'], true)) {
            $tab = 'all';
        }

        $privateRoomsPayload = $this->buildPrivateRoomsForUser($user);
        $directPeersPayload = $this->buildDirectPeersForUser($user);
        $chatUsers = User::query()->orderBy('name')->get(['id', 'name']);

        $viewKind = 'none';
        $messages = collect();
        $selectedTeam = null;
        $selectedPrivateRoom = null;
        $selectedDirect = null;

        if ($request->filled('private_room')) {
            $room = PrivateChatRoom::query()->find((int) $request->query('private_room'));
            if ($room && $room->userIsMember($user)) {
                $viewKind = 'private_room';
                $selectedPrivateRoom = [
                    'id' => $room->id,
                    'name' => $room->name,
                    'creator_id' => $room->creator_id,
                    'is_creator' => (int) $room->creator_id === (int) $user->id,
                ];
                $messages = PrivateChatMessage::query()
                    ->with('user:id,name')
                    ->where('private_chat_room_id', $room->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn (PrivateChatMessage $msg) => $msg->toChatArray())
                    ->values();
            }
        } elseif ($request->filled('direct')) {
            $conversation = DirectConversation::query()->find((int) $request->query('direct'));
            if ($conversation && $conversation->userParticipates($user)) {
                $viewKind = 'direct';
                $peer = $conversation->otherUser($user);
                $selectedDirect = [
                    'id' => $conversation->id,
                    'peer' => $peer ? ['id' => $peer->id, 'name' => $peer->name] : null,
                ];
                $messages = DirectMessage::query()
                    ->with('user:id,name')
                    ->where('direct_conversation_id', $conversation->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn (DirectMessage $msg) => $msg->toChatArray())
                    ->values();
            }
        }

        if ($viewKind === 'none') {
            $selectedSlug = $request->query('team');
            $selectedTeam = $selectedSlug ? $teams->firstWhere('slug', $selectedSlug) : null;

            if ($selectedTeam) {
                TeamNotebookController::rememberNotebookTeam($request, $selectedTeam);
                $viewKind = 'team';
                $messages = TeamChatMessage::query()
                    ->with('user:id,name')
                    ->where('team_id', $selectedTeam->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn (TeamChatMessage $msg) => $this->mapMessage($msg))
                    ->values();
            }
        }

        if ($viewKind === 'team' && $selectedTeam) {
            $this->chatUnread->markTeamAsRead($request, (int) $selectedTeam->id);
        } elseif ($viewKind === 'private_room' && $selectedPrivateRoom) {
            $this->chatUnread->markPrivateRoomAsRead($request, (int) $selectedPrivateRoom['id']);
        } elseif ($viewKind === 'direct' && $selectedDirect) {
            $this->chatUnread->markDirectAsRead($request, (int) $selectedDirect['id']);
        }

        $this->chatNotificationRead->markReadForActiveThread(
            $user,
            $viewKind,
            $viewKind === 'team' && $selectedTeam ? ['id' => $selectedTeam->id, 'slug' => $selectedTeam->slug] : null,
            $selectedPrivateRoom,
            $selectedDirect,
        );

        $chatUnreadPayload = $this->chatUnread->fullUnreadPayload($user);

        $teamLastChatAt = $this->lastTeamChatMessageAtByTeamId();

        $teamsPayload = $teams
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'last_activity_at' => $this->nullableIso8601($teamLastChatAt[(int) $t->id] ?? null),
            ])
            ->values()
            ->all();

        return Inertia::render('Chat/Index', [
            'chatTab' => $tab,
            'viewKind' => $viewKind,
            'teams' => $teamsPayload,
            'selectedTeam' => $selectedTeam ? [
                'id' => $selectedTeam->id,
                'name' => $selectedTeam->name,
                'slug' => $selectedTeam->slug,
            ] : null,
            'privateRooms' => $privateRoomsPayload,
            'directPeers' => $directPeersPayload,
            'chatUsers' => $chatUsers,
            'selectedPrivateRoom' => $selectedPrivateRoom,
            'selectedDirect' => $selectedDirect,
            'messages' => $messages,
            'unreadCounts' => $chatUnreadPayload['unreadCounts'],
            'chatUnread' => $chatUnreadPayload,
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

        $message = TeamChatMessage::query()->create([
            'team_id' => $data['team_id'],
            'user_id' => $request->user()->id,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $message->load('user:id,name');
        broadcast(new TeamChatMessageCreated($message));
        $this->smartNotifications->notifyTeamChatMessage($message, $request->user()?->id);
        $this->chatUnread->markTeamAsRead($request, (int) $data['team_id']);

        return redirect()->back();
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        return response()->json($this->chatUnread->fullUnreadPayload($request->user()));
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

        if (! empty($data['after_id'])) {
            $query->where('id', '>', (int) $data['after_id']);
        } else {
            $query->latest()->limit(150);
        }

        $rows = $query->get();
        if (empty($data['after_id'])) {
            $rows = $rows->reverse()->values();
        }
        $this->chatUnread->markTeamAsRead($request, (int) $data['team_id']);

        return response()->json(array_merge([
            'messages' => $rows->map(fn (TeamChatMessage $msg) => $this->mapMessage($msg))->values(),
        ], $this->chatUnread->fullUnreadPayload($request->user())));
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

        $teamChatMessage->load('user:id,name');
        broadcast(new TeamChatMessageUpdated($teamChatMessage));

        return redirect()->back();
    }

    public function destroy(Request $request, TeamChatMessage $teamChatMessage): RedirectResponse
    {
        $this->ensureCanModify($request, $teamChatMessage);
        if ($teamChatMessage->attachment_path) {
            Storage::disk('public')->delete($teamChatMessage->attachment_path);
        }
        $teamId = (int) $teamChatMessage->team_id;
        $messageId = (int) $teamChatMessage->id;
        $teamChatMessage->delete();
        broadcast(new TeamChatMessageDeleted($teamId, $messageId));

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
            ->filter(fn (array $row) => ! empty($row['id']))
            ->values();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPrivateRoomsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $lastMsgAtByRoom = $this->lastPrivateRoomMessageAtByRoomId();

        return PrivateChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('name')
            ->get()
            ->map(fn (PrivateChatRoom $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'creator_id' => $r->creator_id,
                'is_creator' => (int) $r->creator_id === (int) $user->id,
                'last_activity_at' => $this->nullableIso8601($lastMsgAtByRoom[(int) $r->id] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * كل الموظفين الآخرين مع معرف المحادثة إن وُجدت (بدون الحاجة لإظهار الخيط يدويًا).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDirectPeersForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $conversationPeerMap = [];
        DirectConversation::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with(['users:id,name'])
            ->get()
            ->each(function (DirectConversation $c) use ($user, &$conversationPeerMap): void {
                $peer = $c->otherUser($user);
                if ($peer) {
                    $conversationPeerMap[(int) $peer->id] = (int) $c->id;
                }
            });

        $lastMsgAtByConversation = $this->lastDirectMessageAtByConversationId();

        return User::query()
            ->whereKeyNot($user->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $peer) use ($conversationPeerMap, $lastMsgAtByConversation): array {
                $conversationId = $conversationPeerMap[(int) $peer->id] ?? null;

                return [
                    'user_id' => (int) $peer->id,
                    'name' => $peer->name,
                    'conversation_id' => $conversationId,
                    'last_activity_at' => $this->nullableIso8601(
                        $conversationId ? ($lastMsgAtByConversation[(int) $conversationId] ?? null) : null,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function lastTeamChatMessageAtByTeamId(): array
    {
        return TeamChatMessage::query()
            ->selectRaw('team_id, MAX(created_at) as last_at')
            ->groupBy('team_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->team_id => $row->last_at])
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function lastPrivateRoomMessageAtByRoomId(): array
    {
        return PrivateChatMessage::query()
            ->selectRaw('private_chat_room_id, MAX(created_at) as last_at')
            ->groupBy('private_chat_room_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->private_chat_room_id => $row->last_at])
            ->all();
    }

    /**
     * @return array<int, mixed>
     */
    private function lastDirectMessageAtByConversationId(): array
    {
        return DirectMessage::query()
            ->selectRaw('direct_conversation_id, MAX(created_at) as last_at')
            ->groupBy('direct_conversation_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->direct_conversation_id => $row->last_at])
            ->all();
    }

    private function nullableIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
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
        if (! $user) {
            abort(403);
        }

        if ($user->isAdmin() || (int) $message->user_id === (int) $user->id) {
            return;
        }

        abort(403, 'لا تملك صلاحية تعديل/حذف هذه الرسالة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMessage(TeamChatMessage $msg): array
    {
        return $msg->toChatArray();
    }
}
