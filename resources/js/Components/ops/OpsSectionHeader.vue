<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    meta: { type: String, default: '' },
});
</script>

<template>
    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <h2 class="ops-section-title-lg">
                    {{ title }}
                </h2>
                <span
                    v-if="meta"
                    class="shrink-0 rounded-md bg-slate-100/90 px-2 py-0.5 text-ops-body-sm font-medium text-slate-600 ring-1 ring-slate-200/80"
                >
                    {{ meta }}
                </span>
            </div>
            <p v-if="description" class="ops-section-lead max-w-prose">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
            <slot name="actions" />
        </div>
    </header>
</template>
