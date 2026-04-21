<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('availability_days')->nullable()->after('is_bookable');
            $table->time('availability_start_time')->nullable()->after('availability_days');
            $table->time('availability_end_time')->nullable()->after('availability_start_time');
        });

        DB::table('users')
            ->whereNull('availability_days')
            ->update([
                'availability_days' => json_encode([0, 1, 2, 3, 4]),
                'availability_start_time' => '09:00:00',
                'availability_end_time' => '17:00:00',
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'availability_days',
                'availability_start_time',
                'availability_end_time',
            ]);
        });
    }
};
