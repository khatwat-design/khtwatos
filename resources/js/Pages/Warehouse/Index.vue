<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    clients: Array,
    filters: Object,
    sales_rows: Array,
    campaign_rows: Array,
    permissions: Object,
    alerts: Array,
    analytics: Object,
    daily_trend: Array,
    executive_cards: Object,
    client_performance: Array,
    product_performance: Array,
    team_performance: Array,
    smart_alerts: Array,
});

const filterForm = useForm({
    client_id: props.filters?.client_id || '',
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
});
const showFilters = ref(false);
const showAlerts = ref(false);
const showWindowCards = ref(true);

function applyFilter() {
    router.get(
        route('warehouse.index'),
        {
            client_id: filterForm.client_id || undefined,
            start_date: filterForm.start_date || undefined,
            end_date: filterForm.end_date || undefined,
        },
        { preserveState: true },
    );
}

function formatMoney(value) {
    return new Intl.NumberFormat('ar-IQ', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
}


function deltaClass(value) {
    const number = Number(value || 0);
    if (number > 0) return 'text-emerald-700';
    if (number < 0) return 'text-red-700';
    return 'text-gray-600';
}

function alertClass(type) {
    if (type === 'critical') {
        return 'border-red-200 bg-red-50 text-red-800';
    }
    return 'border-amber-200 bg-amber-50 text-amber-800';
}

const trendChartPoints = computed(() => {
    const rows = [...(props.daily_trend || [])].reverse();
    if (!rows.length) return { revenue: '', spend: '' };

    const width = 560;
    const height = 180;
    const pad = 16;
    const maxRevenue = Math.max(...rows.map((row) => Number(row.revenue || 0)), 1);
    const maxSpend = Math.max(...rows.map((row) => Number(row.ad_spend || 0)), 1);
    const maxValue = Math.max(maxRevenue, maxSpend, 1);
    const stepX = rows.length > 1 ? (width - pad * 2) / (rows.length - 1) : 0;

    const mapPoints = (key) =>
        rows
            .map((row, index) => {
                const x = pad + stepX * index;
                const y = height - pad - ((Number(row[key] || 0) / maxValue) * (height - pad * 2));
                return `${x},${y}`;
            })
            .join(' ');

    return {
        revenue: mapPoints('revenue'),
        spend: mapPoints('ad_spend'),
    };
});

const roasBars = computed(() => {
    const rows = [...(props.daily_trend || [])].reverse();
    const maxRoas = Math.max(...rows.map((row) => Number(row.roas_from_sales || 0)), 1);

    return rows.slice(-14).map((row) => {
        const roas = Number(row.roas_from_sales || 0);
        return {
            date: row.date,
            roas,
            height: `${Math.max(6, (roas / maxRoas) * 100)}%`,
        };
    });
});

const hasTrendData = computed(() =>
    (props.daily_trend || []).some((row) => Number(row.revenue || 0) > 0 || Number(row.ad_spend || 0) > 0),
);

const hasRoasData = computed(() =>
    (props.daily_trend || []).some((row) => Number(row.roas_from_sales || 0) > 0),
);

</script>

<template>
    <Head title="المخزن" />

    <AuthenticatedLayout>
        <template #title>المخزن</template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="ui-card p-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">المخزن والتحليل</h2>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                            title="الفلاتر"
                            @click="showFilters = true"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 6h16M7 12h10M10 18h4" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                            :title="showAlerts ? 'إخفاء التنبيهات' : 'إظهار التنبيهات'"
                            @click="showAlerts = !showAlerts"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 3a5 5 0 0 0-5 5v3.5l-1.6 2.9a1 1 0 0 0 .88 1.6h11.44a1 1 0 0 0 .88-1.6L17 11.5V8a5 5 0 0 0-5-5z" />
                                <path d="M10 19a2 2 0 0 0 4 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">مبيعات اليوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ formatMoney(analytics?.today?.revenue) }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.revenue)">
                        الفرق عن أمس: {{ Number(analytics?.delta?.revenue || 0) >= 0 ? '+' : '' }}{{ formatMoney(analytics?.delta?.revenue || 0) }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">الإنفاق اليوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ formatMoney(analytics?.today?.ad_spend) }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.ad_spend)">
                        الفرق عن أمس: {{ Number(analytics?.delta?.ad_spend || 0) >= 0 ? '+' : '' }}{{ formatMoney(analytics?.delta?.ad_spend || 0) }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">ROAS اليوم (مبيعات/إنفاق)</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ analytics?.today?.roas ?? '—' }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.roas)">
                        الفرق عن أمس: {{ analytics?.delta?.roas === null ? '—' : `${analytics.delta.roas >= 0 ? '+' : ''}${analytics.delta.roas}` }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">تكلفة الرسالة اليوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ analytics?.today?.cost_per_message ?? '—' }}</p>
                    <p class="mt-1 text-xs text-gray-600">
                        رسائل اليوم: {{ analytics?.today?.messages || 0 }} · نقرات اليوم: {{ analytics?.today?.clicks || 0 }}
                    </p>
                </div>
            </div>

            <div v-if="!showWindowCards" class="flex justify-center">
                <button
                    type="button"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    @click="showWindowCards = !showWindowCards"
                >
                    {{ showWindowCards ? 'إخفاء آخر 7 أيام' : 'إظهار آخر 7 أيام' }}
                </button>
            </div>

            <div v-if="showWindowCards" class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">ROAS آخر {{ executive_cards?.window_days || 7 }} يوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ executive_cards?.roas_window ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">Conversion آخر {{ executive_cards?.window_days || 7 }} يوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ executive_cards?.conversion_rate_window ?? '—' }}%</p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">CAC آخر {{ executive_cards?.window_days || 7 }} يوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ executive_cards?.cac_window ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-3">
                    <p class="text-xs text-gray-500">AOV آخر {{ executive_cards?.window_days || 7 }} يوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ executive_cards?.aov_window ?? '—' }}</p>
                </div>
            </div>
            <div v-if="showWindowCards" class="mx-auto w-full md:max-w-sm">
                <div class="ui-card ui-card-hover p-3 text-center">
                    <p class="text-xs text-gray-500">إيراد آخر {{ executive_cards?.window_days || 7 }} يوم</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ formatMoney(executive_cards?.revenue_window || 0) }}</p>
                </div>
            </div>
            <div v-if="showWindowCards" class="flex justify-center">
                <button
                    type="button"
                    class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                    @click="showWindowCards = !showWindowCards"
                >
                    {{ showWindowCards ? 'إخفاء آخر 7 أيام' : 'إظهار آخر 7 أيام' }}
                </button>
            </div>

            <div v-if="showAlerts" class="ui-card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">التنبيهات التلقائية</h2>
                    <span class="text-xs text-gray-500">تحديث مباشر من البيانات الحالية</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div
                        v-for="(alert, idx) in alerts"
                        :key="`alert-${idx}`"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="alertClass(alert.type)"
                    >
                        <p class="font-semibold">{{ alert.title }} - {{ alert.client }}</p>
                        <p class="mt-1 text-xs">{{ alert.message }} <span class="opacity-75">({{ alert.date }})</span></p>
                    </div>
                    <p v-if="!alerts?.length" class="text-sm text-emerald-700">لا توجد تنبيهات حرجة حالياً.</p>
                </div>
            </div>

            <div v-if="showAlerts" class="ui-card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">تنبيهات ذكية تنفيذية</h2>
                    <span class="text-xs text-gray-500">مبنية على العميل/المنتج/الأداء</span>
                </div>
                <div class="mt-3 space-y-2">
                    <div
                        v-for="(alert, idx) in smart_alerts"
                        :key="`smart-alert-${idx}`"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="alertClass(alert.type)"
                    >
                        <p class="font-semibold">{{ alert.title }}</p>
                        <p class="mt-1 text-xs">{{ alert.message }} <span class="opacity-75">({{ alert.date }})</span></p>
                    </div>
                    <p v-if="!smart_alerts?.length" class="text-sm text-emerald-700">لا توجد تنبيهات ذكية حالياً.</p>
                </div>
            </div>

            <div class="ui-card p-5">
                    <h2 class="text-lg font-semibold text-gray-900">الأداء اليومي للحملات</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                <th class="px-2 py-2">التاريخ</th>
                                <th class="px-2 py-2">المبيعات</th>
                                <th class="px-2 py-2">الطلبات</th>
                                <th class="px-2 py-2">الإنفاق</th>
                                <th class="px-2 py-2">الرسائل</th>
                                <th class="px-2 py-2">النقرات</th>
                                <th class="px-2 py-2">ROAS</th>
                                <th class="px-2 py-2">تكلفة الرسالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in daily_trend" :key="`trend-${row.date}`" class="border-b border-gray-100">
                                <td class="px-2 py-2">{{ row.date }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-2 py-2">{{ row.orders_count }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.ad_spend) }}</td>
                                <td class="px-2 py-2">{{ row.messages_count }}</td>
                                <td class="px-2 py-2">{{ row.clicks_count }}</td>
                                <td class="px-2 py-2">{{ row.roas_from_sales ?? '—' }}</td>
                                <td class="px-2 py-2">{{ row.cost_per_message ?? '—' }}</td>
                            </tr>
                            <tr v-if="!daily_trend?.length">
                                <td class="px-2 py-4 text-center text-gray-500" colspan="8">لا توجد بيانات ربط يومي بعد.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="ui-card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">اتجاه الأداء المالي</h2>
                        <span class="text-xs text-gray-500">آخر {{ executive_cards?.window_days || 7 }} يوم</span>
                    </div>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-3 shadow-sm">
                        <svg viewBox="0 0 560 180" class="h-44 w-full md:h-48">
                            <g stroke="#e5e7eb" stroke-width="1">
                                <line x1="16" y1="32" x2="544" y2="32" />
                                <line x1="16" y1="64" x2="544" y2="64" />
                                <line x1="16" y1="96" x2="544" y2="96" />
                                <line x1="16" y1="128" x2="544" y2="128" />
                                <line x1="16" y1="160" x2="544" y2="160" />
                            </g>
                            <polyline
                                fill="none"
                                stroke="#0ea5e9"
                                stroke-width="3.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :points="trendChartPoints.revenue"
                            />
                            <polyline
                                fill="none"
                                stroke="#f59e0b"
                                stroke-width="3.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :points="trendChartPoints.spend"
                            />
                        </svg>
                        <p
                            v-if="!hasTrendData"
                            class="mt-2 rounded-lg border border-dashed border-slate-300 bg-white/80 px-3 py-2 text-center text-xs text-slate-500"
                        >
                            لا توجد بيانات كافية لإظهار منحنى واضح حالياً.
                        </p>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1 text-gray-700">
                            <span class="h-2.5 w-2.5 rounded-full bg-sky-500" />
                            الإيراد
                        </span>
                        <span class="inline-flex items-center gap-1 text-gray-700">
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-500" />
                            الإنفاق
                        </span>
                    </div>
                </div>

                <div class="ui-card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">اتجاه ROAS اليومي</h2>
                        <span class="text-xs text-gray-500">حتى آخر 14 يوم</span>
                    </div>
                    <div class="mt-4 flex h-40 items-end gap-1 rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-3 shadow-sm md:h-48">
                        <div
                            v-for="bar in roasBars"
                            :key="`roas-bar-${bar.date}`"
                            class="group flex min-w-0 flex-1 flex-col items-center justify-end"
                        >
                            <div
                                class="w-full rounded-t-md bg-gradient-to-t from-brand-600/90 to-brand-400/80 transition-all duration-200 group-hover:from-brand-600 group-hover:to-brand-400"
                                :style="{ height: bar.height }"
                            />
                            <span class="mt-1 truncate text-[10px] text-slate-500">{{ bar.date.slice(5) }}</span>
                        </div>
                    </div>
                    <p
                        v-if="!hasRoasData"
                        class="mt-2 rounded-lg border border-dashed border-slate-300 bg-white/80 px-3 py-2 text-center text-xs text-slate-500"
                    >
                        لا توجد قيم ROAS موجبة ضمن الفترة الحالية.
                    </p>
                </div>
            </div>

            <div class="ui-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">أداء المنتجات</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                <th class="px-2 py-2">المنتج</th>
                                <th class="px-2 py-2">الكمية</th>
                                <th class="px-2 py-2">الإيراد</th>
                                <th class="px-2 py-2">ROAS</th>
                                <th class="px-2 py-2">CAC</th>
                                <th class="px-2 py-2">Conv%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in product_performance" :key="`pp-${row.product_id}`" class="border-b border-gray-100">
                                <td class="px-2 py-2">{{ row.product }}</td>
                                <td class="px-2 py-2">{{ row.sold_quantity }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-2 py-2">{{ row.roas ?? '—' }}</td>
                                <td class="px-2 py-2">{{ row.cac ?? '—' }}</td>
                                <td class="px-2 py-2">{{ row.conversion_rate ?? '—' }}</td>
                            </tr>
                            <tr v-if="!product_performance?.length">
                                <td class="px-2 py-4 text-center text-gray-500" colspan="6">لا توجد بيانات منتجات كافية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">بيانات حملات Meta للعملاء</h2>
                <div v-if="campaign_rows?.length" class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                <th class="px-2 py-2">العميل</th>
                                <th class="px-2 py-2">عنوان الحملة</th>
                                <th class="px-2 py-2">التاريخ</th>
                                <th class="px-2 py-2">الإنفاق</th>
                                <th class="px-2 py-2">الإيراد</th>
                                <th class="px-2 py-2">ROAS</th>
                                <th class="px-2 py-2">Leads</th>
                                <th class="px-2 py-2">المشتريات</th>
                                <th class="px-2 py-2">الرسائل</th>
                                <th class="px-2 py-2">النقرات</th>
                                <th class="px-2 py-2">CVR%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in campaign_rows" :key="`campaign-${row.id}`" class="border-b border-gray-100">
                                <td class="px-2 py-2 font-medium text-gray-800">{{ row.client || 'عميل غير معروف' }}</td>
                                <td class="px-2 py-2">{{ row.source_ref || 'Meta Campaign' }}</td>
                                <td class="px-2 py-2">{{ row.report_date || '—' }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.ad_spend) }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.campaign_revenue) }}</td>
                                <td class="px-2 py-2">{{ row.roas ?? '—' }}</td>
                                <td class="px-2 py-2">{{ row.leads_count }}</td>
                                <td class="px-2 py-2">{{ row.purchases_count }}</td>
                                <td class="px-2 py-2">{{ row.messages_count }}</td>
                                <td class="px-2 py-2">{{ row.clicks_count }}</td>
                                <td class="px-2 py-2">{{ row.cvr ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-center text-sm text-slate-500">
                    لا توجد بيانات حملات Meta ضمن الفترة المحددة.
                </div>
            </div>
        </div>

        <div
            v-if="showFilters"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showFilters = false"
        >
            <div class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">الفلاتر</h3>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" @click="showFilters = false">
                        إغلاق
                    </button>
                </div>
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="applyFilter(); showFilters = false;">
                    <div class="md:col-span-2">
                        <InputLabel for="client_filter_modal" value="العميل" />
                        <select id="client_filter_modal" v-model="filterForm.client_id" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm">
                            <option value="">كل العملاء</option>
                            <option v-for="client in clients" :key="`filter-modal-${client.id}`" :value="client.id">
                                {{ client.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="start_date_filter_modal" value="من تاريخ" />
                        <input id="start_date_filter_modal" v-model="filterForm.start_date" type="date" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <InputLabel for="end_date_filter_modal" value="إلى تاريخ" />
                        <input id="end_date_filter_modal" v-model="filterForm.end_date" type="date" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <PrimaryButton type="submit">تطبيق</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.filter-select {
    color: #111111;
    background: #ffffff;
}

.filter-select option {
    color: #111111;
    background: #ffffff;
}

</style>
