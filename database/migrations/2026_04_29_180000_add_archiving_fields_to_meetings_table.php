<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('completed_at');
            $table->foreignId('archived_by_id')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            $table->string('archived_reason', 120)->nullable()->after('archived_by_id');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by_id');
            $table->dropColumn(['archived_at', 'archived_reason']);
        });
    }
};

