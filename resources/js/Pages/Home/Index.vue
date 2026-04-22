<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    cards: Object,
    clientsByStage: Array,
    tasksByColumn: Array,
    meetings: Object,
    employees: Object,
});

const stageLabelMap = {
    new: 'جديد',
    lead: 'عميل محتمل',
    proposal: 'عرض سعر',
    negotiation: 'تفاوض',
    won: 'مغلق رابح',
    lost: 'مغلق خاسر',
    followup: 'متابعة',
    'follow-up': 'متابعة',
    qualified: 'مؤهل',
    unqualified: 'غير مؤهل',
};

function localizeStageName(label) {
    const key = String(label || '')
        .trim()
        .toLowerCase();

    return stageLabelMap[key] || label;
}

const maxStageCount = computed(() =>
    Math.max(1, ...(props.clientsByStage || []).map((row) => Number(row.count || 0))),
);

const maxTeamEmployees = computed(() =>
    Math.max(1, ...((props.employees?.byTeam || []).map((row) => Number(row.employees || 0)))),
);

function ratioNumber(value, total) {
    return Math.round((Number(value || 0) / Math.max(1, Number(total || 1))) * 100);
}

function pct(value, max) {
    return `${ratioNumber(value, max)}%`;
}

function ratio(value, total) {
    return `${ratioNumber(value, total)}%`;
}

const kpiCards = computed(() => [
    {
        key: 'clients',
        title: 'العملاء',
        value: Number(props.cards?.clients_total || 0),
        sub: `المحتملون: ${props.cards?.clients_leads || 0}`,
        tone: 'from-brand-600 to-brand-700',
        toneLight: 'bg-brand-50 text-brand-700',
        progress: Number(props.cards?.clients_total || 0)
            ? ratio(props.cards?.clients_leads || 0, props.cards?.clients_total || 1)
            : '0%',
    },
    {
        key: 'tasks',
        title: 'المهام',
        value: Number(props.cards?.tasks_total || 0),
        sub: `المتأخرة: ${props.cards?.tasks_overdue || 0}`,
        tone: 'from-slate-800 to-slate-900',
        toneLight: 'bg-slate-100 text-slate-700',
        progress: Number(props.cards?.tasks_total || 0)
            ? ratio(props.cards?.tasks_overdue || 0, props.cards?.tasks_total || 1)
            : '0%',
    },
    {
        key: 'meetings',
        title: 'الاجتماعات',
        value: Number(props.cards?.meetings_total || 0),
        sub: `القادمة: ${props.cards?.meetings_upcoming || 0}`,
        tone: 'from-brand-500 to-brand-600',
        toneLight: 'bg-brand-100 text-brand-800',
        progress: Number(props.cards?.meetings_total || 0)
            ? ratio(props.cards?.meetings_upcoming || 0, props.cards?.meetings_total || 1)
            : '0%',
    },
    {
        key: 'employees',
        title: 'الموظفون',
        value: Number(props.cards?.employees_total || 0),
        sub: `القابلون للحجز: ${props.cards?.employees_bookable || 0}`,
        tone: 'from-neutral-700 to-neutral-900',
        toneLight: 'bg-neutral-100 text-neutral-700',
        progress: Number(props.cards?.employees_total || 0)
            ? ratio(props.cards?.employees_bookable || 0, props.cards?.employees_total || 1)
            : '0%',
    },
]);

const meetingsCards = computed(() => [
    { key: 'scheduled', label: 'مجدولة', value: Number(props.meetings?.scheduled || 0), tone: 'bg-brand-50 text-brand-700 ring-brand-100' },
    { key: 'completed', label: 'منتهية', value: Number(props.meetings?.completed || 0), tone: 'bg-emerald-50 text-emerald-700 ring-emerald-100' },
    { key: 'canceled', label: 'ملغاة', value: Number(props.meetings?.canceled || 0), tone: 'bg-red-50 text-red-700 ring-red-100' },
    { key: 'today', label: 'اليوم', value: Number(props.meetings?.today || 0), tone: 'bg-slate-100 text-slate-800 ring-slate-200' },
    { key: 'internal', label: 'داخلية', value: Number(props.meetings?.internal || 0), tone: 'bg-neutral-100 text-neutral-700 ring-neutral-200' },
    { key: 'client', label: 'للعملاء', value: Number(props.meetings?.client || 0), tone: 'bg-brand-100 text-brand-800 ring-brand-200' },
]);

const clientsTotal = computed(() =>
    (props.clientsByStage || []).reduce((sum, row) => sum + Number(row.count || 0), 0),
);

const meetingsCompletionRate = computed(() =>
    ratioNumber(props.meetings?.completed || 0, props.cards?.meetings_total || 0),
);

