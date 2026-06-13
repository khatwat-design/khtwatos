<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('role'); // media_buyer | account_manager

            $table->decimal('spend', 12, 2)->nullable();
            $table->decimal('ctr', 8, 2)->nullable();
            $table->text('client_feedback')->nullable();
            $table->text('personal_feedback')->nullable();

            $table->integer('orders_count')->nullable();
            $table->decimal('revenue', 12, 2)->nullable();

            $table->boolean('is_submitted')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_id', 'report_date', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_operations');
    }
};
