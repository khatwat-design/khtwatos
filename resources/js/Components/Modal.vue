<script setup>
import { modalPanelClassForMaxWidth } from '@/utils/mobileSheetClasses.js';
import { registerOverlayClose, registerOverlayOpen } from '@/state/overlayOpen.js';
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
            registerOverlayOpen();
            showSlot.value = true;
            dialog.value?.showModal();
        } else {
            registerOverlayClose();
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
    if (e.key === 'Escape' && props.show) {
        e.preventDefault();
        close();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    if (props.show) {
        registerOverlayClose();
    }
});

const panelClass = computed(() => modalPanelClassForMaxWidth(props.maxWidth));
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="m-0 min-h-0 min-w-0 border-0 bg-transparent p-0 shadow-none outline-none">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="show"
                    class="app-modal-backdrop"
                    role="dialog"
                    aria-modal="true"
                    @click.self="close"
                >
                    <Transition
                        enter-active-class="transition duration-260 ease-out"
                        enter-from-class="opacity-0 scale-[0.97] translate-y-2"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-[0.97] translate-y-2"
                    >
                        <div v-if="show" :class="panelClass" dir="rtl" @click.stop>
                            <div class="app-modal-scroll">
                                <div class="app-modal-inner text-slate-900">
                                    <slot v-if="showSlot" />
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </dialog>
    </Teleport>
</template>
