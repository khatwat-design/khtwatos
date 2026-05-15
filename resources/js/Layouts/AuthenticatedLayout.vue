<script setup>
import { Capacitor } from '@capacitor/core';
import { initNativePushForAuthenticatedSession } from '@/capacitor/native-push';
import EmployeeCallOverlay from '@/Components/Chat/EmployeeCallOverlay.vue';
import DailyAttendanceModal from '@/Components/DailyAttendanceModal.vue';
import { employeeCallRealtimeEnabled } from '@/echo.js';
import { useEmployeeCall } from '@/composables/useEmployeeCall.js';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import TeamNotebookDock from '@/Components/TeamNotebookDock.vue';
import { usePresenceHeartbeat } from '@/composables/usePresenceHeartbeat';
import { CHAT_MOBILE_MEDIA } from '@/utils/chatMobileViewport.js';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';

const page = usePage();
/** صفحات مثل الدردشة تخفي شريط العنوان على الجوال لعدم حجب أزرار المحادثة */
const concealMobilePageHeader = ref(false);
provide('concealMobilePageHeader', concealMobilePageHeader);

const layoutMobileViewport = ref(
    typeof window !== 'undefined' && window.matchMedia(CHAT_MOBILE_MEDIA).matches,
);

/** على الجوال: إخفاء كامل (DOM) وليس فقط تحريك الهيدر */
const showAppHeader = computed(
    () => !layoutMobileViewport.value || !concealMobilePageHeader.value,
);

const showMobileBottomNav = computed(
    () => layoutMobileViewport.value && !concealMobilePageHeader.value,
);

let layoutMobileMq = null;
let syncLayoutMobileViewport = null;

const sidebarCollapsed = ref(false);
let removeFinishListener = null;
let notificationsTimer = null;
const isNotificationsOpen = ref(false);
const notificationsLoading = ref(false);
const notifications = ref([]);
const unreadCount = ref(Number(page.props.notifications?.unread_count || 0));
const browserNotificationsEnabled = ref(false);
let lastUnreadCount = Number(page.props.notifications?.unread_count || 0);
const vapidPublicKey = computed(() => String(page.props.notifications?.webpush_public_key || ''));
const avatarUrl = computed(() => page.props.auth?.user?.avatar_url || '/images/mobile-logo.png');

const { initEmployeeCalls, teardownEmployeeCalls, ensureEchoSubscription, syncPendingIncomingFromServer } =
    useEmployeeCall();

const nativePushHintDismissed = ref(false);

const showNativePushSetupBanner = computed(() => {
    if (nativePushHintDismissed.value) {
        return false;
    }
    if (!Capacitor.isNativePlatform()) {
        return false;
    }
    if (page.props.notifications?.firebase_mobile_push_enabled) {
        return false;
    }
    return true;
});

function dismissNativePushHint() {
    nativePushHintDismissed.value = true;
    try {
        sessionStorage.setItem('kharij-dismiss-push-hint', '1');
    } catch {
        /* تجاهل */
    }
}

const teamNotebook = computed(() => {
    const nb = page.props.team_notebook;
    return nb?.team_id ? nb : null;
});

/** إخفاء الدفتر في صفحات تحتاج مساحة قراءة كاملة أو لا تناسب الطفو؛ أو حسب تفضيل المستخدم في الملف الشخصي */
const showTeamNotebookDock = computed(() => {
    if (!teamNotebook.value) {
        return false;
    }
    const user = page.props.auth?.user;
    if (user && user.show_team_notebook === false) {
        return false;
    }
    if (route().current('chat.*') || route().current('outside.*')) {
        return false;
    }
    return true;
});

