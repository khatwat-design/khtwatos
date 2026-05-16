<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_product_tour_progress')) {
            return;
        }

        Schema::create('user_product_tour_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_id', 64);
            $table->string('status', 16);
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_id'], 'user_product_tour_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_product_tour_progress');
    }
};
