<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_chat_members')) {
            Schema::create('team_chat_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['team_id', 'user_id']);
            });
        }

        foreach (['team_chat_messages', 'private_chat_messages', 'direct_messages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'reply_to_message_id')) {
                    $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn($tableName, 'sticker_key')) {
                    $table->string('sticker_key', 64)->nullable()->after('body');
                }
            });
        }

        if (Schema::hasTable('team_user') && Schema::hasTable('team_chat_members')) {
            $pairs = DB::table('team_user')->select('team_id', 'user_id')->distinct()->get();
            $now = now();
            foreach ($pairs as $pair) {
                DB::table('team_chat_members')->insertOrIgnore([
                    'team_id' => $pair->team_id,
                    'user_id' => $pair->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['team_chat_messages', 'private_chat_messages', 'direct_messages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $columns = [];
                if (Schema::hasColumn($tableName, 'sticker_key')) {
                    $columns[] = 'sticker_key';
                }
                if (Schema::hasColumn($tableName, 'reply_to_message_id')) {
                    $columns[] = 'reply_to_message_id';
                }
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        Schema::dropIfExists('team_chat_members');
    }
};
