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

const maxCampaignManagerClients = computed(() =>
    Math.max(1, ...((props.employees?.campaignManagers || []).map((row) => Number(row.clients || 0)))),
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
        tone: 'from-brand-500 to-brand-700',
        toneLight: 'bg-brand-100 text-brand-800',
        progress: Number(props.cards?.clients_total || 0)
            ? ratio(props.cards?.clients_leads || 0, props.cards?.clients_total || 1)
            : '0%',
    },
    {
        key: 'tasks',
        title: 'المهام',
        value: Number(props.cards?.tasks_total || 0),
        sub: `المتأخرة: ${props.cards?.tasks_overdue || 0}`,
        tone: 'from-brand-500 to-brand-700',
        toneLight: 'bg-brand-100 text-brand-800',
        progress: Number(props.cards?.tasks_total || 0)
            ? ratio(props.cards?.tasks_overdue || 0, props.cards?.tasks_total || 1)
            : '0%',
    },
    {
        key: 'meetings',
        title: 'الاجتماعات',
        value: Number(props.cards?.meetings_total || 0),
        sub: `القادمة: ${props.cards?.meetings_upcoming || 0}`,
        tone: 'from-brand-500 to-brand-700',
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
        tone: 'from-brand-500 to-brand-700',
        toneLight: 'bg-brand-100 text-brand-800',
        progress: Number(props.cards?.employees_total || 0)
            ? ratio(props.cards?.employees_bookable || 0, props.cards?.employees_total || 1)
            : '0%',
    },
]);

const meetingsCards = computed(() => [
    { key: 'scheduled', label: 'مجدولة', value: Number(props.meetings?.scheduled || 0), tone: 'bg-amber-100 text-amber-900 ring-amber-200' },
    { key: 'completed', label: 'منتهية', value: Number(props.meetings?.completed || 0), tone: 'bg-emerald-50 text-emerald-700 ring-emerald-100' },
    { key: 'canceled', label: 'ملغاة', value: Number(props.meetings?.canceled || 0), tone: 'bg-red-50 text-red-700 ring-red-100' },
    { key: 'today', label: 'اليوم', value: Number(props.meetings?.today || 0), tone: 'bg-slate-100 text-black ring-slate-200' },
    { key: 'internal', label: 'داخلية', value: Number(props.meetings?.internal || 0), tone: 'bg-neutral-100 text-neutral-700 ring-neutral-200' },
    { key: 'client', label: 'للعملاء', value: Number(props.meetings?.client || 0), tone: 'bg-neutral-100 text-neutral-700 ring-neutral-200' },
]);

const clientsTotal = computed(() =>
    (props.clientsByStage || []).reduce((sum, row) => sum + Number(row.count || 0), 0),
);

const stageChartColors = [
    '#E5484D',
    '#F25F63',
    '#CF3E43',
    '#AD3338',
    '#8F2E32',
    '#772C2F',
    '#F07A7D',
    '#D85A5E',
];

const stageDistribution = computed(() => {
    const total = Math.max(1, clientsTotal.value);
    return (props.clientsByStage || []).map((row, index) => {
        const count = Number(row.count || 0);
        const percent = Math.round((count / total) * 100);
        return {
            id: row.id,
            label: localizeStageName(row.label),
            count,
            percent,
            color: stageChartColors[index % stageChartColors.length],
        };
    });
});

const stageDonutSegments = computed(() => {
    const total = Math.max(1, clientsTotal.value);
    const radius = 42;
    const circumference = 2 * Math.PI * radius;
    let acc = 0;

    return stageDistribution.value.map((stage) => {
        const value = Number(stage.count || 0);
        const ratio = value / total;
        const dash = ratio * circumference;
        const segment = {
            ...stage,
            dashArray: `${dash} ${circumference - dash}`,
            dashOffset: -acc,
            circumference,
        };
        acc += dash;
        return segment;
    });
});

