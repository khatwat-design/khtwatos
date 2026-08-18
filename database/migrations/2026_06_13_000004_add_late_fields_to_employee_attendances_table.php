<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->boolean('is_late')->default(false)->after('status');
            $table->unsignedInteger('late_minutes')->nullable()->after('is_late');
            $table->timestamp('late_approved_at')->nullable()->after('late_minutes');
            $table->foreignId('late_approved_by_id')->nullable()->constrained('users')->nullOnDelete()->after('late_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropForeign(['late_approved_by_id']);
            $table->dropColumn(['is_late', 'late_minutes', 'late_approved_at', 'late_approved_by_id']);
        });
    }
};
