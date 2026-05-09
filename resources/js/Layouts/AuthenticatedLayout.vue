<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import TeamNotebookDock from '@/Components/TeamNotebookDock.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
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

const teamNotebook = computed(() => {
    const nb = page.props.team_notebook;
    if (!nb?.team_id) {
        return null;
    }
    const path = String(page.url?.split('?')[0] || '');
    const onTasks = path === '/tasks' || path.startsWith('/tasks/');
    const onChat = path === '/chat' || path.startsWith('/chat/');
    if (!onTasks && !onChat) {
        return null;
    }

    return nb;
});

const nav = [
    ...(page.props.auth?.can?.viewAdminHome
        ? [{ label: 'الرئيسية', routeName: 'home.index', match: 'home.*' }]
        : []),
    { label: 'المهام', routeName: 'tasks.index', match: 'tasks.*' },
    { label: 'الدردشة', routeName: 'chat.index', match: 'chat.*' },
    { label: 'الخارج', routeName: 'outside.index', match: 'outside.*' },
    { label: 'البضاعة', routeName: 'goods.index', match: 'goods.*' },
    { label: 'الاجتماعات', routeName: 'meetings.index', match: 'meetings.*' },
    { label: 'العملاء', routeName: 'clients.index', match: 'clients.*' },
    ...(page.props.auth?.can?.viewWarehouse
        ? [{ label: 'المخزن', routeName: 'warehouse.index', match: 'warehouse.*' }]
        : []),
    { label: 'الأكاديمية', routeName: 'academy.index', match: 'academy.index' },
    ...(page.props.auth?.can?.manageEmployees
        ? [{ label: 'الموظفين', routeName: 'employees.index', match: 'employees.*' }]
        : []),
];

const mobileBottomNav = computed(() =>
    nav.filter((item) => !['academy.index', 'employees.index'].includes(item.routeName)),
);

const mobileDropdownNav = computed(() =>
    nav.filter((item) => ['academy.index', 'employees.index'].includes(item.routeName)),
);

function active(match) {
    return route().current(match);
}

function navIcon(item) {
    if (item.routeName === 'home.index') return 'home';
    if (item.routeName === 'tasks.index') return 'tasks';
    if (item.routeName === 'chat.index') return 'chat';
    if (item.routeName === 'outside.index') return 'outside';
    if (item.routeName === 'goods.index') return 'goods';
    if (item.routeName === 'meetings.index') return 'calendar';
    if (item.routeName === 'clients.index') return 'users';
    if (item.routeName === 'warehouse.index') return 'warehouse';
    if (item.routeName === 'academy.index') return 'academy';
    if (item.routeName === 'employees.index') return 'employees';
    return 'dot';
}

