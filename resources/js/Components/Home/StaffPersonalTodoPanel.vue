<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const activeTab = ref('pending');

const pendingItems = computed(() => (props.items || []).filter((item) => !item.is_done));
const doneItems = computed(() =>
    (props.items || [])
        .filter((item) => item.is_done)
        .sort((a, b) => String(b.completed_at || '').localeCompare(String(a.completed_at || ''))),
);

const addForm = useForm({ title: '' });

function submitAdd() {
    if (!addForm.title.trim()) {
        return;
    }
    addForm.post(route('staff-personal-todos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('title');
            activeTab.value = 'pending';
        },
    });
}

function toggleDone(item) {
    router.patch(
        route('staff-personal-todos.update', item.id),
        { is_done: !item.is_done },
        { preserveScroll: true, onSuccess: () => { if (!item.is_done) activeTab.value = 'done'; } },
    );
}

function removeItem(item) {
    if (!confirm('حذف هذه المهمة من قائمتك؟')) {
        return;
    }
    router.delete(route('staff-personal-todos.destroy', item.id), { preserveScroll: true });
}

function formatDoneAt(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '';
    }
}
</script>

<template>
    <section class="ops-surface overflow-hidden p-4 sm:p-5">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="ops-section-title">قائمة المهام الشخصية</h3>
                <p class="mt-0.5 text-[11px] text-slate-500">مهامك الخاصة — عند الإنجاز تنتقل تلقائياً إلى تبويب «تم»</p>
            </div>
            <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-semibold" role="tablist">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'pending'"
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="activeTab === 'pending' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'pending'"
                >
                    قيد التنفيذ
                    <span
                        v-if="pendingItems.length"
                        class="ms-1 inline-flex min-w-[1.25rem] justify-center rounded-full bg-brand-100 px-1.5 text-[10px] text-brand-800"
                    >
                        {{ pendingItems.length }}
                    </span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'done'"
                    class="rounded-lg px-3 py-1.5 transition"
                    :class="activeTab === 'done' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'done'"
                >
                    تم
                    <span
                        v-if="doneItems.length"
                        class="ms-1 inline-flex min-w-[1.25rem] justify-center rounded-full bg-emerald-100 px-1.5 text-[10px] text-emerald-800"
                    >
                        {{ doneItems.length }}
                    </span>
                </button>
            </div>
        </div>

        <form class="mt-4 flex gap-2" @submit.prevent="submitAdd">
            <input
                v-model="addForm.title"
                type="text"
                maxlength="500"
                placeholder="أضف مهمة جديدة…"
                class="min-h-11 flex-1 rounded-xl border-slate-200 bg-slate-50/50 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
            />
            <button
                type="submit"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50"
                :disabled="addForm.processing || !addForm.title.trim()"
            >
                إضافة
            </button>
        </form>
        <p v-if="addForm.errors.title" class="mt-1 text-xs text-rose-600">{{ addForm.errors.title }}</p>

        <ul v-if="activeTab === 'pending' && pendingItems.length" class="mt-4 space-y-2" role="list">
            <li
                v-for="item in pendingItems"
                :key="`pt-${item.id}`"
                class="group flex items-start gap-3 rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-sm ring-1 ring-slate-100/80"
            >
                <button
                    type="button"
                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-slate-300 transition hover:border-emerald-500 hover:bg-emerald-50"
                    :aria-label="`إنجاز: ${item.title}`"
                    @click="toggleDone(item)"
                />
                <p class="min-w-0 flex-1 pt-0.5 text-sm font-medium text-slate-900">{{ item.title }}</p>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100"
                    aria-label="حذف"
                    @click="removeItem(item)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                    </svg>
                </button>
            </li>
        </ul>
        <p
            v-else-if="activeTab === 'pending'"
            class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 py-8 text-center text-sm text-slate-500"
        >
            لا مهام قيد التنفيذ — أضف مهمة من الأعلى.
        </p>

        <ul v-if="activeTab === 'done' && doneItems.length" class="mt-4 space-y-2" role="list">
            <li
                v-for="item in doneItems"
                :key="`dt-${item.id}`"
                class="group flex items-start gap-3 rounded-xl border border-emerald-100/80 bg-emerald-50/30 px-3 py-2.5"
            >
                <button
                    type="button"
                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-emerald-500 bg-emerald-500 text-white"
                    aria-label="إعادة للقائمة"
                    @click="toggleDone(item)"
                >
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path d="M5 12l5 5L19 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-600 line-through decoration-slate-400">{{ item.title }}</p>
                    <p v-if="item.completed_at" class="mt-0.5 text-[10px] text-emerald-700">
                        أُنجزت {{ formatDoneAt(item.completed_at) }}
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100"
                    aria-label="حذف"
                    @click="removeItem(item)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                    </svg>
                </button>
            </li>
        </ul>
        <p
            v-else-if="activeTab === 'done'"
            class="mt-6 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 py-8 text-center text-sm text-slate-500"
        >
            لا مهام مكتملة بعد.
        </p>
    </section>
</template>
