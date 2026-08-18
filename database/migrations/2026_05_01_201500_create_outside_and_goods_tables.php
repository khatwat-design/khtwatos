<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outside_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->unique();
            $table->string('channel')->default('whatsapp');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('outside_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outside_contact_id')->constrained('outside_contacts')->cascadeOnDelete();
            $table->enum('status', ['open', 'pending', 'qualified', 'closed'])->default('open');
            $table->string('latest_message_preview')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('outside_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outside_conversation_id')->constrained('outside_conversations')->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound', 'system'])->default('inbound');
            $table->string('message_type')->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('external_message_id')->nullable()->index();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outside_contact_id')->nullable()->constrained('outside_contacts')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('company')->nullable();
            $table->enum('status', ['lead', 'prospect', 'active', 'paused', 'lost'])->default('lead');
            $table->text('notes')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_customer_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_customer_id')->constrained('goods_customers')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_customer_status_histories');
        Schema::dropIfExists('goods_customers');
        Schema::dropIfExists('outside_messages');
        Schema::dropIfExists('outside_conversations');
        Schema::dropIfExists('outside_contacts');
    }
};

