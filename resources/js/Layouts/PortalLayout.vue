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

    <div class="min-h-screen bg-gray-100">
        <div class="flex min-h-screen">
            <aside class="hidden w-56 shrink-0 flex-col border-s border-neutral-800 bg-black text-white md:flex">
                <div class="flex h-44 items-center justify-center border-b border-neutral-800 px-4">
                    <Link :href="route('portal.dashboard')" class="flex w-full items-center justify-center">
                        <img
                            src="/images/logo-sidebar.png"
                            alt="خارج المخزون"
                            class="h-32 w-full object-contain"
                        />
                    </Link>
                </div>
                <nav class="flex flex-1 flex-col gap-1 p-3">
                    <Link
                        v-for="item in nav"
                        :key="item.routeName"
                        :href="route(item.routeName)"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition',
                            active(item.match)
                                ? 'bg-neutral-800 text-white ring-1 ring-neutral-700'
                                : 'text-neutral-300 hover:bg-neutral-900 hover:text-white',
                        ]"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 md:px-6">
                    <div class="flex items-center gap-3 md:hidden">
                        <Link :href="route('portal.dashboard')">
                            <img src="/images/mobile-logo.png" alt="خارج المخزون" class="h-10 w-10 rounded-lg object-contain" />
                        </Link>
                        <div class="text-sm font-semibold text-gray-900">{{ title }}</div>
                    </div>
                    <div class="hidden text-base font-semibold text-gray-800 md:block">{{ title }}</div>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-black transition-all duration-200 ease-out hover:bg-gray-50 hover:text-black"
                            >
                                {{ client?.name }}
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
                            <DropdownLink :href="route('portal.profile')">الملف الشخصي</DropdownLink>
                            <DropdownLink :href="route('portal.logout')" method="post" as="button">
                                تسجيل الخروج
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </header>

                <main class="portal-content flex-1 overflow-x-auto p-3 pb-20 md:p-6 md:pb-6">
                    <slot />
                </main>

                <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-2 py-1.5 backdrop-blur-xl md:hidden">
                    <div class="grid grid-cols-3 gap-1">
                        <Link
                            v-for="item in nav"
                            :key="`portal-mobile-bottom-${item.routeName}`"
                            :href="route(item.routeName)"
                            :class="[
                                'inline-flex h-12 items-center justify-center rounded-xl transition-all duration-200 ease-out',
                                active(item.match)
                                    ? 'bg-brand-100 text-black ring-1 ring-brand-200'
                                    : 'text-black/80 hover:bg-gray-100 hover:text-black',
                            ]"
                            :title="item.label"
                        >
                            <svg v-if="navIcon(item) === 'home'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1v-10.5z" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'products'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                <path d="M3 17l9 4 9-4M3 12l9 4 9-4" />
                            </svg>
                            <svg v-else-if="navIcon(item) === 'profile'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 21a8 8 0 0 1 16 0" />
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
.portal-content :deep(*) {
    color: #111111;
}

.portal-content :deep(.text-white),
.portal-content :deep(.text-white\/90),
.portal-content :deep(.text-white\/80),
.portal-content :deep(.text-white\/75),
.portal-content :deep(.text-white\/70),
.portal-content :deep(.text-white\/60),
.portal-content :deep(.text-gray-900),
.portal-content :deep(.text-gray-800),
.portal-content :deep(.text-gray-700),
.portal-content :deep(.text-gray-600),
.portal-content :deep(.text-gray-500),
.portal-content :deep(.text-slate-900),
.portal-content :deep(.text-slate-800),
.portal-content :deep(.text-slate-700),
.portal-content :deep(.text-slate-600),
.portal-content :deep(.text-slate-500),
.portal-content :deep(.text-neutral-900),
.portal-content :deep(.text-neutral-800),
.portal-content :deep(.text-neutral-700),
.portal-content :deep(.text-neutral-600),
.portal-content :deep(.text-neutral-500) {
    color: #111111 !important;
}

.portal-content :deep(input),
.portal-content :deep(select),
.portal-content :deep(textarea),
.portal-content :deep(option) {
    color: #111111 !important;
}

.portal-content :deep(input::placeholder),
.portal-content :deep(textarea::placeholder) {
    color: #555555 !important;
}

@supports (padding: max(0px)) {
    nav[class*='fixed'][class*='bottom-0'] {
        padding-bottom: max(0.375rem, env(safe-area-inset-bottom));
    }
}
</style>
