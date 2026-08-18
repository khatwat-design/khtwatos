<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Semantic tone — maps to calm enterprise surfaces (not decorative). */
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) =>
            ['neutral', 'info', 'success', 'warning', 'danger', 'review', 'revision', 'muted'].includes(v),
    },
    label: { type: String, required: true },
});

const classes = computed(() => {
    const base =
        'inline-flex max-w-full items-center truncate rounded-md px-2 py-0.5 text-ops-label font-semibold uppercase tracking-wide ring-1';
    const map = {
        neutral: 'bg-slate-100/90 text-slate-700 ring-slate-200/80',
        muted: 'bg-slate-50/90 text-slate-500 ring-slate-200/70',
        info: 'bg-sky-50/90 text-sky-800 ring-sky-200/80',
        success: 'bg-emerald-50/90 text-emerald-800 ring-emerald-200/80',
        warning: 'bg-amber-50/90 text-amber-900 ring-amber-200/80',
        danger: 'bg-red-50/90 text-red-800 ring-red-200/80',
        review: 'bg-violet-50/90 text-violet-900 ring-violet-200/80',
        revision: 'bg-orange-50/90 text-orange-900 ring-orange-200/80',
    };
    return `${base} ${map[props.variant] || map.neutral}`;
});
</script>

<template>
    <span :class="classes" :title="label">
        {{ label }}
    </span>
</template>
