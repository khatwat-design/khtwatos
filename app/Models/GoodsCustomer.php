<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsCustomer extends Model
{
    protected $fillable = [
        'outside_contact_id',
        'name',
        'phone',
        'company',
        'status',
        'notes',
        'owner_user_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OutsideContact::class, 'outside_contact_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(GoodsCustomerStatusHistory::class)->orderByDesc('created_at');
    }
}

