<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientWhatsappMilestoneLog extends Model
{
    protected $table = 'client_whatsapp_milestone_logs';

    protected $fillable = [
        'client_id',
        'milestone_key',
        'pipeline_stage_key',
        'recipient_phone',
        'message_body',
        'status',
        'skip_reason',
        'provider_message_id',
        'error_message',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
