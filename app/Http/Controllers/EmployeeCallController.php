<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCall;
use App\Models\User;
use App\Services\EmployeeCallService;
use App\Support\EmployeeCallDiagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeCallController extends Controller
{
    public function __construct(
        private readonly EmployeeCallService $calls,
    ) {}

    public function releaseStale(Request $request): JsonResponse
    {
        return $this->run($request, 'release_stale', function (string $traceId) use ($request) {
            $released = $this->calls->releaseStaleCallsForUser($request->user());
            EmployeeCallDiagnostics::log($traceId, 'released', ['count' => $released]);

            return ['released' => $released];
        });
    }

    public function pending(Request $request): JsonResponse
    {
        return $this->run($request, 'pending', function (string $traceId) use ($request) {
            $call = $this->calls->pendingIncomingForUser($request->user());
            EmployeeCallDiagnostics::log($traceId, 'pending_result', [
                'call' => EmployeeCallDiagnostics::snapshotCall($call),
                'has_offer_sdp' => ! empty($call?->offer_sdp),
            ]);

            return [
                'call' => $call ? $call->toPayload($request->user()) : null,
                'offer_sdp' => $call?->offer_sdp,
            ];
        });
    }

    public function store(Request $request): JsonResponse
    {
        return $this->run($request, 'store', function (string $traceId) use ($request) {
            $data = $request->validate([
                'callee_id' => ['required', 'integer', 'exists:users,id'],
                'type' => ['sometimes', 'string', 'in:voice,video'],
                'sdp' => ['nullable', 'array'],
            ]);

            EmployeeCallDiagnostics::log($traceId, 'store_validated', [
                'callee_id' => $data['callee_id'],
                'type' => $data['type'] ?? 'voice',
                'has_sdp' => ! empty($data['sdp']),
            ]);

            $caller = $request->user();
            $callee = User::query()->findOrFail((int) $data['callee_id']);
            $type = $data['type'] ?? EmployeeCall::TYPE_VOICE;

            $call = $this->calls->initiate($caller, $callee, $type, $data['sdp'] ?? null);

            EmployeeCallDiagnostics::log($traceId, 'store_created', [
                'call' => EmployeeCallDiagnostics::snapshotCall($call),
            ]);

            return [
                'call' => $call->toPayload($caller),
            ];
        });
    }

    public function accept(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        return $this->run($request, 'accept', function (string $traceId) use ($request, $employeeCall) {
            EmployeeCallDiagnostics::log($traceId, 'accept_incoming', [
                'call' => EmployeeCallDiagnostics::snapshotCall($employeeCall),
            ]);

            $call = $this->calls->accept($employeeCall, $request->user());

            EmployeeCallDiagnostics::log($traceId, 'accept_done', [
                'call' => EmployeeCallDiagnostics::snapshotCall($call),
                'offer_sdp_bytes' => $call->offer_sdp ? strlen((string) json_encode($call->offer_sdp)) : 0,
            ]);

            return [
                'call' => $call->toPayload($request->user()),
                'offer_sdp' => $call->offer_sdp,
            ];
        }, $employeeCall);
    }

    public function reject(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        return $this->run($request, 'reject', function (string $traceId) use ($request, $employeeCall) {
            $call = $this->calls->reject($employeeCall, $request->user());

            return [
                'call' => $call->toPayload($request->user()),
            ];
        }, $employeeCall);
    }

    public function end(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        return $this->run($request, 'end', function (string $traceId) use ($request, $employeeCall) {
            $call = $this->calls->end($employeeCall, $request->user());

            return [
                'call' => $call->toPayload($request->user()),
            ];
        }, $employeeCall);
    }

    public function updateMode(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        return $this->run($request, 'update_mode', function (string $traceId) use ($request, $employeeCall) {
            $data = $request->validate([
                'type' => ['required', 'string', 'in:voice,video'],
            ]);

            $call = $this->calls->updateMode($employeeCall, $request->user(), $data['type']);

            return [
                'call' => $call->toPayload($request->user()),
            ];
        }, $employeeCall);
    }

    public function signal(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        return $this->run($request, 'signal', function (string $traceId) use ($request, $employeeCall) {
            if (! $employeeCall->isParticipant($request->user())) {
                abort(403);
            }

            $data = $request->validate([
                'signal_type' => ['required', 'string', 'in:offer,answer,ice'],
                'sdp' => ['nullable', 'array'],
                'candidate' => ['nullable', 'array'],
            ]);

            EmployeeCallDiagnostics::log($traceId, 'signal_relay', [
                'signal_type' => $data['signal_type'],
                'has_sdp' => ! empty($data['sdp']),
                'has_candidate' => ! empty($data['candidate']),
                'call' => EmployeeCallDiagnostics::snapshotCall($employeeCall),
            ]);

            $this->calls->relaySignal(
                $employeeCall,
                $request->user(),
                $data['signal_type'],
                $data['sdp'] ?? null,
                $data['candidate'] ?? null,
            );

            return ['ok' => true];
        }, $employeeCall);
    }

    /**
     * @param  callable(string): array<string, mixed>  $action
     */
    private function run(Request $request, string $operation, callable $action, ?EmployeeCall $call = null): JsonResponse
    {
        $traceId = EmployeeCallDiagnostics::begin($operation, $request->user(), $call?->id);

        try {
            $payload = $action($traceId);
            EmployeeCallDiagnostics::end($traceId, 'ok');

            return response()->json(EmployeeCallDiagnostics::attachTrace($payload, $traceId));
        } catch (ValidationException $e) {
            return EmployeeCallDiagnostics::validationResponse($traceId, $e);
        } catch (Throwable $e) {
            return EmployeeCallDiagnostics::errorResponse($traceId, $operation, $e);
        }
    }
}
