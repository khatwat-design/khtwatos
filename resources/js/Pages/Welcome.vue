<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="خارج المخزون" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-brand-950 to-slate-900 px-6 py-12 text-white"
    >
        <ApplicationLogo class="!h-24 max-h-24 brightness-0 invert" />

        <h1 class="mt-8 text-center text-3xl font-bold tracking-tight">
            خارج المخزون
        </h1>
        <p class="mt-3 max-w-md text-center text-sm text-slate-300">
            نظام إدارة الفريق: المهام، الاجتماعات، والعملاء.
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <Link
                v-if="canLogin && $page.props.auth?.user"
                :href="route('dashboard')"
                class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-brand-500"
            >
                الدخول للوحة
            </Link>
            <template v-else-if="canLogin">
                <Link
                    :href="route('login')"
                    class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-brand-500"
                >
                    تسجيل الدخول
                </Link>
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="rounded-lg border border-white/30 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
                >
                    إنشاء حساب
                </Link>
            </template>
            <Link
                :href="route('book.index')"
                class="rounded-lg border border-white/30 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10"
            >
                حجز موعد
            </Link>
        </div>
    </div>
</template>
