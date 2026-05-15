<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_calls', 'offer_sdp')) {
                $table->json('offer_sdp')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_calls', 'offer_sdp')) {
            Schema::table('employee_calls', function (Blueprint $table): void {
                $table->dropColumn('offer_sdp');
            });
        }
    }
};
