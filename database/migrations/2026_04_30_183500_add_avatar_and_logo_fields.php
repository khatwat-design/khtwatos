<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('availability_schedule');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('portal_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};

