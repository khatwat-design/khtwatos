<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMetaOauthToken extends Model
{
    protected $table = 'client_meta_oauth_tokens';

    protected $fillable = [
        'client_id',
        'connection_status',
        'access_token',
        'token_type',
        'expires_at',
        'meta_user_id',
        'meta_user_name',
        'scopes',
        'missing_permissions',
        'last_error_code',
        'last_error_message',
        'last_error_at',
        'last_error_context',
        'oauth_started_at',
        'last_token_refresh_at',
        'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
            'missing_permissions' => 'array',
            'last_error_at' => 'datetime',
            'last_error_context' => 'array',
            'oauth_started_at' => 'datetime',
            'last_token_refresh_at' => 'datetime',
            'last_connected_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
