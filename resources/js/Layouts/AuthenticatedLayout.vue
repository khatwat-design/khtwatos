<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const nav = [
    ...(page.props.auth?.can?.viewAdminHome
        ? [{ label: 'الرئيسية', routeName: 'home.index', match: 'home.*' }]
        : []),
    { label: 'المهام', routeName: 'tasks.index', match: 'tasks.*' },
    { label: 'الدردشة', routeName: 'chat.index', match: 'chat.*' },
    { label: 'الاجتماعات', routeName: 'meetings.index', match: 'meetings.*' },
    { label: 'العملاء', routeName: 'clients.index', match: 'clients.*' },
    { label: 'خطوة', routeName: 'ai.index', match: 'ai.*' },
    { label: 'الأكاديمية', routeName: 'academy.index', match: 'academy.index' },
    ...(page.props.auth?.can?.manageEmployees
        ? [{ label: 'الموظفين', routeName: 'employees.index', match: 'employees.*' }]
        : []),
];

function active(match) {
    return route().current(match);
}
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <div class="flex min-h-screen">
            <aside
                class="hidden w-56 shrink-0 flex-col border-s border-neutral-800 bg-black text-white md:flex"
            >
                <div class="flex h-44 items-center justify-center border-b border-neutral-800 px-4">
                    <Link :href="route('dashboard')" class="flex w-full items-center justify-center">
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
                <div class="border-t border-neutral-800 p-3 text-xs text-neutral-400">
                    {{ page.props.auth.user?.name }}
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 md:px-6"
                >
                    <div class="flex items-center gap-3 md:hidden">
                        <Link :href="route('dashboard')">
                            <ApplicationLogo class="!h-8 max-h-8" />
                        </Link>
                        <div class="text-sm font-semibold text-gray-800">
                            <slot name="title" />
                        </div>
                    </div>
                    <div class="hidden text-lg font-semibold text-gray-800 md:block">
                        <slot name="title" />
                    </div>
                    <div class="ms-auto">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"
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

                <div class="border-b border-gray-200 bg-white px-2 py-2 md:hidden">
                    <div class="flex gap-2 overflow-x-auto whitespace-nowrap [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <Link
                            v-for="item in nav"
                            :key="`mobile-${item.routeName}`"
                            :href="route(item.routeName)"
                            :class="[
                                'rounded-full px-3 py-1.5 text-xs font-medium ring-1 transition',
                                active(item.match)
                                    ? 'bg-black text-white ring-black'
                                    : 'bg-white text-gray-700 ring-gray-200',
                            ]"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                </div>

                <main class="flex-1 overflow-x-auto p-3 pb-20 md:p-6 md:pb-6">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
