<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('goods_meta_lead_id')
                ->nullable()
                ->after('client_id')
                ->constrained('goods_meta_leads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['goods_meta_lead_id']);
            $table->dropColumn('goods_meta_lead_id');
        });
    }
};
