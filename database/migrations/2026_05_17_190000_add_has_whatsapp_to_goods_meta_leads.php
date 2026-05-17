<?php

use App\Support\IraqiPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_meta_leads', 'has_whatsapp')) {
                $table->boolean('has_whatsapp')->nullable()->after('phone_normalized');
            }
        });

        if (! Schema::hasColumn('goods_meta_leads', 'has_whatsapp')) {
            return;
        }

        DB::table('goods_meta_leads')
            ->orderBy('id')
            ->chunkById(100, function ($leads): void {
                foreach ($leads as $lead) {
                    DB::table('goods_meta_leads')
                        ->where('id', $lead->id)
                        ->update([
                            'has_whatsapp' => IraqiPhone::isLikelyMobile($lead->phone),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::table('goods_meta_leads', function (Blueprint $table) {
            if (Schema::hasColumn('goods_meta_leads', 'has_whatsapp')) {
                $table->dropColumn('has_whatsapp');
            }
        });
    }
};
