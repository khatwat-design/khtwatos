<?php

namespace App\Support;

use App\Models\OutsideConversation;
use App\Models\OutsideMessage;

final class OutsideConversationMetrics
{
    /**
     * @return array{
     *     total_conversations: int,
     *     new_conversations: int,
     *     closed_conversations: int,
     *     outbound_last_7_days: int,
     *     failed_last_7_days: int
     * }
     */
    public static function summary(): array
    {
        return [
            'total_conversations' => OutsideConversation::query()->count(),
            'new_conversations' => OutsideConversation::query()->where('status', 'new')->count(),
            'closed_conversations' => OutsideConversation::query()->where('status', 'closed')->count(),
            'outbound_last_7_days' => OutsideMessage::query()
                ->where('direction', 'outbound')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'failed_last_7_days' => OutsideMessage::query()
                ->where('direction', 'outbound')
                ->where('provider_status', 'failed')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }
}
