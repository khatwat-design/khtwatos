<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PortalProgressTimeline from '@/Components/PortalProgressTimeline.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    client: Object,
    tasks: Array,
    meetings: Array,
    daily_sales: Array,
    products: Array,
    campaign_updates: Array,
    analytics: Object,
    product_analytics: Array,
    active_notes: Array,
    booked: Boolean,
    booking_teams: Array,
    progress_timeline: {
        type: Object,
        default: () => ({}),
    },
});

const showSalesModal = ref(false);
const showMeetingModal = ref(false);
const noteForm = useForm({
    note: '',
});
const salesForm = useForm({
    sales_date: new Date().toISOString().slice(0, 10),
    items: (props.products || []).map((product) => ({
        product_id: product.id,
        quantity: 0,
    })),
    notes: '',
});
const meetingForm = useForm({
    team_slug: props.booking_teams?.[0]?.slug || '',
    selection_mode: 'members',
    participant_ids: [],
    start_at: '',
    project_name: props.client?.company || props.client?.name || '',
    reason: '',
});

const productsMap = computed(() =>
    new Map((props.products || []).map((product) => [Number(product.id), product])),
);

const estimatedTotals = computed(() => {
    let orders = 0;
    let revenue = 0;
    for (const item of salesForm.items || []) {
        const qty = Number(item.quantity || 0);
        if (qty <= 0) continue;
        const product = productsMap.value.get(Number(item.product_id));
        if (!product) continue;
        orders += qty;
        revenue += qty * Number(product.unit_price || 0);
    }
    return { orders, revenue };
});

const selectedTeam = computed(
    () => (props.booking_teams || []).find((t) => t.slug === meetingForm.team_slug) || null,
);

const hosts = computed(() => selectedTeam.value?.members || []);

const selectedMembers = computed(() => {
    if (!hosts.value.length) return [];
    if (meetingForm.selection_mode === 'team') return hosts.value;
    const selectedIds = new Set((meetingForm.participant_ids || []).map((id) => Number(id)));
    return hosts.value.filter((h) => selectedIds.has(Number(h.id)));
});

const availableSlots = computed(() => {
    if (!selectedMembers.value.length) return [];
    const slotMaps = selectedMembers.value.map((member) => {
        const map = new Map();
        for (const slot of member.slots || []) {
            map.set(slot.value, slot.label);
        }
        return map;
    });
    if (!slotMaps.length) return [];

    const firstMap = slotMaps[0];
    const result = [];
    for (const [value, label] of firstMap.entries()) {
        const existsForAll = slotMaps.every((map) => map.has(value));
        if (existsForAll) result.push({ value, label });
    }
    return result;
});

function submitSales() {
    salesForm.post(route('portal.sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showSalesModal.value = false;
            salesForm.reset('notes');
            salesForm.items = (props.products || []).map((product) => ({ product_id: product.id, quantity: 0 }));
        },
    });
}

