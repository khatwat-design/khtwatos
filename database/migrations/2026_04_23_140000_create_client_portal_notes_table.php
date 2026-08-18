<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['client_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_notes');
    }
};
