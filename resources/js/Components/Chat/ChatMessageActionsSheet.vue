<script setup>
import AppModalShell from '@/Components/AppModalShell.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    msg: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    viewKind: { type: String, default: 'team' },
});

const emit = defineEmits(['close', 'copy', 'forward', 'reply', 'create-task', 'start-edit', 'remove']);
</script>

<template>
    <AppModalShell
        :open="open && !!msg"
        size="sm"
        :z-index="110"
        aria-label="إجراءات الرسالة"
        @close="emit('close')"
    >
        <div class="app-modal-body !py-2.5">
            <p class="mb-2 text-center text-[11px] font-medium text-slate-500">خيارات الرسالة</p>

            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                @click="emit('reply')"
            >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">↩</span>
                رد على الرسالة
            </button>

            <button
                type="button"
                class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                @click="emit('create-task')"
            >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-700">✓</span>
                إنشاء مهمة
            </button>

            <button
                type="button"
                class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                @click="emit('copy')"
            >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">📋</span>
                نسخ النص
            </button>

            <button
                type="button"
                class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                @click="emit('forward')"
            >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700">↪</span>
                تحويل لمحادثة أخرى
            </button>

            <template v-if="viewKind === 'team' && canManage">
                <button
                    type="button"
                    class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                    @click="emit('start-edit')"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700">✎</span>
                    تعديل
                </button>
                <button
                    type="button"
                    class="mt-1 flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-start text-sm font-semibold text-rose-700 transition hover:bg-rose-50"
                    @click="emit('remove')"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50">🗑</span>
                    حذف
                </button>
            </template>
        </div>

        <div class="app-modal-footer !pt-2">
            <button
                type="button"
                class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 sm:w-auto sm:min-w-[7rem]"
                @click="emit('close')"
            >
                إلغاء
            </button>
        </div>
    </AppModalShell>
</template>
