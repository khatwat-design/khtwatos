<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

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
    meta_integrations: Array,
    meta_overview: Object,
    meta_oauth: Object,
});

const filterForm = useForm({
    client_id: props.filters?.client_id || '',
    days: Number(props.filters?.days || 7),
    start_date: props.filters?.start_date || '',
    end_date: props.filters?.end_date || '',
});

const metaIntegrationForm = useForm({
    client_id: props.filters?.client_id || '',
    ad_account_id: '',
    is_active: true,
});
const metaSyncForm = useForm({
    client_id: props.filters?.client_id || '',
    days: 2,
});
const metaDisconnectForm = useForm({});

const selectedMetaIntegration = computed(() =>
    (props.meta_integrations || []).find((row) => String(row.client_id) === String(metaIntegrationForm.client_id || props.filters?.client_id || '')) || null
);

watch(
    () => metaIntegrationForm.client_id,
    (value) => {
        metaSyncForm.client_id = value || '';
        const row = (props.meta_integrations || []).find((item) => String(item.client_id) === String(value || ''));
        if (row?.ad_account_id) {
            metaIntegrationForm.ad_account_id = row.ad_account_id;
        }
    }
);

function saveMetaIntegration() {
    metaIntegrationForm.post(route('warehouse.meta-integrations.upsert'), {
        preserveScroll: true,
    });
}

function runMetaSync() {
    metaSyncForm.post(route('warehouse.meta-sync'), {
        preserveScroll: true,
    });
}

function disconnectMetaOAuth() {
    metaDisconnectForm.post(route('warehouse.meta.oauth.disconnect'), {
        preserveScroll: true,
    });
}

