<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_meta_integrations')) {
            return;
        }

        Schema::table('client_meta_integrations', function (Blueprint $table): void {
            $table->dropUnique(['ad_account_id']);
        });

        Schema::table('client_meta_integrations', function (Blueprint $table): void {
            $table->string('ad_account_id')->nullable()->change();
        });

        if (Schema::hasColumn('client_meta_integrations', 'meta_page_id')) {
            Schema::table('client_meta_integrations', function (Blueprint $table): void {
                $table->text('meta_page_id')->nullable()->change();
            });
        }
        if (Schema::hasColumn('client_meta_integrations', 'meta_instagram_account_id')) {
            Schema::table('client_meta_integrations', function (Blueprint $table): void {
                $table->text('meta_instagram_account_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_meta_integrations')) {
            return;
        }

        if (Schema::hasColumn('client_meta_integrations', 'meta_instagram_account_id')) {
            Schema::table('client_meta_integrations', function (Blueprint $table): void {
                $table->string('meta_instagram_account_id')->nullable()->change();
            });
        }
        if (Schema::hasColumn('client_meta_integrations', 'meta_page_id')) {
            Schema::table('client_meta_integrations', function (Blueprint $table): void {
                $table->string('meta_page_id')->nullable()->change();
            });
        }

        Schema::table('client_meta_integrations', function (Blueprint $table): void {
            $table->string('ad_account_id')->nullable(false)->change();
        });

        Schema::table('client_meta_integrations', function (Blueprint $table): void {
            $table->unique('ad_account_id');
        });
    }
};