const navBase = computed(() => [
    { label: 'الرئيسية', routeName: 'home.index', match: 'home.*' },
    { label: 'المهام', routeName: 'tasks.index', match: 'tasks.*' },
    { label: 'الدردشة', routeName: 'chat.index', match: 'chat.*' },
    { label: 'الخارج', routeName: 'outside.index', match: 'outside.*' },
    { label: 'البضاعة', routeName: 'goods.index', match: 'goods.*' },
    ...(page.props.auth?.can?.viewSalesAnalytics
        ? [{ label: 'تحليلات المبيعات', routeName: 'sales.analytics', match: 'sales.*' }]
        : []),
    { label: 'الاجتماعات', routeName: 'meetings.index', match: 'meetings.*' },
    { label: 'العملاء', routeName: 'clients.index', match: 'clients.*' },
    ...(page.props.auth?.can?.viewWarehouse
        ? [{ label: 'المخزن', routeName: 'warehouse.index', match: 'warehouse.*' }]
        : []),
    { label: 'الأكاديمية', routeName: 'academy.index', match: 'academy.index' },
    { label: 'المشاكل والدعم', routeName: 'tickets.index', match: 'tickets.*' },
    ...(page.props.auth?.can?.manageEmployees
        ? [{ label: 'الموظفين', routeName: 'employees.index', match: 'employees.*' }]
        : []),
    ...(page.props.auth?.can?.manageSystemSettings
        ? [{ label: 'الإعدادات', routeName: 'settings.index', match: 'settings.*' }]
        : []),
]);

/** قيود ظهور القائمة حسب الفريق (من الخادم): null = لا قيود إضافية */
const navTeamAllowlist = computed(() => {
    const raw = page.props.nav_team_routes;
    if (raw === null || raw === undefined) {
        return null;
    }
    return Array.isArray(raw) ? new Set(raw) : null;
});

const nav = computed(() => {
    const list = navBase.value;
    const allow = navTeamAllowlist.value;
    if (!allow) {
        return list;
    }
    return list.filter((item) => allow.has(item.routeName));
});

const mobileBottomNav = computed(() =>
    nav.value.filter(
        (item) => !['academy.index', 'employees.index', 'settings.index', 'tickets.index'].includes(item.routeName),
    ),
);

const mobileDropdownNav = computed(() =>
    nav.value.filter((item) =>
        ['academy.index', 'employees.index', 'settings.index', 'tickets.index'].includes(item.routeName),
    ),
);

// نافذة تسجيل الحضور اليومية + قياس النشاط
const isAttendanceModalOpen = ref(false);
const attendanceDismissed = ref(false);

const needsCheckIn = computed(() => Boolean(page.props.attendance?.needs_check_in));
const openTicketsCount = computed(() => Number(page.props.tickets_meta?.open_count || 0));

watch(
    needsCheckIn,
    (val) => {
        if (val && !attendanceDismissed.value) {
            isAttendanceModalOpen.value = true;
        }
    },
    { immediate: true },
);

usePresenceHeartbeat();

function active(match) {
    return route().current(match);
}

function navIcon(item) {
    if (item.routeName === 'home.index') return 'home';
    if (item.routeName === 'tasks.index') return 'tasks';
    if (item.routeName === 'chat.index') return 'chat';
    if (item.routeName === 'outside.index') return 'outside';
    if (item.routeName === 'goods.index') return 'goods';
    if (item.routeName === 'sales.analytics') return 'analytics';
    if (item.routeName === 'meetings.index') return 'calendar';
    if (item.routeName === 'clients.index') return 'users';
    if (item.routeName === 'warehouse.index') return 'warehouse';
    if (item.routeName === 'academy.index') return 'academy';
    if (item.routeName === 'employees.index') return 'employees';
    if (item.routeName === 'tickets.index') return 'tickets';
    if (item.routeName === 'settings.index') return 'settings';
    return 'dot';
}

function dismissAttendanceModal() {
    attendanceDismissed.value = true;
    isAttendanceModalOpen.value = false;
}

onMounted(() => {
    try {
        nativePushHintDismissed.value = sessionStorage.getItem('kharij-dismiss-push-hint') === '1';
    } catch {
        nativePushHintDismissed.value = false;
    }
    removeFinishListener = router.on('finish', () => {
        unreadCount.value = Number(page.props.notifications?.unread_count || unreadCount.value || 0);
        const userId = page.props.auth?.user?.id;
        if (employeeCallRealtimeEnabled() && userId) {
            ensureEchoSubscription(userId);
            void syncPendingIncomingFromServer();
        }
    });
    notificationsTimer = window.setInterval(() => {
        if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
            fetchNotifications({ silent: true });
        }
    }, 5000);
    if (typeof window !== 'undefined') {
        window.addEventListener('focus', onWindowFocus);
    }
    browserNotificationsEnabled.value =
        typeof window !== 'undefined' &&
        'Notification' in window &&
        Notification.permission === 'granted';
    registerServiceWorker();
    void initNativePushForAuthenticatedSession({
        enabled: Boolean(page.props.notifications?.firebase_mobile_push_enabled),
        deviceStoreUrl: route('device-push-tokens.store'),
        onSilentRefresh: () => fetchNotifications({ silent: true }),
    });

    const userId = page.props.auth?.user?.id;
    if (employeeCallRealtimeEnabled() && userId) {
        initEmployeeCalls(userId);
    }

    layoutMobileMq = window.matchMedia(CHAT_MOBILE_MEDIA);
    syncLayoutMobileViewport = () => {
        layoutMobileViewport.value = layoutMobileMq.matches;
    };
    syncLayoutMobileViewport();
    layoutMobileMq.addEventListener('change', syncLayoutMobileViewport);
});

