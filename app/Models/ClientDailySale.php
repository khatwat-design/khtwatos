<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientDailySale extends Model
{
    protected $fillable = [
        'client_id',
        'sales_date',
        'orders_count',
        'revenue',
        'notes',
        'source',
        'submitted_by_name',
        'submitted_by_email',
        'submitted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sales_date' => 'date',
            'orders_count' => 'integer',
            'revenue' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientDailySaleItem::class)->orderByDesc('subtotal');
    }
}
