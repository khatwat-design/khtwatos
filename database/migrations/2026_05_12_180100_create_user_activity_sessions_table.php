<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->string('user_agent', 255)->nullable();
            $table->string('ip', 64)->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
            $table->index(['user_id', 'is_open']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_sessions');
    }
};
