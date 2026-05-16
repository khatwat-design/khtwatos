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
const { startTour, skipTour, resetAllTours, destroyActive, runningTourId } = useProductTour();

const centerOpen = ref(false);
const startingId = ref('');
const autoStarted = ref(new Set());

const autoStartId = computed(() => page.props.product_tours?.auto_start_id || null);
const pendingCount = computed(() => (page.props.product_tours?.pending_ids || []).length);

const canAutoStart = computed(
    () =>
        !props.blocked &&
        overlayOpenCount.value === 0 &&
        !centerOpen.value &&
        !runningTourId.value,
);

async function runTour(tourId, { forceRestart = false } = {}) {
    if (!tourId) {
        return;
    }

    startingId.value = tourId;

    try {
        if (forceRestart) {
            await window.axios.post(route('product-tours.restart', tourId), {}, {
                headers: { Accept: 'application/json' },
            }).then((res) => {
                if (res?.data?.product_tours) {
                    page.props.product_tours = res.data.product_tours;
                }
            });
        }

        centerOpen.value = false;
        await startTour(tourId);
    } finally {
        startingId.value = '';
    }
}

function tryAutoStart() {
    const id = autoStartId.value;
    if (!id || !canAutoStart.value || autoStarted.value.has(id)) {
        return;
    }

    autoStarted.value.add(id);
    void runTour(id);
}

watch(
    () => [autoStartId.value, canAutoStart.value, page.url],
    () => {
        if (!canAutoStart.value) {
            return;
        }
        window.setTimeout(tryAutoStart, 600);
    },
    { immediate: true },
);

watch(
    () => props.blocked,
    (blocked) => {
        if (!blocked) {
            window.setTimeout(tryAutoStart, 400);
        }
    },
);

router.on('finish', () => {
    autoStarted.value.clear();
});

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
    if (!confirm('إعادة تعيين كل الجولات التدريبية؟ ستظهر من جديد عند زيارة كل قسم.')) {
        return;
    }
    await resetAllTours();
    autoStarted.value.clear();
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

    <Teleport to="body">
        <div
            v-if="pendingCount > 0 && !blocked && !centerOpen && !runningTourId"
            class="pointer-events-none fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] start-4 z-[60] max-md:start-3 md:bottom-6"
        >
            <button
                type="button"
                class="pointer-events-auto flex items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-2 text-xs font-semibold text-sky-800 shadow-lg shadow-slate-900/10 transition hover:bg-sky-50 active:scale-95"
                @click="openCenter"
            >
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] font-bold text-white">?</span>
                <span class="hidden sm:inline">متابعة التدريب ({{ pendingCount }})</span>
                <span class="sm:hidden">تدريب · {{ pendingCount }}</span>
            </button>
        </div>
    </Teleport>
</template>
