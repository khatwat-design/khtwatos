<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    client: Object,
});

const nav = [
    { label: 'الرئيسية', routeName: 'portal.dashboard', match: 'portal.dashboard' },
    { label: 'المنتجات', routeName: 'portal.products.index', match: 'portal.products.*' },
    { label: 'الملف الشخصي', routeName: 'portal.profile', match: 'portal.profile*' },
];

function active(match) {
    return route().current(match);
}

function navIcon(item) {
    if (item.routeName === 'portal.dashboard') return 'home';
    if (item.routeName === 'portal.products.index') return 'products';
    if (item.routeName === 'portal.profile') return 'profile';
    return 'dot';
}
</script>

<template>
    <Head :title="title" />

    <div class="min-h-dvh bg-gradient-to-br from-slate-100 via-white to-rose-50/35 text-slate-900 antialiased">
        <div class="mx-auto flex min-h-dvh w-full max-w-[1600px]">
            <aside
                class="portal-sidebar hidden w-[17rem] shrink-0 flex-col border-s border-white/10 bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 text-white shadow-[4px_0_24px_-8px_rgba(15,23,42,0.45)] md:flex lg:w-72 lg:shadow-[6px_0_32px_-10px_rgba(15,23,42,0.5)]"
            >
                <div
                    class="flex min-h-[9.5rem] flex-col items-center justify-center gap-2 border-b border-white/10 bg-gradient-to-b from-white/[0.07] to-transparent px-4 py-6 lg:min-h-[10.5rem] lg:py-7"
                >
                    <Link
                        :href="route('portal.dashboard')"
                        class="flex w-full max-w-[11.5rem] items-center justify-center rounded-2xl p-2 ring-1 ring-white/5 transition hover:bg-white/5 hover:ring-white/10"
                    >
                        <img src="/images/logo-sidebar.png" alt="خارج المخزون" class="h-24 w-full object-contain lg:h-28" />
                    </Link>
                </div>
                <div class="flex min-h-0 flex-1 flex-col px-3 pt-5 lg:px-4 lg:pt-6">
                    <p class="mb-3 px-1 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 lg:text-[11px]">التنقل</p>
                    <nav class="flex flex-col gap-1.5 lg:gap-2">
                        <Link
                            v-for="item in nav"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            :class="[
                                'group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold transition-all duration-200 lg:px-3.5 lg:py-3',
                                active(item.match)
                                    ? 'bg-white text-slate-900 shadow-lg shadow-slate-900/30 ring-1 ring-white/90'
                                    : 'text-slate-300 hover:bg-white/[0.08] hover:text-white',
                            ]"
                        >
                            <span
                                :class="[
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl transition-colors lg:h-10 lg:w-10',
                                    active(item.match)
                                        ? 'bg-brand-50 text-brand-700 ring-1 ring-brand-100/80'
                                        : 'bg-white/10 text-slate-200 group-hover:bg-white/15 group-hover:text-white',
                                ]"
                            >
                                <svg v-if="navIcon(item) === 'home'" class="h-[1.05rem] w-[1.05rem] lg:h-[1.15rem] lg:w-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-10.5z" />
                                </svg>
                                <svg v-else-if="navIcon(item) === 'products'" class="h-[1.05rem] w-[1.05rem] lg:h-[1.15rem] lg:w-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                    <path d="M3 17l9 4 9-4M3 12l9 4 9-4" />
                                </svg>
                                <svg v-else-if="navIcon(item) === 'profile'" class="h-[1.05rem] w-[1.05rem] lg:h-[1.15rem] lg:w-[1.15rem]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21a8 8 0 0 1 16 0" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                        </Link>
                    </nav>
                </div>
                <div class="mt-auto border-t border-white/10 p-4 lg:p-5">
                    <p class="text-center text-[11px] font-medium leading-relaxed text-slate-400">بوابة العميل</p>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="sticky top-0 z-30 flex min-h-14 items-center justify-between gap-2 border-b border-slate-200/80 bg-white/90 px-3 shadow-sm backdrop-blur-md sm:px-5 md:min-h-[3.5rem] md:px-6"
                >
                    <div class="flex min-w-0 items-center gap-3 md:hidden">
                        <Link :href="route('portal.dashboard')" class="shrink-0 rounded-xl ring-1 ring-slate-200/80 transition hover:ring-brand-200">
                            <img src="/images/mobile-logo.png" alt="خارج المخزون" class="h-10 w-10 rounded-lg object-contain" />
                        </Link>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900">{{ title }}</div>
                            <div class="truncate text-[11px] text-slate-500">{{ client?.name }}</div>
                        </div>
                    </div>
                    <div class="hidden min-w-0 md:block">
                        <h1 class="truncate text-base font-bold text-slate-900 lg:text-lg">{{ title }}</h1>
                        <p class="truncate text-xs text-slate-500">{{ client?.name }}</p>
                    </div>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex max-w-[11rem] min-h-11 items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span class="truncate">{{ client?.name }}</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('portal.profile')">الملف الشخصي</DropdownLink>
                            <DropdownLink :href="route('portal.logout')" method="post" as="button">
                                تسجيل الخروج
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </header>

                <main
                    class="portal-content min-w-0 flex-1 overflow-x-hidden px-3 pb-28 pt-3 text-slate-800 sm:px-4 sm:pb-28 sm:pt-4 md:px-6 md:py-5 md:pb-6"
                >
                    <slot />
                </main>

                <nav
                    class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/90 bg-white/95 px-2 pt-2 shadow-[0_-8px_30px_-12px_rgba(15,23,42,0.12)] backdrop-blur-xl md:hidden"
                >
                    <div class="mx-auto grid max-w-lg grid-cols-3 gap-1 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
                        <Link
                            v-for="item in nav"
                            :key="`portal-mobile-bottom-${item.routeName}`"
                            :href="route(item.routeName)"
                            :class="[
                                'flex min-h-[3.25rem] flex-col items-center justify-center gap-0.5 rounded-2xl px-1 py-1.5 text-[10px] font-semibold transition-all duration-200 active:scale-[0.97]',
                                active(item.match)
                                    ? 'bg-brand-50 text-brand-800 ring-1 ring-brand-200/80'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                            ]"
                        >
                            <svg v-if="navIcon(item) === 'home'" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-10.5z" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'products'" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                <path d="M3 17l9 4 9-4M3 12l9 4 9-4" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'profile'" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 21a8 8 0 0 1 16 0" />
                            </svg>
                            <span class="leading-tight">{{ item.label }}</span>
                        </Link>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* وضوح الحقول فقط — دون إلغاء تدرجات الألوان في الواجهة */
.portal-content :deep(input:not([type='checkbox']):not([type='radio'])),
.portal-content :deep(select),
.portal-content :deep(textarea),
.portal-content :deep(option) {
    color: #0f172a;
}

.portal-content :deep(input::placeholder),
.portal-content :deep(textarea::placeholder) {
    color: #64748b;
}
</style>
