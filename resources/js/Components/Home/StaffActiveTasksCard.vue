<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    cards: { type: Object, default: () => ({}) },
});

const activeCount = computed(() => Number(props.cards?.tasks_assigned || 0));
const overdueCount = computed(() => Number(props.cards?.tasks_overdue || 0));

function taskLabel(task) {
    const title = String(task?.title || '').trim();
    return title || `مهمة #${task?.id || ''}`;
}

function taskHref(task) {
    return route('tasks.index', {
        team: task.team_slug || undefined,
        task: task.id,
        column: task.board_column_id || undefined,
    });
}
</script>

<template>
    <section
        class="ops-surface overflow-hidden shadow-sm"
        :class="overdueCount > 0 ? 'ring-1 ring-rose-200/80' : ''"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5"
            :class="overdueCount > 0 ? 'bg-rose-50/30' : 'bg-white'"
        >
            <div>
                <p class="ops-kicker">مهامي النشطة</p>
                <p class="mt-0.5 text-[11px] text-slate-500">اضغط على المهمة للانتقال إلى تبويب الفريق والعمود</p>
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

        <ul v-if="tasks?.length" class="divide-y divide-slate-100" role="list">
            <li v-for="t in tasks" :key="`active-task-${t.id}`">
                <Link
                    :href="taskHref(t)"
                    class="group flex items-center gap-3 px-4 py-3.5 transition sm:px-5"
                    :class="t.is_overdue ? 'hover:bg-rose-50/50' : 'hover:bg-brand-50/35'"
                >
                    <span
                        class="h-2 w-2 shrink-0 rounded-full"
                        :class="t.is_overdue ? 'bg-rose-500' : 'bg-brand-400'"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 text-sm font-semibold text-slate-900 group-hover:text-brand-900">
                        {{ taskLabel(t) }}
                    </span>
                    <svg
                        class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-brand-600"
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

        <p v-else class="px-4 py-10 text-center text-sm text-slate-500 sm:px-5">
            لا توجد مهام نشطة مسندة إليك.
        </p>
    </section>
</template>
