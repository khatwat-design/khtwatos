<?php

namespace App\Operational\Automation;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight structured logging for automation runs (Phase 9).
 * Future: swap implementation for queued audit / OpenTelemetry without changing callers.
 */
final class OperationalAutomationTrace
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $automationId, string $phase, array $context = []): void
    {
        Log::info('operational.automation', array_merge([
            'automation_id' => $automationId,
            'phase' => $phase,
            'recorded_at' => now()->toIso8601String(),
        ], $context));
    }
}
