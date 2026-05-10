<?php

namespace App\Support;

/**
 * عناصر القائمة الجانبية الرئيسية (أسماء المسارات كما في Ziggy).
 */
final class NavigationCatalog
{
    /**
     * @return array<string, string> route_name => label_ar
     */
    public static function items(): array
    {
        return [
            'home.index' => 'الرئيسية',
            'tasks.index' => 'المهام',
            'chat.index' => 'الدردشة',
            'outside.index' => 'الخارج',
            'goods.index' => 'البضاعة',
            'meetings.index' => 'الاجتماعات',
            'clients.index' => 'العملاء',
            'warehouse.index' => 'المخزن',
            'academy.index' => 'الأكاديمية',
            'employees.index' => 'الموظفين',
            'settings.index' => 'إعدادات النظام',
        ];
    }

    /**
     * @return list<string>
     */
    public static function routeNames(): array
    {
        return array_keys(self::items());
    }
}
