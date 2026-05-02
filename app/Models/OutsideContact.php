<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutsideContact extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'instagram_psid',
        'channel',
        'client_id',
        'assigned_user_id',
        'last_message_at',
        'meta',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'meta' => 'array',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(OutsideConversation::class);
    }

    public function goodsCustomers(): HasMany
    {
        return $this->hasMany(GoodsCustomer::class);
    }
}