function applyFilter() {
    router.get(
        route('warehouse.index'),
        {
            client_id: filterForm.client_id || undefined,
            days: filterForm.days || 7,
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

const dayOptions = [
    { label: 'آخر 7 أيام', value: 7 },
    { label: 'آخر 14 يوم', value: 14 },
    { label: 'آخر 30 يوم', value: 30 },
    { label: 'آخر 60 يوم', value: 60 },
];

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
</script>

<template>
    <Head title="المخزن" />

    <AuthenticatedLayout>
        <template #title>المخزن</template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="ui-card p-4">
                <form class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilter">
                    <div class="min-w-0">
                        <InputLabel for="client_filter" value="تصفية حسب العميل" />
                        <select id="client_filter" v-model="filterForm.client_id" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm">
                            <option value="">كل العملاء</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">
                                {{ client.name }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <InputLabel for="days_filter" value="فترة التحليل (أيام)" />
                        <select id="days_filter" v-model.number="filterForm.days" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm">
                            <option v-for="option in dayOptions" :key="`days-option-${option.value}`" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <InputLabel for="start_date_filter" value="من تاريخ" />
                        <input id="start_date_filter" v-model="filterForm.start_date" type="date" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="min-w-0">
                        <InputLabel for="end_date_filter" value="إلى تاريخ" />
                        <input id="end_date_filter" v-model="filterForm.end_date" type="date" class="filter-select mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton type="submit" class="w-full justify-center">تطبيق</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">مبيعات اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ formatMoney(analytics?.today?.revenue) }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.revenue)">
                        الفرق عن أمس: {{ Number(analytics?.delta?.revenue || 0) >= 0 ? '+' : '' }}{{ formatMoney(analytics?.delta?.revenue || 0) }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">الإنفاق اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ formatMoney(analytics?.today?.ad_spend) }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.ad_spend)">
                        الفرق عن أمس: {{ Number(analytics?.delta?.ad_spend || 0) >= 0 ? '+' : '' }}{{ formatMoney(analytics?.delta?.ad_spend || 0) }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">ROAS اليوم (مبيعات/إنفاق)</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.today?.roas ?? '—' }}</p>
                    <p class="mt-1 text-xs" :class="deltaClass(analytics?.delta?.roas)">
                        الفرق عن أمس: {{ analytics?.delta?.roas === null ? '—' : `${analytics.delta.roas >= 0 ? '+' : ''}${analytics.delta.roas}` }}
                    </p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">تكلفة الرسالة اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.today?.cost_per_message ?? '—' }}</p>
                    <p class="mt-1 text-xs text-gray-600">
                        رسائل اليوم: {{ analytics?.today?.messages || 0 }} · نقرات اليوم: {{ analytics?.today?.clicks || 0 }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">ROAS آخر {{ executive_cards?.window_days || filterForm.days }} يوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ executive_cards?.roas_window ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">Conversion آخر {{ executive_cards?.window_days || filterForm.days }} يوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ executive_cards?.conversion_rate_window ?? '—' }}%</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">CAC آخر {{ executive_cards?.window_days || filterForm.days }} يوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ executive_cards?.cac_window ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">AOV آخر {{ executive_cards?.window_days || filterForm.days }} يوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ executive_cards?.aov_window ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">إيراد آخر {{ executive_cards?.window_days || filterForm.days }} يوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ formatMoney(executive_cards?.revenue_window || 0) }}</p>
                </div>
            </div>

            <div class="ui-card p-5">
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

            <div class="ui-card p-5">
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

            <div v-if="permissions?.can_manage_campaign_updates" class="ui-card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">تغذية المخزن من Meta (تلقائي)</h2>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Manual input disabled</span>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    الإدخال اليدوي للإنفاق والرسائل معطل. البيانات تُجلب تلقائياً من Meta لكل عميل مرتبط.
                </p>
            </div>

            <div v-if="permissions?.can_manage_campaign_updates" class="ui-card p-5">
                <div class="mb-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs text-gray-500">عملاء مربوطين مع Meta</p>
                        <p class="mt-1 text-2xl font-black text-gray-900">{{ meta_overview?.connected_clients || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs text-gray-500">إعداد مكتمل</p>
                        <p class="mt-1 text-2xl font-black text-emerald-700">{{ meta_overview?.ready_clients || 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs text-gray-500">يحتاج متابعة</p>
                        <p class="mt-1 text-2xl font-black text-amber-700">{{ meta_overview?.issues_count || 0 }}</p>
                    </div>
                </div>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white/80 p-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">ربط OAuth مع Meta</p>
                        <p class="text-xs text-gray-600">
                            الحالة:
                            <span v-if="meta_oauth?.connected" class="text-emerald-700">متصل ({{ meta_oauth?.meta_user_name || 'Meta User' }})</span>
                            <span v-else class="text-amber-700">غير متصل</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            :href="route('warehouse.meta.oauth.redirect')"
                            class="inline-flex items-center rounded-xl bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                        >
                            ربط Meta OAuth
                        </a>
                        <button
                            v-if="meta_oauth?.connected"
                            type="button"
                            class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                            @click="disconnectMetaOAuth"
                        >
                            فصل الربط
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">ربط Meta لكل عميل</h2>
                    <span class="text-xs text-gray-500">كل عميل = حساب إعلاني مستقل لمنع تداخل البيانات</span>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <InputLabel for="meta_client_id" value="العميل" />
                        <select id="meta_client_id" v-model="metaIntegrationForm.client_id" class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm" required>
                            <option value="" disabled>اختر العميل</option>
                            <option v-for="client in clients" :key="`meta-client-${client.id}`" :value="client.id">{{ client.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="meta_ad_account_id" value="Meta Ad Account ID" />
                        <TextInput id="meta_ad_account_id" v-model="metaIntegrationForm.ad_account_id" type="text" class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm" placeholder="act_123.. أو 123.." required />
                    </div>
                    <div>
                        <InputLabel for="meta_sync_days" value="مزامنة آخر (أيام)" />
                        <TextInput id="meta_sync_days" v-model="metaSyncForm.days" type="number" min="1" max="14" class="mt-1 block w-full rounded-xl border-slate-200 bg-white text-sm shadow-sm" />
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <PrimaryButton type="button" :disabled="metaIntegrationForm.processing || !metaIntegrationForm.client_id || !metaIntegrationForm.ad_account_id" @click="saveMetaIntegration">
                            حفظ الربط
                        </PrimaryButton>
                        <PrimaryButton type="button" :disabled="metaSyncForm.processing || !metaSyncForm.client_id" @click="runMetaSync">
                            مزامنة الآن
                        </PrimaryButton>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-600">
                    <span v-if="selectedMetaIntegration?.ad_account_id">
                        الحساب الحالي: <strong>{{ selectedMetaIntegration.ad_account_id }}</strong>
                        <span class="mx-1">·</span>
                        آخر مزامنة: {{ selectedMetaIntegration.last_synced_at || '—' }}
                    </span>
                    <span v-else>لا يوجد ربط Meta محفوظ لهذا العميل حتى الآن.</span>
                </div>
                <p v-if="selectedMetaIntegration?.last_error" class="mt-2 text-xs text-red-700">
                    آخر خطأ من Meta: {{ selectedMetaIntegration.last_error }}
                </p>
                <div v-if="selectedMetaIntegration?.issues?.length" class="mt-3 space-y-2">
                    <div
                        v-for="(issue, idx) in selectedMetaIntegration.issues"
                        :key="`selected-meta-issue-${idx}`"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                    >
                        <p class="font-semibold">{{ issue.title || 'نحتاج خطوة بسيطة منك' }}</p>
                        <p class="mt-1">{{ issue.message }}</p>
                        <a
                            v-if="issue.fix_url"
                            :href="issue.fix_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex rounded-md bg-amber-600 px-2 py-1 text-[11px] font-semibold text-white"
                        >
                            {{ issue.fix_label || 'حل المشكلة' }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="ui-card p-5">
                    <h2 class="text-lg font-semibold text-gray-900">ربط يومي: الحملات مع مبيعات العميل</h2>
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
                        <h2 class="text-lg font-semibold text-gray-900">اتجاه الإيراد مقابل الإنفاق</h2>
                        <span class="text-xs text-gray-500">آخر {{ executive_cards?.window_days || filterForm.days }} يوم</span>
                    </div>
                    <div class="mt-4 rounded-xl border border-gray-200/70 bg-white/70 p-3">
                        <svg viewBox="0 0 560 180" class="h-44 w-full md:h-48">
                            <polyline
                                fill="none"
                                stroke="#e5484d"
                                stroke-width="3"
                                stroke-linecap="round"
                                :points="trendChartPoints.revenue"
                            />
                            <polyline
                                fill="none"
                                stroke="#f59e0b"
                                stroke-width="3"
                                stroke-linecap="round"
                                :points="trendChartPoints.spend"
                            />
                        </svg>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-xs">
                        <span class="inline-flex items-center gap-1 text-gray-700">
                            <span class="h-2 w-2 rounded-full bg-brand-600" />
                            الإيراد
                        </span>
                        <span class="inline-flex items-center gap-1 text-gray-700">
                            <span class="h-2 w-2 rounded-full bg-amber-500" />
                            الإنفاق
                        </span>
                    </div>
                </div>

                <div class="ui-card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">أعمدة ROAS اليومية</h2>
                        <span class="text-xs text-gray-500">حتى آخر 14 يوم</span>
                    </div>
                    <div class="mt-4 flex h-40 items-end gap-1 rounded-xl border border-gray-200/70 bg-white/70 p-3 md:h-48">
                        <div
                            v-for="bar in roasBars"
                            :key="`roas-bar-${bar.date}`"
                            class="group flex min-w-0 flex-1 flex-col items-center justify-end"
                        >
                            <div
                                class="w-full rounded-t bg-brand-500/80 transition-all duration-200 group-hover:bg-brand-500"
                                :style="{ height: bar.height }"
                            />
                            <span class="mt-1 truncate text-[10px] text-gray-500">{{ bar.date.slice(5) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ui-card p-5">
                <h2 class="text-lg font-semibold text-gray-900">أداء المنتجات (حسب الفترة المحددة)</h2>
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
                <h2 class="text-lg font-semibold text-gray-900">أداء الفرق (حسب الفترة المحددة)</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                <th class="px-2 py-2">الفريق</th>
                                <th class="px-2 py-2">الأعضاء</th>
                                <th class="px-2 py-2">Revenue</th>
                                <th class="px-2 py-2">Spend</th>
                                <th class="px-2 py-2">ROAS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in team_performance" :key="`team-${row.slug}`" class="border-b border-gray-100">
                                <td class="px-2 py-2">{{ row.team }}</td>
                                <td class="px-2 py-2">{{ row.members }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-2 py-2">{{ formatMoney(row.ad_spend) }}</td>
                                <td class="px-2 py-2">{{ row.roas ?? '—' }}</td>
                            </tr>
                            <tr v-if="!team_performance?.length">
                                <td class="px-2 py-4 text-center text-gray-500" colspan="5">لا توجد بيانات فرق كافية.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="ui-card p-5">
                    <h2 class="text-lg font-semibold text-gray-900">مبيعات العميل اليومية (من البوابة)</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                    <th class="px-2 py-2">العميل</th>
                                    <th class="px-2 py-2">التاريخ</th>
                                    <th class="px-2 py-2">الطلبات</th>
                                    <th class="px-2 py-2">المبيعات</th>
                                    <th class="px-2 py-2">المصدر</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in sales_rows" :key="`sale-${row.id}`" class="border-b border-gray-100">
                                    <td class="px-2 py-2">{{ row.client }}</td>
                                    <td class="px-2 py-2">{{ row.sales_date }}</td>
                                    <td class="px-2 py-2">{{ row.orders_count }}</td>
                                    <td class="px-2 py-2">{{ formatMoney(row.revenue) }}</td>
                                    <td class="px-2 py-2">{{ row.source === 'portal' ? 'بوابة العميل' : 'داخلي' }}</td>
                                </tr>
                                <tr v-if="!sales_rows?.length">
                                    <td class="px-2 py-4 text-center text-gray-500" colspan="5">لا توجد بيانات مبيعات حالياً.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ui-card p-5">
                    <h2 class="text-lg font-semibold text-gray-900">تحديثات الحملات</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                    <th class="px-2 py-2">العميل</th>
                                    <th class="px-2 py-2">التاريخ</th>
                                    <th class="px-2 py-2">Spend</th>
                                    <th class="px-2 py-2">الرسائل</th>
                                    <th class="px-2 py-2">النقرات</th>
                                    <th class="px-2 py-2">المصدر</th>
                                    <th class="px-2 py-2">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in campaign_rows" :key="`campaign-${row.id}`" class="border-b border-gray-100">
                                    <td class="px-2 py-2">{{ row.client }}</td>
                                    <td class="px-2 py-2">{{ row.report_date }}</td>
                                    <td class="px-2 py-2">{{ formatMoney(row.ad_spend) }}</td>
                                    <td class="px-2 py-2">{{ row.messages_count }}</td>
                                    <td class="px-2 py-2">{{ row.clicks_count }}</td>
                                    <td class="px-2 py-2">{{ row.data_source === 'meta' ? 'Meta API' : 'يدوي' }}</td>
                                    <td class="px-2 py-2">{{ row.actions_taken || '—' }}</td>
                                </tr>
                                <tr v-if="!campaign_rows?.length">
                                    <td class="px-2 py-4 text-center text-gray-500" colspan="7">لا توجد تحديثات حملات حالياً.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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
