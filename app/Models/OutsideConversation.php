<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutsideConversation extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    protected $fillable = [
        'outside_contact_id',
        'status',
        'latest_message_preview',
        'unread_count',
        'last_inbound_at',
        'last_outbound_at',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(OutsideContact::class, 'outside_contact_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutsideMessage::class)->orderByDesc('created_at');
    }
}
