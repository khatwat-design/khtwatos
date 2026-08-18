<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMetaIntegration extends Model
{
    protected $fillable = [
        'client_id',
        'ad_account_id',
        'meta_business_id',
        'meta_page_id',
        'meta_instagram_account_id',
        'is_active',
        'setup_status',
        'last_synced_at',
        'last_error',
        'last_scan_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_scan_payload' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
