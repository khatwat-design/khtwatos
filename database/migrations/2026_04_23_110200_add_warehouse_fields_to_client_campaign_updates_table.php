<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_campaign_updates', function (Blueprint $table) {
            $table->unsignedInteger('messages_count')->default(0)->after('ad_spend');
            $table->unsignedInteger('clicks_count')->default(0)->after('messages_count');
            $table->text('actions_taken')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('client_campaign_updates', function (Blueprint $table) {
            $table->dropColumn(['messages_count', 'clicks_count', 'actions_taken']);
        });
    }
};
