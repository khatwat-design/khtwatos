<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('direct_conversation_id')->nullable()->constrained('direct_conversations')->nullOnDelete();
            $table->string('type', 16)->default('voice');
            $table->string('status', 24)->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['caller_id', 'status']);
            $table->index(['callee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_calls');
    }
};
