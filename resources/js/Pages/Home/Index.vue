<script setup>
import StaffPersonalTodoPanel from '@/Components/Home/StaffPersonalTodoPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    dashboard_mode: {
        type: String,
        default: 'admin',
    },
    staff: {
        type: Object,
        default: null,
    },
    staff_personal_todos: { type: Array, default: () => [] },
    outside_metrics: Object,
    cards: Object,
    clientsByStage: Array,
    tasksByColumn: Array,
    meetings: Object,
    employees: Object,
});

function formatStaffDateTime(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return '—';
    }
}

function staffTaskHref(task) {
    return route('tasks.index', {
        team: task.team_slug || undefined,
        task: task.id,
    });
}

/** بطاقات KPI للموظف (قيم من staff.cards فقط) */
const staffKpiCards = computed(() => {
    const c = props.staff?.cards || {};
    const cards = [
        {
            key: 'tasks',
            title: 'مهامي النشطة',
            value: Number(c.tasks_assigned || 0),
            sub: `متأخرة: ${Number(c.tasks_overdue || 0)}`,
            route: 'tasks.index',
        },
        {
            key: 'meetings',
            title: 'اجتماعات قادمة',
            value: Number(c.meetings_upcoming || 0),
            sub: 'مجدولة لك كمضيف أو مدعو',
        },
        {
            key: 'clients_am',
            title: 'عملاء (مسؤول حساب)',
            value: Number(c.clients_account_manager || 0),
            sub: 'مرتبطون بك كمسؤول حساب',
        },
        {
            key: 'clients_cm',
            title: 'عملاء (مدير حملات)',
            value: Number(c.clients_campaign_manager || 0),
            sub: 'مرتبطون بك كمدير حملات',
        },
        {
            key: 'outside',
            title: 'الخارج — غير مقروء',
            value: Number(c.outside_unread_assigned || 0),
            sub: 'محادثات موجهة إليك بانتظار القراءة',
        },
    ];

    if (props.staff?.is_meta_leads_rep) {
        cards.unshift(
            {
                key: 'meta_calls',
                title: 'مكالمات ليدز قادمة',
                value: Number(c.meta_calls_upcoming || 0),
                sub: 'مواعيد مكالمات ميتا مسندة إليك',
                route: 'goods.index',
                routeParams: { tab: 'meta_leads', meta_view: 'upcoming_calls' },
            },
            {
                key: 'meta_leads_today',
                title: 'ليدز ميتا اليوم',
                value: Number(c.meta_leads_today || 0),
                sub: 'ليدز جديدة اليوم مسندة إليك',
                route: 'goods.index',
                routeParams: { tab: 'meta_leads' },
            },
        );
    }

    return cards;
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

function truncateChartLabel(name, maxLen = 14) {
    const s = String(name || '').trim();
    if (s.length <= maxLen) {
        return s;
    }
    return `${s.slice(0, maxLen - 1)}…`;
}

/** نقطة شدّة تشغيلية (خطر / تحذير / معلومات / سليم) — ألوان دلالية فقط */
function attentionKindDotClass(kind) {
    const map = {
        danger: 'bg-rose-600',
        warning: 'bg-amber-500',
        info: 'bg-sky-600',
        success: 'bg-emerald-600',
    };
    return map[kind] || 'bg-slate-400';
}

function attentionBorderClassForItems(items) {
    const list = items || [];
    if (!list.length) {
        return 'border-s-slate-200';
    }
    if (list.some((i) => i.kind === 'danger')) {
        return 'border-s-rose-600';
    }
    if (list.some((i) => i.kind === 'warning')) {
        return 'border-s-amber-500';
    }
    if (list.some((i) => i.kind === 'info')) {
        return 'border-s-sky-600';
    }
    return 'border-s-emerald-600';
}

const taskColumnChart = computed(() => {
    const rows = [...(props.tasksByColumn || [])];
    const n = rows.length;
    if (!n) {
        return {
            bars: [],
            yTicks: [],
            W: 400,
            H: 320,
            padL: 48,
            padT: 34,
            innerH: 0,
            padB: 112,
            max: 0,
        };
    }

    const counts = rows.map((r) => Number(r.count || 0));
    const max = Math.max(1, ...counts);
    const W = Math.min(960, Math.max(400, 58 * n + 128));
    const H = 380;
    const padL = 48;
    const padR = 16;
    const padT = 34;
    const padB = 112;
    const innerW = W - padL - padR;
    const innerH = H - padT - padB;
    const gap = Math.min(12, Math.max(4, Math.floor(innerW / Math.max(n, 1) / 5)));
    const barW = (innerW - gap * Math.max(0, n - 1)) / n;
    const labelMaxLen = n > 14 ? 12 : n > 10 ? 16 : 22;
    const labelAngle = n > 8 ? -38 : n > 5 ? -28 : 0;
    const labelFontSize = n > 12 ? 10 : 11;

    const bars = rows.map((col, i) => {
        const cnt = Number(col.count || 0);
        const h = (cnt / max) * innerH;
        const x = padL + i * (barW + gap);
        const y = padT + innerH - h;
        const cx = x + barW / 2;
        const labelY = padT + innerH + (labelAngle ? 14 : 18);
        return {
            id: col.id,
            name: col.name,
            label: truncateChartLabel(col.name, labelMaxLen),
            count: cnt,
            x,
            y,
            w: barW,
            h: Math.max(h, cnt > 0 ? 5 : 0),
            cx,
            labelY,
            labelAngle,
            labelFontSize,
        };
    });

    const step = max <= 4 ? 1 : Math.max(1, Math.ceil(max / 4));
    const yTicks = [];
    for (let t = 0; t <= max; t += step) {
        yTicks.push(t);
    }
    if (yTicks[yTicks.length - 1] !== max) {
        yTicks.push(max);
    }

    return { bars, yTicks, W, H, padL, padT, innerH, padB, max };
});

/** Bar width for comparing a value to a fixed maximum (e.g. largest team). */
function widthPctOfMax(value, max) {
    const m = Math.max(1, Number(max) || 1);
    const v = Math.max(0, Number(value || 0));
    return `${Math.min(100, Math.round((v / m) * 1000) / 10)}%`;
}

function ratioNumber(value, total) {
    return Math.round((Number(value || 0) / Math.max(1, Number(total || 1))) * 100);
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
        progress: Number(props.cards?.clients_total || 0)
            ? ratio(props.cards?.clients_leads || 0, props.cards?.clients_total || 1)
            : '0%',
    },
    {
        key: 'tasks',
        title: 'المهام',
        value: Number(props.cards?.tasks_total || 0),
        sub: `المتأخرة: ${props.cards?.tasks_overdue || 0}`,
        progress: Number(props.cards?.tasks_total || 0)
            ? ratio(props.cards?.tasks_overdue || 0, props.cards?.tasks_total || 1)
            : '0%',
    },
    {
        key: 'meetings',
        title: 'الاجتماعات',
        value: Number(props.cards?.meetings_total || 0),
        sub: `القادمة: ${props.cards?.meetings_upcoming || 0}`,
        progress: Number(props.cards?.meetings_total || 0)
            ? ratio(props.cards?.meetings_upcoming || 0, props.cards?.meetings_total || 1)
            : '0%',
    },
    {
        key: 'employees',
        title: 'الموظفون',
        value: Number(props.cards?.employees_total || 0),
        sub: `القابلون للحجز: ${props.cards?.employees_bookable || 0}`,
        progress: Number(props.cards?.employees_total || 0)
            ? ratio(props.cards?.employees_bookable || 0, props.cards?.employees_total || 1)
            : '0%',
    },
]);

