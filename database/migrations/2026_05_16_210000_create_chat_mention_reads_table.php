<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_mention_reads')) {
            return;
        }

        Schema::create('chat_mention_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 32);
            $table->unsignedBigInteger('context_id');
            $table->unsignedBigInteger('last_ack_message_id')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'context_type', 'context_id'], 'chat_mention_reads_ctx_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mention_reads');
    }
};
