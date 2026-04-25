<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const sidebarCollapsed = ref(false);
const isPageLoading = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

const nav = [
    ...(page.props.auth?.can?.viewAdminHome
        ? [{ label: 'الرئيسية', routeName: 'home.index', match: 'home.*' }]
        : []),
    { label: 'المهام', routeName: 'tasks.index', match: 'tasks.*' },
    { label: 'الدردشة', routeName: 'chat.index', match: 'chat.*' },
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
    if (item.routeName === 'meetings.index') return 'calendar';
    if (item.routeName === 'clients.index') return 'users';
    if (item.routeName === 'warehouse.index') return 'warehouse';
    if (item.routeName === 'academy.index') return 'academy';
    if (item.routeName === 'employees.index') return 'employees';
    return 'dot';
}

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isPageLoading.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isPageLoading.value = false;
    });
});

onBeforeUnmount(() => {
    removeStartListener?.();
    removeFinishListener?.();
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-950 text-slate-100">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-0 h-80 w-80 rounded-full bg-brand-500/20 blur-3xl" />
            <div class="absolute right-0 top-32 h-72 w-72 rounded-full bg-indigo-500/15 blur-3xl" />
            <div class="absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl" />
        </div>
        <div class="relative flex min-h-screen">
            <div v-if="isPageLoading" class="loading-bar" />
            <aside
                :class="[
                    'hidden shrink-0 flex-col border-s border-white/10 bg-white/5 text-white/90 backdrop-blur-xl transition-all duration-300 ease-out md:flex',
                    sidebarCollapsed ? 'w-[88px]' : 'w-60',
                ]"
            >
                <div class="flex h-28 items-center justify-center border-b border-white/10 px-4">
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
                        class="inline-flex h-9 w-full items-center justify-center rounded-xl border border-white/15 bg-white/5 text-xs font-medium text-white/80 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-white/10"
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
                                ? 'bg-white/15 text-white ring-1 ring-white/20 shadow-lg shadow-black/20'
                                : 'text-white/70 hover:bg-white/10 hover:text-white',
                        ]"
                        :title="item.label"
                    >
                        <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        <span v-else class="mx-auto block h-2 w-2 rounded-full bg-current/80" />
                    </Link>
                </nav>
                <div class="border-t border-white/10 p-3 text-xs text-white/60">
                    <span v-if="!sidebarCollapsed">{{ page.props.auth.user?.name }}</span>
                    <span v-else class="mx-auto block h-2.5 w-2.5 rounded-full bg-emerald-400" />
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-white/10 bg-slate-900/55 px-4 backdrop-blur-xl md:px-6"
                >
                    <div class="flex items-center gap-3 md:hidden">
                        <Link :href="route('dashboard')">
                            <img src="/images/mobile-logo.png" alt="خارج المخزون" class="h-10 w-10 rounded-lg object-contain" />
                        </Link>
                        <div class="text-sm font-semibold text-white/90">
                            <slot name="title" />
                        </div>
                    </div>
                    <div class="hidden text-lg font-semibold tracking-tight text-white/90 md:block">
                        <slot name="title" />
                    </div>
                    <div class="ms-auto">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-sm font-medium text-white/85 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-white/15 hover:text-white"
                                >
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

                <main
                    :class="[
                        'relative z-10 flex-1 overflow-x-auto p-3 pb-20 transition-opacity duration-200 md:p-6 md:pb-6',
                        isPageLoading ? 'opacity-85' : 'opacity-100',
                    ]"
                >
                    <slot />
                </main>
                <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/15 bg-slate-950/90 px-2 py-1.5 backdrop-blur-xl md:hidden">
                    <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${Math.max(mobileBottomNav.length, 1)}, minmax(0, 1fr))` }">
                        <Link
                            v-for="item in mobileBottomNav"
                            :key="`mobile-bottom-${item.routeName}`"
                            :href="route(item.routeName)"
                            prefetch
                            :class="[
                                'inline-flex h-12 items-center justify-center rounded-xl transition-all duration-200 ease-out',
                                active(item.match)
                                    ? 'bg-white/15 text-white ring-1 ring-white/20'
                                    : 'text-white/70 hover:bg-white/10',
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
.loading-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 80;
    height: 3px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.2);
}

.loading-bar::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, rgba(59, 130, 246, 0), rgba(56, 189, 248, 0.9), rgba(59, 130, 246, 0));
    animation: slide 1s linear infinite;
}

@keyframes slide {
    to {
        transform: translateX(100%);
    }
}

@supports (padding: max(0px)) {
    nav[class*='fixed'][class*='bottom-0'] {
        padding-bottom: max(0.375rem, env(safe-area-inset-bottom));
    }
}
</style>
