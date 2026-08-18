<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_meta_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('access_token');
            $table->string('token_type', 20)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('meta_user_id')->nullable();
            $table->string('meta_user_name')->nullable();
            $table->text('scopes')->nullable();
            $table->timestamps();

            $table->unique('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_meta_oauth_tokens');
    }
};
