<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_automated_report_logs')) {
            Schema::create('client_automated_report_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('report_type', 16);
                $table->string('period_key', 40);
                $table->text('body')->nullable();
                $table->string('delivery', 16);
                $table->string('status', 16);
                $table->string('skip_reason', 64)->nullable();
                $table->foreignId('portal_note_id')->nullable()->constrained('client_portal_notes')->nullOnDelete();
                $table->string('provider_message_id', 128)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->indexExists('client_automated_report_logs', 'carl_client_type_period_status_idx')) {
            Schema::table('client_automated_report_logs', function (Blueprint $table) {
                $table->index(['client_id', 'report_type', 'period_key', 'status'], 'carl_client_type_period_status_idx');
            });
        }

        if (! $this->indexExists('client_automated_report_logs', 'carl_type_period_idx')) {
            Schema::table('client_automated_report_logs', function (Blueprint $table) {
                $table->index(['report_type', 'period_key'], 'carl_type_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_automated_report_logs');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $indexName]
        );

        return $row !== null;
    }
};
