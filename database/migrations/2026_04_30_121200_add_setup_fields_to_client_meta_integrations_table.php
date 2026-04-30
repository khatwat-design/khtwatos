<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_meta_integrations', function (Blueprint $table) {
            $table->string('meta_business_id')->nullable()->after('ad_account_id');
            $table->string('meta_page_id')->nullable()->after('meta_business_id');
            $table->string('meta_instagram_account_id')->nullable()->after('meta_page_id');
            $table->string('setup_status', 30)->nullable()->after('is_active');
            $table->json('last_scan_payload')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('client_meta_integrations', function (Blueprint $table) {
            $table->dropColumn([
                'meta_business_id',
                'meta_page_id',
                'meta_instagram_account_id',
                'setup_status',
                'last_scan_payload',
            ]);
        });
    }
};
