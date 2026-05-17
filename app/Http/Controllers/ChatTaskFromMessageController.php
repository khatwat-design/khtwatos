<?php

namespace App\Http\Controllers;

use App\Services\ChatCreateTaskFromMessageService;
use App\Support\ChatStoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatTaskFromMessageController extends Controller
{
    public function __construct(
        private readonly ChatCreateTaskFromMessageService $createTaskFromChat,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', 'string', 'in:team,private_room,direct'],
            'team_slug' => ['required', 'string', 'max:64'],
            'team_id' => ['required_if:context,team', 'integer', 'exists:teams,id'],
            'private_room_id' => ['required_if:context,private_room', 'integer', 'exists:private_chat_rooms,id'],
            'direct_conversation_id' => ['required_if:context,direct', 'integer', 'exists:direct_conversations,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'source_message_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->createTaskFromChat->create($request->user(), $data);

        return ChatStoreResponse::created(
            $request,
            $result['message'],
            ['task' => $result['task']],
        );
    }
}
