<?php

namespace App\Support;

/**
 * Default Kanban columns for team task boards (Arabic labels).
 * Single source of truth for TaskController::ensureBoardStructure,
 * ClientWorkflowAutomationService, and seeders.
 */
final class TaskBoardDefaults
{
    /**
     * @var list<array{name: string, sort_order: int}>
     */
    public const COLUMNS = [
        ['name' => 'قائمة الانتظار', 'sort_order' => 10],
        ['name' => 'قيد التنفيذ', 'sort_order' => 20],
        ['name' => 'مراجعة', 'sort_order' => 30],
        ['name' => 'تعديل', 'sort_order' => 35],
        ['name' => 'تم', 'sort_order' => 40],
    ];

    /**
     * @return list<string>
     */
    public static function columnNames(): array
    {
        return array_column(self::COLUMNS, 'name');
    }
}
