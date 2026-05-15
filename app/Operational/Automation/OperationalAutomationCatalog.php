<?php

namespace App\Operational\Automation;

/**
 * Single source of truth for documented automations (Phase 9).
 * UI and internal tools consume {@see OperationalAutomationDescriptor::toLegacyCatalogRow()}.
 */
final class OperationalAutomationCatalog
{
    /**
     * @return list<OperationalAutomationDescriptor>
     */
    public static function all(): array
    {
        return [
            new OperationalAutomationDescriptor(
                id: 'portal_daily_sales_reminders',
                nameAr: 'تذكير مبيعات يومية (بوابة العميل)',
                trigger: 'Console: SendClientPortalDailySalesRemindersCommand',
                codePaths: ['app/Console/Commands/SendClientPortalDailySalesRemindersCommand.php'],
                notes: 'يرسل تذكيرات واتساب عند توفر جهة خارج مرتبطة بالعميل.',
                domain: OperationalAutomationDomain::Crm,
            ),
            new OperationalAutomationDescriptor(
                id: 'goods_daily_sales_reminders',
                nameAr: 'تذكير مبيعات يومية (البضاعة)',
                trigger: 'Console: SendDailyGoodsSalesRemindersCommand',
                codePaths: ['app/Console/Commands/SendDailyGoodsSalesRemindersCommand.php'],
                notes: null,
                domain: OperationalAutomationDomain::Operations,
            ),
            new OperationalAutomationDescriptor(
                id: 'goods_weekly_survey',
                nameAr: 'استبيان أسبوعي (البضاعة)',
                trigger: 'Console: SendWeeklyGoodsSurveyCommand',
                codePaths: ['app/Console/Commands/SendWeeklyGoodsSurveyCommand.php'],
                notes: null,
                domain: OperationalAutomationDomain::Operations,
            ),
            new OperationalAutomationDescriptor(
                id: 'client_whatsapp_milestones',
                nameAr: 'مراسلات واتساب عند مراحل العميل',
                trigger: 'Service: ClientMilestoneWhatsAppService',
                codePaths: [
                    'app/Services/ClientMilestoneWhatsAppService.php',
                    'app/Models/ClientWhatsappMilestoneLog.php',
                ],
                notes: 'سجلّات في client_whatsapp_milestone_logs.',
                domain: OperationalAutomationDomain::Messaging,
            ),
            new OperationalAutomationDescriptor(
                id: 'client_stage_workflow',
                nameAr: 'أتمّة عند دخول مرحلة عميل',
                trigger: 'Controller/Bridge + ClientWorkflowAutomationService',
                codePaths: [
                    'app/Services/ClientWorkflowAutomationService.php',
                    'app/Http/Controllers/ClientController.php',
                    'app/Services/OutsideGoodsClientBridgeService.php',
                ],
                notes: 'لا يزال يُستدعى من مسارات HTTP/جسر البضاعة؛ المراقبة عبر OperationalAutomationTrace.',
                domain: OperationalAutomationDomain::Crm,
            ),
            new OperationalAutomationDescriptor(
                id: 'smart_notifications',
                nameAr: 'إشعارات تشغيلية (مهام، مراحل، …)',
                trigger: 'SmartNotificationService',
                codePaths: ['app/Services/SmartNotificationService.php'],
                notes: 'نقطة مركزية لاحقاً لعرض حالة التوجيه وفشل الإرسال.',
                domain: OperationalAutomationDomain::Notifications,
            ),
            new OperationalAutomationDescriptor(
                id: 'automated_report_delivery',
                nameAr: 'تسليم تقارير تلقائي',
                trigger: 'ClientAutomatedReportDeliveryService',
                codePaths: ['app/Services/ClientAutomatedReportDeliveryService.php'],
                notes: null,
                domain: OperationalAutomationDomain::Crm,
            ),
            new OperationalAutomationDescriptor(
                id: 'stale_attendance_sessions',
                nameAr: 'إغلاق جلسات حضور قديمة',
                trigger: 'Console: CloseStaleAttendanceSessionsCommand',
                codePaths: ['app/Console/Commands/CloseStaleAttendanceSessionsCommand.php'],
                notes: null,
                domain: OperationalAutomationDomain::Workforce,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function legacyCatalogRows(): array
    {
        return array_map(
            static fn (OperationalAutomationDescriptor $d) => $d->toLegacyCatalogRow(),
            self::all()
        );
    }
}
