<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->string('messenger_psid', 128)->nullable()->after('instagram_psid');
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->unique('messenger_psid');
        });
    }

    public function down(): void
    {
        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->dropUnique(['messenger_psid']);
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->dropColumn('messenger_psid');
        });
    }
};
