<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDailySalesReminderLog extends Model
{
    protected $fillable = [
        'client_id',
        'reminder_date',
        'recipient_phone',
        'body',
        'status',
        'skip_reason',
        'provider_message_id',
        'outside_message_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function outsideMessage(): BelongsTo
    {
        return $this->belongsTo(OutsideMessage::class);
    }
}
