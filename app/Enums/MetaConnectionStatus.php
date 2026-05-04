<?php

namespace App\Enums;

enum MetaConnectionStatus: string
{
    case NotConnected = 'not_connected';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case PartiallyConnected = 'partially_connected';
    case Error = 'error';
    case NeedsReconnect = 'needs_reconnect';
}
