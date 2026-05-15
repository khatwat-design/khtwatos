<script setup>
import { Capacitor } from '@capacitor/core';
import { modalMobilePanelByMaxWidth } from '@/utils/mobileSheetClasses.js';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/** في WebView الأصلي نعرض اللوحة كـ bottom sheet */
const isNativeShell = Capacitor.isNativePlatform();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);

watch(
    () => props.show,
    () => {
        if (props.show) {
            document.body.style.overflow = 'hidden';
            showSlot.value = true;

            dialog.value?.showModal();
        } else {
            document.body.style.overflow = '';

            setTimeout(() => {
                dialog.value?.close();
                showSlot.value = false;
            }, 200);
        }
    },
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        e.preventDefault();

        if (props.show) {
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);

    document.body.style.overflow = '';
});

const mobilePanelClass = computed(
    () => modalMobilePanelByMaxWidth[props.maxWidth] ?? modalMobilePanelByMaxWidth['2xl'],
);

const panelTransitionEnterFrom = isNativeShell
    ? 'opacity-0 translate-y-full'
    : 'opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95';

const panelTransitionEnterTo = isNativeShell ? 'opacity-100 translate-y-0' : 'opacity-100 translate-y-0 sm:scale-100';

const panelTransitionLeaveTo = isNativeShell
    ? 'opacity-0 translate-y-full'
    : 'opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95';

const panelTransitionLeaveFrom = isNativeShell ? 'opacity-100 translate-y-0' : 'opacity-100 translate-y-0 sm:scale-100';

const panelBoxClass = computed(() => {
    const safeBottom = 'pb-[max(0.5rem,env(safe-area-inset-bottom,0px))]';

    if (isNativeShell) {
        return ['modal-content-light w-full max-w-none mx-0 mb-0 shadow-xl transition-all transform', mobilePanelClass.value, safeBottom];
    }

    return [
        'modal-content-light mb-6 overflow-hidden transition-all transform sm:mx-auto sm:mb-6 sm:w-full sm:overflow-hidden sm:rounded-lg',
        'max-lg:mb-0 max-lg:mx-auto max-lg:w-full max-lg:max-w-[calc(100%-0.25rem)]',
        mobilePanelClass.value,
        safeBottom,
    ];
});
</script>

<template>
    <dialog
        class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent"
        ref="dialog"
    >
        <div
            class="fixed inset-0 z-50 max-lg:flex max-lg:flex-col max-lg:justify-end max-lg:overflow-hidden max-lg:bg-black/45 max-lg:p-4 max-lg:pt-8 max-lg:pb-[calc(0.5rem+env(safe-area-inset-bottom,0px))] sm:block sm:overflow-y-auto sm:px-4 sm:py-6 sm:pt-6"
            scroll-region
        >
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 transform transition-all max-lg:pointer-events-auto"
                    @click="close"
                >
                    <div class="absolute inset-0 bg-gray-500/75 max-lg:bg-black/45" />
                </div>
            </Transition>

            <Transition
                enter-active-class="ease-out duration-300"
                :enter-from-class="panelTransitionEnterFrom"
                :enter-to-class="panelTransitionEnterTo"
                leave-active-class="ease-in duration-200"
                :leave-from-class="panelTransitionLeaveFrom"
                :leave-to-class="panelTransitionLeaveTo"
            >
                <div v-show="show" class="bg-white max-lg:relative max-lg:shrink-0" :class="panelBoxClass" @click.stop>
                    <slot v-if="showSlot" />
                </div>
            </Transition>
        </div>
    </dialog>
</template>

<style scoped>
.modal-content-light {
    color: #111111;
}

.modal-content-light :deep(.text-white),
.modal-content-light :deep(.text-white\/90),
.modal-content-light :deep(.text-white\/80),
.modal-content-light :deep(.text-slate-900),
.modal-content-light :deep(.text-slate-800),
.modal-content-light :deep(.text-slate-700),
.modal-content-light :deep(.text-slate-600),
.modal-content-light :deep(.text-slate-500),
.modal-content-light :deep(.text-slate-400),
.modal-content-light :deep(.text-gray-900),
.modal-content-light :deep(.text-gray-800),
.modal-content-light :deep(.text-gray-700),
.modal-content-light :deep(.text-gray-600),
.modal-content-light :deep(.text-gray-500),
.modal-content-light :deep(.text-gray-400),
.modal-content-light :deep(label),
.modal-content-light :deep(h1),
.modal-content-light :deep(h2),
.modal-content-light :deep(h3),
.modal-content-light :deep(h4),
.modal-content-light :deep(h5),
.modal-content-light :deep(h6),
.modal-content-light :deep(p),
.modal-content-light :deep(span) {
    color: #111111 !important;
}

.modal-content-light :deep(input),
.modal-content-light :deep(select),
.modal-content-light :deep(textarea) {
    color: #111111 !important;
}

.modal-content-light :deep(input::placeholder),
.modal-content-light :deep(textarea::placeholder) {
    color: #6b7280 !important;
}

.modal-content-light :deep(.glass-modal) {
    background: transparent !important;
    backdrop-filter: none !important;
    max-height: none !important;
    overflow: visible !important;
    padding: 0 !important;
    border-radius: 0 !important;
}
</style>
