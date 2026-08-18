<script setup>
import { computed } from 'vue';

const props = defineProps({
    levels: {
        type: Array,
        default: () => [],
    },
    barCount: {
        type: Number,
        default: 28,
    },
    active: {
        type: Boolean,
        default: true,
    },
});

const bars = computed(() => {
    const source = props.levels?.length ? props.levels : Array(props.barCount).fill(0.12);
    const out = [];
    for (let i = 0; i < props.barCount; i += 1) {
        out.push(Number(source[i % source.length] || 0.12));
    }
    return out;
});
</script>

<template>
    <div
        class="flex h-10 flex-1 items-center justify-center gap-[3px] px-1"
        role="img"
        aria-label="موجات صوتية أثناء التسجيل"
    >
        <span
            v-for="(level, index) in bars"
            :key="index"
            class="w-[3px] min-h-[4px] max-h-10 rounded-full transition-[height] duration-75 ease-out"
            :class="active ? 'bg-rose-500' : 'bg-rose-300'"
            :style="{ height: `${Math.round(4 + level * 36)}px` }"
        />
    </div>
</template>
