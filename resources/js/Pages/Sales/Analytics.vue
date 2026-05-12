<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    range_days: { type: Number, default: 30 },
    analytics_range_days: { type: Number, default: 30 },
    team: { type: Object, default: null },
    reps: { type: Array, default: () => [] },
    team_totals: { type: Object, default: () => ({}) },
    leaderboard: { type: Object, default: () => ({}) },
    pipeline: { type: Object, default: () => ({}) },
    channel_mix: { type: Object, default: () => ({}) },
    recent_status_changes: { type: Array, default: () => [] },
});

const page = usePage();

const rangeOptions = [
    { value: 7, label: '7 أيام' },
    { value: 14, label: '14 يوم' },
    { value: 30, label: '30 يوم' },
    { value: 60, label: '60 يوم' },
    { value: 90, label: '90 يوم' },
];
const selectedRange = ref(props.analytics_range_days || props.range_days || 30);
function changeRange(v) {
    selectedRange.value = v;
    router.get(route('sales.analytics'), { range: v }, { preserveScroll: true, preserveState: true });
}

const statusLabels = {
    new: 'جديد',
    potential: 'محتمل',
    qualified: 'مؤهّل',
    unlikely: 'غير محتمل',
    closed: 'مغلقة',
    active: 'نشط',
    paused: 'معلّق',
    lost: 'مفقود',
};
const statusToneClass = {
    new: 'bg-slate-100 text-slate-700',
    potential: 'bg-amber-100 text-amber-800',
    qualified: 'bg-emerald-100 text-emerald-800',
    unlikely: 'bg-rose-100 text-rose-700',
    closed: 'bg-slate-200 text-slate-600',
    active: 'bg-emerald-100 text-emerald-800',
    paused: 'bg-slate-200 text-slate-600',
    lost: 'bg-rose-200 text-rose-800',
};

const channelLabels = {
    whatsapp: 'واتساب',
    instagram: 'إنستغرام',
    messenger: 'ماسنجر',
};

const intelligenceLabels = {
    lead: 'فرصة بيعية',
    existing_client: 'عميل حالي',
    support: 'دعم',
    spam: 'مزعج',
};

function fmtSeconds(s) {
    const t = Math.max(0, Number(s || 0));
    if (!t) return '—';
    const h = Math.floor(t / 3600);
    const m = Math.floor((t % 3600) / 60);
    if (h <= 0 && m <= 0) return `${t}ث`;
    if (h <= 0) return `${m} د`;
    return `${h}س ${m}د`;
}

function percent(r) {
    if (r == null) return null;
    return Math.round(Number(r) * 100);
}

function fmtPercent(r) {
    const p = percent(r);
    return p == null ? '—' : `${p}%`;
}

function gradeClass(grade) {
    switch (grade) {
        case 'A':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300';
        case 'B':
            return 'bg-sky-100 text-sky-800 ring-1 ring-sky-300';
        case 'C':
            return 'bg-amber-100 text-amber-800 ring-1 ring-amber-300';
        case 'D':
            return 'bg-orange-100 text-orange-800 ring-1 ring-orange-300';
        default:
            return 'bg-rose-100 text-rose-800 ring-1 ring-rose-300';
    }
}

function scoreBarColor(s) {
    if (s >= 85) return 'bg-emerald-500';
    if (s >= 70) return 'bg-sky-500';
    if (s >= 55) return 'bg-amber-500';
    if (s >= 40) return 'bg-orange-500';
    return 'bg-rose-500';
}

function signalToneClass(tone) {
    switch (tone) {
        case 'emerald':
            return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200';
        case 'sky':
            return 'bg-sky-50 text-sky-800 ring-1 ring-sky-200';
        case 'rose':
            return 'bg-rose-50 text-rose-800 ring-1 ring-rose-200';
        case 'orange':
            return 'bg-orange-50 text-orange-800 ring-1 ring-orange-200';
        case 'amber':
            return 'bg-amber-50 text-amber-800 ring-1 ring-amber-200';
        default:
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
    }
}

function fmtDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ar-IQ', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

const totalPipeline = computed(() => {
    return Object.values(props.pipeline || {}).reduce((a, b) => a + Number(b || 0), 0);
});

const totalChannels = computed(() => {
    return Object.values(props.channel_mix || {}).reduce((a, b) => a + Number(b || 0), 0);
});

const sortBy = ref('score');
const sortOptions = [
    { value: 'score', label: 'الأداء الأعلى' },
    { value: 'conversion', label: 'الأعلى تحويلاً' },
    { value: 'volume', label: 'الأكثر نشاطاً' },
    { value: 'response', label: 'الأسرع رداً' },
    { value: 'stalled', label: 'الأكثر ركوداً' },
];

const sortedReps = computed(() => {
    const arr = (props.reps || []).slice();
    arr.sort((a, b) => {
        switch (sortBy.value) {
            case 'conversion':
                return (b.quality?.conversion_rate || 0) - (a.quality?.conversion_rate || 0);
            case 'volume':
                return (b.volume?.messages_out || 0) - (a.volume?.messages_out || 0);
            case 'response':
                return (a.quality?.first_response_avg_seconds || Infinity) - (b.quality?.first_response_avg_seconds || Infinity);
            case 'stalled':
                return (b.quality?.stalled_conversations || 0) - (a.quality?.stalled_conversations || 0);
            case 'score':
            default:
                return (b.score || 0) - (a.score || 0);
        }
    });
    return arr;
});
</script>

