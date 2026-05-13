<script setup>
defineProps({
    /** When true, slightly denser padding for mobile toolbars. */
    dense: { type: Boolean, default: false },
});
</script>

<template>
    <div
        :class="[
            'flex flex-col gap-ops-3 rounded-xl border border-slate-200/80 bg-white/80 shadow-sm ring-1 ring-slate-200/40 backdrop-blur-sm',
            dense ? 'p-ops-3 sm:p-ops-3' : 'p-ops-3 sm:p-ops-4',
            'sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-ops-3',
        ]"
    >
        <slot />
    </div>
</template>