onMounted(() => {
    removeFinishListener = router.on('finish', () => {
        unreadCount.value = Number(page.props.notifications?.unread_count || unreadCount.value || 0);
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
});

onBeforeUnmount(() => {
    removeFinishListener?.();
    if (notificationsTimer) {
        window.clearInterval(notificationsTimer);
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('focus', onWindowFocus);
    }
});

function onWindowFocus() {
    fetchNotifications({ silent: true });
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
    <div class="relative min-h-screen overflow-hidden bg-white text-black">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-0 h-80 w-80 rounded-full bg-brand-500/20 blur-3xl" />
            <div class="absolute right-0 top-32 h-72 w-72 rounded-full bg-indigo-500/15 blur-3xl" />
            <div class="absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl" />
        </div>
        <div class="relative flex min-h-screen">
            <aside
                :class="[
                    'hidden shrink-0 flex-col border-s border-slate-200 bg-white/80 text-black backdrop-blur-xl transition-all duration-300 ease-out md:flex',
                    sidebarCollapsed ? 'w-[88px]' : 'w-60',
                ]"
            >
                <div class="flex h-28 items-center justify-center border-b border-slate-200 px-4">
                    <Link :href="route('dashboard')" class="flex w-full items-center justify-center">
                        <img
                            src="/images/logo-sidebar.png"
                            alt="خارج المخزون"
                            class="w-full object-contain transition-all duration-300"
                            :class="sidebarCollapsed ? 'h-12' : 'h-20'"
                        />
                    </Link>
                </div>
                <div class="px-3 pt-3">
                    <button
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center rounded-xl border border-slate-300 bg-white text-xs font-medium text-slate-700 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-slate-50"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        {{ sidebarCollapsed ? 'توسيع' : 'طي القائمة' }}
                    </button>
                </div>
                <nav class="flex flex-1 flex-col gap-1.5 p-3">
                    <Link
                        v-for="item in nav"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        prefetch
                        :class="[
                            'group rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 ease-out hover:scale-[1.02]',
                            active(item.match)
                                ? 'bg-brand-100 text-black ring-1 ring-brand-200 shadow-lg shadow-slate-200'
                                : 'text-slate-700 hover:bg-slate-100 hover:text-black',
                        ]"
                        :title="item.label"
                    >
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        <span v-else class="mx-auto block h-2 w-2 rounded-full bg-current/80" />
                    </Link>
                </nav>
                <div class="border-t border-slate-200 p-3 text-xs text-slate-600">
                    <div v-if="!sidebarCollapsed" class="flex items-center gap-2">
                        <img :src="avatarUrl" alt="avatar" class="h-7 w-7 rounded-full border border-slate-200 object-cover" />
                        <span>{{ page.props.auth.user?.name }}</span>
                    </div>
                    <img v-else :src="avatarUrl" alt="avatar" class="mx-auto h-7 w-7 rounded-full border border-slate-200 object-cover" />
                </div>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-slate-200 bg-white/85 px-4 backdrop-blur-xl md:px-6"
                >
                    <div class="flex items-center gap-3 md:hidden">
                        <Link :href="route('dashboard')">
                            <img src="/images/mobile-logo.png" alt="خارج المخزون" class="h-10 w-10 rounded-lg object-contain" />
                        </Link>
                        <div class="text-sm font-semibold text-black">
                            <slot name="title" />
                        </div>
                    </div>
                    <div class="hidden text-lg font-semibold tracking-tight text-black md:block">
                        <slot name="title" />
                    </div>
                    <div class="ms-auto flex items-center gap-2">
                        <div class="relative">
                            <button
                                type="button"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-800 shadow-sm transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-slate-50"
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
                                class="notifications-panel-solid fixed left-2 right-2 top-16 z-[90] rounded-2xl border border-slate-200 p-2 text-black shadow-2xl ring-1 ring-slate-200 md:absolute md:left-auto md:right-0 md:top-12 md:w-[22rem] md:max-w-[92vw]"
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
                                <div v-else class="max-h-80 space-y-1 overflow-y-auto px-1 pb-1">
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
                                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-black transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-slate-50"
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

                <main class="relative z-10 flex min-h-0 flex-1 flex-col overflow-auto p-3 pb-20 md:p-6 md:pb-6">
                    <slot />
                </main>

                <TeamNotebookDock v-if="teamNotebook" :key="teamNotebook.team_id" :notebook="teamNotebook" />
                <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 py-1.5 backdrop-blur-xl md:hidden">
                    <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${Math.max(mobileBottomNav.length, 1)}, minmax(0, 1fr))` }">
                        <Link
                            v-for="item in mobileBottomNav"
                            :key="`mobile-bottom-${item.routeName}`"
                            :href="route(item.routeName)"
                            prefetch
                            :class="[
                                'inline-flex h-12 items-center justify-center rounded-xl transition-all duration-200 ease-out',
                                active(item.match)
                                    ? 'bg-brand-100 text-black ring-1 ring-brand-200'
                                    : 'text-slate-700 hover:bg-slate-100',
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
                            <span v-else class="h-2 w-2 rounded-full bg-current" />
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
