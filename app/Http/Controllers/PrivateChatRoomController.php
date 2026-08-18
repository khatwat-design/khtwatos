<?php

namespace App\Http\Controllers;

use App\Models\PrivateChatMessage;
use App\Models\PrivateChatRoom;
use App\Models\User;
use App\Models\ChatMentionRead;
use App\Services\ChatMentionService;
use App\Services\ChatMentionUnreadService;
use App\Services\ChatReadReceiptService;
use App\Services\ChatUnreadService;
use App\Services\SmartNotificationService;
use App\Support\ChatAttachmentRules;
use App\Support\ChatReplyResolver;
use App\Support\ChatStoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateChatRoomController extends Controller
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatUnreadService $chatUnread,
        private readonly ChatReadReceiptService $readReceipts,
        private readonly ChatMentionService $chatMentions,
        private readonly ChatMentionUnreadService $mentionUnread,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ]);

        $ids = collect($data['member_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $user->id)
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->back()->withErrors([
                'member_ids' => 'اختر عضوًا واحدًا على الأقل بخلافك.',
            ]);
        }

        $room = PrivateChatRoom::query()->create([
            'name' => trim($data['name']),
            'creator_id' => $user->id,
        ]);

        $memberIds = $ids->push((int) $user->id)->unique()->values()->all();
        $room->members()->attach($memberIds);

        return redirect()->route('chat.index', [
            'tab' => 'rooms',
            'private_room' => $room->id,
        ]);
    }

    public function destroy(Request $request, PrivateChatRoom $privateChatRoom): RedirectResponse
    {
        $user = $request->user();
        if ((int) $privateChatRoom->creator_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403, 'حذف الغرفة متاح لمنشئها فقط.');
        }

        foreach ($privateChatRoom->messages()->get() as $message) {
            if ($message->attachment_path) {
                Storage::disk('public')->delete($message->attachment_path);
            }
        }

        $privateChatRoom->delete();

        return redirect()->route('chat.index', ['tab' => 'rooms']);
    }

    public function leave(Request $request, PrivateChatRoom $privateChatRoom): RedirectResponse
    {
        $user = $request->user();
        $this->assertMember($privateChatRoom, $user);

        if ((int) $privateChatRoom->creator_id === (int) $user->id) {
            abort(403, 'استخدم «حذف الغرفة» إذا كنت المنشئ.');
        }

        $privateChatRoom->members()->detach($user->id);

        return redirect()->route('chat.index', ['tab' => 'rooms']);
    }

    public function storeMessage(Request $request, PrivateChatRoom $privateChatRoom): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->assertMember($privateChatRoom, $user);

        $request->merge([
            'body' => $request->filled('body') ? $request->input('body') : null,
        ]);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
            'sticker_key' => ['nullable', 'string', 'max:64'],
            'attachment' => ChatAttachmentRules::attachmentValidation(),
            'voice_note' => ['sometimes', 'boolean'],
        ]);

        $sticker = ChatReplyResolver::validatedSticker($request);
        if (! ChatReplyResolver::messageHasContent($request, $sticker)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'اكتب رسالة أو أرفق ملفًا أو اختر ملصقًا.',
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

        $roomId = (int) $privateChatRoom->id;
        $message = PrivateChatMessage::query()->create([
            'private_chat_room_id' => $roomId,
            'user_id' => $user->id,
            'reply_to_message_id' => ChatReplyResolver::resolvePrivateReplyId($request, $roomId),
            'sticker_key' => $sticker['key'] ?? null,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $message->load(['user:id,name,avatar_path', 'replyTo.user:id,name', 'mentions.user:id,name,username']);
        $mentionedIds = $this->chatMentions->processPrivateMessage($message, $privateChatRoom, (int) $user->id);
        $this->smartNotifications->notifyPrivateRoomMessage($privateChatRoom, $message, (int) $user->id, $mentionedIds);
        $this->chatUnread->markPrivateRoomAsRead($request, (int) $privateChatRoom->id);

        $enriched = $this->readReceipts->enrichPrivateRoomMessages(
            collect([$message]),
            $roomId,
            (int) $user->id,
        )->first();

        return ChatStoreResponse::created(
            $request,
            $enriched ?? $message->toChatArray(),
            $this->chatApiPayload($user, ChatMentionRead::CONTEXT_PRIVATE_ROOM, (int) $privateChatRoom->id),
        );
    }

    public function messages(Request $request, PrivateChatRoom $privateChatRoom): JsonResponse
    {
        $this->assertMember($privateChatRoom, $request->user());

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'around_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $roomId = (int) $privateChatRoom->id;
        $query = PrivateChatMessage::query()
            ->with(['user:id,name,avatar_path', 'replyTo.user:id,name', 'mentions.user:id,name,username'])
            ->where('private_chat_room_id', $roomId)
            ->orderBy('id');

        if (! empty($data['around_message_id'])) {
            $anchor = (int) $data['around_message_id'];
            $query->where('id', '>=', max(1, $anchor - 100))->limit(220);
        } elseif (! empty($data['after_id'])) {
            $query->where('id', '>', (int) $data['after_id']);
        } else {
            $query->latest()->limit(150);
        }

        $rows = $query->get();
        if (empty($data['after_id']) && empty($data['around_message_id'])) {
            $rows = $rows->reverse()->values();
        }

        if (empty($data['around_message_id'])) {
            $this->chatUnread->markPrivateRoomAsRead($request, $roomId);
        }

        $viewerId = (int) $request->user()->id;
        $enriched = $this->readReceipts->enrichPrivateRoomMessages($rows, $roomId, $viewerId);

        return response()->json(array_merge([
            'messages' => $enriched->values(),
        ], $this->chatApiPayload($request->user(), ChatMentionRead::CONTEXT_PRIVATE_ROOM, $roomId)));
    }

    /**
     * @return array<string, mixed>
     */
    private function chatApiPayload($user, ?string $activeContextType = null, ?int $activeContextId = null): array
    {
        $payload = array_merge(
            $this->chatUnread->fullUnreadPayload($user),
            $this->mentionUnread->summaryPayload($user),
        );

        if ($user && $activeContextType && $activeContextId) {
            $payload['mentionInbox'] = $this->mentionUnread->inboxForContext($user, $activeContextType, $activeContextId);
        }

        return $payload;
    }

    private function assertMember(PrivateChatRoom $room, ?User $user): void
    {
        if (! $user || ! $room->userIsMember($user)) {
            abort(403);
        }
    }
}
