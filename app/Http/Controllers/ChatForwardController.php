<?php

namespace App\Http\Controllers;

use App\Services\ChatMessageForwardService;
use App\Services\ChatUnreadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatForwardController extends Controller
{
    public function __construct(
        private readonly ChatMessageForwardService $forwardService,
        private readonly ChatUnreadService $chatUnread,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_kind' => ['required', 'string', 'in:team,private_room,direct'],
            'source_message_id' => ['required', 'integer', 'min:1'],
            'target_kind' => ['required', 'string', 'in:team,private_room,direct'],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $result = $this->forwardService->forward(
            $user,
            $data['source_kind'],
            (int) $data['source_message_id'],
            $data['target_kind'],
            (int) $data['target_id'],
        );

        if ($data['target_kind'] === 'team') {
            $this->chatUnread->markTeamAsRead($request, (int) $data['target_id']);
        } elseif ($data['target_kind'] === 'private_room') {
            $this->chatUnread->markPrivateRoomAsRead($request, (int) $data['target_id']);
        } else {
            $this->chatUnread->markDirectAsRead($request, (int) $data['target_id']);
        }

        return response()->json([
            'ok' => true,
            'redirect_url' => $result['redirect_url'],
        ]);
    }
}
