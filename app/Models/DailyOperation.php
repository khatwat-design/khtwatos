<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyOperation extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'report_date',
        'role',
        'spend',
        'ctr',
        'client_feedback',
        'personal_feedback',
        'orders_count',
        'revenue',
        'is_submitted',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'spend' => 'decimal:2',
            'ctr' => 'decimal:2',
            'revenue' => 'decimal:2',
            'is_submitted' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
