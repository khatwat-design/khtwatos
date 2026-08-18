<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapping = [
            'new'       => 'kull',
            'following' => 'matabaa',
            'potential' => 'mohtamal',
            'unlikely'  => 'barid',
            'qualified' => 'kull',
            'won'       => 'moutafaq',
            'lost'      => 'lam_yattasel',
            'rejected'  => 'lam_yattasel',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('goods_meta_leads')
                ->where('workflow_status', $old)
                ->update(['workflow_status' => $new]);

            DB::table('goods_meta_lead_status_histories')
                ->where('from_status', $old)
                ->update(['from_status' => $new]);

            DB::table('goods_meta_lead_status_histories')
                ->where('to_status', $old)
                ->update(['to_status' => $new]);
        }

        DB::statement("ALTER TABLE goods_meta_leads ALTER COLUMN workflow_status SET DEFAULT 'kull'");
    }

    public function down(): void
    {
        $mapping = [
            'kull'       => 'new',
            'sakhin'     => 'new',
            'matabaa'      => 'following',
            'mohtamal'     => 'potential',
            'barid'        => 'unlikely',
            'moutafaq'     => 'won',
            'lam_yattasel' => 'lost',
        ];

        foreach ($mapping as $old => $new) {
            DB::table('goods_meta_leads')
                ->where('workflow_status', $old)
                ->update(['workflow_status' => $new]);

            DB::table('goods_meta_lead_status_histories')
                ->where('from_status', $old)
                ->update(['from_status' => $new]);

            DB::table('goods_meta_lead_status_histories')
                ->where('to_status', $old)
                ->update(['to_status' => $new]);
        }

        DB::statement("ALTER TABLE goods_meta_leads ALTER COLUMN workflow_status SET DEFAULT 'new'");
    }
};
