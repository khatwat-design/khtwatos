<script setup>
import {
    mobileSheetBackdrop,
    mobileSheetCloseBtn,
    mobileSheetFooter,
    mobileSheetHeader,
    mobileSheetPanelLg,
    mobileSheetPanelMd,
    mobileSheetPanelSm,
    mobileSheetPanelXl,
    mobileSheetPanel2xl,
    mobileSheetTitle,
} from '@/utils/mobileSheetClasses.js';
import { registerOverlayClose, registerOverlayOpen } from '@/state/overlayOpen.js';
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl', '2xl'].includes(v),
    },
});

const emit = defineEmits(['close']);

const panelClass = computed(() => {
    const map = {
        sm: mobileSheetPanelSm,
        md: mobileSheetPanelMd,
        lg: mobileSheetPanelLg,
        xl: mobileSheetPanelXl,
        '2xl': mobileSheetPanel2xl,
    };
    return map[props.size] ?? mobileSheetPanelMd;
});

watch(
    () => props.show,
    (open, wasOpen) => {
        if (open && !wasOpen) {
            registerOverlayOpen();
        } else if (!open && wasOpen) {
            registerOverlayClose();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.show) {
        registerOverlayClose();
    }
});

function close() {
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" :class="mobileSheetBackdrop" role="dialog" aria-modal="true" @click.self="close">
            <div
                :class="[panelClass, 'flex max-h-[inherit] flex-col !overflow-hidden p-0']"
                @click.stop
            >
                <header v-if="$slots.header" :class="mobileSheetHeader">
                    <slot name="header" />
                </header>
                <div v-else-if="$slots.title" :class="mobileSheetHeader">
                    <h3 :class="mobileSheetTitle">
                        <slot name="title" />
                    </h3>
                    <slot name="close">
                        <button type="button" :class="mobileSheetCloseBtn" @click="close">إغلاق</button>
                    </slot>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <slot />
                </div>

                <footer v-if="$slots.footer" :class="mobileSheetFooter">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
