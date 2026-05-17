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
            if (! Schema::hasColumn('goods_meta_leads', 'next_call_at')) {
                $table->timestamp('next_call_at')->nullable()->after('next_contact_date')->index();
            }
            if (! Schema::hasColumn('goods_meta_leads', 'call_reminder_sent_at')) {
                $table->timestamp('call_reminder_sent_at')->nullable()->after('next_call_at');
            }
            if (! Schema::hasColumn('goods_meta_leads', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('owner_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            foreach (['next_call_at', 'call_reminder_sent_at', 'assigned_at'] as $col) {
                if (Schema::hasColumn('goods_meta_leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