const todayDateLabel = computed(() =>
    new Date().toLocaleDateString('ar-SA', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }),
);
</script>

<template>
    <Head title="الرئيسية" />

    <AuthenticatedLayout>
        <template #title>الرئيسية</template>

        <div class="mx-auto max-w-7xl space-y-4 md:space-y-5">
            <div class="overflow-hidden rounded-2xl bg-gradient-to-l from-black via-neutral-900 to-brand-900 text-white shadow-lg ring-1 ring-black/10">
                <div class="grid gap-4 p-5 md:grid-cols-[1fr,auto] md:items-center">
                    <div>
                        <h2 class="text-lg font-black md:text-xl">لوحة التحكم الإدارية</h2>
                        <p class="mt-1 text-sm text-neutral-200">
                            متابعة موحدة للأداء اليومي: العملاء، المهام، الاجتماعات، والموظفين ضمن نفس الهوية.
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs md:min-w-64">
                        <div class="rounded-lg bg-white/10 px-3 py-2 backdrop-blur-sm">
                            <p class="text-neutral-300">اليوم</p>
                            <p class="mt-1 font-semibold text-white">{{ props.meetings?.today || 0 }}</p>
                        </div>
                        <div class="rounded-lg bg-white/10 px-3 py-2 backdrop-blur-sm">
                            <p class="text-neutral-300">إنجاز الاجتماعات</p>
                            <p class="mt-1 font-semibold text-white">{{ meetingsCompletionRate }}%</p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-white/10 bg-black/20 px-5 py-2 text-xs text-neutral-300">
                    {{ todayDateLabel }}
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in kpiCards"
                    :key="card.key"
                    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="bg-gradient-to-r px-4 py-3 text-white" :class="card.tone">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold">{{ card.title }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="card.toneLight">
                                مؤشر
                            </span>
                        </div>
                        <p class="mt-1 text-2xl font-black">{{ card.value }}</p>
                    </div>
                    <div class="p-4">
                        <div class="mb-2 flex items-center justify-between text-xs">
                            <p class="text-gray-600">{{ card.sub }}</p>
                            <span class="font-semibold text-gray-800">{{ card.progress }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-brand-600" :style="{ width: card.progress }" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 xl:col-span-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-gray-900">العملاء حسب المراحل</h3>
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
                            الإجمالي: {{ clientsTotal }}
                        </span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="row in clientsByStage" :key="`stage-${row.id}`">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-gray-700">{{ localizeStageName(row.label) }}</span>
                                <span class="font-semibold text-gray-900">{{ row.count }} ({{ pct(row.count, maxStageCount) }})</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-700 transition-all" :style="{ width: pct(row.count, maxStageCount) }" />
                            </div>
                        </div>
                        <p v-if="!clientsByStage?.length" class="text-center text-xs text-gray-500">
                            لا توجد بيانات مراحل عملاء حتى الآن.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-sm font-bold text-gray-900">تحليلات الاجتماعات</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div
                            v-for="card in meetingsCards"
                            :key="`meeting-${card.key}`"
                            class="rounded-xl p-3 ring-1"
                            :class="card.tone"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium">{{ card.label }}</span>
                                <span class="h-2 w-2 rounded-full bg-current/60" />
                            </div>
                            <p class="mt-1 text-2xl font-black tabular-nums">{{ card.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 class="text-sm font-bold text-gray-900">تحليلات الموظفين</h3>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                        <div class="rounded-xl bg-neutral-50 p-3 text-center ring-1 ring-neutral-100">
                            <p class="text-xs text-gray-500">مدراء نظام</p>
                            <p class="mt-1 text-2xl font-black text-gray-900">{{ employees.admins }}</p>
                        </div>
                        <div class="rounded-xl bg-brand-50 p-3 text-center ring-1 ring-brand-100">
                            <p class="text-xs text-gray-500">قادة فرق</p>
                            <p class="mt-1 text-2xl font-black text-brand-800">{{ employees.leads }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-100 p-3 text-center ring-1 ring-slate-200">
                            <p class="text-xs text-gray-500">موظفون</p>
                            <p class="mt-1 text-2xl font-black text-slate-800">{{ employees.members }}</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="team in employees.byTeam" :key="`team-${team.id}`">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-gray-700">{{ team.name }}</span>
                                <span class="font-semibold text-gray-900">
                                    {{ team.employees }} • قادة: {{ team.leads }}
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-slate-900 transition-all" :style="{ width: pct(team.employees, maxTeamEmployees) }" />
                            </div>
                        </div>
                        <p v-if="!employees?.byTeam?.length" class="text-center text-xs text-gray-500">
                            لا توجد بيانات فرق موظفين حتى الآن.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

