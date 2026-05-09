<script setup>
import CapacitorOfflineBanner from '@/Components/CapacitorOfflineBanner.vue';
import GlobalPageLoader from '@/Components/GlobalPageLoader.vue';
import { Capacitor } from '@capacitor/core';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const showLoader = ref(false);
let showTimer = null;
const removeFns = [];

onMounted(() => {
    document.documentElement.removeAttribute('data-app-shell');

    if (Capacitor.isNativePlatform()) {
        import('@capacitor/splash-screen').then(({ SplashScreen }) =>
            SplashScreen.hide({ fadeOutDuration: 280 }).catch(() => {}),
        );
    }
    removeFns.push(
        router.on('start', (event) => {
            // لا تعرض لودر الشعار لزيارات الخلفية (مثل reload جزئي async في قسم الخارج كل ثانية)
            const visit = event.detail?.visit;
            if (visit && visit.showProgress === false) {
                return;
            }
            window.clearTimeout(showTimer);
            showTimer = window.setTimeout(() => {
                showLoader.value = true;
            }, 100);
        }),
    );
    removeFns.push(
        router.on('finish', () => {
            window.clearTimeout(showTimer);
            showLoader.value = false;
        }),
    );
    removeFns.push(
        router.on('error', () => {
            window.clearTimeout(showTimer);
            showLoader.value = false;
        }),
    );
});

onBeforeUnmount(() => {
    window.clearTimeout(showTimer);
    removeFns.forEach((fn) => fn?.());
});
</script>

<template>
    <div class="contents">
        <CapacitorOfflineBanner />
        <GlobalPageLoader :show="showLoader" />
        <slot />
    </div>
</template>
