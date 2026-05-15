<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_chat_messages')) {
            Schema::table('team_chat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('team_chat_messages', 'forwarded_from_user_name')) {
                    $table->string('forwarded_from_user_name')->nullable()->after('body');
                }
                if (! Schema::hasColumn('team_chat_messages', 'forwarded_from_context')) {
                    $table->string('forwarded_from_context', 255)->nullable()->after('forwarded_from_user_name');
                }
            });
        }

        if (Schema::hasTable('private_chat_messages')) {
            Schema::table('private_chat_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('private_chat_messages', 'forwarded_from_user_name')) {
                    $table->string('forwarded_from_user_name')->nullable()->after('body');
                }
                if (! Schema::hasColumn('private_chat_messages', 'forwarded_from_context')) {
                    $table->string('forwarded_from_context', 255)->nullable()->after('forwarded_from_user_name');
                }
            });
        }

        if (Schema::hasTable('direct_messages')) {
            Schema::table('direct_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('direct_messages', 'forwarded_from_user_name')) {
                    $table->string('forwarded_from_user_name')->nullable()->after('body');
                }
                if (! Schema::hasColumn('direct_messages', 'forwarded_from_context')) {
                    $table->string('forwarded_from_context', 255)->nullable()->after('forwarded_from_user_name');
                }
            });
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
                if (Schema::hasColumn($tableName, 'forwarded_from_context')) {
                    $columns[] = 'forwarded_from_context';
                }
                if (Schema::hasColumn($tableName, 'forwarded_from_user_name')) {
                    $columns[] = 'forwarded_from_user_name';
                }
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
