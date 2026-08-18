<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-[var(--app-visual-vh,100dvh)] flex-col items-center bg-gray-100 pt-[max(1.5rem,env(safe-area-inset-top,0px))] sm:justify-center sm:pt-0 md:min-h-screen"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="!h-16 max-h-16" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
