<script setup>
defineProps({
    count: { type: Number, default: 0 },
});

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="flex h-11 min-w-[2.75rem] items-center justify-center gap-0.5 rounded-full border border-sky-200/90 bg-white px-3 text-sky-600 shadow-lg shadow-slate-900/10 ring-1 ring-sky-100 transition hover:bg-sky-50 active:scale-95"
        aria-label="انتقل إلى رسالة المنشن"
        @click="$emit('click')"
    >
        <span class="text-base font-bold leading-none">@</span>
        <span
            v-if="count > 1"
            class="text-[10px] font-semibold tabular-nums text-sky-500"
        >
            {{ count > 9 ? '9+' : count }}
        </span>
    </button>
</template>
