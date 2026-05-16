<?php

namespace App\Support;

/**
 * تعريف الجولات التدريبية: الجمهور، الترتيب، والمسار المرتبط.
 */
final class ProductTourCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     route_name: string,
     *     route_match: string,
     *     order: int,
     *     roles: list<string>|null,
     *     team_slugs: list<string>|null,
     *     team_lead_only: bool,
     *     gate: string|null,
     *     personas: list<string>|null
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'welcome',
                'title' => 'مرحباً بك في خطوات',
                'description' => 'نظرة سريعة على فكرة النظام وطريقة العمل اليومية.',
                'route_name' => 'home.index',
                'route_match' => 'home.*',
                'order' => 10,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'navigation',
                'title' => 'التنقل والإشعارات',
                'description' => 'أين تجد الأقسام، الإشعارات، والملف الشخصي.',
                'route_name' => 'home.index',
                'route_match' => 'home.*',
                'order' => 20,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'tasks',
                'title' => 'المهام',
                'description' => 'لوحة كانبان، التسليم، والمتابعة مع الفريق.',
                'route_name' => 'tasks.index',
                'route_match' => 'tasks.*',
                'order' => 30,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'chat',
                'title' => 'الدردشة',
                'description' => 'غرف الفريق، الخاصة، المنشن، والمكالمات.',
                'route_name' => 'chat.index',
                'route_match' => 'chat.*',
                'order' => 40,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'outside',
                'title' => 'الخارج',
                'description' => 'محادثات واتساب والعملاء من قناة خارجية.',
                'route_name' => 'outside.index',
                'route_match' => 'outside.*',
                'order' => 50,
                'roles' => null,
                'team_slugs' => ['writing', 'media-buyer', 'account', 'sales'],
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'goods',
                'title' => 'البضاعة',
                'description' => 'متابعة الطلبات والعملاء في قناة البضاعة.',
                'route_name' => 'goods.index',
                'route_match' => 'goods.*',
                'order' => 55,
                'roles' => null,
                'team_slugs' => ['sales', 'accounting', 'media-buyer'],
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'sales-analytics',
                'title' => 'تحليلات المبيعات',
                'description' => 'مؤشرات الأداء والإيرادات لفريق المبيعات.',
                'route_name' => 'sales.analytics',
                'route_match' => 'sales.*',
                'order' => 60,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => 'viewSalesAnalytics',
                'personas' => null,
            ],
            [
                'id' => 'meetings',
                'title' => 'الاجتماعات',
                'description' => 'جدولة الاجتماعات ومشاركة الفريق.',
                'route_name' => 'meetings.index',
                'route_match' => 'meetings.*',
                'order' => 65,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'clients',
                'title' => 'العملاء',
                'description' => 'مسار العميل، الاشتراكات، وربط المسؤولين.',
                'route_name' => 'clients.index',
                'route_match' => 'clients.*',
                'order' => 70,
                'roles' => null,
                'team_slugs' => ['sales', 'account', 'media-buyer'],
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'warehouse',
                'title' => 'المخزن',
                'description' => 'المنتجات، المخزون، والحركات.',
                'route_name' => 'warehouse.index',
                'route_match' => 'warehouse.*',
                'order' => 75,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => 'viewWarehouse',
                'personas' => null,
            ],
            [
                'id' => 'academy',
                'title' => 'الأكاديمية',
                'description' => 'مواد تدريبية ومسارات تعلم داخل الشركة.',
                'route_name' => 'academy.index',
                'route_match' => 'academy.*',
                'order' => 80,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'tickets',
                'title' => 'المشاكل والدعم',
                'description' => 'رفع مشكلة ومتابعة الحل مع الفريق.',
                'route_name' => 'tickets.index',
                'route_match' => 'tickets.*',
                'order' => 85,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => null,
                'personas' => null,
            ],
            [
                'id' => 'employees',
                'title' => 'إدارة الموظفين',
                'description' => 'الحسابات، الفرق، وصلاحيات الوصول.',
                'route_name' => 'employees.index',
                'route_match' => 'employees.*',
                'order' => 90,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => 'manageEmployees',
                'personas' => null,
            ],
            [
                'id' => 'settings',
                'title' => 'إعدادات النظام',
                'description' => 'التكاملات، القائمة، والميزات العامة.',
                'route_name' => 'settings.index',
                'route_match' => 'settings.*',
                'order' => 95,
                'roles' => ['admin'],
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => 'manageSystemSettings',
                'personas' => null,
            ],
            [
                'id' => 'admin-home',
                'title' => 'لوحة المدير',
                'description' => 'مؤشرات الشركة والمتابعة التشغيلية.',
                'route_name' => 'home.index',
                'route_match' => 'home.*',
                'order' => 15,
                'roles' => ['admin'],
                'team_slugs' => null,
                'team_lead_only' => false,
                'gate' => 'viewAdminHome',
                'personas' => null,
            ],
            [
                'id' => 'team-lead',
                'title' => 'دور قائد الفريق',
                'description' => 'متابعة الفريق، المهام، والتنسيق اليومي.',
                'route_name' => 'home.index',
                'route_match' => 'home.*',
                'order' => 25,
                'roles' => null,
                'team_slugs' => null,
                'team_lead_only' => true,
                'gate' => null,
                'personas' => null,
            ],
        ];
    }

    public static function find(string $tourId): ?array
    {
        foreach (self::definitions() as $def) {
            if ($def['id'] === $tourId) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function tourIds(): array
    {
        return array_map(fn (array $d) => $d['id'], self::definitions());
    }
}
