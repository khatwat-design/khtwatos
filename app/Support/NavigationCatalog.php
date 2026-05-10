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

    /**
     * تجميع الصفحات لعرض أوضح في إعدادات القائمة.
     *
     * @return list<array{id: string, label: string, routes: list<string>}>
     */
    public static function sections(): array
    {
        return [
            [
                'id' => 'workspace',
                'label' => 'لوحة العمل والتواصل',
                'routes' => ['home.index', 'tasks.index', 'chat.index'],
            ],
            [
                'id' => 'channels',
                'label' => 'القنوات والميدان',
                'routes' => ['outside.index', 'goods.index'],
            ],
            [
                'id' => 'commercial',
                'label' => 'العملاء والمواعيد والمخزن',
                'routes' => ['meetings.index', 'clients.index', 'warehouse.index'],
            ],
            [
                'id' => 'org',
                'label' => 'التعليم والموارد',
                'routes' => ['academy.index', 'employees.index'],
            ],
            [
                'id' => 'system',
                'label' => 'إدارة النظام',
                'routes' => ['settings.index'],
            ],
        ];
    }

    /**
     * أقسام مع تسميات مسارات جاهزة للواجهة.
     *
     * @return list<array{id: string, label: string, routes: list<array{route_name: string, label: string}>}>
     */
    public static function sectionsForUi(): array
    {
        $labels = self::items();

        return collect(self::sections())
            ->map(function (array $section) use ($labels): array {
                return [
                    'id' => $section['id'],
                    'label' => $section['label'],
                    'routes' => collect($section['routes'])
                        ->map(fn (string $route): array => [
                            'route_name' => $route,
                            'label' => $labels[$route] ?? $route,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
