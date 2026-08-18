<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_campaign_updates', function (Blueprint $table) {
            $table->string('data_source', 20)->default('manual')->after('report_date');
            $table->string('source_ref')->nullable()->after('data_source');
            $table->timestamp('fetched_at')->nullable()->after('source_ref');

            $table->index(['client_id', 'data_source']);
        });
    }

    public function down(): void
    {
        Schema::table('client_campaign_updates', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'data_source']);
            $table->dropColumn(['data_source', 'source_ref', 'fetched_at']);
        });
    }
};
