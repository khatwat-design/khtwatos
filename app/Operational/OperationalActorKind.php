<?php

namespace App\Operational;

/**
 * Normalized actor classification for operational events (read-model semantics).
 * Distinct from free-form display names stored in {@see OperationalEvent::$actor}.
 */
enum OperationalActorKind: string
{
    case User = 'user';
    case System = 'system';
    case Integration = 'integration';
}
