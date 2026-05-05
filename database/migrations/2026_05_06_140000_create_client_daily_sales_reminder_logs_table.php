<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_daily_sales_reminder_logs')) {
            return;
        }

        Schema::create('client_daily_sales_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('reminder_date');
            $table->string('recipient_phone', 32)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 24);
            $table->string('skip_reason', 64)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->foreignId('outside_message_id')->nullable()->constrained('outside_messages')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'reminder_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_daily_sales_reminder_logs');
    }
};
