<script setup>
import { computed } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** sm | md | lg | xl | 2xl | task */
    size: { type: String, default: 'md' },
    zIndex: { type: [Number, String], default: 100 },
    ariaLabel: { type: String, default: 'نافذة منبثقة' },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const panelClass = computed(() => {
    const size = ['sm', 'md', 'lg', 'xl', '2xl', 'task'].includes(props.size) ? props.size : 'md';

    return `app-modal-panel app-modal-panel--${size}`;
});

const backdropStyle = computed(() => ({
    zIndex: Number(props.zIndex) || 100,
}));

function onBackdropClick() {
    if (props.closeOnBackdrop) {
        emit('close');
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="app-modal-backdrop"
                :style="backdropStyle"
                role="dialog"
                aria-modal="true"
                :aria-label="ariaLabel"
                @click.self="onBackdropClick"
            >
                <div :class="panelClass" dir="rtl" @click.stop>
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