onBeforeUnmount(() => {
    teardownEmployeeCalls();
    removeFinishListener?.();
    if (notificationsTimer) {
        window.clearInterval(notificationsTimer);
    }
    if (layoutMobileMq && syncLayoutMobileViewport) {
        layoutMobileMq.removeEventListener('change', syncLayoutMobileViewport);
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('focus', onWindowFocus);
    }
});

function onWindowFocus() {
    fetchNotifications({ silent: true });
    const userId = page.props.auth?.user?.id;
    if (employeeCallRealtimeEnabled() && userId) {
        ensureEchoSubscription(userId);
        void syncPendingIncomingFromServer();
    }
}

async function registerServiceWorker() {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;
    try {
        await navigator.serviceWorker.register('/sw.js');
        if (browserNotificationsEnabled.value) {
            await ensurePushSubscription();
        }
    } catch {
        // silent fail for unsupported environments
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}

async function ensurePushSubscription() {
    if (
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator) ||
        !('PushManager' in window) ||
        !vapidPublicKey.value
    ) {
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey.value),
        });
    }

    await window.axios.post(
        route('push-subscriptions.store'),
        {
            endpoint: subscription.endpoint,
            keys: subscription.toJSON().keys,
            contentEncoding: subscription.options?.applicationServerKey ? 'aes128gcm' : 'aesgcm',
        },
        { headers: { Accept: 'application/json' } },
    );
}

function formatNotificationTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' });
}

async function fetchNotifications({ silent = false } = {}) {
    if (notificationsLoading.value && silent) return;
    if (!silent) {
        notificationsLoading.value = true;
    }
    try {
        const response = await window.axios.get(route('notifications.index'), {
            params: { scope: 'system' },
            headers: { Accept: 'application/json' },
        });
        notifications.value = response?.data?.notifications || [];
        unreadCount.value = Number(response?.data?.unread_count || 0);
        maybePushBrowserNotification();
    } finally {
        notificationsLoading.value = false;
    }
}

function maybePushBrowserNotification() {
    if (!browserNotificationsEnabled.value) return;
    if (unreadCount.value <= lastUnreadCount) {
        lastUnreadCount = unreadCount.value;
        return;
    }

    const latestUnread = notifications.value.find((note) => !note.read_at);
    if (latestUnread) {
        const title = latestUnread?.data?.title || latestUnread?.data?.task_title || 'إشعار جديد';
        const body = latestUnread?.data?.body || 'لديك تحديث جديد في النظام';
        new Notification(title, { body, icon: '/images/logo-mark.svg' });
    }
    lastUnreadCount = unreadCount.value;
}

async function toggleNotificationsPanel() {
    isNotificationsOpen.value = !isNotificationsOpen.value;
    if (isNotificationsOpen.value) {
        if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'default') {
            try {
                const perm = await Notification.requestPermission();
                browserNotificationsEnabled.value = perm === 'granted';
                if (browserNotificationsEnabled.value) {
                    await ensurePushSubscription();
                }
            } catch {
                browserNotificationsEnabled.value = false;
            }
        }
        await fetchNotifications();
    }
}

async function markNotificationRead(notificationId) {
    await window.axios.post(route('notifications.read', notificationId), {}, { headers: { Accept: 'application/json' } });
    await fetchNotifications({ silent: true });
}

async function markAllNotificationsRead() {
    await window.axios.post(route('notifications.read-all'), {}, { headers: { Accept: 'application/json' } });
    await fetchNotifications({ silent: true });
}

