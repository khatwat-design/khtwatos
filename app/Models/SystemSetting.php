<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forgetCache(string $key): void
    {
        Cache::forget('system_setting.'.$key);
    }

    public static function getValue(string $key): ?string
    {
        if (! Schema::hasTable('system_settings')) {
            return null;
        }

        return Cache::rememberForever('system_setting.'.$key, function () use ($key): ?string {
            $row = static::query()->where('key', $key)->first();

            return $row === null ? null : $row->value;
        });
    }

    public static function boolean(string $key, bool $defaultWhenMissing): bool
    {
        $raw = static::getValue($key);
        if ($raw === null || $raw === '') {
            return $defaultWhenMissing;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function put(string $key, string $value, ?int $updatedById): void
    {
        static::forgetCache($key);
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $updatedById],
        );
        static::forgetCache($key);
    }
}
