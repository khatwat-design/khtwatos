<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('availability_schedule')->nullable()->after('availability_end_time');
        });

        User::query()->select([
            'id',
            'availability_days',
            'availability_start_time',
            'availability_end_time',
        ])->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $days = is_array($user->availability_days) && count($user->availability_days)
                    ? array_map('intval', $user->availability_days)
                    : [0, 1, 2, 3, 4];

                $start = $user->availability_start_time ? substr((string) $user->availability_start_time, 0, 5) : '09:00';
                $end = $user->availability_end_time ? substr((string) $user->availability_end_time, 0, 5) : '17:00';
                $schedule = [];

                for ($day = 0; $day <= 6; $day++) {
                    $enabled = in_array($day, $days, true);
                    $schedule[(string) $day] = [
                        'enabled' => $enabled,
                        'start' => $enabled ? $start : null,
                        'end' => $enabled ? $end : null,
                    ];
                }

                User::query()->whereKey($user->id)->update([
                    'availability_schedule' => json_encode($schedule, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('availability_schedule');
        });
    }
};
