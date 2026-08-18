<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    avatarUrl: { type: String, default: '' },
    sizeClass: { type: String, default: 'h-8 w-8 sm:h-9 sm:w-9' },
    roundedClass: { type: String, default: 'rounded-xl' },
    gradientClass: { type: String, default: 'from-slate-500 to-slate-700' },
    textClass: { type: String, default: 'text-[10px] sm:text-[11px]' },
});

const initial = computed(() => {
    const value = String(props.name || '?').trim();
    return value.charAt(0) || '?';
});
</script>

<template>
    <img
        v-if="avatarUrl"
        :src="avatarUrl"
        :alt="name || 'صورة المستخدم'"
        class="shrink-0 object-cover shadow ring-2 ring-white"
        :class="[sizeClass, roundedClass]"
    >
    <div
        v-else
        class="flex shrink-0 items-center justify-center bg-gradient-to-br font-bold text-white shadow ring-2 ring-white"
        :class="[sizeClass, roundedClass, gradientClass, textClass]"
        aria-hidden="true"
    >
        {{ initial }}
    </div>
</template>
