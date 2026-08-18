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
            if (! Schema::hasColumn('goods_meta_leads', 'workflow_status_managed_at')) {
                $table->timestamp('workflow_status_managed_at')->nullable()->after('workflow_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            if (Schema::hasColumn('goods_meta_leads', 'workflow_status_managed_at')) {
                $table->dropColumn('workflow_status_managed_at');
            }
        });
    }
};
