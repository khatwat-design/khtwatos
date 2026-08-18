<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamNavigationGrant extends Model
{
    protected $fillable = [
        'team_id',
        'route_name',
        'allow_members',
        'allow_leads',
    ];

    protected function casts(): array
    {
        return [
            'allow_members' => 'boolean',
            'allow_leads' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
