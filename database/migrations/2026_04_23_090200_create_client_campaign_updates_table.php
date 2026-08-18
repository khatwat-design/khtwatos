<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_campaign_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('ad_spend', 14, 2)->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->unsignedInteger('purchases_count')->default(0);
            $table->decimal('campaign_revenue', 14, 2)->nullable();
            $table->decimal('roas', 10, 2)->nullable();
            $table->decimal('cpa', 10, 2)->nullable();
            $table->decimal('cvr', 10, 2)->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'report_date']);
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_campaign_updates');
    }
};
