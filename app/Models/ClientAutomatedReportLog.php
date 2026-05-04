<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAutomatedReportLog extends Model
{
    protected $fillable = [
        'client_id',
        'report_type',
        'period_key',
        'body',
        'delivery',
        'status',
        'skip_reason',
        'portal_note_id',
        'provider_message_id',
        'error_message',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function portalNote(): BelongsTo
    {
        return $this->belongsTo(ClientPortalNote::class);
    }
}
