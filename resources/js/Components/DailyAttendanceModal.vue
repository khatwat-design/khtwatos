<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'checked-in']);

const page = usePage();
const submitting = ref(false);
const error = ref('');
const selectedTaskIds = ref([]);
const mood = ref('good');
const note = ref('');

const userName = computed(() => page.props.auth?.user?.name ?? '');
const openTasks = computed(() => Array.isArray(page.props.attendance?.open_tasks) ? page.props.attendance.open_tasks : []);
const totalTasks = computed(() => openTasks.value.length);
const overdueCount = computed(() => openTasks.value.filter((t) => t.is_overdue).length);

const todayFormatted = computed(() => {
    try {
        return new Date().toLocaleDateString('ar-IQ', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return '';
    }
});

const moodOptions = [
    { value: 'great', label: 'ممتاز', emoji: '🤩' },
    { value: 'good', label: 'جيد', emoji: '🙂' },
    { value: 'neutral', label: 'عادي', emoji: '😐' },
    { value: 'tired', label: 'متعب', emoji: '😴' },
    { value: 'low', label: 'مشغول', emoji: '🥺' },
];

function toggleTask(taskId) {
    const idx = selectedTaskIds.value.indexOf(taskId);
    if (idx >= 0) {
        selectedTaskIds.value.splice(idx, 1);
    } else {
        selectedTaskIds.value.push(taskId);
    }
}

function isTaskSelected(taskId) {
    return selectedTaskIds.value.includes(taskId);
}

function formatDueDate(iso) {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleDateString('ar-IQ', { day: 'numeric', month: 'short' });
    } catch {
        return null;
    }
}

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    error.value = '';
    try {
        await window.axios.post(
            route('attendance.check'),
            {
                mood: mood.value,
                note: note.value,
                selected_task_ids: selectedTaskIds.value,
            },
            { headers: { Accept: 'application/json' } },
        );
        emit('checked-in');
        emit('close');
        router.reload({ only: ['attendance'] });
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذّر تسجيل الحضور، يرجى المحاولة لاحقاً.';
    } finally {
        submitting.value = false;
    }
}

watch(
    () => props.open,
    (val) => {
        if (val) {
            error.value = '';
            selectedTaskIds.value = [];
        }
    },
);
</script>

<template>
    <div
        v-if="open"
        class="mobile-sheet-backdrop z-[120] !bg-slate-900/70 sm:items-center"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
    >
        <div
            class="mobile-sheet-panel mobile-sheet-panel--lg relative flex w-full max-w-2xl flex-col overflow-hidden p-0 shadow-2xl ring-1 ring-slate-200"
            @click.stop
        >
            <!-- Header -->
            <div class="shrink-0 border-b border-slate-200/80 bg-white px-5 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest text-slate-500">تسجيل الحضور اليومي</div>
                        <div class="mt-1 text-lg font-bold text-slate-900 sm:text-xl">
                            صباحك إنجاز، {{ userName || 'بطل' }} <span aria-hidden="true">👋</span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-slate-500">{{ todayFormatted }}</div>
                    </div>
                    <div class="hidden text-end sm:block">
                        <div class="text-[10px] text-slate-500">مهامك المفتوحة</div>
                        <div class="text-2xl font-bold text-slate-900">{{ totalTasks }}</div>
                        <div v-if="overdueCount" class="text-[10px] font-semibold text-rose-600">
                            ⚠️ {{ overdueCount }} متأخرة
                        </div>
                    </div>
                </div>
            </div>

            <!-- Body (scrollable) -->
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <!-- Mood -->
                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-800">كيف حالك مزاجياً؟</div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="opt in moodOptions"
                            :key="opt.value"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-all',
                                mood === opt.value
                                    ? 'border-amber-400 bg-amber-50 text-amber-900 ring-1 ring-amber-300 shadow-sm'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50',
                            ]"
                            @click="mood = opt.value"
                        >
                            <span class="text-base leading-none">{{ opt.emoji }}</span>
                            <span>{{ opt.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Today's tasks checklist -->
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="text-sm font-semibold text-slate-800">ماذا ستنجز اليوم؟</div>
                            <div class="text-[11px] text-slate-500">اختر مهامك من القائمة، وسننقلها لعمود "قيد التنفيذ" تلقائياً.</div>
                        </div>
                        <span v-if="selectedTaskIds.length" class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700">
                            {{ selectedTaskIds.length }} مختارة
                        </span>
                    </div>

                    <div v-if="totalTasks" class="space-y-1.5">
                        <button
                            v-for="task in openTasks"
                            :key="task.id"
                            type="button"
                            :class="[
                                'group flex w-full items-start gap-2.5 rounded-xl border px-3 py-2 text-start transition-all',
                                isTaskSelected(task.id)
                                    ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-300 shadow-sm'
                                    : task.is_overdue
                                      ? 'border-rose-200 bg-rose-50/60 hover:border-rose-300 hover:bg-rose-50'
                                      : task.is_in_progress
                                        ? 'border-amber-200 bg-amber-50/60 hover:border-amber-300 hover:bg-amber-50'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50',
                            ]"
                            @click="toggleTask(task.id)"
                        >
                            <span
                                :class="[
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-all',
                                    isTaskSelected(task.id)
                                        ? 'border-brand-600 bg-brand-600 text-white'
                                        : 'border-slate-300 bg-white text-transparent',
                                ]"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="truncate text-sm font-semibold text-slate-900">{{ task.title }}</div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <span
                                            v-if="task.is_in_progress"
                                            class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800"
                                            title="قيد التنفيذ"
                                        >
                                            قيد التنفيذ
                                        </span>
                                        <span
                                            v-else-if="task.column_name"
                                            class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600"
                                        >
                                            {{ task.column_name }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-slate-500">
                                    <span v-if="task.client_name">👤 {{ task.client_name }}</span>
                                    <span v-if="task.due_at" :class="task.is_overdue ? 'font-bold text-rose-700' : ''">
                                        🕒 {{ formatDueDate(task.due_at) }}{{ task.is_overdue ? ' · متأخرة' : '' }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-5 text-center text-xs text-slate-500"
                    >
                        لا توجد مهام مفتوحة مخوّلة لك حالياً. استمتع بيومك! 🎉
                    </div>
                </div>

                <!-- Optional note -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-800">ملاحظة للإدارة (اختياري)</div>
                        <span class="text-[10px] text-slate-400">{{ note.length }}/480</span>
                    </div>
                    <textarea
                        v-model="note"
                        rows="2"
                        maxlength="480"
                        placeholder="مثال: مغادرة مبكرة 3:30 لظرف عائلي"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    />
                </div>

                <div v-if="error" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ error }}
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t border-slate-200 bg-slate-50/80 px-5 py-3">
                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-[11px] text-slate-500">
                        سيبدأ احتساب وقت دوامك تلقائياً فور تأكيد الحضور.
                    </p>
                    <button
                        type="button"
                        :disabled="submitting"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-all hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ submitting ? 'جاري الحفظ...' : 'تأكيد الحضور وبدء الدوام' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
