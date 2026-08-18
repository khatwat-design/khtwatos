<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('private_chat_room_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['private_chat_room_id', 'user_id']);
        });

        Schema::create('private_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_chat_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_messages');
        Schema::dropIfExists('private_chat_room_user');
        Schema::dropIfExists('private_chat_rooms');
    }
};
