<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsCustomerStatusHistory extends Model
{
    protected $fillable = [
        'goods_customer_id',
        'from_status',
        'to_status',
        'note',
        'user_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(GoodsCustomer::class, 'goods_customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

