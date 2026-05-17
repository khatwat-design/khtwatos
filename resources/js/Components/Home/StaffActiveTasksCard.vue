<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    cards: { type: Object, default: () => ({}) },
    firstOverdueTask: { type: Object, default: null },
});

const activeCount = computed(() => Number(props.cards?.tasks_assigned || 0));
const overdueCount = computed(() => Number(props.cards?.tasks_overdue || 0));

const overdueTasks = computed(() => (props.tasks || []).filter((t) => t.is_overdue));
const otherTasks = computed(() => (props.tasks || []).filter((t) => !t.is_overdue));
const nearestTask = computed(() => (props.tasks || [])[0] || null);

function formatDue(iso) {
    if (!iso) {
        return 'بدون موعد';
    }
    try {
        return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
    }
}

function taskHref(task) {
    return route('tasks.index', {
        team: task.team_slug || undefined,
        task: task.id,
    });
}
</script>

<template>
    <section
        class="ops-surface overflow-hidden shadow-sm"
        :class="overdueCount > 0 ? 'ring-1 ring-rose-200/80' : ''"
    >
        <div
            class="border-b border-slate-100 px-4 py-4 sm:px-5"
            :class="overdueCount > 0 ? 'bg-rose-50/30' : 'bg-white'"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="ops-kicker">مهامي النشطة</p>
                    <p class="mt-1 text-ops-meta sm:text-ops-body-sm">
                        اضغط على أي مهمة للانتقال إليها مباشرة في لوحة المهام
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold tabular-nums text-slate-800"
                    >
                        نشطة
                        <span class="text-base text-slate-900">{{ activeCount }}</span>
                    </span>
                    <span
                        v-if="overdueCount > 0"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-800"
                    >
                        متأخرة
                        <span class="text-base">{{ overdueCount }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div v-if="tasks?.length" class="divide-y divide-slate-100">
            <div v-if="overdueTasks.length" class="px-4 py-2 sm:px-5">
                <p class="text-[11px] font-bold text-rose-700">متأخرة — تحتاج متابعة</p>
            </div>
            <ul role="list">
                <li v-for="t in overdueTasks" :key="`task-od-${t.id}`">
                    <Link
                        :href="taskHref(t)"
                        class="group flex gap-3 px-4 py-3 transition hover:bg-rose-50/50 sm:px-5 sm:py-3.5"
                    >
                        <span
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-rose-500"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 group-hover:text-brand-900">{{ t.title }}</p>
                            <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                <span
                                    v-if="t.column_name"
                                    class="rounded-md bg-white px-1.5 py-0.5 font-medium ring-1 ring-slate-200/80"
                                >
                                    {{ t.column_name }}
                                </span>
                                <span v-if="t.team_name" class="text-slate-500">{{ t.team_name }}</span>
                                <span class="font-bold text-rose-700">متأخرة</span>
                                <span class="tabular-nums">{{ formatDue(t.due_at) }}</span>
                            </p>
                        </div>
                        <svg
                            class="mt-0.5 h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-[-2px] group-hover:text-brand-600"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </Link>
                </li>
            </ul>

            <div v-if="otherTasks.length && overdueTasks.length" class="px-4 py-2 sm:px-5">
                <p class="text-[11px] font-bold text-slate-600">مهامك المسندة</p>
            </div>
            <ul role="list">
                <li v-for="t in otherTasks" :key="`task-${t.id}`">
                    <Link
                        :href="taskHref(t)"
                        class="group flex gap-3 px-4 py-3 transition hover:bg-brand-50/35 sm:px-5 sm:py-3.5"
                    >
                        <span
                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-400"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 group-hover:text-brand-900">{{ t.title }}</p>
                            <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                <span
                                    v-if="t.column_name"
                                    class="rounded-md bg-slate-50 px-1.5 py-0.5 font-medium ring-1 ring-slate-200/80"
                                >
                                    {{ t.column_name }}
                                </span>
                                <span v-if="t.team_name">{{ t.team_name }}</span>
                                <span class="tabular-nums">{{ formatDue(t.due_at) }}</span>
                            </p>
                        </div>
                        <svg
                            class="mt-0.5 h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-[-2px] group-hover:text-brand-600"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </Link>
                </li>
            </ul>
        </div>

        <p
            v-else
            class="border-t border-slate-100 px-4 py-10 text-center text-sm text-slate-500 sm:px-5"
        >
            لا توجد مهام نشطة مسندة إليك — أحسنت!
        </p>

        <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/60 px-4 py-3 sm:px-5">
            <p class="w-full text-[11px] font-semibold text-slate-500 sm:w-auto sm:me-auto sm:self-center">إجراءات سريعة</p>
            <Link
                v-if="firstOverdueTask?.id"
                :href="taskHref(firstOverdueTask)"
                class="inline-flex min-h-9 items-center rounded-lg border border-rose-200 bg-white px-3 text-xs font-bold text-rose-800 transition hover:bg-rose-50"
            >
                أقدم مهمة متأخرة
            </Link>
            <Link
                :href="route('tasks.index')"
                class="inline-flex min-h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-900"
            >
                لوحة المهام
            </Link>
            <Link
                v-if="nearestTask"
                :href="taskHref(nearestTask)"
                class="inline-flex min-h-9 items-center rounded-lg bg-brand-600 px-3 text-xs font-bold text-white shadow-sm transition hover:bg-brand-700"
            >
                فتح أقرب مهمة
            </Link>
        </div>
    </section>
</template>