<template>
    <Head title="تحليلات المبيعات" />
    <AuthenticatedLayout>
        <template #title>تحليلات فريق المبيعات</template>

        <div class="mx-auto w-full min-w-0 max-w-6xl space-y-4 px-3 pb-8 sm:px-4">
            <div
                v-if="page.props.flash?.success"
                class="ui-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ page.props.flash.success }}
            </div>

            <!-- Header -->
            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-base font-bold text-slate-900 sm:text-lg">تحليلات أداء المبيعات</h1>
                    <p class="mt-0.5 text-xs text-slate-600">
                        تتبّع الكمية والجودة لكل مندوب في "الخارج" و"البضاعة": عدد العملاء، سرعة الرد، نسبة التحويل، توزيع الحالات والقنوات.
                    </p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        v-for="opt in rangeOptions"
                        :key="`r-${opt.value}`"
                        type="button"
                        :class="['rounded-full border px-2.5 py-1 text-[11px] font-semibold transition-all', selectedRange === opt.value ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                        @click="changeRange(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Team totals strip -->
            <div class="ui-card overflow-hidden">
                <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">أعضاء الفريق</div>
                        <div class="text-xl font-bold text-slate-900">{{ team_totals.member_count }}</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">عملاء جدد في الفترة</div>
                        <div class="text-xl font-bold text-brand-700">{{ team_totals.contacts_new }}</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">رسائل مُرسَلة</div>
                        <div class="text-xl font-bold text-sky-700">{{ team_totals.messages_out }}</div>
                        <div v-if="team_totals.failed_rate != null" class="text-[10px] text-rose-700">
                            فشل: {{ fmtPercent(team_totals.failed_rate) }}
                        </div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">عملاء مُؤكَّدون</div>
                        <div class="text-xl font-bold text-emerald-700">{{ team_totals.promoted_to_client }}</div>
                        <div class="text-[10px] text-slate-500">رُقّوا إلى عميل فعلي</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">معدل التحويل</div>
                        <div class="text-xl font-bold" :class="(team_totals.avg_conversion_rate ?? 0) >= 0.3 ? 'text-emerald-700' : (team_totals.avg_conversion_rate ?? 0) >= 0.15 ? 'text-amber-700' : 'text-rose-700'">
                            {{ fmtPercent(team_totals.avg_conversion_rate) }}
                        </div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">متوسط زمن الرد</div>
                        <div class="text-xl font-bold text-slate-900">{{ fmtSeconds(team_totals.team_first_response_avg_seconds) }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">محادثات مفتوحة</div>
                        <div class="text-xl font-bold text-slate-900">{{ team_totals.open_assigned }}</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">محادثات راكدة</div>
                        <div class="text-xl font-bold text-rose-700">{{ team_totals.stalled_conversations }}</div>
                        <div class="text-[10px] text-slate-500">عميل لم يُردّ عليه &gt;24س</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">تغييرات حالة</div>
                        <div class="text-xl font-bold text-slate-900">{{ team_totals.status_changes }}</div>
                        <div class="text-[10px] text-slate-500">نشاط بيعي فعلي</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">متوسط نقاط الأداء</div>
                        <div class="text-xl font-bold" :class="(team_totals.avg_score ?? 0) >= 70 ? 'text-emerald-700' : (team_totals.avg_score ?? 0) >= 50 ? 'text-amber-700' : 'text-rose-700'">
                            {{ team_totals.avg_score }}<span class="text-xs text-slate-400">/100</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pipeline distribution + channel mix -->
            <div class="grid gap-3 md:grid-cols-2">
                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">🪜</span>
                            <span class="text-sm font-bold text-slate-900">قمع المبيعات (مجموع الفريق)</span>
                        </div>
                        <span class="text-[10px] text-slate-500">{{ totalPipeline }} محادثة</span>
                    </div>
                    <div v-if="totalPipeline > 0" class="space-y-1.5">
                        <div v-for="(cnt, st) in pipeline" :key="`p-${st}`" class="flex items-center gap-2">
                            <span class="w-20 text-xs font-semibold text-slate-700">{{ statusLabels[st] || st }}</span>
                            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" :class="(statusToneClass[st] || 'bg-slate-200').replace('text-', 'tx-').split(' ').find(c=>c.startsWith('bg-'))?.replace('-100','-400').replace('-200','-400') || 'bg-slate-400'" :style="{ width: ((cnt / totalPipeline) * 100) + '%' }" />
                            </div>
                            <span class="w-12 text-end text-xs font-bold text-slate-800">{{ cnt }}</span>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-center text-[11px] text-slate-500">
                        لا توجد محادثات مُسندة للفريق بعد.
                    </div>
                </div>

                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">📡</span>
                            <span class="text-sm font-bold text-slate-900">توزيع القنوات (الرسائل الصادرة)</span>
                        </div>
                        <span class="text-[10px] text-slate-500">{{ totalChannels }} رسالة</span>
                    </div>
                    <div v-if="totalChannels > 0" class="space-y-1.5">
                        <div v-for="(cnt, ch) in channel_mix" :key="`c-${ch}`" class="flex items-center gap-2">
                            <span class="w-20 text-xs font-semibold text-slate-700">{{ channelLabels[ch] || ch }}</span>
                            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full"
                                    :class="ch === 'whatsapp' ? 'bg-emerald-500' : ch === 'instagram' ? 'bg-pink-500' : ch === 'messenger' ? 'bg-sky-500' : 'bg-slate-400'"
                                    :style="{ width: ((cnt / totalChannels) * 100) + '%' }"
                                />
                            </div>
                            <span class="w-12 text-end text-xs font-bold text-slate-800">{{ cnt }}</span>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-center text-[11px] text-slate-500">
                        لم تُرسَل رسائل خارجية بعد.
                    </div>
                </div>
            </div>

            <!-- Sort toolbar -->
            <div class="ui-card flex flex-wrap items-center gap-2 p-3">
                <div class="text-[11px] font-semibold text-slate-600">ترتيب البائعين:</div>
                <button
                    v-for="o in sortOptions"
                    :key="`so-${o.value}`"
                    type="button"
                    :class="['rounded-full border px-2.5 py-1 text-[11px] font-semibold transition-all', sortBy === o.value ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                    @click="sortBy = o.value"
                >
                    {{ o.label }}
                </button>
                <span class="ms-auto text-[11px] text-slate-500">{{ reps.length }} بائع</span>
            </div>

            <!-- Per-rep cards -->
            <div class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="rep in sortedReps"
                    :key="`rep-${rep.id}`"
                    class="ui-card overflow-hidden p-0"
                >
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-3 border-b border-slate-200/70 bg-gradient-to-l from-slate-50 via-white to-brand-50/40 p-4">
                        <div class="flex items-start gap-3">
                            <img
                                :src="rep.avatar_url || '/images/mobile-logo.png'"
                                alt="avatar"
                                class="h-11 w-11 rounded-full border border-slate-200 object-cover shadow-sm"
                            />
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-bold text-slate-900">{{ rep.name }}</span>
                                    <span v-if="rep.is_lead" class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 ring-1 ring-amber-200">قائد الفريق</span>
                                </div>
                                <div class="text-[11px] text-slate-500" dir="ltr">@{{ rep.username }}</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold" :class="gradeClass(rep.grade)">{{ rep.grade }}</span>
                                <div>
                                    <div class="text-[9px] text-slate-500">نقاط الأداء</div>
                                    <div class="text-base font-bold text-slate-900">{{ rep.score }}<span class="text-[10px] text-slate-400">/100</span></div>
                                </div>
                            </div>
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" :class="scoreBarColor(rep.score)" :style="{ width: rep.score + '%' }" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <!-- Signals -->
                        <div v-if="(rep.signals || []).length" class="flex flex-wrap gap-1">
                            <span
                                v-for="sig in rep.signals"
                                :key="`sig-${rep.id}-${sig.code}`"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="signalToneClass(sig.tone)"
                            >
                                {{ sig.label }}
                            </span>
                        </div>

                        <!-- Quality grid -->
                        <div class="grid grid-cols-2 gap-2 text-center text-[11px] sm:grid-cols-4">
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5">
                                <div class="text-[10px] text-emerald-700">معدل التحويل</div>
                                <div class="text-sm font-bold text-emerald-900">{{ fmtPercent(rep.quality.conversion_rate) }}</div>
                                <div class="text-[9px] text-slate-500">{{ rep.quality.promoted_to_client }} عميل مؤكَّد</div>
                            </div>
                            <div class="rounded-lg border border-sky-200 bg-sky-50 px-2 py-1.5">
                                <div class="text-[10px] text-sky-700">سرعة الرد</div>
                                <div class="text-sm font-bold text-sky-900">{{ fmtSeconds(rep.quality.first_response_avg_seconds) }}</div>
                                <div class="text-[9px] text-slate-500">على {{ rep.quality.first_response_count }} محادثة</div>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5">
                                <div class="text-[10px] text-amber-700">مفتوحة</div>
                                <div class="text-sm font-bold text-amber-900">{{ rep.volume.open_assigned }}</div>
                                <div class="text-[9px] text-rose-700">{{ rep.quality.stalled_conversations }} راكدة</div>
                            </div>
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1.5">
                                <div class="text-[10px] text-indigo-700">عملاء جدد</div>
                                <div class="text-sm font-bold text-indigo-900">{{ rep.volume.contacts_new }}</div>
                                <div class="text-[9px] text-slate-500">{{ rep.volume.messages_per_contact }} رسالة/عميل</div>
                            </div>
                        </div>

                        <!-- Volume grid -->
                        <div class="grid grid-cols-2 gap-2 text-center text-[10px] sm:grid-cols-4">
                            <div class="rounded-md border border-slate-200 bg-white px-2 py-1.5">
                                <div class="text-slate-500">رسائل مُرسَلة</div>
                                <div class="text-sm font-bold text-slate-900">{{ rep.volume.messages_out }}</div>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white px-2 py-1.5">
                                <div class="text-slate-500">أيام نشطة</div>
                                <div class="text-sm font-bold text-slate-900">{{ rep.volume.days_active }}</div>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white px-2 py-1.5">
                                <div class="text-slate-500">تغييرات حالة</div>
                                <div class="text-sm font-bold text-slate-900">{{ rep.volume.status_changes }}</div>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white px-2 py-1.5">
                                <div class="text-slate-500">فشل الإرسال</div>
                                <div class="text-sm font-bold" :class="(rep.volume.failed_rate ?? 0) >= 0.1 ? 'text-rose-700' : 'text-slate-900'">
                                    {{ fmtPercent(rep.volume.failed_rate) }}
                                </div>
                            </div>
                        </div>

                        <!-- Pipeline distribution per rep -->
                        <details v-if="Object.keys(rep.pipeline || {}).length" class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/40">
                            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">🪜 توزيع محادثاته على الحالات</span>
                            </summary>
                            <div class="flex flex-wrap gap-1 px-3 pb-2.5">
                                <span
                                    v-for="(cnt, st) in rep.pipeline"
                                    :key="`rp-${rep.id}-${st}`"
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="statusToneClass[st] || 'bg-slate-100 text-slate-700'"
                                >
                                    {{ statusLabels[st] || st }}: {{ cnt }}
                                </span>
                            </div>
                        </details>

                        <!-- AI classification distribution -->
                        <details v-if="Object.keys(rep.ai_classification || {}).length" class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/40">
                            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">🤖 تصنيف الذكاء لمحادثاته</span>
                            </summary>
                            <div class="flex flex-wrap gap-1 px-3 pb-2.5">
                                <span
                                    v-for="(cnt, cls) in rep.ai_classification"
                                    :key="`ai-${rep.id}-${cls}`"
                                    class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-800 ring-1 ring-indigo-100"
                                >
                                    {{ intelligenceLabels[cls] || cls }}: {{ cnt }}
                                </span>
                            </div>
                        </details>

                        <!-- Channel mix per rep -->
                        <details v-if="Object.keys(rep.channel_mix || {}).length" class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/40">
                            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">📡 توزيع رسائله على القنوات</span>
                            </summary>
                            <div class="flex flex-wrap gap-1 px-3 pb-2.5">
                                <span
                                    v-for="(cnt, ch) in rep.channel_mix"
                                    :key="`rch-${rep.id}-${ch}`"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700"
                                >
                                    {{ channelLabels[ch] || ch }}: {{ cnt }}
                                </span>
                            </div>
                        </details>
                    </div>
                </div>
                <div v-if="!reps.length" class="ui-card flex flex-col items-center justify-center gap-2 px-4 py-12 text-center md:col-span-2">
                    <div class="text-4xl">🛒</div>
                    <div class="text-sm font-semibold text-slate-700">لا يوجد بائعون مُسندون لفريق المبيعات بعد.</div>
                    <Link :href="route('employees.index')" class="text-xs font-semibold text-brand-700 hover:underline">
                        أضِف موظفين إلى فريق "المبيعات"
                    </Link>
                </div>
            </div>

            <!-- Recent status changes feed -->
            <div v-if="(recent_status_changes || []).length" class="ui-card p-3">
                <div class="mb-2 flex items-center gap-1.5">
                    <span class="text-base">📋</span>
                    <span class="text-sm font-bold text-slate-900">أحدث تحركات الفريق</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-for="h in recent_status_changes" :key="`h-${h.id}`" class="flex items-start gap-2 py-1.5">
                        <img v-if="h.user?.avatar_url" :src="h.user.avatar_url" class="h-6 w-6 shrink-0 rounded-full border border-slate-200 object-cover" />
                        <div class="min-w-0 flex-1 text-[11px] leading-relaxed text-slate-700">
                            <span class="font-semibold text-slate-800">{{ h.user?.name || '—' }}</span>
                            نقل
                            <span class="font-semibold">{{ h.customer?.name || '—' }}</span>
                            من <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold">{{ statusLabels[h.from] || h.from || '—' }}</span>
                            إلى <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800">{{ statusLabels[h.to] || h.to || '—' }}</span>
                            <span class="ms-1 text-slate-400">· {{ fmtDateTime(h.created_at) }}</span>
                            <div v-if="h.note" class="mt-0.5 italic text-slate-500">"{{ h.note }}"</div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
