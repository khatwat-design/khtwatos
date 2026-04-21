<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->default('calendly');
            $table->string('external_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('invitee_name')->nullable();
            $table->string('invitee_email')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
