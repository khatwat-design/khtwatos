<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('portal_username', 120)->nullable()->unique()->after('portal_token');
            $table->string('portal_password')->nullable()->after('portal_username');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['portal_username']);
            $table->dropColumn(['portal_username', 'portal_password']);
        });
    }
};
