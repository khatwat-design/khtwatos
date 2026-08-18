<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outside_conversations', function (Blueprint $table): void {
            $table->timestamp('last_inbound_at')->nullable()->after('unread_count');
            $table->timestamp('last_outbound_at')->nullable()->after('last_inbound_at');
        });

        Schema::table('outside_messages', function (Blueprint $table): void {
            $table->string('provider_status')->default('queued')->after('external_message_id');
            $table->text('provider_error')->nullable()->after('provider_status');
            $table->unsignedInteger('retry_count')->default(0)->after('provider_error');
        });

        Schema::create('outside_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_customer_id')->constrained('goods_customers')->cascadeOnDelete();
            $table->foreignId('outside_conversation_id')->nullable()->constrained('outside_conversations')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reminder_type')->default('daily_sales');
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outside_reminder_logs');

        Schema::table('outside_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_status',
                'provider_error',
                'retry_count',
            ]);
        });

        Schema::table('outside_conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'last_inbound_at',
                'last_outbound_at',
            ]);
        });
    }
};

