<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMetaOauthToken extends Model
{
    protected $table = 'client_meta_oauth_tokens';

    protected $fillable = [
        'client_id',
        'access_token',
        'token_type',
        'expires_at',
        'meta_user_id',
        'meta_user_name',
        'scopes',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
