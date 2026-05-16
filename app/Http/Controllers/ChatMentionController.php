<?php

namespace App\Http\Controllers;

use App\Models\ChatMentionRead;
use App\Services\ChatMentionUnreadService;
use App\Services\ChatUnreadService;
use App\Services\TeamChatMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMentionController extends Controller
{
    public function __construct(
        private readonly ChatMentionUnreadService $mentionUnread,
        private readonly ChatUnreadService $chatUnread,
        private readonly TeamChatMemberService $teamChatMembers,
    ) {}

    public function acknowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context_type' => ['required', 'string', 'in:team,private_room,direct'],
            'context_id' => ['required', 'integer', 'min:1'],
            'up_to_message_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $contextType = (string) $data['context_type'];
        $contextId = (int) $data['context_id'];

        $this->assertCanAccessContext($user, $contextType, $contextId);

        $this->mentionUnread->acknowledge(
            $user,
            $contextType,
            $contextId,
            (int) $data['up_to_message_id'],
        );

        return response()->json(array_merge(
            $this->mentionUnread->summaryPayload($user),
            [
                'mentionInbox' => $this->mentionUnread->inboxForContext($user, $contextType, $contextId),
            ],
            $this->chatUnread->fullUnreadPayload($user),
        ));
    }

    private function assertCanAccessContext($user, string $contextType, int $contextId): void
    {
        if ($contextType === ChatMentionRead::CONTEXT_TEAM) {
            abort_unless(
                $this->teamChatMembers->userCanAccessTeam($user, $contextId),
                403,
            );

            return;
        }

        if ($contextType === ChatMentionRead::CONTEXT_PRIVATE_ROOM) {
            abort_unless(
                \App\Models\PrivateChatRoom::query()->find($contextId)?->userIsMember($user) ?? false,
                403,
            );

            return;
        }

        abort_unless(
            \App\Models\DirectConversation::query()->find($contextId)?->userParticipates($user) ?? false,
            403,
        );
    }
}
