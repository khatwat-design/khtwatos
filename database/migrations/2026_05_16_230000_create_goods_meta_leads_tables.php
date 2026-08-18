<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goods_meta_leads')) {
            return;
        }

        Schema::create('goods_meta_leads', function (Blueprint $table) {
            $table->id();
            $table->string('meta_lead_id', 128)->unique();
            $table->timestamp('lead_created_at')->nullable()->index();
            $table->string('full_name')->nullable();
            $table->string('phone', 64)->nullable()->index();
            $table->string('phone_normalized', 32)->nullable()->index();
            $table->string('platform', 32)->nullable();
            $table->string('campaign_id', 64)->nullable()->index();
            $table->string('campaign_name')->nullable();
            $table->string('adset_id', 64)->nullable();
            $table->string('adset_name')->nullable();
            $table->string('ad_id', 64)->nullable();
            $table->string('ad_name')->nullable();
            $table->string('form_id', 64)->nullable();
            $table->string('form_name')->nullable();
            $table->boolean('is_organic')->default(false);
            $table->string('meta_lead_status', 64)->nullable();
            $table->string('monthly_orders_answer')->nullable();
            $table->string('goal_answer')->nullable();
            $table->text('team_notes')->nullable();
            $table->string('probability_label')->nullable();
            $table->string('reason_label')->nullable();
            $table->string('outcome_label')->nullable();
            $table->string('workflow_status', 32)->default('new')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('goods_customer_id')->nullable()->constrained('goods_customers')->nullOnDelete();
            $table->date('first_contact_date')->nullable();
            $table->date('last_contact_date')->nullable();
            $table->date('next_contact_date')->nullable();
            $table->json('form_answers')->nullable();
            $table->json('raw_row')->nullable();
            $table->timestamp('sheet_synced_at')->nullable();
            $table->unsignedInteger('sheet_row_number')->nullable();
            $table->timestamps();

            $table->index(['workflow_status', 'owner_user_id']);
        });

        Schema::create('goods_meta_lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_meta_lead_id')->constrained('goods_meta_leads')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_meta_lead_status_histories');
        Schema::dropIfExists('goods_meta_leads');
    }
};
