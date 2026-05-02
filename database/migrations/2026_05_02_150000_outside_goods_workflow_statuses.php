<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE outside_conversations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'open'");
        }

        if ($driver === 'sqlite') {
            Schema::table('outside_conversations', function (Blueprint $table): void {
                $table->string('status', 32)->default('open')->change();
            });
        }

        DB::table('outside_conversations')->whereIn('status', ['open', 'pending'])->update(['status' => 'new']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE outside_conversations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'new'");
        }

        if ($driver === 'sqlite') {
            Schema::table('outside_conversations', function (Blueprint $table): void {
                $table->string('status', 32)->default('new')->change();
            });
        }

        if (! Schema::hasColumn('goods_customers', 'client_id')) {
            Schema::table('goods_customers', function (Blueprint $table): void {
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            });
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE goods_customers MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'lead'");
        }

        if ($driver === 'sqlite') {
            Schema::table('goods_customers', function (Blueprint $table): void {
                $table->string('status', 32)->default('lead')->change();
            });
        }

        DB::table('goods_customers')->where('status', 'lead')->update(['status' => 'new']);
        DB::table('goods_customers')->where('status', 'prospect')->update(['status' => 'potential']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE goods_customers MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'new'");
        }

        if ($driver === 'sqlite') {
            Schema::table('goods_customers', function (Blueprint $table): void {
                $table->string('status', 32)->default('new')->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('outside_conversations')->where('status', 'new')->update(['status' => 'open']);
        DB::table('goods_customers')->where('status', 'new')->update(['status' => 'lead']);
        DB::table('goods_customers')->where('status', 'potential')->update(['status' => 'prospect']);

        if (Schema::hasColumn('goods_customers', 'client_id')) {
            Schema::table('goods_customers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('client_id');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE outside_conversations MODIFY COLUMN status ENUM('open','pending','qualified','closed') NOT NULL DEFAULT 'open'");
            DB::statement("ALTER TABLE goods_customers MODIFY COLUMN status ENUM('lead','prospect','active','paused','lost') NOT NULL DEFAULT 'lead'");
        }
    }
};
