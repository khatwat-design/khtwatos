<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->timestamp('subscription_started_at')->nullable()->after('logo_path');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at');
            $table->unsignedBigInteger('subscription_activated_by')->nullable()->after('subscription_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'subscription_started_at',
                'subscription_ends_at',
                'subscription_activated_by',
            ]);
        });
    }
};

