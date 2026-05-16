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
use App\Services\ChatReadReceiptService;
use App\Services\ChatUnreadService;
use App\Services\SmartNotificationService;
use App\Services\ChatMentionService;
use App\Services\TeamChatMemberService;
use App\Support\ChatAttachmentRules;
use App\Support\ChatReplyResolver;
use App\Support\ChatStickerCatalog;
use App\Support\ChatStoreResponse;
use App\Support\UserAvatar;
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
        private readonly ChatReadReceiptService $readReceipts,
        private readonly TeamChatMemberService $teamChatMembers,
        private readonly ChatMentionService $chatMentions,
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
        $chatUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'avatar_path'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar_url' => UserAvatar::url($u),
            ])
            ->values()
            ->all();

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
                $privateRows = PrivateChatMessage::query()
                    ->with(['user:id,name,avatar_path', 'mentions.user:id,name,username'])
                    ->where('private_chat_room_id', $room->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values();
                $messages = $this->readReceipts
                    ->enrichPrivateRoomMessages($privateRows, (int) $room->id, (int) $user->id)
                    ->values();
            }
        } elseif ($request->filled('direct')) {
            $conversation = DirectConversation::query()->find((int) $request->query('direct'));
            if ($conversation && $conversation->userParticipates($user)) {
                $viewKind = 'direct';
                $peer = $conversation->otherUser($user);
                $selectedDirect = [
                    'id' => $conversation->id,
                    'peer' => $peer ? [
                        'id' => $peer->id,
                        'name' => $peer->name,
                        'avatar_url' => UserAvatar::url($peer),
                    ] : null,
                ];
                $directRows = DirectMessage::query()
                    ->with(['user:id,name,avatar_path', 'employeeCall', 'mentions.user:id,name,username'])
                    ->where('direct_conversation_id', $conversation->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values();
                $messages = $this->readReceipts
                    ->enrichDirectMessages($directRows, (int) $conversation->id, (int) $user->id)
                    ->values();
            }
        }

        if ($viewKind === 'none') {
            $selectedSlug = $request->query('team');
            $selectedTeam = $selectedSlug ? $teams->firstWhere('slug', $selectedSlug) : null;

            if ($selectedTeam) {
                abort_unless(
                    $this->teamChatMembers->userCanAccessTeam($user, (int) $selectedTeam->id),
                    403,
                    'ليس لديك صلاحية الدخول إلى دردشة هذا القسم.',
                );
                TeamNotebookController::rememberNotebookTeam($request, $selectedTeam);
                $viewKind = 'team';
                $teamRows = TeamChatMessage::query()
                    ->with(['user:id,name,avatar_path', 'replyTo.user:id,name', 'mentions.user:id,name,username'])
                    ->where('team_id', $selectedTeam->id)
                    ->latest()
                    ->limit(150)
                    ->get()
                    ->reverse()
                    ->values();
                $messages = $this->readReceipts
                    ->enrichTeamMessages($teamRows, (int) $selectedTeam->id, (int) $user->id)
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

        $accessibleTeamIds = $this->teamChatMembers->accessibleTeamIdsForUser($user);

        $teamsPayload = $teams
            ->filter(fn (Team $t) => in_array((int) $t->id, $accessibleTeamIds, true))
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
            'stickerPacks' => ChatStickerCatalog::packsForFrontend(),
            'canManageTeamChatMembers' => (bool) $user?->isAdmin(),
            'selectedTeamChatMembers' => $selectedTeam && $user?->isAdmin()
                ? $this->teamChatMembers->membersPayloadForTeam((int) $selectedTeam->id)
                : [],
            'mentionableUsers' => $this->resolveMentionableUsers(
                $viewKind,
                $selectedTeam,
                $selectedPrivateRoom,
                $selectedDirect,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['nullable', 'string', 'max:4000'],
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
            'sticker_key' => ['nullable', 'string', 'max:64'],
            'attachment' => ChatAttachmentRules::attachmentValidation(),
            'voice_note' => ['sometimes', 'boolean'],
        ]);

        $teamId = (int) $data['team_id'];
        abort_unless(
            $this->teamChatMembers->userCanAccessTeam($request->user(), $teamId),
            403,
            'ليس لديك صلاحية الإرسال في هذه الغرفة.',
        );

        $sticker = ChatReplyResolver::validatedSticker($request);
        if (! ChatReplyResolver::messageHasContent($request, $sticker)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'اكتب رسالة أو أرفق ملفًا أو اختر ملصقًا.',
                    'errors' => ['body' => ['اكتب رسالة أو أرفق ملفًا أو اختر ملصقًا.']],
                ], 422);
            }

            return redirect()->back()->withErrors([
                'body' => 'اكتب رسالة أو أرفق ملفًا أو اختر ملصقًا.',
            ]);
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $stored = ChatAttachmentRules::storeUploadedFile(
                $request->file('attachment'),
                $request->boolean('voice_note'),
            );
            $attachmentPath = $stored['path'];
            $attachmentName = $stored['name'];
            $attachmentMime = $stored['mime'];
            $attachmentSize = $stored['size'];
        }

        $message = TeamChatMessage::query()->create([
            'team_id' => $teamId,
            'user_id' => $request->user()->id,
            'reply_to_message_id' => ChatReplyResolver::resolveTeamReplyId($request, $teamId),
            'sticker_key' => $sticker['key'] ?? null,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $message->load(['user:id,name,avatar_path', 'replyTo.user:id,name', 'mentions.user:id,name,username']);
        $mentionedIds = $this->chatMentions->processTeamMessage($message, $request->user()?->id);
        broadcast(new TeamChatMessageCreated($message));
        $this->smartNotifications->notifyTeamChatMessage($message, $request->user()?->id, $mentionedIds);
        $this->chatUnread->markTeamAsRead($request, (int) $data['team_id']);

        $enriched = $this->readReceipts->enrichTeamMessages(
            collect([$message]),
            $teamId,
            (int) $request->user()->id,
        )->first();

        return ChatStoreResponse::created(
            $request,
            $enriched ?? $message->toChatArray(),
            $this->chatUnread->fullUnreadPayload($request->user()),
        );
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

        $teamId = (int) $data['team_id'];
        abort_unless(
            $this->teamChatMembers->userCanAccessTeam($request->user(), $teamId),
            403,
        );

        $query = TeamChatMessage::query()
            ->with(['user:id,name,avatar_path', 'replyTo.user:id,name', 'mentions.user:id,name,username'])
            ->where('team_id', $teamId)
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
        $this->chatUnread->markTeamAsRead($request, $teamId);

        $userId = (int) $request->user()->id;
        $enriched = $this->readReceipts->enrichTeamMessages($rows, $teamId, $userId);

        return response()->json(array_merge([
            'messages' => $enriched->values(),
        ], $this->chatUnread->fullUnreadPayload($request->user())));
    }

    public function readReceipts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['receipts' => []]);
        }

        $data = $request->validate([
            'kind' => ['required', 'string', 'in:team,private_room,direct'],
            'team_id' => ['required_if:kind,team', 'integer', 'exists:teams,id'],
            'private_room_id' => ['required_if:kind,private_room', 'integer', 'exists:private_chat_rooms,id'],
            'direct_conversation_id' => ['required_if:kind,direct', 'integer', 'exists:direct_conversations,id'],
        ]);

        $viewerId = (int) $user->id;

        if ($data['kind'] === 'team') {
            $payload = $this->readReceipts->teamReceiptsPayload((int) $data['team_id'], $viewerId);

            return response()->json($payload ?? ['receipts' => []]);
        }

        if ($data['kind'] === 'private_room') {
            $roomId = (int) $data['private_room_id'];
            $room = PrivateChatRoom::query()->findOrFail($roomId);
            if (! $room->userIsMember($user)) {
                abort(403);
            }
            $rows = PrivateChatMessage::query()
                ->where('private_chat_room_id', $roomId)
                ->where('user_id', $viewerId)
                ->orderByDesc('id')
                ->limit(80)
                ->get();
            $enriched = $this->readReceipts->enrichPrivateRoomMessages($rows, $roomId, $viewerId);

            return response()->json([
                'receipts' => $enriched
                    ->filter(fn (array $row) => ! empty($row['read_receipt']))
                    ->mapWithKeys(fn (array $row) => [(int) $row['id'] => $row['read_receipt']])
                    ->all(),
            ]);
        }

        $conversationId = (int) $data['direct_conversation_id'];
        $conversation = DirectConversation::query()->findOrFail($conversationId);
        if (! $conversation->userParticipates($user)) {
            abort(403);
        }
        $rows = DirectMessage::query()
            ->where('direct_conversation_id', $conversationId)
            ->where('user_id', $viewerId)
            ->orderByDesc('id')
            ->limit(80)
            ->get();
        $enriched = $this->readReceipts->enrichDirectMessages($rows, $conversationId, $viewerId);

        return response()->json([
            'receipts' => $enriched
                ->filter(fn (array $row) => ! empty($row['read_receipt']))
                ->mapWithKeys(fn (array $row) => [(int) $row['id'] => $row['read_receipt']])
                ->all(),
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

        $this->chatMentions->resyncForEditedMessage($teamChatMessage);
        $teamChatMessage->load(['user:id,name,avatar_path', 'mentions.user:id,name,username']);
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

        $teamId = (int) $data['team_id'];
        abort_unless($this->teamChatMembers->userCanAccessTeam($request->user(), $teamId), 403);

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

        $teamId = (int) $data['team_id'];
        abort_unless($this->teamChatMembers->userCanAccessTeam($request->user(), $teamId), 403);

        $users = TeamChatTypingState::query()
            ->with('user:id,name,avatar_path')
            ->where('team_id', $teamId)
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
            ->get(['id', 'name', 'avatar_path'])
            ->map(function (User $peer) use ($conversationPeerMap, $lastMsgAtByConversation): array {
                $conversationId = $conversationPeerMap[(int) $peer->id] ?? null;

                return [
                    'user_id' => (int) $peer->id,
                    'name' => $peer->name,
                    'avatar_url' => UserAvatar::url($peer),
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

    /**
     * @return list<array{id: int, name: string, username: string}>
     */
    private function resolveMentionableUsers(
        string $viewKind,
        ?Team $selectedTeam,
        ?array $selectedPrivateRoom,
        ?array $selectedDirect,
    ): array {
        if ($viewKind === 'team' && $selectedTeam) {
            return $this->chatMentions->mentionableUsersForTeam((int) $selectedTeam->id);
        }

        if ($viewKind === 'private_room' && $selectedPrivateRoom) {
            return $this->chatMentions->mentionableUsersForPrivateRoom((int) $selectedPrivateRoom['id']);
        }

        if ($viewKind === 'direct' && $selectedDirect) {
            return $this->chatMentions->mentionableUsersForDirectConversation((int) $selectedDirect['id']);
        }

        return [];
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
    private function mapMessage(TeamChatMessage $msg, ?int $viewerId = null): array
    {
        $arr = $msg->toChatArray();
        if ($viewerId === null) {
            return $arr;
        }

        $enriched = $this->readReceipts->enrichTeamMessages(
            collect([$msg]),
            (int) $msg->team_id,
            $viewerId,
        )->first();

        return is_array($enriched) ? $enriched : $arr;
    }
}
