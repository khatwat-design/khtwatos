<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_message_mentions')) {
            return;
        }

        Schema::create('chat_message_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('mentionable_type');
            $table->unsignedBigInteger('mentionable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mentionable_type', 'mentionable_id', 'user_id']);
            $table->index(['mentionable_type', 'mentionable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_mentions');
    }
};
