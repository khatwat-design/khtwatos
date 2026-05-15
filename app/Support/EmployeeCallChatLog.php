<?php

namespace App\Support;

use App\Models\DirectMessage;
use App\Models\EmployeeCall;

class EmployeeCallChatLog
{
    public static function logToDirectChat(EmployeeCall $call): void
    {
        if ($call->chat_logged_at || ! $call->direct_conversation_id) {
            return;
        }

        if (! in_array($call->status, [
            EmployeeCall::STATUS_ENDED,
            EmployeeCall::STATUS_REJECTED,
            EmployeeCall::STATUS_MISSED,
            EmployeeCall::STATUS_BUSY,
        ], true)) {
            return;
        }

        $exists = DirectMessage::query()
            ->where('employee_call_id', $call->id)
            ->exists();

        if ($exists) {
            $call->update(['chat_logged_at' => now()]);

            return;
        }

        DirectMessage::query()->create([
            'direct_conversation_id' => $call->direct_conversation_id,
            'user_id' => $call->caller_id,
            'body' => '',
            'employee_call_id' => $call->id,
        ]);

        $call->update(['chat_logged_at' => now()]);
    }

    public static function durationSeconds(EmployeeCall $call): int
    {
        if (! $call->answered_at || ! $call->ended_at) {
            return 0;
        }

        return max(0, $call->answered_at->diffInSeconds($call->ended_at));
    }

    public static function displayStatus(EmployeeCall $call): string
    {
        if ($call->status === EmployeeCall::STATUS_REJECTED) {
            return 'rejected';
        }

        if (in_array($call->status, [EmployeeCall::STATUS_MISSED, EmployeeCall::STATUS_BUSY], true)) {
            return 'missed';
        }

        if ($call->status === EmployeeCall::STATUS_ENDED) {
            return self::durationSeconds($call) > 0 ? 'completed' : 'missed';
        }

        return 'missed';
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 1) {
            return '0:00';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForViewer(EmployeeCall $call, ?int $viewerId): array
    {
        $direction = $viewerId && (int) $call->caller_id === (int) $viewerId
            ? 'outgoing'
            : 'incoming';

        $type = $call->type === EmployeeCall::TYPE_VIDEO ? 'video' : 'voice';
        $status = self::displayStatus($call);
        $durationSeconds = self::durationSeconds($call);
        $typeLabel = $type === 'video' ? 'مكالمة فيديو' : 'مكالمة صوتية';

        $label = match ($status) {
            'completed' => $typeLabel.' · '.self::formatDuration($durationSeconds),
            'rejected' => 'تم رفض المكالمة',
            default => $typeLabel.' فائتة',
        };

        return [
            'id' => $call->id,
            'type' => $type,
            'status' => $status,
            'direction' => $direction,
            'duration_seconds' => $durationSeconds,
            'label' => $label,
        ];
    }
}
