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
        'is_late',
        'late_minutes',
        'late_reason',
        'late_approved_at',
        'late_approved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'active_seconds' => 'integer',
            'is_late' => 'boolean',
            'late_minutes' => 'integer',
            'late_approved_at' => 'datetime',
        ];
    }

    public function lateApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'late_approved_by_id');
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
