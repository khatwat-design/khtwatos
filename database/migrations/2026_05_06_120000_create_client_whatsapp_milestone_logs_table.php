<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_whatsapp_milestone_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('milestone_key', 64);
            $table->string('pipeline_stage_key', 64);
            $table->string('recipient_phone', 32)->nullable();
            $table->text('message_body')->nullable();
            $table->string('status', 16);
            $table->string('skip_reason', 64)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'milestone_key']);
            $table->index(['client_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_whatsapp_milestone_logs');
    }
};