function submitNote() {
    noteForm.post(route('portal.notes.store'), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

function submitMeeting() {
    meetingForm.post(route('portal.meetings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showMeetingModal.value = false;
            meetingForm.reason = '';
        },
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ar-IQ', { maximumFractionDigits: 2, minimumFractionDigits: 0 }).format(Number(value || 0));
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

watch(
    () => meetingForm.team_slug,
    () => {
        meetingForm.participant_ids = meetingForm.selection_mode === 'team'
            ? hosts.value.map((m) => m.id)
            : (hosts.value[0]?.id ? [hosts.value[0].id] : []);
    },
    { immediate: true },
);

watch(
    () => meetingForm.selection_mode,
    () => {
        meetingForm.participant_ids = meetingForm.selection_mode === 'team'
            ? hosts.value.map((m) => m.id)
            : (hosts.value[0]?.id ? [hosts.value[0].id] : []);
    },
    { immediate: true },
);

watch(
    () => availableSlots.value,
    () => {
        meetingForm.start_at = availableSlots.value[0]?.value || '';
    },
    { immediate: true },
);

function reloadProgressTimeline() {
    router.reload({
        only: ['progress_timeline'],
        preserveScroll: true,
        preserveState: true,
    });
}

let progressPollId = null;

function onVisibilityForTimeline() {
    if (document.visibilityState === 'visible') {
        reloadProgressTimeline();
    }
}

onMounted(() => {
    progressPollId = setInterval(() => {
        if (document.visibilityState === 'visible') {
            reloadProgressTimeline();
        }
    }, 45000);
    document.addEventListener('visibilitychange', onVisibilityForTimeline);
});

onUnmounted(() => {
    if (progressPollId !== null) {
        clearInterval(progressPollId);
    }
    document.removeEventListener('visibilitychange', onVisibilityForTimeline);
});
</script>

<template>
    <PortalLayout title="الرئيسية" :client="client">
        <div class="mx-auto w-full min-w-0 max-w-5xl space-y-4 sm:space-y-6">
            <div
                v-if="booked"
                class="rounded-2xl border border-emerald-200/90 bg-gradient-to-l from-emerald-50 to-white px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm ring-1 ring-emerald-100/60 sm:text-base"
            >
                تم إرسال الحجز بنجاح وربطه بملف العميل في النظام.
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold leading-snug tracking-tight text-slate-900 sm:text-2xl">{{ client.name }}</h1>
                        <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ client.company || '—' }}</p>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-brand-600 px-5 text-sm font-bold text-white shadow-md transition hover:bg-brand-700 sm:w-auto"
                            @click="showSalesModal = true"
                        >
                            إضافة المبيعات
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200/90 bg-white px-5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                            @click="showMeetingModal = true"
                        >
                            حجز اجتماع
                        </button>
                    </div>
                </div>
            </div>

            <PortalProgressTimeline :timeline="progress_timeline" />

            <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">مبيعات اليوم</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ formatMoney(analytics?.today?.revenue) }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">إنفاق اليوم</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ formatMoney(analytics?.today?.ad_spend) }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">ROAS اليوم</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.today?.roas ?? '—' }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">ROAS أسبوعي</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.week?.roas ?? '—' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-5">
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">تحويل أسبوعي</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.week?.conversion_rate ?? '—' }}%</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">CAC أسبوعي</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.week?.cac ?? '—' }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">AOV أسبوعي</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.week?.aov ?? '—' }}</p>
                </div>
                <div class="min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">ROAS شهري</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.month?.roas ?? '—' }}</p>
                </div>
                <div class="col-span-2 min-w-0 rounded-2xl border border-slate-100/90 bg-white/95 p-3 shadow-sm ring-1 ring-slate-100/40 transition hover:shadow-md sm:col-span-1 sm:p-5">
                    <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">تحويل شهري</p>
                    <p class="mt-1 truncate text-lg font-black tabular-nums text-slate-900 sm:text-2xl">{{ analytics?.month?.conversion_rate ?? '—' }}%</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">آخر المبيعات</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="row in daily_sales" :key="row.id" class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 shadow-sm">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-sm font-medium leading-snug">{{ formatDate(row.sales_date) }}</span>
                                <span class="text-sm text-gray-700">{{ row.orders_count }} طلب</span>
                            </div>
                            <p class="mt-1 text-sm font-semibold tabular-nums">{{ formatMoney(row.revenue) }}</p>
                        </li>
                        <li v-if="!daily_sales?.length" class="py-4 text-center text-sm text-slate-500">لا توجد بيانات مبيعات بعد.</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">تحليل الحملات</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="row in campaign_updates" :key="row.id" class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-1">
                                <span class="font-semibold leading-snug">{{ formatDate(row.report_date) }}</span>
                                <span class="text-gray-800">الإنفاق: <span class="tabular-nums">{{ formatMoney(row.ad_spend) }}</span></span>
                                <span class="text-gray-800">الرسائل: <span class="tabular-nums">{{ row.messages_count }}</span></span>
                                <span class="text-gray-800">ROAS: <span class="tabular-nums">{{ row.roas ?? '—' }}</span></span>
                            </div>
                        </li>
                        <li v-if="!campaign_updates?.length" class="py-4 text-center text-sm text-slate-500">لا توجد بيانات حملات بعد.</li>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">تحليل المنتجات (آخر 30 يوم)</h2>

                    <div class="mt-3 hidden md:block">
                        <div class="overflow-x-hidden rounded-xl border border-gray-100">
                            <table class="w-full min-w-0 text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50/80 text-right text-xs text-gray-500">
                                        <th class="px-3 py-2.5 font-medium">المنتج</th>
                                        <th class="px-3 py-2.5 font-medium">الكمية</th>
                                        <th class="px-3 py-2.5 font-medium">الإيراد</th>
                                        <th class="px-3 py-2.5 font-medium">ROAS</th>
                                        <th class="px-3 py-2.5 font-medium">CAC</th>
                                        <th class="px-3 py-2.5 font-medium">التحويل</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in product_analytics" :key="`pa-${row.id}`" class="border-b border-gray-100 last:border-0">
                                        <td class="max-w-[10rem] truncate px-3 py-2.5 font-medium" :title="row.name">{{ row.name }}</td>
                                        <td class="px-3 py-2.5 tabular-nums">{{ row.sold_quantity_30d }}</td>
                                        <td class="px-3 py-2.5 tabular-nums">{{ formatMoney(row.revenue_30d) }}</td>
                                        <td class="px-3 py-2.5 tabular-nums">{{ row.estimated_roas_30d ?? '—' }}</td>
                                        <td class="px-3 py-2.5 tabular-nums">{{ row.estimated_cac_30d ?? '—' }}</td>
                                        <td class="px-3 py-2.5 tabular-nums">{{ row.estimated_conversion_rate_30d ?? '—' }}%</td>
                                    </tr>
                                    <tr v-if="!product_analytics?.length">
                                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">لا توجد بيانات كافية للتحليل.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <ul class="mt-3 space-y-3 md:hidden">
                        <li
                            v-for="row in product_analytics"
                            :key="`pa-m-${row.id}`"
                            class="rounded-xl border border-gray-100 bg-gray-50/90 p-3 text-sm leading-relaxed"
                        >
                            <p class="font-semibold text-gray-900">{{ row.name }}</p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-xs text-gray-700">
                                <div><dt class="text-gray-500">الكمية</dt><dd class="font-medium tabular-nums">{{ row.sold_quantity_30d }}</dd></div>
                                <div><dt class="text-gray-500">الإيراد</dt><dd class="font-medium tabular-nums">{{ formatMoney(row.revenue_30d) }}</dd></div>
                                <div><dt class="text-gray-500">ROAS</dt><dd class="font-medium tabular-nums">{{ row.estimated_roas_30d ?? '—' }}</dd></div>
                                <div><dt class="text-gray-500">CAC</dt><dd class="font-medium tabular-nums">{{ row.estimated_cac_30d ?? '—' }}</dd></div>
                                <div class="col-span-2"><dt class="text-gray-500">التحويل</dt><dd class="font-medium tabular-nums">{{ row.estimated_conversion_rate_30d ?? '—' }}%</dd></div>
                            </dl>
                        </li>
                        <li v-if="!product_analytics?.length" class="py-4 text-center text-sm text-gray-500">لا توجد بيانات كافية للتحليل.</li>
                    </ul>

                    <p class="mt-3 text-xs leading-relaxed text-slate-500">ملاحظة: توزيع الإنفاق على المنتجات في هذا الجدول تقديري حسب حصة الإيراد لكل منتج.</p>
                </div>

                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">ملاحظات للمتابعة</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="submitNote">
                        <textarea
                            v-model="noteForm.note"
                            rows="3"
                            class="block min-h-[5.5rem] w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                            placeholder="اكتب ملاحظة سريعة للفريق..."
                        />
                        <InputError :message="noteForm.errors.note" />
                        <PrimaryButton class="w-full justify-center rounded-xl sm:w-auto" :disabled="noteForm.processing">إضافة ملاحظة</PrimaryButton>
                    </form>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="note in active_notes"
                            :key="`sticky-${note.id}`"
                            class="rounded-xl border border-amber-200/90 bg-amber-50/95 p-3 text-sm text-amber-950 shadow-sm ring-1 ring-amber-100/60"
                        >
                            <p>{{ note.note }}</p>
                            <p class="mt-1 text-[11px] text-amber-700">
                                تنتهي: {{ formatDate(note.expires_at) }}
                            </p>
                        </div>
                        <p v-if="!active_notes?.length" class="text-sm text-slate-500">لا توجد ملاحظات حالية.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">المهام المرتبطة</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="task in tasks" :key="task.id" class="flex flex-col gap-0.5 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 leading-snug sm:flex-row sm:items-baseline sm:gap-2">
                            <span class="font-medium">{{ task.title }}</span>
                            <span class="text-sm text-slate-500">{{ task.column || '—' }}</span>
                        </li>
                        <li v-if="!tasks?.length" class="py-4 text-center text-sm text-slate-500">لا توجد مهام مرتبطة.</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                    <h2 class="text-base font-bold tracking-tight text-slate-900 sm:text-lg">الاجتماعات القادمة</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-for="meeting in meetings" :key="meeting.id" class="flex flex-col gap-0.5 rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-2.5 leading-snug sm:flex-row sm:items-baseline sm:gap-2">
                            <span class="font-medium">{{ meeting.title || 'اجتماع' }}</span>
                            <span class="text-xs text-slate-500 sm:text-sm">{{ formatDate(meeting.start_at) }}</span>
                        </li>
                        <li v-if="!meetings?.length" class="py-4 text-center text-sm text-slate-500">لا توجد اجتماعات قادمة.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            v-if="showSalesModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            @click.self="showSalesModal = false"
        >
            <div
                class="glass-modal portal-light-modal flex max-h-[min(92dvh,56rem)] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:rounded-2xl"
            >
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur-sm sm:px-5 sm:py-4">
                    <h3 class="text-base font-bold leading-snug text-slate-900 sm:text-lg">إضافة المبيعات</h3>
                    <button type="button" class="min-h-10 shrink-0 rounded-xl px-3 text-sm font-medium text-slate-500 transition hover:bg-slate-100" @click="showSalesModal = false">إغلاق</button>
                </div>
                <form class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-y-contain px-4 py-4 sm:px-5 sm:py-5" @submit.prevent="submitSales">
                    <div>
                        <InputLabel for="sales_date" value="تاريخ المبيعات" />
                        <TextInput id="sales_date" v-model="salesForm.sales_date" type="date" class="mt-1 block min-h-11 w-full text-base sm:text-sm" required />
                        <InputError class="mt-1" :message="salesForm.errors.sales_date" />
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-3">
                        <p class="text-sm font-bold text-slate-800">حسب كل منتج</p>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="(item, index) in salesForm.items"
                                :key="`item-${item.product_id}`"
                                class="grid gap-2 rounded-lg border border-slate-100 bg-white p-2 sm:grid-cols-[1fr,120px]"
                            >
                                <div class="text-sm">
                                    <p class="font-medium">{{ productsMap.get(Number(item.product_id))?.name }}</p>
                                    <p class="text-xs text-slate-500">السعر: {{ formatMoney(productsMap.get(Number(item.product_id))?.unit_price) }}</p>
                                </div>
                                <TextInput :id="`qty-${item.product_id}`" v-model="salesForm.items[index].quantity" type="number" min="0" class="block min-h-11 w-full text-base sm:text-sm" />
                            </div>
                        </div>
                        <InputError class="mt-2" :message="salesForm.errors.items" />
                        <p class="mt-2 text-xs font-medium text-slate-600">الإجمالي: {{ estimatedTotals.orders }} طلب · {{ formatMoney(estimatedTotals.revenue) }}</p>
                    </div>
                    <div>
                        <InputLabel for="notes" value="ملاحظات (اختياري)" />
                        <textarea id="notes" v-model="salesForm.notes" rows="3" class="mt-1 block min-h-[5.5rem] w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <button type="button" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto" @click="showSalesModal = false">إلغاء</button>
                        <PrimaryButton class="w-full justify-center sm:w-auto" :disabled="salesForm.processing || !products?.length">حفظ المبيعات</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showMeetingModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            @click.self="showMeetingModal = false"
        >
            <div
                class="glass-modal portal-light-modal flex max-h-[min(92dvh,56rem)] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:rounded-2xl"
            >
                <div class="flex shrink-0 flex-col gap-1 border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur-sm sm:px-5 sm:py-4">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-bold leading-snug text-slate-900 sm:text-lg">حجز اجتماع مع الفريق</h3>
                        <button type="button" class="min-h-10 shrink-0 rounded-xl px-3 text-sm font-medium text-slate-500 transition hover:bg-slate-100" @click="showMeetingModal = false">إغلاق</button>
                    </div>
                    <p class="text-xs leading-relaxed text-slate-600">
                        بيانات العميل تُربط تلقائيًا بملفه، ولا حاجة لإدخال الاسم والبريد كل مرة.
                    </p>
                </div>
                <form class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-y-contain px-4 py-4 sm:px-5 sm:py-5" @submit.prevent="submitMeeting">
                    <div>
                        <InputLabel for="meeting_team_slug" value="القسم" />
                        <select
                            id="meeting_team_slug"
                            v-model="meetingForm.team_slug"
                            class="mt-1 block min-h-11 w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                            required
                        >
                            <option
                                v-for="team in booking_teams"
                                :key="`portal-booking-team-${team.slug}`"
                                :value="team.slug"
                            >
                                {{ team.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="meetingForm.errors.team_slug" />
                    </div>
                    <div>
                        <InputLabel for="meeting_selection_mode" value="طريقة اختيار الحضور" />
                        <select
                            id="meeting_selection_mode"
                            v-model="meetingForm.selection_mode"
                            class="mt-1 block min-h-11 w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                            required
                        >
                            <option value="members">اختيار موظفين محددين</option>
                            <option value="team">اختيار القسم بالكامل</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="meeting_participant_ids" :value="meetingForm.selection_mode === 'team' ? 'حضور الاجتماع (القسم بالكامل)' : 'الموظفون المشاركون'" />
                        <select
                            id="meeting_participant_ids"
                            v-model="meetingForm.participant_ids"
                            class="mt-1 block min-h-11 w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                            :multiple="meetingForm.selection_mode !== 'team'"
                            :size="meetingForm.selection_mode !== 'team' ? 6 : 1"
                            :disabled="meetingForm.selection_mode === 'team'"
                            required
                        >
                            <option
                                v-for="m in hosts"
                                :key="`portal-booking-member-${m.id}`"
                                :value="m.id"
                            >
                                {{ m.name }}{{ m.is_lead || m.role === 'lead' ? ' • مدير قسم' : '' }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="meetingForm.errors.participant_ids" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="meeting_start_at" value="موعد الاجتماع المتاح" />
                            <select
                                id="meeting_start_at"
                                v-model="meetingForm.start_at"
                                class="mt-1 block min-h-11 w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                                required
                            >
                                <option
                                    v-for="slot in availableSlots"
                                    :key="`portal-slot-${slot.value}`"
                                    :value="slot.value"
                                >
                                    {{ slot.label }}
                                </option>
                            </select>
                            <p
                                v-if="!availableSlots.length"
                                class="mt-1 text-xs text-amber-700"
                            >
                                لا توجد أوقات مشتركة متاحة حالياً للموظفين المحددين.
                            </p>
                            <InputError class="mt-1" :message="meetingForm.errors.start_at" />
                        </div>
                        <div>
                            <InputLabel for="meeting_project_name" value="اسم المشروع" />
                            <TextInput
                                id="meeting_project_name"
                                v-model="meetingForm.project_name"
                                type="text"
                                class="mt-1 block min-h-11 w-full text-base sm:text-sm"
                                required
                            />
                            <InputError class="mt-1" :message="meetingForm.errors.project_name" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="meeting_reason" value="سبب الاجتماع / ملاحظات" />
                        <textarea
                            id="meeting_reason"
                            v-model="meetingForm.reason"
                            rows="3"
                            class="mt-1 block min-h-[5.5rem] w-full rounded-xl border-slate-200 text-base shadow-sm sm:text-sm"
                        />
                        <InputError class="mt-1" :message="meetingForm.errors.reason" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <button type="button" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 sm:w-auto" @click="showMeetingModal = false">إلغاء</button>
                        <PrimaryButton class="w-full justify-center sm:w-auto" :disabled="meetingForm.processing || !availableSlots.length">تأكيد الحجز</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
</template>

<style scoped>
.glass-modal {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    animation: modal-in 220ms ease-out;
}

.portal-light-modal {
    color: #0f172a;
}

.portal-light-modal :deep(input:not([type='checkbox']):not([type='radio'])),
.portal-light-modal :deep(select),
.portal-light-modal :deep(textarea),
.portal-light-modal :deep(option) {
    color: #0f172a;
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
