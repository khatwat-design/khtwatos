<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDailySaleItem extends Model
{
    protected $fillable = [
        'client_daily_sale_id',
        'client_product_id',
        'product_name',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function dailySale(): BelongsTo
    {
        return $this->belongsTo(ClientDailySale::class, 'client_daily_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ClientProduct::class, 'client_product_id');
    }
}
