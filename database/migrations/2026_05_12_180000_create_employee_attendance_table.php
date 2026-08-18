<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('status', 16)->default('present');
            $table->string('mood', 16)->nullable();
            $table->string('plan_for_today', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendances');
    }
};
