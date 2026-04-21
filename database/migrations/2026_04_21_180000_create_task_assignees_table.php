<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id']);
        });

        $rows = DB::table('tasks')
            ->whereNotNull('assignee_id')
            ->select(['id as task_id', 'assignee_id as user_id'])
            ->get()
            ->map(fn ($row) => [
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if (!empty($rows)) {
            DB::table('task_assignees')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
