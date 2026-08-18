<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outside_conversations')) {
            return;
        }

        Schema::table('outside_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('outside_conversations', 'intelligence_classification')) {
                $table->string('intelligence_classification', 32)->nullable()->after('last_outbound_at');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_classification_at')) {
                $table->timestamp('intelligence_classification_at')->nullable()->after('intelligence_classification');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_summary')) {
                $table->text('intelligence_summary')->nullable()->after('intelligence_classification_at');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_summary_at')) {
                $table->timestamp('intelligence_summary_at')->nullable()->after('intelligence_summary');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_summary_inbound_count')) {
                $table->unsignedInteger('intelligence_summary_inbound_count')->default(0)->after('intelligence_summary_at');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_suggested_replies')) {
                $table->json('intelligence_suggested_replies')->nullable()->after('intelligence_summary_inbound_count');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_suggested_at')) {
                $table->timestamp('intelligence_suggested_at')->nullable()->after('intelligence_suggested_replies');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_routing')) {
                $table->json('intelligence_routing')->nullable()->after('intelligence_suggested_at');
            }
            if (! Schema::hasColumn('outside_conversations', 'intelligence_client_context')) {
                $table->json('intelligence_client_context')->nullable()->after('intelligence_routing');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('outside_conversations')) {
            return;
        }

        $cols = [
            'intelligence_classification',
            'intelligence_classification_at',
            'intelligence_summary',
            'intelligence_summary_at',
            'intelligence_summary_inbound_count',
            'intelligence_suggested_replies',
            'intelligence_suggested_at',
            'intelligence_routing',
            'intelligence_client_context',
        ];
        $drop = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('outside_conversations', $c)));
        if ($drop === []) {
            return;
        }

        Schema::table('outside_conversations', function (Blueprint $table) use ($drop): void {
            $table->dropColumn($drop);
        });
    }
};
