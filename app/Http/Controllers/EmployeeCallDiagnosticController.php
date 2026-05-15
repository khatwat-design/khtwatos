<?php

namespace App\Http\Controllers;

use App\Support\EmployeeCallDiagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeCallDiagnosticController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $status = EmployeeCallDiagnostics::serverStatus();

        return response()->json([
            'status' => $status,
            'diag_trace' => EmployeeCallDiagnostics::newTraceId(),
        ]);
    }

    public function storeClientReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trace' => ['nullable', 'string', 'max:64'],
            'steps' => ['nullable', 'array'],
            'steps.*.step' => ['nullable', 'string', 'max:128'],
            'error' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
        ]);

        $traceId = EmployeeCallDiagnostics::ingestClientReport($request->user(), $data);

        return response()->json([
            'ok' => true,
            'diag_trace' => $traceId,
        ]);
    }
}
