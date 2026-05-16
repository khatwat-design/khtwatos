/**
 * خطوات الجولات التدريبية (عربي — RTL).
 *
 * @param {string} tourId
 * @param {{ personaLabel?: string, isAdminHome?: boolean }} ctx
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
        outside: () => outsideSteps(),
        goods: () => goodsSteps(),
        'sales-analytics': () => salesAnalyticsSteps(),
        meetings: () => meetingsSteps(),
        clients: () => clientsSteps(),
        warehouse: () => warehouseSteps(),
        academy: () => academySteps(),
        tickets: () => ticketsSteps(),
        employees: () => employeesSteps(),
        settings: () => settingsSteps(),
    };

    const fn = builders[tourId];
    return fn ? fn() : [];
}

function step(element, title, description, side = 'bottom') {
    return {
        element: element || undefined,
        popover: {
            title,
            description,
            side,
            align: 'center',
        },
    };
}

function welcomeSteps(personaLabel) {
    return [
        step(
            '[data-tour="home-dashboard"]',
            `أهلاً ${personaLabel}`,
            'هذا نظام خطوات الموحّد: مهام، عملاء، دردشة، وخارج — كل شيء في مكان واحد لزيادة إنتاجيتك.',
            'bottom',
        ),
        step(
            '[data-tour="nav-home.index"]',
            'الرئيسية',
            'تبدأ يومك من هنا: ملخص سريع لمهامك، الاجتماعات، والتنبيهات المهمة.',
            'left',
        ),
        step(
            undefined,
            'جولة تفاعلية',
            'سنريك كل قسم عند زيارته لأول مرة. يمكنك إعادة أي جولة من زر «التدريب» أعلى الشاشة.',
            'bottom',
        ),
    ];
}

function adminHomeSteps() {
    return [
        step(
            '[data-tour="home-dashboard"]',
            'لوحة المدير',
            'مؤشرات الشركة: العملاء، المهام، الفرق، ونقاط تحتاج انتباهك اليوم.',
            'bottom',
        ),
        step(
            '[data-tour="nav-tickets.index"]',
            'المشاكل والدعم',
            'تابع التذاكر المفتوة من الفرق — الرقم الأحمر يوضح ما يحتاج تدخلًا.',
            'left',
        ),
        step(
            '[data-tour="nav-employees.index"]',
            'الموظفين',
            'إدارة الحسابات، الفرق، وصلاحيات الوصول من مكان واحد.',
            'left',
        ),
    ];
}

function teamLeadSteps(personaLabel) {
    return [
        step(
            '[data-tour="home-dashboard"]',
            `دور ${personaLabel}`,
            'كقائد فريق راقب مهام فريقك، الاجتماعات، والعملاء المرتبطين بك من الرئيسية.',
            'bottom',
        ),
        step(
            '[data-tour="nav-tasks.index"]',
            'متابعة المهام',
            'راجع لوحة المهام بانتظام واسحب البطاقات بين الأعمدة لمتابعة التقدم.',
            'left',
        ),
        step(
            '[data-tour="nav-chat.index"]',
            'تنسيق الفريق',
            'الدردشة للتنسيق السريع — استخدم @لمنشن زميل عند الحاجة لرد سريع.',
            'left',
        ),
    ];
}

function navMenuElement() {
    return (
        document.querySelector('[data-tour="app-sidebar"]') ||
        document.querySelector('[data-tour="mobile-bottom-nav"]')
    );
}

function navigationSteps() {
    return [
        {
            element: navMenuElement,
            popover: {
                title: 'التنقل في النظام',
                description:
                    'كل أقسام النظام هنا: على الحاسوب من القائمة الجانبية، وعلى الجوال من الشريط السفلي.',
                side: 'left',
                align: 'center',
            },
        },
        step(
            '[data-tour="notifications-btn"]',
            'الإشعارات',
            'تنبيهات المهام، العملاء، والنظام. اضغط على أي إشعار للانتقال مباشرة.',
            'bottom',
        ),
        step(
            '[data-tour="user-menu"]',
            'حسابك',
            'الملف الشخصي، الإعدادات (إن وُجدت)، وتسجيل الخروج.',
            'bottom',
        ),
        step(
            '[data-tour="tour-help-btn"]',
            'مركز التدريب',
            'من هنا تعيد أي جولة أو تتابع ما تبقّى من التدريب.',
            'bottom',
        ),
    ];
}

function tasksSteps() {
    return [
        step(
            '[data-tour="tasks-toolbar"]',
            'شريط المهام',
            'اختر الفريق، صفِّ حسب العميل، وابحث عن مهمة محددة.',
            'bottom',
        ),
        step(
            '[data-tour="tasks-board"]',
            'لوحة كانبان',
            'اسحب المهام بين الأعمدة (للعمل → قيد التنفيذ → مراجعة → تم). اضغط البطاقة للتفاصيل.',
            'top',
        ),
        step(
            undefined,
            'نصيحة إنتاجية',
            'حدّث حالة المهمة فور الانتهاء — الفريق يرى التقدم مباشرة دون رسائل إضافية.',
            'bottom',
        ),
    ];
}

function chatSteps() {
    return [
        step(
            '[data-tour="chat-inbox"]',
            'قائمة المحادثات',
            'غرف الفريق، الغرف الخاصة، والرسائل المباشرة. الرقم الأزرق = رسائل غير مقروءة، و@ = منشن.',
            'left',
        ),
        step(
            '[data-tour="chat-composer"]',
            'الإرسال',
            'اكتب رسالة، أرفق ملفًا أو صوتًا، أو اختر ملصقًا. اكتب @ لمنشن زميل.',
            'top',
        ),
        step(
            undefined,
            'مكالمات الموظفين',
            'من داخل الدردشة المباشرة يمكن بدء مكالمة صوتية/مرئية مع الزميل عند تفعيل الميزة.',
            'bottom',
        ),
    ];
}

function outsideSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'قناة الخارج',
            'محادثات واتساب والعملاء الواردة من خارج النظام — ردّ من هنا دون التبديل بين التطبيقات.',
            'bottom',
        ),
        step(
            undefined,
            'التعيين والمتابعة',
            'عيّن المحادثة لنفسك أو لزميل، وعلّمها كمقروءة بعد الرد ليبقى العداد دقيقًا.',
            'bottom',
        ),
    ];
}

function goodsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'البضاعة',
            'متابعة طلبات البضاعة وعملائها — مفيدة لفريق المبيعات والمحاسبة.',
            'bottom',
        ),
    ];
}

function salesAnalyticsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'تحليلات المبيعات',
            'مؤشرات الأداء والإيرادات — استخدمها في الاجتماعات الأسبوعية واتخاذ القرار.',
            'bottom',
        ),
    ];
}

function meetingsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'الاجتماعات',
            'اعرض الاجتماعات القادمة، أنشئ اجتماعًا جديدًا، وادعُ المشاركين من الفريق.',
            'bottom',
        ),
    ];
}

function clientsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'إدارة العملاء',
            'مسار العميل من lead إلى مغلق، ربط مسؤول الحساب ومدير الحملات، والاشتراكات.',
            'bottom',
        ),
        step(
            undefined,
            'بطاقة العميل',
            'افتح أي عميل لرؤية المهام، الاجتماعات، والربط مع بوابة العميل إن وُجد.',
            'bottom',
        ),
    ];
}

function warehouseSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'المخزن',
            'المنتجات، الكميات، والحركات — راجع المخزون قبل الموافقة على الطلبات.',
            'bottom',
        ),
    ];
}

function academySteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'الأكاديمية',
            'مواد تدريب داخلية — ابدأ بمسار مبيعات أو المحتوى المخصص لفريقك.',
            'bottom',
        ),
    ];
}

function ticketsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'المشاكل والدعم',
            'واجهت مشكلة؟ افتح تذكرة وصف المشكلة — الفريق المختص يتابعها حتى الإغلاق.',
            'bottom',
        ),
    ];
}

function employeesSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'الموظفين',
            'إضافة حسابات، ربط الفرق، وتحديد من يقود أي قسم. غيّر الصلاحيات بحذر.',
            'bottom',
        ),
    ];
}

function settingsSteps() {
    return [
        step(
            '[data-tour="page-main"]',
            'إعدادات النظام',
            'التكاملات (واتساب، Firebase، Meta)، ظهور القائمة لكل فريق، والميزات العامة.',
            'bottom',
        ),
    ];
}
