<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog
        class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent"
        ref="dialog"
    >
        <div
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
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
                    class="fixed inset-0 transform transition-all"
                    @click="close"
                >
                    <div
                        class="absolute inset-0 bg-gray-500 opacity-75"
                    />
                </div>
            </Transition>

            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    class="modal-content-light mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full"
                    :class="maxWidthClass"
                >
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
</style>
