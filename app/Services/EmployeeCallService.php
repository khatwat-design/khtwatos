<?php

namespace App\Services;

use App\Events\EmployeeCallSignaling;
use App\Models\DirectConversation;
use App\Models\EmployeeCall;
use App\Models\User;
use App\Support\EmployeeCallChatLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeCallService
{
    public function releaseStaleCallsForUser(User $user): int
    {
        $released = 0;

        $calls = EmployeeCall::query()
            ->whereIn('status', [EmployeeCall::STATUS_RINGING, EmployeeCall::STATUS_ACTIVE])
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)->orWhere('callee_id', $user->id);
            })
            ->get();

        foreach ($calls as $call) {
            if (! $this->callIsStale($call)) {
                continue;
            }

            $this->forceTerminate($call);
            $released++;
        }

        return $released;
    }

    public function pendingIncomingForUser(User $user): ?EmployeeCall
    {
        $this->releaseStaleCallsForUser($user);

        return EmployeeCall::query()
            ->with(['caller:id,name,avatar_path', 'callee:id,name,avatar_path'])
            ->where('callee_id', $user->id)
            ->where('status', EmployeeCall::STATUS_RINGING)
            ->latest('id')
            ->first();
    }

    public function activeCallForUser(User $user): ?EmployeeCall
    {
        $this->releaseStaleCallsForUser($user);

        return EmployeeCall::query()
            ->whereIn('status', [EmployeeCall::STATUS_RINGING, EmployeeCall::STATUS_ACTIVE])
            ->where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)->orWhere('callee_id', $user->id);
            })
            ->latest('id')
            ->first();
    }

    public function initiate(User $caller, User $callee, string $type = EmployeeCall::TYPE_VOICE): EmployeeCall
    {
        if ((int) $caller->id === (int) $callee->id) {
            throw ValidationException::withMessages([
                'callee_id' => 'لا يمكن الاتصال بنفسك.',
            ]);
        }

        if (! in_array($type, [EmployeeCall::TYPE_VOICE, EmployeeCall::TYPE_VIDEO], true)) {
            $type = EmployeeCall::TYPE_VOICE;
        }

        return DB::transaction(function () use ($caller, $callee, $type) {
            $this->releaseStaleCallsForUser($caller);
            $this->releaseStaleCallsForUser($callee);

            if ($this->activeCallForUser($caller) || $this->activeCallForUser($callee)) {
                throw ValidationException::withMessages([
                    'callee_id' => 'الطرف الآخر مشغول في مكالمة أخرى.',
                ]);
            }

            $conversation = DirectConversation::findOrCreateBetween($caller, $callee);

            $call = EmployeeCall::query()->create([
                'caller_id' => $caller->id,
                'callee_id' => $callee->id,
                'direct_conversation_id' => $conversation->id,
                'type' => $type,
                'status' => EmployeeCall::STATUS_RINGING,
            ]);

            $this->broadcastToUser($callee, 'incoming', $call, $caller, [
                'call' => $call->toPayload($callee),
            ]);

            return $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);
        });
    }

    public function accept(EmployeeCall $call, User $user): EmployeeCall
    {
        $this->assertCallee($call, $user);

        if ($call->status !== EmployeeCall::STATUS_RINGING) {
            throw ValidationException::withMessages([
                'call' => 'المكالمة لم تعد متاحة.',
            ]);
        }

        $call->update([
            'status' => EmployeeCall::STATUS_ACTIVE,
            'answered_at' => now(),
        ]);

        $call = $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);

        $this->broadcastToUser($call->caller, 'accepted', $call, $user, [
            'call' => $call->toPayload($call->caller),
        ]);

        return $call;
    }

    public function reject(EmployeeCall $call, User $user): EmployeeCall
    {
        if (! $call->isParticipant($user)) {
            abort(403);
        }

        if (! in_array($call->status, [EmployeeCall::STATUS_RINGING, EmployeeCall::STATUS_ACTIVE], true)) {
            return $call;
        }

        $call->update([
            'status' => EmployeeCall::STATUS_REJECTED,
            'ended_at' => now(),
        ]);

        $call = $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);
        $other = $call->otherUser($user);

        if ($other) {
            $this->broadcastToUser($other, 'rejected', $call, $user, [
                'call' => $call->toPayload($other),
            ]);
        }

        EmployeeCallChatLog::logToDirectChat($call);

        return $call;
    }

    public function updateMode(EmployeeCall $call, User $user, string $type): EmployeeCall
    {
        if (! $call->isParticipant($user)) {
            abort(403);
        }

        if ($call->status !== EmployeeCall::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'type' => 'يمكن تغيير النوع أثناء المكالمة النشطة فقط.',
            ]);
        }

        if (! in_array($type, [EmployeeCall::TYPE_VOICE, EmployeeCall::TYPE_VIDEO], true)) {
            $type = EmployeeCall::TYPE_VOICE;
        }

        $call->update(['type' => $type]);
        $call = $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);
        $other = $call->otherUser($user);

        if ($other) {
            $this->broadcastToUser($other, 'mode-changed', $call, $user, [
                'type' => $type,
                'call' => $call->toPayload($other),
            ]);
        }

        return $call;
    }

    public function end(EmployeeCall $call, User $user): EmployeeCall
    {
        if (! $call->isParticipant($user)) {
            abort(403);
        }

        if (in_array($call->status, [
            EmployeeCall::STATUS_ENDED,
            EmployeeCall::STATUS_MISSED,
            EmployeeCall::STATUS_REJECTED,
            EmployeeCall::STATUS_BUSY,
        ], true)) {
            return $call;
        }

        $status = $call->status === EmployeeCall::STATUS_RINGING
            ? EmployeeCall::STATUS_MISSED
            : EmployeeCall::STATUS_ENDED;

        $call->update([
            'status' => $status,
            'ended_at' => now(),
        ]);

        $call = $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);
        $other = $call->otherUser($user);

        if ($other) {
            $this->broadcastToUser($other, 'ended', $call, $user, [
                'call' => $call->toPayload($other),
            ]);
        }

        EmployeeCallChatLog::logToDirectChat($call);

        return $call;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function relaySignal(
        EmployeeCall $call,
        User $from,
        string $signalType,
        ?array $sdp = null,
        ?array $candidate = null,
    ): void {
        if (! $call->isParticipant($from)) {
            abort(403);
        }

        if (! in_array($call->status, [EmployeeCall::STATUS_RINGING, EmployeeCall::STATUS_ACTIVE], true)) {
            return;
        }

        $target = $call->otherUser($from);
        if (! $target) {
            return;
        }

        $this->broadcastToUser($target, $signalType, $call, $from, array_filter([
            'sdp' => $sdp,
            'candidate' => $candidate,
            'type' => $call->type,
        ]));
    }

    private function assertCallee(EmployeeCall $call, User $user): void
    {
        if ((int) $call->callee_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function callIsStale(EmployeeCall $call): bool
    {
        if ($call->status === EmployeeCall::STATUS_RINGING) {
            return $call->created_at !== null && $call->created_at->lt(now()->subMinutes(2));
        }

        if ($call->status === EmployeeCall::STATUS_ACTIVE) {
            $anchor = $call->answered_at ?? $call->updated_at ?? $call->created_at;

            return $anchor !== null && $anchor->lt(now()->subMinutes(30));
        }

        return false;
    }

    private function forceTerminate(EmployeeCall $call): void
    {
        if (in_array($call->status, [
            EmployeeCall::STATUS_ENDED,
            EmployeeCall::STATUS_MISSED,
            EmployeeCall::STATUS_REJECTED,
            EmployeeCall::STATUS_BUSY,
        ], true)) {
            return;
        }

        $status = $call->status === EmployeeCall::STATUS_RINGING
            ? EmployeeCall::STATUS_MISSED
            : EmployeeCall::STATUS_ENDED;

        $call->update([
            'status' => $status,
            'ended_at' => now(),
        ]);

        $call = $call->fresh(['caller:id,name,avatar_path', 'callee:id,name,avatar_path']);

        EmployeeCallChatLog::logToDirectChat($call);

        foreach ([$call->caller, $call->callee] as $participant) {
            if ($participant) {
                $this->broadcastToUser($participant, 'ended', $call, $participant, [
                    'call' => $call->toPayload($participant),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function broadcastToUser(User $target, string $action, EmployeeCall $call, User $from, array $extra = []): void
    {
        broadcast(new EmployeeCallSignaling(
            targetUserId: (int) $target->id,
            action: $action,
            callId: (int) $call->id,
            fromUserId: (int) $from->id,
            payload: array_merge([
                'call_id' => $call->id,
                'type' => $call->type,
            ], $extra),
        ));
    }
}
