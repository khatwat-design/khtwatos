<?php

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskBoard;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $boards = TaskBoard::query()->with(['columns' => fn ($q) => $q->orderBy('sort_order')])->get();

        foreach ($boards as $board) {
            $hasRevision = $board->columns->contains(fn (BoardColumn $c) => $c->name === 'تعديل');
            if ($hasRevision) {
                continue;
            }

            $review = $board->columns->firstWhere('name', 'مراجعة');
            $done = $board->columns->firstWhere('name', 'تم');
            if (! $review || ! $done) {
                continue;
            }

            $between = (int) round(($review->sort_order + $done->sort_order) / 2);
            if ($between <= $review->sort_order) {
                $between = $review->sort_order + 1;
            }
            if ($between >= $done->sort_order) {
                $between = $done->sort_order - 1;
            }

            BoardColumn::query()->create([
                'task_board_id' => $board->id,
                'name' => 'تعديل',
                'sort_order' => $between,
            ]);
        }
    }

    public function down(): void
    {
        foreach (TaskBoard::query()->with('columns')->get() as $board) {
            $revision = $board->columns->firstWhere('name', 'تعديل');
            if (! $revision) {
                continue;
            }
            $waiting = $board->columns->firstWhere('name', 'قائمة الانتظار');
            if ($waiting) {
                Task::query()
                    ->where('board_column_id', $revision->id)
                    ->update(['board_column_id' => $waiting->id]);
            }
            $revision->delete();
        }
    }
};
