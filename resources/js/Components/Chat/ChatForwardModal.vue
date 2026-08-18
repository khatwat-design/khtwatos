<script setup>
import AppModalShell from '@/Components/AppModalShell.vue';
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
    <AppModalShell
        :open="open"
        size="md"
        :z-index="111"
        aria-label="تحويل الرسالة"
        @close="emit('close')"
    >
        <header class="app-modal-header !items-center">
            <div class="min-w-0 flex-1">
                <h3 class="app-modal-title">تحويل إلى محادثة</h3>
                <p class="app-modal-subtitle">ستظهر للطرف الآخر كرسالة محوّلة مع اسم المرسل الأصلي.</p>
                <input
                    v-model="filter"
                    type="search"
                    autocomplete="off"
                    placeholder="بحث عن محادثة…"
                    class="mt-3 block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                >
            </div>
        </header>

        <div class="app-modal-body app-modal-body--flush !px-2">
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

        <footer class="app-modal-footer">
            <button
                type="button"
                class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 sm:w-auto sm:min-w-[7rem]"
                @click="emit('close')"
            >
                إلغاء
            </button>
        </footer>
    </AppModalShell>
</template>
