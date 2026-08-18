<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->string('instagram_psid', 128)->nullable()->after('phone');
            $table->foreignId('client_id')->nullable()->after('assigned_user_id')->constrained('clients')->nullOnDelete();
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->unique('instagram_psid');
        });

        Schema::table('outside_messages', function (Blueprint $table): void {
            $table->string('channel', 32)->default('whatsapp')->after('outside_conversation_id');
        });

        if (Schema::hasTable('outside_messages')) {
            DB::table('outside_messages')->update(['channel' => 'whatsapp']);
        }
    }

    public function down(): void
    {
        Schema::table('outside_messages', function (Blueprint $table): void {
            $table->dropColumn('channel');
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->dropUnique(['instagram_psid']);
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('outside_contacts', function (Blueprint $table): void {
            $table->dropColumn('instagram_psid');
        });
    }
};
