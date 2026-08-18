<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserActivitySession extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'started_at',
        'last_heartbeat_at',
        'ended_at',
        'active_seconds',
        'user_agent',
        'ip',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ended_at' => 'datetime',
            'active_seconds' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function setWorkDateAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['work_date'] = null;

            return;
        }

        $this->attributes['work_date'] = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : Carbon::parse((string) $value)->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
