<script setup>
defineProps({
    reply: { type: Object, required: true },
});

const emit = defineEmits(['clear']);
</script>

<template>
    <div
        class="flex items-center gap-2 border-b border-brand-100 bg-brand-50/90 px-3 py-2"
        dir="rtl"
    >
        <span class="w-1 shrink-0 self-stretch rounded-full bg-brand-500" aria-hidden="true" />
        <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold text-brand-700">رد على {{ reply.user_name || 'عضو' }}</p>
            <p class="truncate text-xs text-slate-700">{{ reply.preview || 'رسالة' }}</p>
        </div>
        <button
            type="button"
            class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-white"
            aria-label="إلغاء الرد"
            @click="emit('clear')"
        >
            ✕
        </button>
    </div>
</template>
