<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('member')->after('password');
            $table->text('calendly_url')->nullable()->after('role');
            $table->boolean('is_bookable')->default(true)->after('calendly_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'calendly_url', 'is_bookable']);
        });
    }
};
