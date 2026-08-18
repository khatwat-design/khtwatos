<script setup>
import { Capacitor } from '@capacitor/core';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const online = ref(true);

function syncBrowserOnline() {
    online.value = typeof navigator !== 'undefined' ? navigator.onLine : true;
}

function onCapNetwork(ev) {
    online.value = Boolean(ev.detail?.connected);
}

function onWindowOnline() {
    online.value = true;
}

function onWindowOffline() {
    online.value = false;
}

onMounted(() => {
    syncBrowserOnline();
    window.addEventListener('online', onWindowOnline);
    window.addEventListener('offline', onWindowOffline);
    window.addEventListener('capacitor:network', onCapNetwork);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', onWindowOnline);
    window.removeEventListener('offline', onWindowOffline);
    window.removeEventListener('capacitor:network', onCapNetwork);
});

const showBanner = computed(() => !online.value && Capacitor.isNativePlatform());
</script>

<template>
    <div
        v-if="showBanner"
        class="pointer-events-none fixed inset-x-0 top-0 z-[99998] flex justify-center pt-[env(safe-area-inset-top,0px)]"
        role="status"
        aria-live="polite"
    >
        <div
            class="pointer-events-auto mx-3 mt-2 rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-2 text-center text-[12px] font-semibold text-amber-950 shadow-md shadow-amber-900/10"
        >
            لا يوجد اتصال بالإنترنت — بعض الإجراءات قد لا تعمل حتى يعود الاتصال.
        </div>
    </div>
</template>
