<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outside_conversations', function (Blueprint $table) {
            $table->string('flow_state')->default('new')->after('status');
            $table->boolean('bot_active')->default(true)->after('flow_state');
            $table->text('bot_system_prompt_override')->nullable()->after('bot_active');
            $table->timestamp('bot_last_reply_at')->nullable()->after('bot_system_prompt_override');
            $table->integer('bot_message_count')->default(0)->after('bot_last_reply_at');
        });

        Schema::create('bot_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outside_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction'); // inbound | outbound
            $table->text('message_body');
            $table->string('message_type')->default('text');
            $table->json('ai_context')->nullable();
            $table->json('ai_response')->nullable();
            $table->float('ai_confidence')->nullable();
            $table->float('response_time_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_interaction_logs');
        Schema::dropIfExists('bot_settings');

        Schema::table('outside_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'flow_state', 'bot_active', 'bot_system_prompt_override',
                'bot_last_reply_at', 'bot_message_count',
            ]);
        });
    }
};
