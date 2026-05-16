<script setup>
import ProductTourCenterModal from '@/Components/ProductTour/ProductTourCenterModal.vue';
import { useProductTour } from '@/composables/useProductTour.js';
import { overlayOpenCount } from '@/state/overlayOpen.js';
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    blocked: { type: Boolean, default: false },
});

const page = usePage();
const { startTour, resetAllTours, destroyActive, runningTourId } = useProductTour();

const centerOpen = ref(false);
const startingId = ref('');
const introOffered = ref(false);

const autoStartId = computed(() => page.props.product_tours?.auto_start_id || null);
const pendingCount = computed(() => (page.props.product_tours?.pending_ids || []).length);

const canAutoStart = computed(
    () => !props.blocked && overlayOpenCount.value === 0 && !centerOpen.value && !runningTourId.value,
);

function introStorageKey() {
    const uid = page.props.auth?.user?.id;
    const tourId = autoStartId.value;
    if (!uid || !tourId) {
        return null;
    }
    return `kht-tour-intro-${uid}-${tourId}`;
}

/** جولة ترحيب واحدة فقط عند أول زيارة للرئيسية — بدون إزعاج في كل صفحة */
function tryIntroAutoStart() {
    const id = autoStartId.value;
    if (!id || !canAutoStart.value || introOffered.value) {
        return;
    }

    const key = introStorageKey();
    if (!key) {
        return;
    }

    try {
        if (sessionStorage.getItem(key) === '1') {
            return;
        }
        sessionStorage.setItem(key, '1');
    } catch {
        /* تجاهل */
    }

    introOffered.value = true;
    window.setTimeout(() => void runTour(id), 900);
}

async function runTour(tourId, { forceRestart = false } = {}) {
    if (!tourId) {
        return;
    }

    startingId.value = tourId;

    try {
        if (forceRestart) {
            const res = await window.axios.post(route('product-tours.restart', tourId), {}, {
                headers: { Accept: 'application/json' },
            });
            if (res?.data?.product_tours) {
                page.props.product_tours = res.data.product_tours;
            }
        }

        centerOpen.value = false;
        await startTour(tourId, { force: forceRestart });
    } finally {
        startingId.value = '';
    }
}

watch(
    () => [autoStartId.value, canAutoStart.value],
    () => {
        if (canAutoStart.value && (autoStartId.value === 'welcome' || autoStartId.value === 'admin-home')) {
            tryIntroAutoStart();
        }
    },
    { immediate: true },
);

watch(
    () => props.blocked,
    (blocked) => {
        if (!blocked && (autoStartId.value === 'welcome' || autoStartId.value === 'admin-home')) {
            window.setTimeout(tryIntroAutoStart, 500);
        }
    },
);

function openCenter() {
    centerOpen.value = true;
}

function closeCenter() {
    centerOpen.value = false;
}

async function onCenterStart(tourId) {
    const row = (page.props.product_tours?.catalog || []).find((t) => t.id === tourId);
    const force = row && row.status !== 'pending';
    await runTour(tourId, { forceRestart: force });
}

async function onResetAll() {
    if (!confirm('إعادة تعيين كل الجولات التدريبية؟')) {
        return;
    }
    await resetAllTours();
    introOffered.value = false;
    try {
        const uid = page.props.auth?.user?.id;
        if (uid) {
            for (const key of Object.keys(sessionStorage)) {
                if (key.startsWith(`kht-tour-intro-${uid}-`)) {
                    sessionStorage.removeItem(key);
                }
            }
        }
    } catch {
        /* تجاهل */
    }
}

onBeforeUnmount(() => {
    destroyActive();
});

defineExpose({ openCenter });
</script>

<template>
    <ProductTourCenterModal
        :open="centerOpen"
        :starting-id="startingId"
        @close="closeCenter"
        @start="onCenterStart"
        @reset="onResetAll"
    />
</template>
