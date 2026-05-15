<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCall;
use App\Models\User;
use App\Services\EmployeeCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeCallController extends Controller
{
    public function __construct(
        private readonly EmployeeCallService $calls,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'callee_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['sometimes', 'string', 'in:voice,video'],
        ]);

        $caller = $request->user();
        $callee = User::query()->findOrFail((int) $data['callee_id']);
        $type = $data['type'] ?? EmployeeCall::TYPE_VOICE;

        $call = $this->calls->initiate($caller, $callee, $type);

        return response()->json([
            'call' => $call->toPayload($caller),
        ]);
    }

    public function accept(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        $call = $this->calls->accept($employeeCall, $request->user());

        return response()->json([
            'call' => $call->toPayload($request->user()),
        ]);
    }

    public function reject(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        $call = $this->calls->reject($employeeCall, $request->user());

        return response()->json([
            'call' => $call->toPayload($request->user()),
        ]);
    }

    public function end(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        $call = $this->calls->end($employeeCall, $request->user());

        return response()->json([
            'call' => $call->toPayload($request->user()),
        ]);
    }

    public function updateMode(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:voice,video'],
        ]);

        $call = $this->calls->updateMode($employeeCall, $request->user(), $data['type']);

        return response()->json([
            'call' => $call->toPayload($request->user()),
        ]);
    }

    public function signal(Request $request, EmployeeCall $employeeCall): JsonResponse
    {
        if (! $employeeCall->isParticipant($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'signal_type' => ['required', 'string', 'in:offer,answer,ice'],
            'sdp' => ['nullable', 'array'],
            'candidate' => ['nullable', 'array'],
        ]);

        $this->calls->relaySignal(
            $employeeCall,
            $request->user(),
            $data['signal_type'],
            $data['sdp'] ?? null,
            $data['candidate'] ?? null,
        );

        return response()->json(['ok' => true]);
    }
}
