<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('private_chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['private_chat_room_id', 'user_id']);
        });

        Schema::create('direct_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('direct_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['direct_conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_chat_reads');
        Schema::dropIfExists('private_chat_reads');
    }
};
