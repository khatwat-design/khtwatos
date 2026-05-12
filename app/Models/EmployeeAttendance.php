<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeAttendance extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'checked_in_at',
        'checked_out_at',
        'status',
        'mood',
        'plan_for_today',
        'note',
        'active_seconds',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'active_seconds' => 'integer',
        ];
    }

    /**
     * نُخزّن work_date كسلسلة Y-m-d فقط لضمان عمل whereBetween/whereDate في SQLite دون فروقات وقت.
     */
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