const topStage = computed(() => {
    if (!stageDistribution.value.length) {
        return null;
    }
    return [...stageDistribution.value].sort((a, b) => b.count - a.count)[0];
});

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

        <div class="home-page mx-auto max-w-7xl space-y-4 md:space-y-5">
            <div class="overflow-hidden rounded-3xl border border-white/15 bg-gradient-to-l from-slate-900/90 via-slate-900/75 to-brand-900/75 text-white shadow-2xl shadow-black/25 backdrop-blur-xl">
                <div class="grid gap-4 p-5 md:grid-cols-[1fr,auto] md:items-center">
                    <div>
                        <h2 class="text-lg font-black tracking-tight md:text-xl">لوحة التحكم الإدارية</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs md:min-w-64">
                        <div class="rounded-xl border border-white/15 bg-white/10 px-3 py-2 backdrop-blur-sm">
                            <p class="text-white/75">اليوم</p>
                            <p class="mt-1 font-semibold text-white">{{ props.meetings?.today || 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-white/15 bg-white/10 px-3 py-2 backdrop-blur-sm">
                            <p class="text-white/75">إنجاز الاجتماعات</p>
                            <p class="mt-1 font-semibold text-white">{{ meetingsCompletionRate }}%</p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-white/10 bg-black/20 px-5 py-2 text-xs text-white/70">
                    {{ todayDateLabel }}
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in kpiCards"
                    :key="card.key"
                    class="ui-card overflow-hidden transition-all duration-300 ease-out hover:scale-[1.02] hover:bg-white/80 hover:shadow-2xl hover:shadow-slate-900/15"
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
                            <p class="text-slate-600">{{ card.sub }}</p>
                            <span class="font-semibold text-slate-800">{{ card.progress }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-200/80">
                            <div class="h-full rounded-full bg-brand-500 transition-all duration-300 ease-out" :style="{ width: card.progress }" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="ui-card p-5 xl:col-span-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold text-slate-900">العملاء حسب المراحل</h3>
                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
                            الإجمالي: {{ clientsTotal }}
                        </span>
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-[220px,1fr]">
                        <div class="ui-card-soft flex flex-col items-center justify-center p-4">
                            <div class="relative h-32 w-32">
                                <svg class="h-32 w-32 -rotate-90">
                                    <circle
                                        cx="64"
                                        cy="64"
                                        r="42"
                                        stroke="rgba(255,255,255,0.14)"
                                        stroke-width="14"
                                        fill="none"
                                    />
                                    <circle
                                        v-for="seg in stageDonutSegments"
                                        :key="`seg-${seg.id}`"
                                        cx="64"
                                        cy="64"
                                        r="42"
                                        fill="none"
                                        stroke-width="14"
                                        stroke-linecap="round"
                                        :stroke="seg.color"
                                        :stroke-dasharray="seg.dashArray"
                                        :stroke-dashoffset="seg.dashOffset"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <p class="text-[11px] text-slate-500">العملاء</p>
                                    <p class="text-xl font-black text-slate-900">{{ clientsTotal }}</p>
                                </div>
                            </div>
                            <p v-if="topStage" class="mt-3 text-center text-xs text-slate-500">
                                أعلى مرحلة: <span class="font-semibold text-slate-800">{{ topStage.label }}</span>
                                ({{ topStage.percent }}%)
                            </p>
                        </div>

                        <div class="space-y-3">
                        <div v-for="row in stageDistribution" :key="`stage-${row.id}`">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-slate-700">{{ row.label }}</span>
                                <span class="font-semibold text-slate-900">{{ row.count }} ({{ row.percent }}%)</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200/70">
                                <div
                                    class="h-full rounded-full transition-all duration-300 ease-out"
                                    :style="{ width: `${row.percent}%`, backgroundColor: row.color }"
                                />
                            </div>
                        </div>
                        <p v-if="!clientsByStage?.length" class="text-center text-xs text-slate-500">
                            لا توجد بيانات مراحل عملاء حتى الآن.
                        </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="ui-card p-5">
                    <h3 class="text-sm font-bold text-slate-900">تحليلات الاجتماعات</h3>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div
                            v-for="card in meetingsCards"
                            :key="`meeting-${card.key}`"
                            class="rounded-2xl p-3 ring-1 transition-all duration-200 ease-out hover:scale-[1.02]"
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

                <div class="ui-card p-5">
                    <h3 class="text-sm font-bold text-slate-900">تحليلات الموظفين</h3>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                        <div class="rounded-2xl bg-neutral-50/90 p-3 text-center ring-1 ring-neutral-200/70 transition-all duration-200 ease-out hover:scale-[1.02]">
                            <p class="text-xs text-black">مدراء نظام</p>
                            <p class="mt-1 text-2xl font-black text-black">{{ employees.admins }}</p>
                        </div>
                        <div class="rounded-2xl bg-brand-50/90 p-3 text-center ring-1 ring-brand-200/70 transition-all duration-200 ease-out hover:scale-[1.02]">
                            <p class="text-xs text-black">قادة فرق</p>
                            <p class="mt-1 text-2xl font-black text-black">{{ employees.leads }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-100/90 p-3 text-center ring-1 ring-slate-200 transition-all duration-200 ease-out hover:scale-[1.02]">
                            <p class="text-xs text-black">موظفون</p>
                            <p class="mt-1 text-2xl font-black text-black">{{ employees.members }}</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div v-for="team in employees.byTeam" :key="`team-${team.id}`">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-slate-700">{{ team.name }}</span>
                                <span class="font-semibold text-slate-900">
                                    {{ team.employees }} • قادة: {{ team.leads }}
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-200/70">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-slate-900 transition-all" :style="{ width: pct(team.employees, maxTeamEmployees) }" />
                            </div>
                        </div>
                        <p v-if="!employees?.byTeam?.length" class="text-center text-xs text-slate-500">
                            لا توجد بيانات فرق موظفين حتى الآن.
                        </p>
                    </div>
                    <div class="mt-5 border-t border-slate-200/70 pt-4">
                        <h4 class="text-xs font-bold text-slate-900">تحليل مدراء الحملات (عدد العملاء)</h4>
                        <div class="mt-3 space-y-3">
                            <div v-for="manager in employees.campaignManagers || []" :key="`campaign-manager-${manager.id}`">
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-slate-700">{{ manager.name }}</span>
                                    <span class="font-semibold text-slate-900">
                                        {{ manager.clients }} عميل
                                    </span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-slate-200/70">
                                    <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-700 transition-all" :style="{ width: pct(manager.clients, maxCampaignManagerClients) }" />
                                </div>
                            </div>
                            <p v-if="!(employees?.campaignManagers || []).length" class="text-center text-xs text-slate-500">
                                لا يوجد مدراء حملات مضافون حتى الآن.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.home-page :deep(.bg-white),
.home-page :deep(.bg-white\/90),
.home-page :deep(.bg-white\/80),
.home-page :deep(.bg-white\/75),
.home-page :deep(.bg-white\/70),
.home-page :deep(.bg-white\/50),
.home-page :deep(.bg-slate-50),
.home-page :deep(.bg-slate-50\/80),
.home-page :deep(.bg-slate-100),
.home-page :deep(.bg-slate-100\/90),
.home-page :deep(.bg-neutral-50),
.home-page :deep(.bg-neutral-50\/90),
.home-page :deep(.bg-brand-50),
.home-page :deep(.bg-brand-50\/90),
.home-page :deep(.bg-amber-50),
.home-page :deep(.bg-emerald-50),
.home-page :deep(.bg-red-50) {
    color: #111111 !important;
}

.home-page :deep(.bg-white *),
.home-page :deep(.bg-white\/90 *),
.home-page :deep(.bg-white\/80 *),
.home-page :deep(.bg-white\/75 *),
.home-page :deep(.bg-white\/70 *),
.home-page :deep(.bg-white\/50 *),
.home-page :deep(.bg-slate-50 *),
.home-page :deep(.bg-slate-50\/80 *),
.home-page :deep(.bg-slate-100 *),
.home-page :deep(.bg-slate-100\/90 *),
.home-page :deep(.bg-neutral-50 *),
.home-page :deep(.bg-neutral-50\/90 *),
.home-page :deep(.bg-brand-50 *),
.home-page :deep(.bg-brand-50\/90 *),
.home-page :deep(.bg-amber-50 *),
.home-page :deep(.bg-emerald-50 *),
.home-page :deep(.bg-red-50 *) {
    color: #111111 !important;
}
</style>

