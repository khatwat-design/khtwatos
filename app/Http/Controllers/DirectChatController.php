<?php

namespace App\Http\Controllers;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Services\ChatUnreadService;
use App\Services\SmartNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DirectChatController extends Controller
{
    public function __construct(
        private readonly SmartNotificationService $smartNotifications,
        private readonly ChatUnreadService $chatUnread,
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

    public function storeMessage(Request $request, DirectConversation $directConversation): RedirectResponse
    {
        $user = $request->user();
        $this->assertParticipant($directConversation, $user);

        $request->merge([
            'body' => $request->filled('body') ? $request->input('body') : null,
        ]);

        $data = $request->validate([
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

        $message = DirectMessage::query()->create([
            'direct_conversation_id' => $directConversation->id,
            'user_id' => $user->id,
            'body' => trim((string) ($data['body'] ?? '')),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);
        $message->load('user:id,name');
        $this->smartNotifications->notifyDirectMessage($directConversation, $message, (int) $user->id);
        $this->chatUnread->markDirectAsRead($request, (int) $directConversation->id);

        return redirect()->back();
    }

    public function messages(Request $request, DirectConversation $directConversation): JsonResponse
    {
        $this->assertParticipant($directConversation, $request->user());

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = DirectMessage::query()
            ->with('user:id,name')
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

        return response()->json(array_merge([
            'messages' => $rows->map(fn (DirectMessage $msg) => $msg->toChatArray())->values(),
        ], $this->chatUnread->fullUnreadPayload($request->user())));
    }

    private function assertParticipant(DirectConversation $conversation, ?User $user): void
    {
        if (! $user || ! $conversation->userParticipates($user)) {
            abort(403);
        }
    }
}
