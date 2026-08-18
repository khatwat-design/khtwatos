<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'meta_oauth_connecting_at')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->timestamp('meta_oauth_connecting_at')->nullable();
            });
        }

        if (Schema::hasTable('client_meta_oauth_tokens')) {
            Schema::table('client_meta_oauth_tokens', function (Blueprint $table) {
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'connection_status')) {
                    $table->string('connection_status', 32)->default('not_connected')->after('client_id');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'missing_permissions')) {
                    $table->json('missing_permissions')->nullable()->after('scopes');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_error_code')) {
                    $table->string('last_error_code', 64)->nullable()->after('missing_permissions');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_error_message')) {
                    $table->text('last_error_message')->nullable()->after('last_error_code');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_error_at')) {
                    $table->timestamp('last_error_at')->nullable()->after('last_error_message');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_error_context')) {
                    $table->json('last_error_context')->nullable()->after('last_error_at');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'oauth_started_at')) {
                    $table->timestamp('oauth_started_at')->nullable()->after('last_error_context');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_token_refresh_at')) {
                    $table->timestamp('last_token_refresh_at')->nullable()->after('oauth_started_at');
                }
                if (! Schema::hasColumn('client_meta_oauth_tokens', 'last_connected_at')) {
                    $table->timestamp('last_connected_at')->nullable()->after('last_token_refresh_at');
                }
            });
        }

        if (! Schema::hasTable('client_meta_connection_logs')) {
            Schema::create('client_meta_connection_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('event', 64);
                $table->string('actor_type', 32)->default('portal_client');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('message')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'created_at']);
            });
        }

        if (Schema::hasTable('client_meta_oauth_tokens') && Schema::hasColumn('client_meta_oauth_tokens', 'connection_status')) {
            DB::table('client_meta_oauth_tokens')
                ->whereNotNull('access_token')
                ->where(function ($q) {
                    $q->whereNull('connection_status')
                        ->orWhere('connection_status', '')
                        ->orWhere('connection_status', 'not_connected');
                })
                ->update(['connection_status' => 'connected']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_meta_connection_logs');

        if (Schema::hasTable('client_meta_oauth_tokens')) {
            $drop = collect([
                'connection_status',
                'missing_permissions',
                'last_error_code',
                'last_error_message',
                'last_error_at',
                'last_error_context',
                'oauth_started_at',
                'last_token_refresh_at',
                'last_connected_at',
            ])->filter(fn (string $col) => Schema::hasColumn('client_meta_oauth_tokens', $col))->all();
            if ($drop !== []) {
                Schema::table('client_meta_oauth_tokens', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'meta_oauth_connecting_at')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('meta_oauth_connecting_at');
            });
        }
    }
};
