<?php

namespace App\Http\Controllers;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\ChatReadReceiptService;
use App\Services\ChatUnreadService;
use App\Services\SmartNotificationService;
use App\Support\ChatAttachmentRules;
use App\Support\ChatReplyResolver;
use App\Support\ChatStoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DirectChatController extends Controller
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatUnreadService $chatUnread,
        private readonly ChatReadReceiptService $readReceipts,
    ) {}

    public function open(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tab' => ['sometimes', 'string', 'in:all,rooms,direct'],
        ]);

        $peerId = (int) $data['user_id'];
        if ($peerId === (int) $user->id) {
            return redirect()->back()->withErrors([
                'user_id' => 'لا يمكن بدء محادثة مع نفسك.',
            ]);
        }

        $peer = User::query()->findOrFail($peerId);
        $conversation = DirectConversation::findOrCreateBetween($user, $peer);

        $tab = $data['tab'] ?? 'direct';
        if (! in_array($tab, ['all', 'rooms', 'direct'], true)) {
            $tab = 'direct';
        }

        return redirect()->route('chat.index', [
            'tab' => $tab,
            'direct' => $conversation->id,
        ]);
    }

    public function storeMessage(Request $request, DirectConversation $directConversation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->assertParticipant($directConversation, $user);

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

        $conversationId = (int) $directConversation->id;
        $message = DirectMessage::query()->create([
            'direct_conversation_id' => $conversationId,
            'user_id' => $user->id,
            'reply_to_message_id' => ChatReplyResolver::resolveDirectReplyId($request, $conversationId),
            'sticker_key' => $sticker['key'] ?? null,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $message->load(['user:id,name,avatar_path', 'replyTo.user:id,name']);
        $this->smartNotifications->notifyDirectMessage($directConversation, $message, (int) $user->id);
        $this->chatUnread->markDirectAsRead($request, (int) $directConversation->id);

        $enriched = $this->readReceipts->enrichDirectMessages(
            collect([$message]),
            $conversationId,
            (int) $user->id,
        )->first();

        return ChatStoreResponse::created(
            $request,
            $enriched ?? $message->toChatArray((int) $user->id),
            $this->chatUnread->fullUnreadPayload($user),
        );
    }

    public function messages(Request $request, DirectConversation $directConversation): JsonResponse
    {
        $this->assertParticipant($directConversation, $request->user());

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = DirectMessage::query()
            ->with(['user:id,name,avatar_path', 'replyTo.user:id,name', 'employeeCall'])
            ->where('direct_conversation_id', $directConversation->id)
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

        $this->chatUnread->markDirectAsRead($request, (int) $directConversation->id);

        $viewerId = (int) $request->user()->id;
        $enriched = $this->readReceipts->enrichDirectMessages(
            $rows,
            (int) $directConversation->id,
            $viewerId,
        );

        return response()->json(array_merge([
            'messages' => $enriched->values(),
        ], $this->chatUnread->fullUnreadPayload($request->user())));
    }

    private function assertParticipant(DirectConversation $conversation, ?User $user): void
    {
        if (! $user || ! $conversation->userParticipates($user)) {
            abort(403);
        }
    }
}
