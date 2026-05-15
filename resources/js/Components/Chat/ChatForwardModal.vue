<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    targets: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'select']);

const filter = ref('');

const filteredTargets = computed(() => {
    const kw = filter.value.trim().toLowerCase();
    if (!kw) {
        return props.targets;
    }
    return (props.targets || []).filter((t) => String(t.label || '').toLowerCase().includes(kw));
});
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
                v-if="open"
                class="fixed inset-0 z-[111] flex items-end justify-center bg-slate-950/55 p-2 backdrop-blur-[2px] sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-label="تحويل الرسالة"
                @click.self="emit('close')"
            >
                <div
                    class="flex max-h-[min(85vh,560px)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    dir="rtl"
                    @click.stop
                >
                    <div class="shrink-0 border-b border-slate-200 px-4 py-3">
                        <h3 class="text-base font-bold text-slate-900">تحويل إلى محادثة</h3>
                        <p class="mt-0.5 text-xs text-slate-500">ستظهر للطرف الآخر كرسالة محوّلة مع اسم المرسل الأصلي.</p>
                        <input
                            v-model="filter"
                            type="search"
                            autocomplete="off"
                            placeholder="بحث عن محادثة…"
                            class="mt-3 block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                        >
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                        <button
                            v-for="target in filteredTargets"
                            :key="target.key"
                            type="button"
                            class="mb-1 flex w-full items-center gap-3 rounded-xl px-3 py-3 text-start transition hover:bg-slate-50 disabled:opacity-50"
                            :disabled="processing"
                            @click="emit('select', target)"
                        >
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold text-white"
                                :class="target.badgeClass"
                            >
                                {{ target.initial }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ target.label }}</span>
                                <span class="mt-0.5 block text-[11px] text-slate-500">{{ target.hint }}</span>
                            </span>
                        </button>
                        <p v-if="!filteredTargets.length" class="px-3 py-8 text-center text-sm text-slate-500">
                            لا توجد محادثات أخرى متاحة للتحويل.
                        </p>
                    </div>

                    <div class="shrink-0 border-t border-slate-100 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
                        <button
                            type="button"
                            class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"
                            @click="emit('close')"
                        >
                            إلغاء
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