function notificationTitle(note) {
    return note?.data?.title || note?.data?.task_title || 'إشعار جديد';
}

function notificationBody(note) {
    if (note?.data?.body) return note.data.body;
    if (note?.data?.severity === 'overdue') return 'مهمة متأخرة عن الموعد';
    if (note?.data?.severity === 'due_soon') return 'موعد المهمة قريب (خلال 24 ساعة)';
    if (note?.data?.severity === 'due_today') return 'موعد المهمة اليوم';
    return 'تحديث جديد';
}

async function openNotification(note) {
    await markNotificationRead(note.id);
    if (note?.data?.link) {
        router.visit(note.data.link);
        isNotificationsOpen.value = false;
    }
}
</script>

<template>
    <div class="relative min-h-[var(--app-visual-vh,100dvh)] min-w-0 bg-[var(--bg-secondary)] text-[var(--text-primary)] md:min-h-screen">
        <div class="relative flex min-h-[var(--app-visual-vh,100dvh)] md:min-h-screen">
            <aside
                :class="[
                    'hidden shrink-0 flex-col border-s border-slate-200 bg-white transition-[width] duration-200 ease-out md:flex',
                    sidebarCollapsed ? 'w-[88px]' : 'w-60',
                ]"
            >
                <div class="flex h-24 items-center justify-center border-b border-slate-200 px-3">
                    <Link :href="route('dashboard')" class="flex w-full items-center justify-center">
                        <img
                            src="/images/logo-sidebar.png"
                            alt="خارج المخزون"
                            class="w-full object-contain transition-all duration-300"
                            :class="sidebarCollapsed ? 'h-12' : 'h-20'"
                        />
                    </Link>
                </div>
                <div class="px-2.5 pt-2">
                    <button
                        type="button"
                        class="inline-flex h-8 w-full items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-100"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        {{ sidebarCollapsed ? 'توسيع' : 'طي القائمة' }}
                    </button>
                </div>
                <nav class="flex flex-1 flex-col gap-0.5 p-2">
                    <Link
                        v-for="item in nav"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        prefetch
                        :class="[
                            'group flex items-center justify-between gap-2 rounded-md px-2.5 py-2 text-[13px] font-medium transition-colors',
                            active(item.match)
                                ? 'border-s-2 border-brand-600 bg-slate-100 font-semibold text-slate-900'
                                : 'border-s-2 border-transparent text-slate-700 hover:bg-slate-50 hover:text-slate-900',
                        ]"
                        :title="item.label"
                    >
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        <span v-else class="mx-auto block h-2 w-2 rounded-full bg-current/80" />
                        <span
                            v-if="!sidebarCollapsed && item.routeName === 'tickets.index' && openTicketsCount > 0"
                            class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-rose-600 px-1.5 text-[10px] font-bold text-white"
                            :title="`${openTicketsCount} تذكرة مفتوحة`"
                        >
                            {{ openTicketsCount > 99 ? '99+' : openTicketsCount }}
                        </span>
                    </Link>
                </nav>
                <div class="border-t border-slate-200 p-2.5 text-[11px] text-slate-600">
                    <div v-if="!sidebarCollapsed" class="flex items-center gap-2">
                        <img :src="avatarUrl" alt="avatar" class="h-7 w-7 rounded-full border border-slate-200 object-cover" />
                        <span>{{ page.props.auth.user?.name }}</span>
                    </div>
                    <img v-else :src="avatarUrl" alt="avatar" class="mx-auto h-7 w-7 rounded-full border border-slate-200 object-cover" />
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header
                    v-if="showAppHeader"
                    class="sticky top-0 z-20 flex min-h-12 items-center justify-between gap-3 border-b border-slate-200 bg-white px-3 shadow-[0_1px_0_rgba(15,23,42,0.04)] max-lg:min-h-[calc(3rem+env(safe-area-inset-top,0px))] max-lg:pt-[env(safe-area-inset-top,0px)] md:px-5"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-3 md:min-w-0 md:flex-initial md:gap-0">
                        <Link :href="route('dashboard')" class="shrink-0 md:hidden">
                            <img src="/images/mobile-logo.png" alt="خارج المخزون" class="h-10 w-10 rounded-lg object-contain" />
                        </Link>
                        <h1 class="ops-page-title min-w-0">
                            <slot name="title" />
                        </h1>
                    </div>
                    <div class="ms-auto flex items-center gap-2">
                        <div class="relative">
                            <button
                                type="button"
                                class="relative inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-800 transition-colors hover:bg-slate-50"
                                title="الإشعارات"
                                @click="toggleNotificationsPanel"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9" />
                                </svg>
                                <span
                                    v-if="unreadCount > 0"
                                    class="absolute -end-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                                >
                                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                                </span>
                            </button>
                            <div
                                v-if="isNotificationsOpen"
                                class="notifications-panel-solid fixed left-2 right-2 z-[90] flex max-h-[min(24rem,70dvh)] flex-col rounded-lg border border-slate-200 p-2 text-black shadow-lg max-md:top-[max(0.75rem,calc(env(safe-area-inset-top,0px)+3.25rem))] max-md:max-h-[min(72dvh,calc(var(--app-visual-vh,100dvh)-5.5rem))] md:absolute md:left-auto md:right-0 md:top-11 md:max-h-[min(24rem,70vh)] md:w-[22rem] md:max-w-[92vw]"
                            >
                                <div class="mb-2 flex items-center justify-between px-2">
                                    <div class="text-sm font-semibold text-slate-900">الإشعارات</div>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-brand-700 hover:underline"
                                        @click="markAllNotificationsRead"
                                    >
                                        تعليم الكل كمقروء
                                    </button>
                                </div>
                                <div v-if="notificationsLoading" class="p-3 text-xs text-slate-500">جاري تحميل الإشعارات...</div>
                                <div v-else-if="!notifications.length" class="p-3 text-xs text-slate-500">لا توجد إشعارات حالياً.</div>
                                <div v-else class="min-h-0 flex-1 space-y-1 overflow-y-auto px-1 pb-1 max-md:max-h-[min(50dvh,22rem)] md:max-h-80">
                                    <button
                                        v-for="note in notifications"
                                        :key="note.id"
                                        type="button"
                                        class="w-full rounded-xl border px-2 py-2 text-start transition-all duration-200 ease-out hover:bg-slate-50"
                                        :class="note.read_at ? 'border-slate-200 bg-white text-slate-700' : 'border-brand-200 bg-brand-50 text-slate-900'"
                                        @click="openNotification(note)"
                                    >
                                        <div class="text-xs font-semibold">
                                            {{ notificationTitle(note) }}
                                        </div>
                                        <div class="mt-1 text-[11px] opacity-80">
                                            {{ notificationBody(note) }}
                                            <span v-if="note.data?.client_name"> • {{ note.data.client_name }}</span>
                                        </div>
                                        <div class="mt-1 text-[10px] opacity-70">
                                            {{ formatNotificationTime(note.created_at) }}
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[13px] font-medium text-slate-900 transition-colors hover:bg-slate-50"
                                >
                                    <img :src="avatarUrl" alt="avatar" class="h-7 w-7 rounded-full border border-slate-200 object-cover" />
                                    {{ page.props.auth.user.name }}
                                    <svg
                                        class="ms-1 h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink
                                    v-for="item in mobileDropdownNav"
                                    :key="`mobile-menu-${item.routeName}`"
                                    :href="route(item.routeName)"
                                    class="md:hidden"
                                >
                                    {{ item.label }}
                                </DropdownLink>
                                <DropdownLink
                                    v-if="page.props.auth?.can?.manageSystemSettings"
                                    :href="route('settings.index')"
                                >
                                    إعدادات النظام
                                </DropdownLink>
                                <DropdownLink :href="route('profile.edit')">
                                    الملف الشخصي
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    تسجيل الخروج
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </header>

                <div
                    v-if="showNativePushSetupBanner && showAppHeader"
                    class="relative z-10 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-amber-200 bg-amber-50 px-3 py-2.5 text-center text-[11px] leading-snug text-amber-950 md:justify-between md:px-6 md:text-start md:text-xs"
                    role="status"
                >
                    <p class="max-w-3xl">
                        إشعارات شريط الهاتف (خارج التطبيق) غير مفعّلة بعد؛ الخادم يوقف تسجيل الجهاز حتى يكتمل إعداد Firebase وملف
                        google-services في بناء التطبيق. الإشعارات ما زالت تظهر من زر الجرس داخل التطبيق بعد تحديث الصفحة.
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-amber-900 underline decoration-amber-600 underline-offset-2 hover:bg-amber-100/80 md:text-xs"
                        @click="dismissNativePushHint"
                    >
                        إخفاء
                    </button>
                </div>

                <main
                    class="relative z-10 flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto px-ops-4 py-ops-4 md:px-ops-5 md:py-ops-5 md:pb-ops-5"
                    :class="
                        concealMobilePageHeader
                            ? 'max-lg:!overflow-hidden max-lg:!p-0 max-lg:!pb-0'
                            : showMobileBottomNav
                              ? 'pb-[calc(5rem+env(safe-area-inset-bottom,0px))]'
                              : ''
                    "
                >
                    <slot />
                </main>

                <EmployeeCallOverlay />

                <DailyAttendanceModal
                    :open="isAttendanceModalOpen && needsCheckIn"
                    @close="dismissAttendanceModal"
                    @checked-in="dismissAttendanceModal"
                />

                <TeamNotebookDock v-if="showTeamNotebookDock" :key="teamNotebook.team_id" :notebook="teamNotebook" />
                <nav
                    v-if="showMobileBottomNav"
                    class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-2 py-1 ps-[max(0.5rem,env(safe-area-inset-left,0px))] pe-[max(0.5rem,env(safe-area-inset-right,0px))] md:hidden"
                >
                    <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${Math.max(mobileBottomNav.length, 1)}, minmax(0, 1fr))` }">
                        <Link
                            v-for="item in mobileBottomNav"
                            :key="`mobile-bottom-${item.routeName}`"
                            :href="route(item.routeName)"
                            prefetch
                            :class="[
                                'relative inline-flex h-11 items-center justify-center rounded-md transition-colors',
                                active(item.match)
                                    ? 'bg-slate-100 text-slate-900 ring-1 ring-slate-200'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            ]"
                            :title="item.label"
                        >
                            <svg v-if="navIcon(item) === 'home'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-10.5z" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'tasks'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'chat'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V12A8.5 8.5 0 1 1 21 12z" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'outside'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M7 10h10M7 14h7" />
                                <path d="M4 6h16a1 1 0 0 1 1 1v10l-4-2H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'goods'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 7h18" />
                                <path d="M5 7l1.5 12h11L19 7" />
                                <path d="M9 11v5M15 11v5" />
                                <path d="M9 7V5a3 3 0 0 1 6 0v2" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'analytics'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 20h18" />
                                <path d="M6 17V9M11 17V5M16 17v-7M21 17v-3" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'calendar'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="16" rx="2" />
                                <path d="M16 3v4M8 3v4M3 10h18" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'users'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="8.5" cy="7" r="3.5" />
                                <path d="M20 8v6M23 11h-6" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'warehouse'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 10l9-6 9 6v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V10z" />
                                <path d="M9 21V12h6v9" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'academy'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                <path d="M7 10v4c0 1.7 2.2 3 5 3s5-1.3 5-3v-4" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'employees'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="9" cy="7" r="3" />
                                <circle cx="17" cy="8" r="2.5" />
                                <path d="M3 20a6 6 0 0 1 12 0M14 20a4.5 4.5 0 0 1 7 0" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'settings'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
                            </svg>
                            <span v-else class="h-2 w-2 rounded-full bg-current" />
                            <span
                                v-if="
                                    item.routeName === 'chat.index' &&
                                    Number(page.props.notifications?.chat_messages_unread_total || 0) > 0
                                "
                                class="absolute -top-0.5 end-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-emerald-600 px-0.5 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white"
                            >
                                {{
                                    Number(page.props.notifications?.chat_messages_unread_total) > 99
                                        ? '99+'
                                        : page.props.notifications.chat_messages_unread_total
                                }}
                            </span>
                        </Link>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</template>

<style scoped>
@supports (padding: max(0px)) {
    nav[class*='fixed'][class*='bottom-0'] {
        padding-bottom: max(0.375rem, env(safe-area-inset-bottom));
    }
}

.notifications-panel-solid {
    background: #ffffff !important;
    opacity: 1 !important;
    backdrop-filter: none !important;
}
</style>
