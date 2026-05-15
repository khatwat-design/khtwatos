<?php

namespace App\Support;

use App\Models\EmployeeCall;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EmployeeCallDiagnostics
{
    /**
     * @return array<string, mixed>
     */
    public static function serverStatus(): array
    {
        $checks = [
            'broadcast_connection' => (string) config('broadcasting.default'),
            'reverb_configured' => filled(config('broadcasting.connections.reverb.key')),
            'reverb_host' => (string) config('broadcasting.connections.reverb.options.host'),
            'reverb_scheme' => (string) config('broadcasting.connections.reverb.options.scheme'),
            'employee_calls_table' => Schema::hasTable('employee_calls'),
            'offer_sdp_column' => Schema::hasColumn('employee_calls', 'offer_sdp'),
            'chat_logged_at_column' => Schema::hasColumn('employee_calls', 'chat_logged_at'),
            'app_debug' => (bool) config('app.debug'),
        ];

        $checks['healthy'] = $checks['employee_calls_table']
            && $checks['offer_sdp_column']
            && $checks['broadcast_connection'] === 'reverb'
            && $checks['reverb_configured'];

        if (! $checks['offer_sdp_column']) {
            $checks['fix_hint'] = 'نفّذ على السيرفر: php artisan migrate --force';
        } elseif ($checks['broadcast_connection'] !== 'reverb') {
            $checks['fix_hint'] = 'اضبط BROADCAST_CONNECTION=reverb في .env ثم config:cache';
        } elseif (! $checks['reverb_configured']) {
            $checks['fix_hint'] = 'اضبط REVERB_APP_KEY و REVERB_* في .env ثم npm run build';
        }

        return $checks;
    }

    public static function newTraceId(): string
    {
        return 'ec-'.Str::lower(Str::random(12));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $traceId, string $step, array $context = [], string $level = 'info'): void
    {
        $payload = array_merge([
            'trace' => $traceId,
            'step' => $step,
        ], $context);

        Log::log($level, 'employee_call.'.$step, $payload);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function begin(string $operation, ?User $user = null, ?int $callId = null, array $context = []): string
    {
        $traceId = self::newTraceId();

        self::log($traceId, 'begin', array_merge([
            'operation' => $operation,
            'user_id' => $user?->id,
            'call_id' => $callId,
        ], $context));

        return $traceId;
    }

    public static function end(string $traceId, string $result = 'ok', array $context = []): void
    {
        self::log($traceId, 'end', array_merge(['result' => $result], $context));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attachTrace(array $data, string $traceId): array
    {
        $data['diag_trace'] = $traceId;

        return $data;
    }

    public static function validationResponse(string $traceId, ValidationException $e): JsonResponse
    {
        self::log($traceId, 'validation_failed', [
            'errors' => $e->errors(),
        ], 'warning');

        return response()->json([
            'message' => collect($e->errors())->flatten()->first() ?: 'بيانات غير صالحة.',
            'errors' => $e->errors(),
            'diag_trace' => $traceId,
        ], 422);
    }

    public static function errorResponse(string $traceId, string $operation, Throwable $e): JsonResponse
    {
        self::log($traceId, 'exception', [
            'operation' => $operation,
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 'error');

        $payload = [
            'message' => self::publicMessage($e),
            'diag_trace' => $traceId,
            'diag_operation' => $operation,
        ];

        if (config('app.debug')) {
            $payload['diag_debug'] = [
                'exception' => $e::class,
                'detail' => $e->getMessage(),
                'file' => basename($e->getFile()).':'.$e->getLine(),
            ];
        }

        return response()->json($payload, 500);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ingestClientReport(?User $user, array $payload): string
    {
        $traceId = (string) ($payload['trace'] ?? self::newTraceId());

        self::log($traceId, 'client_report', [
            'user_id' => $user?->id,
            'client_error' => $payload['error'] ?? null,
            'steps' => $payload['steps'] ?? [],
            'meta' => $payload['meta'] ?? [],
        ], isset($payload['error']) ? 'error' : 'info');

        return $traceId;
    }

    public static function snapshotCall(?EmployeeCall $call): ?array
    {
        if (! $call) {
            return null;
        }

        return [
            'id' => $call->id,
            'status' => $call->status,
            'type' => $call->type,
            'caller_id' => $call->caller_id,
            'callee_id' => $call->callee_id,
            'has_offer_sdp' => ! empty($call->offer_sdp),
            'answered_at' => $call->answered_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
        ];
    }

    private static function publicMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())->flatten()->first() ?: 'بيانات غير صالحة.';
        }

        $msg = $e->getMessage();

        if (str_contains($msg, 'offer_sdp')) {
            return 'قاعدة البيانات غير محدّثة (offer_sdp). نفّذ php artisan migrate --force على السيرفر.';
        }

        if (str_contains($msg, 'employee_calls')) {
            return 'جدول المكالمات غير موجود. نفّذ php artisan migrate --force.';
        }

        return config('app.debug') && $msg !== ''
            ? $msg
            : 'Server Error';
    }
}
