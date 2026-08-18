<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    public const CATEGORIES = [
        'bug',
        'feature',
        'access',
        'billing',
        'performance',
        'ui_ux',
        'integration',
        'data',
        'security',
        'notifications',
        'backup',
        'mobile',
        'training',
        'task_management',
        'general',
    ];

    protected $fillable = [
        'reference',
        'reporter_id',
        'assignee_id',
        'client_id',
        'resolved_by_id',
        'title',
        'body',
        'category',
        'priority',
        'status',
        'first_response_at',
        'resolved_at',
        'resolution_seconds',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'resolution_seconds' => 'integer',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'TKT-'.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        } while (static::query()->where('reference', $ref)->exists());

        return $ref;
    }
}
