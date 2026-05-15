<?php

namespace App\Operational;

/**
 * Read-side severity for unified operational timeline (UI hierarchy only).
 * Not persisted; maps from legacy sources heuristically.
 */
enum OperationalEventSeverity: string
{
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
}