function meetingCardDotClass(kind) {
    const map = {
        warning: 'bg-amber-500',
        success: 'bg-emerald-600',
        danger: 'bg-rose-600',
        neutral: 'bg-slate-400',
        info: 'bg-sky-600',
    };
    return map[kind] || 'bg-slate-400';
}

const meetingsCards = computed(() => [
    { key: 'scheduled', label: 'مجدولة', value: Number(props.meetings?.scheduled || 0), kind: 'warning' },
    { key: 'completed', label: 'منتهية', value: Number(props.meetings?.completed || 0), kind: 'success' },
    { key: 'canceled', label: 'ملغاة', value: Number(props.meetings?.canceled || 0), kind: 'danger' },
    { key: 'today', label: 'اليوم', value: Number(props.meetings?.today || 0), kind: 'neutral' },
    { key: 'internal', label: 'داخلية', value: Number(props.meetings?.internal || 0), kind: 'neutral' },
    { key: 'client', label: 'للعملاء', value: Number(props.meetings?.client || 0), kind: 'info' },
]);

const clientsTotal = computed(() =>
    (props.clientsByStage || []).reduce((sum, row) => sum + Number(row.count || 0), 0),
);

const stageChartColors = [
    'rgb(51 65 85)',
    'rgb(71 85 105)',
    'rgb(100 116 139)',
    'rgb(148 163 184)',
    'rgb(190 18 60)',
    'rgb(120 113 108)',
    'rgb(87 83 78)',
    'rgb(168 162 158)',
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
        const ratioVal = value / total;
        const dash = ratioVal * circumference;
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

/** لقطة إدارية: ما يستحق الانتباه من نفس الـ props (بدون تغيير API) */
const adminAttentionItems = computed(() => {
    const items = [];
    const overdue = Number(props.cards?.tasks_overdue || 0);
    if (overdue > 0) {
        items.push({
            kind: 'danger',
            title: 'مهام متأخرة',
            detail:
                overdue === 1
                    ? 'مهمة واحدة تجاوزت الموعد ولم تُغلق في عمود غير «تم».'
                    : `${overdue} مهام تجاوزت الموعد ولم تُغلق بعد.`,
            route: 'tasks.index',
            action_label: 'المهام',
        });
    }
    const failed = Number(props.outside_metrics?.failed_last_7_days ?? 0);
    if (failed > 0) {
        items.push({
            kind: 'danger',
            title: 'فشل إرسال في الخارج',
            detail:
                failed === 1
                    ? 'فشل إرسال واحد خلال آخر ٧ أيام — راجع الرسائل والقنوات.'
                    : `${failed} فشل إرسال خلال آخر ٧ أيام — يستحق المراجعة الفورية.`,
            route: 'outside.index',
            action_label: 'الخارج',
        });
    }
    const leads = Number(props.cards?.clients_leads || 0);
    const nonLead = Number(props.cards?.clients_total || 0);
    const pipelineApprox = nonLead + leads;
    if (pipelineApprox > 0 && leads > 0) {
        const leadRatio = leads / pipelineApprox;
        if (leadRatio >= 0.35) {
            items.push({
                kind: 'warning',
                title: 'وزن عالٍ للعملاء المحتملين',
                detail: `${leads} عميلاً في مرحلة «عميل محتمل» من إجمالي تقريبي ${pipelineApprox} في التوزيع الحالي.`,
                route: 'clients.index',
                action_label: 'العملاء',
            });
        }
    }
    const ts = topStage.value;
    if (ts && clientsTotal.value > 0) {
        const labelLower = String(ts.label || '').toLowerCase();
        const isTerminal =
            labelLower.includes('مغلق') ||
            labelLower.includes('won') ||
            labelLower.includes('lost') ||
            labelLower.includes('رابح') ||
            labelLower.includes('خاسر');
        if (!isTerminal && ts.percent >= 32) {
            items.push({
                kind: 'info',
                title: 'ازدحام في مرحلة المسار',
                detail: `أعلى تجمّع حاليًا في «${ts.label}» (${ts.percent}٪ من العملاء في المسار).`,
                route: 'clients.index',
                action_label: 'المسار',
            });
        }
    }
    if (items.length === 0) {
        items.push({
            kind: 'success',
            title: 'لا تنبيهات تشغيلية بارزة',
            detail: 'لا مهام متأخرة في لقطة البيانات الحالية، ولا فشل إرسال حديث في الخارج ضمن العتبات المعروضة.',
            route: null,
            action_label: null,
        });
    }
    return items.slice(0, 6);
});

const attentionBorderClass = computed(() => {
    if (props.dashboard_mode === 'staff' && props.staff) {
        return attentionBorderClassForItems(props.staff.priority_followups || []);
    }
    return attentionBorderClassForItems(adminAttentionItems.value);
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

const outsideMetricTiles = computed(() => [
    {
        key: 'total',
        label: 'الإجمالي',
        hint: 'كل المحادثات',
        value: props.outside_metrics?.total_conversations ?? 0,
        wrapClass: 'border-slate-200 bg-slate-50/60',
        labelClass: 'text-slate-600',
        valueClass: 'text-slate-900',
    },
    {
        key: 'new',
        label: 'جديد',
        hint: 'لم تُغلق بعد',
        value: props.outside_metrics?.new_conversations ?? 0,
        wrapClass: 'border-slate-200 bg-white',
        labelClass: 'text-slate-600',
        valueClass: 'text-slate-900',
    },
    {
        key: 'closed',
        label: 'مغلقة',
        hint: 'مكتملة',
        value: props.outside_metrics?.closed_conversations ?? 0,
        wrapClass: 'border-slate-200 bg-slate-50/40',
        labelClass: 'text-slate-600',
        valueClass: 'text-slate-900',
    },
    {
        key: 'out7',
        label: 'صادر ٧ أيام',
        hint: 'رسائل صادرة',
        value: props.outside_metrics?.outbound_last_7_days ?? 0,
        wrapClass: 'border-slate-200 bg-white',
        labelClass: 'text-slate-600',
        valueClass: 'text-slate-900',
    },
    {
        key: 'fail7',
        label: 'فشل إرسال ٧ أيام',
        hint: 'يستحق مراجعة',
        value: props.outside_metrics?.failed_last_7_days ?? 0,
        wrapClass: 'border-rose-200 bg-rose-50/50',
        labelClass: 'text-rose-900',
        valueClass: 'text-rose-950',
    },
]);
</script>

<template>
    <Head title="الرئيسية" />

    <AuthenticatedLayout>
        <template #title>الرئيسية</template>

        <div
            v-if="dashboard_mode === 'staff' && staff"
            data-tour="home-dashboard"
            class="staff-home mx-auto max-w-6xl space-y-5 pb-6 md:space-y-6 md:pb-8"
        >
            <!-- بطاقة ترحيب: نفس لغة التصميم العامة (أبيض + ظلال خفيفة + لمسة brand) -->
            <section class="ops-surface overflow-hidden">
                <div class="relative border-b border-slate-100 px-4 pb-4 pt-4 sm:px-5 sm:pb-5 sm:pt-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="ops-kicker ops-kicker--accent">
                                مساحة عملك
                            </p>
                            <h2 class="mt-2 text-ops-title-xl font-semibold tracking-tight text-slate-900 sm:text-[1.35rem]">
                                مرحبًا، {{ $page.props.auth?.user?.name }}
                            </h2>
                            <p class="ops-section-lead mt-2 max-w-prose">
                                <span class="font-semibold text-slate-800">{{ staff.role_label }}</span>
                                <template v-if="staff.teams?.length">
                                    <span class="text-slate-400"> · </span>
                                    <span class="text-slate-600">الفِرق التي تنضوي تحتها</span>
                                </template>
                            </p>
                        </div>
                        <div
                            class="hidden shrink-0 rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-center sm:block"
                        >
                            <p class="ops-kicker">اليوم</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">
                                {{ new Date().toLocaleDateString('ar-SA', { day: 'numeric', month: 'short' }) }}
                            </p>
                        </div>
                    </div>
                    <div v-if="staff.teams?.length" class="mt-5 flex flex-wrap gap-2">
                        <span
                            v-for="(t, idx) in staff.teams"
                            :key="`team-chip-${idx}-${t.slug}`"
                            class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[12px] font-semibold text-slate-800"
                        >
                            {{ t.name }}
                            <span
                                v-if="t.is_lead"
                                class="rounded-lg bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm"
                            >
                                قائد فريق
                            </span>
                        </span>
                    </div>
                </div>
            </section>

            <!-- أولوية تشغيلية: يُقرأ قبل أي KPI -->
            <section
                class="ops-surface overflow-hidden border-s-4 bg-white p-0 shadow-sm"
                :class="attentionBorderClass"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                    <div>
                        <h3 class="ops-section-title-lg">مركز الانتباه</h3>
                        <p class="ops-section-lead mt-0.5 text-ops-meta sm:text-ops-body-sm">
                            ما يحتاج إجراءً أو متابعة من بياناتك المباشرة في النظام.
                        </p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-ops-meta font-semibold text-slate-600">
                        مباشر
                    </span>
                </div>
                <ul v-if="staff.priority_followups?.length" class="divide-y divide-slate-100" role="list">
                    <li v-for="(item, pi) in staff.priority_followups" :key="`pf-${pi}-${item.title}`">
                        <div class="flex gap-3 px-4 py-3 sm:items-center sm:justify-between sm:gap-4 sm:px-5 sm:py-3.5">
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full sm:mt-0"
                                :class="attentionKindDotClass(item.kind)"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-ops-body-md font-semibold text-slate-900">{{ item.title }}</p>
                                <p class="mt-0.5 text-ops-body-sm leading-relaxed text-slate-600">{{ item.detail }}</p>
                            </div>
                            <Link
                                v-if="item.route && item.action_label"
                                :href="route(item.route)"
                                class="shrink-0 self-center rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-ops-label font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-50"
                            >
                                {{ item.action_label }}
                            </Link>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- KPIs: سطح واحد لتقليل تنافس البطاقات -->
            <section class="ops-surface overflow-hidden p-0 shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                    <p class="ops-kicker">مؤشرات مساحة العمل</p>
                    <p class="ops-section-lead mt-0.5 text-ops-meta">أرقام لحظية لما يخصك مباشرةً</p>
                </div>
                <div
                    class="grid grid-cols-2 divide-x divide-slate-100 divide-y divide-slate-100 sm:grid-cols-2 lg:grid-cols-5 lg:divide-y-0"
                >
                    <component
                        :is="card.route ? Link : 'article'"
                        v-for="card in staffKpiCards"
                        :key="`stk-${card.key}`"
                        :href="card.route ? route(card.route, card.routeParams || {}) : undefined"
                        class="min-h-[6.5rem] px-4 py-3 sm:min-h-[6.75rem] sm:px-4 sm:py-4"
                        :class="[
                            card.key === 'tasks' && Number(staff.cards?.tasks_overdue || 0) > 0
                                ? 'bg-rose-50/25'
                                : 'bg-white',
                            card.route ? 'block transition hover:bg-brand-50/40' : '',
                        ]"
                    >
                        <p class="text-ops-label font-semibold text-slate-500">{{ card.title }}</p>
                        <p class="mt-1.5 text-2xl font-semibold tabular-nums text-slate-900 sm:text-[1.65rem]">{{ card.value }}</p>
                        <p class="mt-1 text-ops-meta leading-snug text-slate-500 sm:text-ops-body-sm">{{ card.sub }}</p>
                    </component>
                </div>
            </section>

            <StaffPersonalTodoPanel :items="staff_personal_todos" />

            <section class="grid gap-4 lg:grid-cols-2">
                <div
                    class="ops-surface p-4 sm:p-5"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="ops-section-title">مهامي</h3>
                            <p class="mt-0.5 text-[11px] text-slate-500">اضغط على مهمة للانتقال إليها في لوحة المهام</p>
                        </div>
                        <Link
                            :href="route('tasks.index')"
                            class="rounded-lg px-2 py-1 text-[11px] font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-900"
                        >
                            لوحة المهام
                        </Link>
                    </div>
                    <ul v-if="staff.recent_tasks?.length" class="mt-4 space-y-2" role="list">
                        <li v-for="t in staff.recent_tasks" :key="`rt-${t.id}`">
                            <Link
                                :href="staffTaskHref(t)"
                                class="group block rounded-xl border px-3 py-2.5 transition"
                                :class="
                                    t.is_overdue
                                        ? 'border-rose-200/80 bg-rose-50/40 hover:border-rose-300 hover:bg-rose-50/70'
                                        : 'border-slate-100 bg-slate-50/50 hover:border-brand-300/60 hover:bg-brand-50/35'
                                "
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <p class="min-w-0 flex-1 text-[13px] font-semibold text-slate-900 group-hover:text-brand-900">
                                        {{ t.title }}
                                    </p>
                                    <svg
                                        class="mt-0.5 h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-brand-600"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                    <span
                                        v-if="t.column_name"
                                        class="rounded-md bg-white px-1.5 py-0.5 font-medium text-slate-600 ring-1 ring-slate-200/80"
                                    >
                                        {{ t.column_name }}
                                    </span>
                                    <span v-if="t.is_overdue" class="font-bold text-rose-700">متأخرة</span>
                                    <span class="tabular-nums">{{ formatStaffDateTime(t.due_at) }}</span>
                                </p>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 py-8 text-center text-sm text-slate-500">
                        لا توجد مهام مسندة إليك حاليًا.
                    </p>
                </div>

                <div
                    class="ops-surface p-4 sm:p-5"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-4">
                        <h3 class="ops-section-title">اجتماعاتك القادمة</h3>
                        <Link
                            :href="route('meetings.index')"
                            class="rounded-lg px-2 py-1 text-[11px] font-bold text-brand-700 transition hover:bg-brand-50 hover:text-brand-900"
                        >
                            الجدول الكامل
                        </Link>
                    </div>
                    <ul v-if="staff.upcoming_meetings?.length" class="mt-4 space-y-2" role="list">
                        <li v-for="m in staff.upcoming_meetings" :key="`um-${m.id}`">
                            <Link
                                :href="route('meetings.edit', m.id)"
                                class="block rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-2.5 transition hover:border-brand-300/60 hover:bg-brand-50/35"
                            >
                                <p class="text-[13px] font-semibold text-slate-900">{{ m.title }}</p>
                                <p class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                    <span class="tabular-nums font-medium text-slate-600">{{ formatStaffDateTime(m.start_at) }}</span>
                                    <span v-if="m.client_name">· {{ m.client_name }}</span>
                                    <span
                                        v-if="m.is_host"
                                        class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-900 ring-1 ring-amber-200/80"
                                    >
                                        مضيف
                                    </span>
                                </p>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 py-8 text-center text-sm text-slate-500">
                        لا توجد اجتماعات مجدولة قادمة لك.
                    </p>
                </div>
            </section>
        </div>

        <div
            v-else
            data-tour="home-dashboard"
            class="home-dashboard mx-auto max-w-7xl space-y-5 pb-4 md:space-y-6 md:pb-6 lg:max-w-6xl lg:space-y-4 lg:pb-5"
        >
            <!-- نبض تنفيذي -->
            <section class="ops-surface overflow-hidden">
                <div class="relative grid gap-4 p-4 md:grid-cols-[1fr_auto] md:items-center md:gap-5 md:p-5 lg:gap-4 lg:p-4">
                    <div class="min-w-0">
                        <p class="ops-kicker ops-kicker--accent lg:text-ops-meta">
                            نظرة تشغيلية
                        </p>
                        <h2 class="mt-1.5 text-ops-title-xl font-semibold leading-tight tracking-tight text-slate-900 md:text-[1.35rem]">
                            لوحة التحكم الإدارية
                        </h2>
                        <p class="ops-section-lead mt-2 max-w-prose lg:text-ops-body">
                            مزامنة مؤشرات الاجتماعات، قناة الخارج، والعملاء في صفحة واحدة واضحة.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2.5 md:flex-nowrap md:justify-end lg:gap-2">
                        <div
                            class="flex min-h-[5.25rem] min-w-[46%] flex-1 flex-col justify-center rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:min-w-[9.75rem] sm:flex-none md:min-w-[10.5rem] lg:min-h-[4.25rem] lg:min-w-[9rem] lg:p-3"
                        >
                            <span class="text-ops-label font-semibold text-slate-500 lg:text-ops-meta">اليوم</span>
                            <span class="mt-1 text-ops-title-xl font-semibold tabular-nums text-slate-900 lg:text-ops-title-lg">{{ meetings?.today ?? 0 }}</span>
                            <span class="mt-0.5 text-ops-meta text-slate-400">اجتماعات مجدولة اليوم</span>
                        </div>
                        <div
                            class="flex min-h-[5.25rem] min-w-[46%] flex-1 flex-col justify-center rounded-lg border border-slate-200 bg-slate-50 p-4 sm:min-w-[9.75rem] sm:flex-none md:min-w-[10.5rem] lg:min-h-[4.25rem] lg:min-w-[9rem] lg:p-3"
                        >
                            <span class="text-ops-label font-semibold text-slate-500 lg:text-ops-meta">إنجاز الاجتماعات</span>
                            <span class="mt-1 text-ops-title-xl font-semibold tabular-nums text-emerald-700 lg:text-ops-title-lg">{{ meetingsCompletionRate }}٪</span>
                            <span class="mt-0.5 text-ops-meta text-slate-500">من إجمالي سجل الاجتماعات</span>
                        </div>
                    </div>
                </div>
                <div
                    class="relative flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/70 bg-slate-50/60 px-5 py-2.5 text-xs text-slate-600 md:px-6 lg:px-5 lg:py-2 lg:text-[11px]"
                >
                    <span class="font-medium tabular-nums text-slate-700">{{ todayDateLabel }}</span>
                    <span class="text-[11px] text-slate-400">تحديث تلقائي عند كل زيارة للصفحة</span>
                </div>
            </section>

            <!-- مركز الانتباه (إداري): نفس الـ props — بدون API جديد -->
            <section
                class="ops-surface overflow-hidden border-s-4 bg-white p-0 shadow-sm"
                :class="attentionBorderClass"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                    <div>
                        <h3 class="ops-section-title-lg">مركز الانتباه التشغيلي</h3>
                        <p class="ops-section-lead mt-0.5 text-ops-meta sm:text-ops-body-sm">
                            تأخير المهام، فشل الإرسال في الخارج، وزن العملاء المحتملين، وازدحام مرحلة في المسار — من لقطة البيانات الحالية.
                        </p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-ops-meta font-semibold text-slate-600">لقطة</span>
                </div>
                <ul class="divide-y divide-slate-100" role="list">
                    <li v-for="(item, ai) in adminAttentionItems" :key="`adm-att-${ai}-${item.title}`">
                        <div class="flex gap-3 px-4 py-3 sm:items-center sm:justify-between sm:gap-4 sm:px-5 sm:py-3.5">
                            <span
                                class="mt-1.5 h-2 w-2 shrink-0 rounded-full sm:mt-0"
                                :class="attentionKindDotClass(item.kind)"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-ops-body-md font-semibold text-slate-900">{{ item.title }}</p>
                                <p class="mt-0.5 text-ops-body-sm leading-relaxed text-slate-600">{{ item.detail }}</p>
                            </div>
                            <Link
                                v-if="item.route && item.action_label"
                                :href="route(item.route)"
                                class="shrink-0 self-center rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-ops-label font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-50"
                            >
                                {{ item.action_label }}
                            </Link>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- KPIs + الخارج: سطح واحد لتقليل «تساوي أوزان البطاقات» -->
            <section class="ops-surface overflow-hidden p-0 shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
                    <p class="ops-kicker">المنظومة التشغيلية</p>
                    <h3 class="ops-section-title-lg mt-1">المؤشرات ومخرجات الخارج</h3>
                    <p class="ops-section-lead mt-1 text-ops-meta sm:text-ops-body-sm">
                        عملاء، مهام، اجتماعات، موظفون — ثم قناة الخارج في نفس الإطار لتسهيل المسح السريع.
                    </p>
                </div>
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 md:grid-cols-4">
                    <article
                        v-for="card in kpiCards"
                        :key="`kpi-${card.key}`"
                        class="min-h-[7rem] bg-white px-4 py-3 sm:min-h-[7.25rem] sm:px-4 sm:py-4"
                    >
                        <p class="text-ops-label font-semibold text-slate-500">{{ card.title }}</p>
                        <p class="mt-1.5 truncate text-2xl font-semibold tabular-nums text-slate-900 sm:text-[1.65rem]">{{ card.value }}</p>
                        <p class="mt-2 text-ops-body-sm text-slate-600">{{ card.sub }}</p>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full max-w-full rounded-full bg-slate-600/90 transition-[width] duration-300"
                                :style="{ width: card.progress }"
                            />
                        </div>
                    </article>
                </div>
                <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/40 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <p class="text-ops-label font-semibold text-slate-600">قناة الخارج</p>
                        <p class="ops-section-lead mt-0.5 text-ops-meta">
                            إجمالي
                            <span class="font-semibold tabular-nums text-slate-800">{{ outside_metrics?.total_conversations ?? 0 }}</span>
                            محادثة مسجّلة — للمزامنة أثناء العمل استخدم صفحة الخارج.
                        </p>
                    </div>
                    <Link
                        :href="route('outside.index')"
                        class="shrink-0 rounded-md border border-slate-200 bg-white px-3 py-2 text-ops-label font-semibold text-slate-800 transition-colors hover:border-slate-300 hover:bg-slate-50"
                    >
                        فتح الخارج
                    </Link>
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-slate-100 p-3 sm:gap-2.5 sm:p-4 lg:grid-cols-5 lg:gap-2 lg:p-4">
                    <div
                        v-for="tile in outsideMetricTiles"
                        :key="`om-${tile.key}`"
                        class="rounded-lg border p-3 sm:p-3.5"
                        :class="[tile.wrapClass, tile.key === 'fail7' ? 'col-span-2 lg:col-span-1' : '']"
                    >
                        <p class="text-ops-meta font-semibold uppercase tracking-wide" :class="tile.labelClass">
                            {{ tile.label }}
                        </p>
                        <p class="mt-1.5 text-xl font-semibold tabular-nums leading-none sm:text-2xl" :class="tile.valueClass">
                            {{ tile.value }}
                        </p>
                        <p class="mt-1 text-ops-meta text-slate-500">{{ tile.hint }}</p>
                    </div>
                </div>
            </section>

            <!-- مسار العملاء -->
            <section class="ops-surface overflow-hidden p-4 sm:p-5 lg:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between lg:gap-2">
                    <div>
                        <h3 class="ops-section-title-lg">العملاء حسب المراحل</h3>
                        <p class="ops-section-lead mt-1 md:text-ops-body-md lg:text-ops-body">
                            توزيع حيّ على مسار المبيعات الحالي في النظام.
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center rounded-full border border-brand-200/90 bg-brand-50 px-3 py-1.5 text-[11px] font-bold text-brand-800 lg:px-2.5 lg:py-1 lg:text-[10px]"
                    >
                        الإجمالي:
                        <span class="ms-1 tabular-nums">{{ clientsTotal }}</span>
                    </span>
                </div>

                <div class="mt-6 grid gap-6 lg:mt-5 lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] lg:gap-6 xl:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] xl:gap-8">
                    <div
                        class="flex flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-4 md:p-5 lg:p-4"
                    >
                        <div class="relative h-40 w-40 shrink-0 md:h-48 md:w-48 lg:h-36 lg:w-36">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 128 128" aria-hidden="true">
                                <circle
                                    cx="64"
                                    cy="64"
                                    r="42"
                                    stroke="rgb(241 245 249)"
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
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <p class="text-xs font-medium text-slate-500 md:text-[13px] lg:text-[11px]">العملاء</p>
                                <p class="text-3xl font-semibold tabular-nums text-slate-900 md:text-4xl lg:text-2xl">{{ clientsTotal }}</p>
                            </div>
                        </div>
                        <p v-if="topStage" class="mt-4 max-w-[18rem] text-center text-xs leading-relaxed text-slate-600 md:text-sm lg:mt-3 lg:max-w-[14rem] lg:text-[11px]">
                            أعلى مرحلة حالياً:
                            <span class="font-bold text-slate-900">{{ topStage.label }}</span>
                            <span class="tabular-nums text-slate-500">({{ topStage.percent }}٪)</span>
                        </p>
                    </div>

                    <div class="min-w-0 lg:min-h-0">
                        <p class="mb-3 text-[11px] leading-relaxed text-slate-600 md:text-sm lg:mb-2 lg:text-[11px]">
                            لكل مرحلة: الاسم كاملاً، ثم عدد العملاء ونسبته من الإجمالي
                            <span class="font-bold tabular-nums text-slate-800">({{ clientsTotal }})</span>
                            ، ثم شريط يمثل تلك النسبة (يبدأ من اليسار ويمتد بقدر الحصة).
                        </p>
                        <ul
                            v-if="stageDistribution.length"
                            class="max-h-[min(72vh,32rem)] space-y-2.5 overflow-y-auto overflow-x-hidden pe-1 [-webkit-overflow-scrolling:touch] lg:grid lg:max-h-[min(70vh,28rem)] lg:auto-rows-min lg:grid-cols-2 lg:gap-2 lg:space-y-0 xl:grid-cols-3"
                            role="list"
                        >
                            <li
                                v-for="stage in stageDistribution"
                                :key="`stage-row-${stage.id}`"
                                class="rounded-md border border-slate-200 bg-white px-3 py-2.5 transition-colors hover:bg-slate-50 md:px-3.5 md:py-3 lg:px-2.5 lg:py-2"
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3 lg:gap-1.5">
                                    <p class="min-w-0 text-[13px] font-semibold leading-snug text-slate-900 sm:flex-1 sm:text-end md:text-sm lg:text-[12px]">
                                        {{ stage.label }}
                                    </p>
                                    <div class="flex shrink-0 flex-wrap items-baseline gap-x-2 sm:justify-end lg:gap-1">
                                        <span class="text-lg font-semibold tabular-nums text-slate-900 md:text-xl lg:text-base">{{ stage.count }}</span>
                                        <span class="text-[11px] font-medium text-slate-500 md:text-xs lg:text-[10px]">من {{ clientsTotal }}</span>
                                        <span class="rounded-md bg-white/80 px-2 py-0.5 text-[10px] font-bold tabular-nums text-slate-600 ring-1 ring-slate-200/80 md:text-[11px] lg:px-1.5 lg:py-0 lg:text-[9px]">
                                            {{ stage.percent }}٪
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2.5 md:mt-3.5 lg:mt-2" dir="ltr">
                                    <div class="h-2.5 min-h-2.5 flex-1 overflow-hidden rounded-full bg-slate-200/90 md:h-3 lg:h-2">
                                        <div
                                            class="h-full max-w-full rounded-full transition-[width] duration-500 ease-out"
                                            :style="{
                                                width: widthPctOfMax(stage.count, clientsTotal),
                                                backgroundColor: stage.color,
                                            }"
                                            :title="`${stage.label}: ${stage.count} من ${clientsTotal} (${stage.percent}٪)`"
                                        />
                                    </div>
                                    <span class="w-12 shrink-0 text-end text-[10px] font-bold tabular-nums text-slate-500 md:text-xs">
                                        {{ widthPctOfMax(stage.count, clientsTotal) }}
                                    </span>
                                </div>
                            </li>
                        </ul>
                        <p v-if="!clientsByStage?.length" class="py-6 text-center text-sm text-slate-500">
                            لا توجد بيانات مراحل عملاء حتى الآن.
                        </p>
                    </div>
                </div>
            </section>

            <!-- اجتماعات · موظفون · مهام -->
            <section class="grid gap-4 lg:grid-cols-3 lg:gap-4 xl:gap-5">
                <div class="ops-surface p-4 sm:p-5 lg:p-4">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3 lg:pb-2">
                        <h3 class="ops-section-title">تحليلات الاجتماعات</h3>
                        <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 lg:text-[9px]">6 مؤشرات</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2.5 sm:gap-3 lg:mt-3 lg:gap-2">
                        <div
                            v-for="card in meetingsCards"
                            :key="`meeting-${card.key}`"
                            class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 lg:p-2.5"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="min-w-0 flex-1 text-end text-[11px] font-semibold text-slate-800 lg:text-[10px]">{{ card.label }}</span>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="meetingCardDotClass(card.kind)" aria-hidden="true" />
                            </div>
                            <p class="text-end text-xl font-semibold tabular-nums text-slate-900 lg:text-lg">{{ card.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="ops-surface p-4 sm:p-5 lg:p-4">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3 lg:pb-2">
                        <h3 class="ops-section-title">تحليلات الموظفين</h3>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 md:gap-3 lg:mt-3 lg:gap-2">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/90 p-2.5 text-center shadow-sm sm:p-3 md:p-4 lg:rounded-xl lg:p-2"
                        >
                            <p class="text-[10px] font-semibold text-slate-600 md:text-[11px] lg:text-[9px]">مدراء نظام</p>
                            <p class="mt-1 text-xl font-black tabular-nums text-slate-900 md:text-2xl lg:text-lg">{{ employees?.admins ?? 0 }}</p>
                        </div>
                        <div
                            class="rounded-2xl border border-brand-200/80 bg-brand-50/90 p-2.5 text-center shadow-sm sm:p-3 md:p-4 lg:rounded-xl lg:p-2"
                        >
                            <p class="text-[10px] font-semibold text-brand-900/80 md:text-[11px] lg:text-[9px]">قادة فرق</p>
                            <p class="mt-1 text-xl font-black tabular-nums text-brand-950 md:text-2xl lg:text-lg">{{ employees?.leads ?? 0 }}</p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-2.5 text-center shadow-sm sm:p-3 md:p-4 lg:rounded-xl lg:p-2"
                        >
                            <p class="text-[10px] font-semibold text-slate-600 md:text-[11px] lg:text-[9px]">موظفون</p>
                            <p class="mt-1 text-xl font-black tabular-nums text-slate-900 md:text-2xl lg:text-lg">{{ employees?.members ?? 0 }}</p>
                        </div>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-4 lg:mt-4 lg:pt-3">
                        <p class="ops-kicker">حسب الفريق</p>
                        <p class="mt-1 text-[11px] leading-relaxed text-slate-600 md:text-sm lg:text-[11px]">
                            بطاقات لكل فريق: الاسم والعدد. أقصى عدد موظفين في فريق واحد عندك حالياً:
                            <span class="font-bold tabular-nums text-slate-800">{{ maxTeamEmployees }}</span>.
                        </p>
                        <ul
                            v-if="(employees?.byTeam || []).length"
                            class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 lg:gap-2 xl:grid-cols-4"
                            role="list"
                        >
                            <li
                                v-for="team in employees?.byTeam || []"
                                :key="`team-${team.id}`"
                                class="flex flex-col justify-between gap-1 rounded-xl border border-slate-200/80 bg-slate-50/60 px-2.5 py-2 text-end shadow-sm ring-1 ring-slate-900/[0.02] sm:gap-1.5 sm:px-3 sm:py-2.5"
                            >
                                <p class="text-[12px] font-bold leading-snug text-slate-900 sm:text-[13px] lg:text-[11px]">
                                    {{ team.name }}
                                </p>
                                <div class="flex flex-wrap items-end justify-end gap-x-1 gap-y-0.5 tabular-nums">
                                    <span class="text-lg font-black text-slate-900 sm:text-xl lg:text-base">{{ team.employees }}</span>
                                    <span class="text-[10px] font-semibold text-slate-600 lg:text-[9px]">موظفون</span>
                                    <span class="text-[10px] text-slate-400 lg:text-[9px]">·</span>
                                    <span class="text-[10px] font-semibold text-slate-600 lg:text-[9px]">{{ team.leads }} قادة</span>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-center text-xs text-slate-500">لا توجد بيانات فرق بعد.</p>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-4 lg:mt-4 lg:pt-3">
                        <h4 class="ops-kicker">مدراء الحملات (عدد العملاء)</h4>
                        <p class="mt-1 text-[11px] leading-relaxed text-slate-600 md:text-sm lg:text-[11px]">
                            بطاقات لكل مدير: الاسم وعدد العملاء. أعلى عدد عملاء لمدير حملات عندك حالياً:
                            <span class="font-bold tabular-nums text-slate-800">{{ maxCampaignManagerClients }}</span>.
                        </p>
                        <ul
                            v-if="(employees?.campaignManagers || []).length"
                            class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 lg:gap-2 xl:grid-cols-4"
                            role="list"
                        >
                            <li
                                v-for="manager in employees?.campaignManagers || []"
                                :key="`cm-${manager.id}`"
                                class="flex flex-col justify-between gap-1 rounded-xl border border-slate-200/80 bg-slate-50/60 px-2.5 py-2 text-end shadow-sm ring-1 ring-slate-900/[0.02] sm:gap-1.5 sm:px-3 sm:py-2.5"
                            >
                                <p class="text-[12px] font-bold leading-snug text-slate-900 sm:text-[13px] lg:text-[11px]">
                                    {{ manager.name }}
                                </p>
                                <div class="flex items-end justify-end gap-1 tabular-nums">
                                    <span class="text-lg font-black text-slate-900 sm:text-xl lg:text-base">{{ manager.clients }}</span>
                                    <span class="pb-0.5 text-[10px] font-medium text-slate-500 lg:text-[9px]">عميل</span>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-center text-xs text-slate-500">لا يوجد مدراء حملات مضافون بعد.</p>
                    </div>
                </div>

                <div class="ops-surface flex min-h-0 flex-col p-4 sm:p-5 lg:min-h-0 lg:p-4">
                    <div class="shrink-0 border-b border-slate-100 pb-3 lg:pb-2">
                        <h3 class="ops-section-title">المهام حسب العمود</h3>
                        <p class="ops-section-lead mt-1 md:text-ops-body-sm lg:text-ops-meta">
                            رسم أعمدة: الارتفاع يتناسب مع العدد مقارنة بأعلى عمود ({{ taskColumnChart.max }}).
                        </p>
                    </div>
                    <div
                        v-if="taskColumnChart.bars.length"
                        class="mt-4 flex min-h-0 flex-1 flex-col overflow-x-auto [-webkit-overflow-scrolling:touch] lg:mt-3"
                    >
                        <svg
                            class="mx-auto block h-auto w-full min-w-[min(100%,22rem)] max-w-full text-slate-600 lg:max-h-[14rem] lg:min-h-0"
                            :viewBox="`0 0 ${taskColumnChart.W} ${taskColumnChart.H}`"
                            role="img"
                            aria-labelledby="home-task-chart-title"
                            preserveAspectRatio="xMidYMid meet"
                            dir="ltr"
                        >
                            <title id="home-task-chart-title">توزيع المهام حسب أعمدة اللوح</title>
                            <defs>
                                <linearGradient id="homeTasksColGrad" x1="0" y1="1" x2="0" y2="0">
                                    <stop offset="0%" stop-color="#6366f1" />
                                    <stop offset="55%" stop-color="#7c3aed" />
                                    <stop offset="100%" stop-color="#22d3ee" />
                                </linearGradient>
                            </defs>

                            <g v-for="(tick, ti) in taskColumnChart.yTicks" :key="`yt-${ti}-${tick}`">
                                <line
                                    :x1="taskColumnChart.padL - 6"
                                    :x2="taskColumnChart.W - 12"
                                    :y1="taskColumnChart.padT + taskColumnChart.innerH - (tick / taskColumnChart.max) * taskColumnChart.innerH"
                                    :y2="taskColumnChart.padT + taskColumnChart.innerH - (tick / taskColumnChart.max) * taskColumnChart.innerH"
                                    stroke="rgb(226 232 240)"
                                    stroke-width="1"
                                    stroke-dasharray="4 4"
                                />
                                <text
                                    :x="taskColumnChart.padL - 10"
                                    :y="4 + taskColumnChart.padT + taskColumnChart.innerH - (tick / taskColumnChart.max) * taskColumnChart.innerH"
                                    text-anchor="end"
                                    class="fill-slate-400"
                                    font-size="12"
                                    font-weight="600"
                                >
                                    {{ tick }}
                                </text>
                            </g>

                            <line
                                :x1="taskColumnChart.padL - 6"
                                :x2="taskColumnChart.W - 12"
                                :y1="taskColumnChart.padT + taskColumnChart.innerH"
                                :y2="taskColumnChart.padT + taskColumnChart.innerH"
                                stroke="rgb(148 163 184)"
                                stroke-width="1.5"
                            />

                            <g v-for="b in taskColumnChart.bars" :key="`bar-${b.id}`">
                                <rect
                                    :x="b.x"
                                    :y="b.y"
                                    :width="b.w"
                                    :height="b.h"
                                    rx="8"
                                    ry="8"
                                    fill="url(#homeTasksColGrad)"
                                    opacity="0.92"
                                >
                                    <title>{{ b.name }} — {{ b.count }}</title>
                                </rect>
                                <text
                                    v-if="b.count > 0"
                                    :x="b.cx"
                                    :y="b.y - 8"
                                    text-anchor="middle"
                                    class="fill-slate-800"
                                    font-size="14"
                                    font-weight="800"
                                >
                                    {{ b.count }}
                                </text>
                                <text
                                    :x="b.cx"
                                    :y="b.labelY"
                                    text-anchor="middle"
                                    class="fill-slate-600"
                                    :font-size="b.labelFontSize"
                                    font-weight="600"
                                    :transform="b.labelAngle ? `rotate(${b.labelAngle} ${b.cx} ${b.labelY})` : null"
                                >
                                    {{ b.label }}
                                </text>
                            </g>
                        </svg>
                    </div>
                    <p v-if="!taskColumnChart.bars.length" class="py-8 text-center text-sm text-slate-500">
                        لا توجد أعمدة مهام بعد.
                    </p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
