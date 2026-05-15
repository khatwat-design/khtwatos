<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_calls', 'chat_logged_at')) {
                $table->timestamp('chat_logged_at')->nullable()->after('ended_at');
            }
        });

        if (Schema::hasTable('direct_messages')) {
            Schema::table('direct_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('direct_messages', 'employee_call_id')) {
                    $table->foreignId('employee_call_id')
                        ->nullable()
                        ->unique()
                        ->after('user_id')
                        ->constrained('employee_calls')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('direct_messages') && Schema::hasColumn('direct_messages', 'employee_call_id')) {
            Schema::table('direct_messages', function (Blueprint $table): void {
                $table->dropForeign(['employee_call_id']);
                $table->dropColumn('employee_call_id');
            });
        }

        if (Schema::hasColumn('employee_calls', 'chat_logged_at')) {
            Schema::table('employee_calls', function (Blueprint $table): void {
                $table->dropColumn('chat_logged_at');
            });
        }
    }
};
