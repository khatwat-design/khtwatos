<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->index(['client_id', 'report_type', 'period_key', 'status']);
            $table->index(['report_type', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_automated_report_logs');
    }
};
