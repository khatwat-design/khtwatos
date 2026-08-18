<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('was_overdue')->default(false);
            $table->unsignedInteger('overdue_seconds')->default(0);
            $table->string('from_column_name', 80)->nullable();
            $table->string('to_column_name', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
            $table->index(['task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_logs');
    }
};
