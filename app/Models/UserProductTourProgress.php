<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProductTourProgress extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'user_product_tour_progress';

    protected $fillable = [
        'user_id',
        'tour_id',
        'status',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
