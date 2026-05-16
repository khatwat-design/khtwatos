<?php

namespace App\Support;

use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\TeamChatMessage;
use Illuminate\Http\Request;

final class ChatReplyResolver
{
    public static function resolveTeamReplyId(Request $request, int $teamId): ?int
    {
        if (! $request->filled('reply_to_message_id')) {
            return null;
        }

        $replyId = (int) $request->input('reply_to_message_id');
        $exists = TeamChatMessage::query()
            ->whereKey($replyId)
            ->where('team_id', $teamId)
            ->exists();

        return $exists ? $replyId : null;
    }

    public static function resolvePrivateReplyId(Request $request, int $roomId): ?int
    {
        if (! $request->filled('reply_to_message_id')) {
            return null;
        }

        $replyId = (int) $request->input('reply_to_message_id');
        $exists = PrivateChatMessage::query()
            ->whereKey($replyId)
            ->where('private_chat_room_id', $roomId)
            ->exists();

        return $exists ? $replyId : null;
    }

    public static function resolveDirectReplyId(Request $request, int $conversationId): ?int
    {
        if (! $request->filled('reply_to_message_id')) {
            return null;
        }

        $replyId = (int) $request->input('reply_to_message_id');
        $exists = DirectMessage::query()
            ->whereKey($replyId)
            ->where('direct_conversation_id', $conversationId)
            ->whereNull('employee_call_id')
            ->exists();

        return $exists ? $replyId : null;
    }

    /**
     * @return array{key: string, emoji: string}|null
     */
    public static function validatedSticker(Request $request): ?array
    {
        $key = $request->input('sticker_key');
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        if (! ChatStickerCatalog::isValidKey($key)) {
            return null;
        }

        return ChatStickerCatalog::stickerPayload($key);
    }

    public static function messageHasContent(Request $request, ?array $sticker): bool
    {
        if ($sticker !== null) {
            return true;
        }

        if ($request->hasFile('attachment')) {
            return true;
        }

        return trim((string) $request->input('body', '')) !== '';
    }
}
