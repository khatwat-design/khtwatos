/**
 * خطوات الجولات — عناصر ظاهرة فقط عبر productTourDom.
 */
import {
    isTourElementVisible,
    pickTourNavLink,
    pickTourPageAnchor,
    tourStep,
} from '@/utils/productTourDom.js';

/**
 * @param {string} tourId
 * @param {{ personaLabel?: string }} ctx
 */
export function buildTourSteps(tourId, ctx = {}) {
    const persona = ctx.personaLabel || 'موظف';
    const builders = {
        welcome: () => welcomeSteps(persona),
        'admin-home': () => adminHomeSteps(),
        'team-lead': () => teamLeadSteps(persona),
        navigation: () => navigationSteps(),
        tasks: () => tasksSteps(),
        chat: () => chatSteps(),
        outside: () => pageIntroSteps('الخارج', 'محادثات واتساب والعملاء من قناة خارجية — ردّ من هنا دون التبديل بين التطبيقات.'),
        goods: () => pageIntroSteps('البضاعة', 'متابعة طلبات البضاعة وعملائها.'),
        'sales-analytics': () =>
            pageIntroSteps('تحليلات المبيعات', 'مؤشرات الأداء والإيرادات لفريق المبيعات.'),
        meetings: () => pageIntroSteps('الاجتماعات', 'جدولة الاجتماعات ودعوة المشاركين.'),
        clients: () => pageIntroSteps('العملاء', 'مسار العميل، الاشتراكات، وربط المسؤولين.'),
        warehouse: () => pageIntroSteps('المخزن', 'المنتجات، الكميات، والحركات.'),
        academy: () => pageIntroSteps('الأكاديمية', 'مواد تدريب داخلية ومسارات تعلم.'),
        tickets: () => pageIntroSteps('المشاكل والدعم', 'افتح تذكرة عند أي عطل — يتابعها الفريق حتى الإغلاق.'),
        employees: () => pageIntroSteps('الموظفين', 'إدارة الحسابات والفرق وصلاحيات الوصول.'),
        settings: () => pageIntroSteps('إعدادات النظام', 'التكاملات، القائمة، والميزات العامة.'),
    };

    const fn = builders[tourId];
    return fn ? fn() : [];
}

function navMenuPicker() {
    return () => {
        const sidebar = document.querySelector('[data-tour="app-sidebar"]');
        if (isTourElementVisible(sidebar)) {
            return sidebar;
        }
        return document.querySelector('[data-tour="mobile-bottom-nav"]');
    };
}

function welcomeSteps(personaLabel) {
    return [
        tourStep('[data-tour="home-dashboard"]', `أهلاً ${personaLabel}`, 'هذه لوحة عملك اليومية: ملخص المهام والتنبيهات المهمة.', 'bottom'),
        tourStep(navMenuPicker, 'التنقل', 'من هنا تنتقل بين الأقسام — على الجوال من الشريط السفلي.', 'left'),
        tourStep('[data-tour="notifications-btn"]', 'الإشعارات', 'تنبيهات المهام والعملاء — اضغط أي إشعار للانتقال مباشرة.', 'bottom'),
        tourStep('[data-tour="tour-help-btn"]', 'مركز التدريب', 'زر «?» يفتح كل الجولات؛ يمكنك إعادة أي شرح متى شئت.', 'bottom'),
    ];
}

function adminHomeSteps() {
    return [
        tourStep('[data-tour="home-dashboard"]', 'لوحة المدير', 'مؤشرات الشركة ونقاط تحتاج انتباهك اليوم.', 'bottom'),
        tourStep(() => pickTourNavLink('tickets.index'), 'المشاكل والدعم', 'تابع التذاكر المفتوحة — الرقم الأحمر يوضح ما يحتاج تدخلًا.', 'left'),
        tourStep(() => pickTourNavLink('employees.index'), 'الموظفين', 'إدارة الحسابات والفرق من مكان واحد.', 'left'),
        tourStep('[data-tour="tour-help-btn"]', 'التدريب', 'من هنا تعيد جولات التعريف لأي قسم.', 'bottom'),
    ];
}

function teamLeadSteps(personaLabel) {
    return [
        tourStep('[data-tour="home-dashboard"]', `دور ${personaLabel}`, 'راقب مهام فريقك والاجتماعات من الرئيسية.', 'bottom'),
        tourStep(() => pickTourNavLink('tasks.index'), 'المهام', 'لوحة كانبان لمتابعة تقدم الفريق.', 'left'),
        tourStep(() => pickTourNavLink('chat.index'), 'الدردشة', 'نسّق مع الفريق بسرعة — استخدم @ للمنشن عند الحاجة.', 'left'),
    ];
}

function navigationSteps() {
    return [
        tourStep(navMenuPicker, 'القائمة', 'كل أقسام النظام من القائمة الجانبية أو الشريط السفلي على الجوال.', 'left'),
        tourStep('[data-tour="notifications-btn"]', 'الإشعارات', 'تنبيهات المهام والعملاء والنظام.', 'bottom'),
        tourStep('[data-tour="user-menu"]', 'حسابك', 'الملف الشخصي وإعداداتك وتسجيل الخروج.', 'bottom'),
    ];
}

function tasksSteps() {
    return [
        tourStep('[data-tour="tasks-toolbar"]', 'شريط المهام', 'اختر الفريق وصفِّ حسب العميل.', 'bottom'),
        tourStep('[data-tour="tasks-board"]', 'لوحة كانبان', 'اسحب المهام بين الأعمدة لمتابعة التقدم.', 'top'),
    ];
}

function chatSteps() {
    const steps = [
        tourStep('[data-tour="chat-inbox"]', 'قائمة المحادثات', 'غرف الفريق، الخاصة، والرسائل المباشرة.', 'left'),
        tourStep('[data-tour="chat-composer"]', 'الإرسال', 'رسالة، ملف، صوت، أو ملصق — @ لمنشن زميل.', 'top'),
    ];
    return steps;
}

function pageIntroSteps(title, description) {
    return [tourStep(pickTourPageAnchor(), title, description, 'bottom')];
}
