<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_message_mentions')) {
            Schema::create('chat_message_mentions', function (Blueprint $table) {
                $table->id();
                $table->string('mentionable_type');
                $table->unsignedBigInteger('mentionable_id');
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['mentionable_type', 'mentionable_id', 'user_id'], 'chat_msg_mentions_uniq');
                $table->index(['mentionable_type', 'mentionable_id'], 'chat_msg_mentions_morph');
            });

            return;
        }

        $this->ensureIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_mentions');
    }

    private function ensureIndexes(): void
    {
        $indexNames = collect(Schema::getConnection()->select('SHOW INDEX FROM chat_message_mentions'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('chat_message_mentions', function (Blueprint $table) use ($indexNames) {
            if (! in_array('chat_msg_mentions_uniq', $indexNames, true)) {
                $table->unique(['mentionable_type', 'mentionable_id', 'user_id'], 'chat_msg_mentions_uniq');
            }

            if (! in_array('chat_msg_mentions_morph', $indexNames, true)) {
                $table->index(['mentionable_type', 'mentionable_id'], 'chat_msg_mentions_morph');
            }
        });
    }
};
