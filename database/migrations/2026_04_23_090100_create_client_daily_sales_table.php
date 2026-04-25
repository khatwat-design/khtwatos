<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_daily_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('sales_date');
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('source', 32)->default('internal');
            $table->string('submitted_by_name')->nullable();
            $table->string('submitted_by_email')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'sales_date']);
            $table->index(['sales_date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_daily_sales');
    }
};
