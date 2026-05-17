<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_meta_leads', 'sheet_name')) {
                $table->string('sheet_name', 128)->nullable()->after('meta_lead_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            if (Schema::hasColumn('goods_meta_leads', 'sheet_name')) {
                $table->dropColumn('sheet_name');
            }
        });
    }
};
