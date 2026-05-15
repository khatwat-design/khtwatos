<script setup>
const props = defineProps({
    open: { type: Boolean, default: false },
    msg: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    viewKind: { type: String, default: 'team' },
});

const emit = defineEmits(['close', 'copy', 'forward', 'start-edit', 'remove']);
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
                v-if="open && msg"
                class="mobile-sheet-backdrop z-[110] !bg-slate-950/50 p-3 backdrop-blur-[2px] sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-label="إجراءات الرسالة"
                @click.self="emit('close')"
            >
                <div
                    class="mobile-sheet-panel mobile-sheet-panel--sm w-full max-w-md p-2 shadow-2xl sm:p-3"
                    dir="rtl"
                    @click.stop
                >
                    <div class="mb-2 px-2 pt-1 text-center text-[11px] font-medium text-slate-500">
                        خيارات الرسالة
                    </div>

                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                        @click="emit('copy')"
                    >
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">📋</span>
                        نسخ النص
                    </button>

                    <button
                        type="button"
                        class="mt-1 flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                        @click="emit('forward')"
                    >
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700">↪</span>
                        تحويل لمحادثة أخرى
                    </button>

                    <template v-if="viewKind === 'team' && canManage">
                        <button
                            type="button"
                            class="mt-1 flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start text-sm font-semibold text-slate-800 transition hover:bg-slate-50"
                            @click="emit('start-edit')"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-700">✎</span>
                            تعديل
                        </button>
                        <button
                            type="button"
                            class="mt-1 flex w-full items-center gap-3 rounded-xl px-4 py-3 text-start text-sm font-semibold text-rose-700 transition hover:bg-rose-50"
                            @click="emit('remove')"
                        >
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-rose-50">🗑</span>
                            حذف
                        </button>
                    </template>

                    <button
                        type="button"
                        class="mt-2 w-full rounded-xl border border-slate-200 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        إلغاء
                    </button>

                    <div class="pb-[max(0.35rem,env(safe-area-inset-bottom))] sm:hidden" aria-hidden="true" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
