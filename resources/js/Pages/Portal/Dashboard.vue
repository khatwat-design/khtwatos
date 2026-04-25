<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
</script>

<template>
    <PortalLayout title="الرئيسية" :client="client">
        <div class="mx-auto max-w-6xl space-y-6">
            <div v-if="booked" class="rounded-2xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-emerald-800">
                تم إرسال الحجز بنجاح وربطه بملف العميل في النظام.
            </div>

            <div class="ui-card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ client.name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ client.company || '—' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                            @click="showSalesModal = true"
                        >
                            إضافة المبيعات
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            @click="showMeetingModal = true"
                        >
                            حجز اجتماع
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-4">
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">مبيعات اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ formatMoney(analytics?.today?.revenue) }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">إنفاق اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ formatMoney(analytics?.today?.ad_spend) }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">ROAS اليوم</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.today?.roas ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">ROAS أسبوعي</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.week?.roas ?? '—' }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-5">
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">تحويل أسبوعي</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.week?.conversion_rate ?? '—' }}%</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">CAC أسبوعي</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.week?.cac ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">AOV أسبوعي</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.week?.aov ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">ROAS شهري</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.month?.roas ?? '—' }}</p>
                </div>
                <div class="ui-card ui-card-hover p-4">
                    <p class="text-xs text-gray-500">تحويل شهري</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ analytics?.month?.conversion_rate ?? '—' }}%</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">آخر المبيعات</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="row in daily_sales" :key="row.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ formatDate(row.sales_date) }}</span>
                                <span>{{ row.orders_count }} طلب</span>
                            </div>
                            <p class="mt-1">{{ formatMoney(row.revenue) }}</p>
                        </li>
                        <li v-if="!daily_sales?.length" class="text-gray-500">لا توجد بيانات مبيعات بعد.</li>
                    </ul>
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">تحليل الحملات</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="row in campaign_updates" :key="row.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-semibold">{{ formatDate(row.report_date) }}</span>
                                <span>الإنفاق: {{ formatMoney(row.ad_spend) }}</span>
                                <span>الرسائل: {{ row.messages_count }}</span>
                                <span>ROAS: {{ row.roas ?? '—' }}</span>
                            </div>
                        </li>
                        <li v-if="!campaign_updates?.length" class="text-gray-500">لا توجد بيانات حملات بعد.</li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">تحليل المنتجات (آخر 30 يوم)</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                    <th class="px-2 py-2">المنتج</th>
                                    <th class="px-2 py-2">الكمية</th>
                                    <th class="px-2 py-2">الإيراد</th>
                                    <th class="px-2 py-2">ROAS</th>
                                    <th class="px-2 py-2">CAC</th>
                                    <th class="px-2 py-2">التحويل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in product_analytics" :key="`pa-${row.id}`" class="border-b border-gray-100">
                                    <td class="px-2 py-2">{{ row.name }}</td>
                                    <td class="px-2 py-2">{{ row.sold_quantity_30d }}</td>
                                    <td class="px-2 py-2">{{ formatMoney(row.revenue_30d) }}</td>
                                    <td class="px-2 py-2">{{ row.estimated_roas_30d ?? '—' }}</td>
                                    <td class="px-2 py-2">{{ row.estimated_cac_30d ?? '—' }}</td>
                                    <td class="px-2 py-2">{{ row.estimated_conversion_rate_30d ?? '—' }}%</td>
                                </tr>
                                <tr v-if="!product_analytics?.length">
                                    <td colspan="6" class="px-2 py-4 text-center text-gray-500">لا توجد بيانات كافية للتحليل.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">ملاحظة: توزيع الإنفاق على المنتجات في هذا الجدول تقديري حسب حصة الإيراد لكل منتج.</p>
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">ملاحظات للمتابعة</h2>
                    <form class="mt-3 space-y-2" @submit.prevent="submitNote">
                        <textarea
                            v-model="noteForm.note"
                            rows="3"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            placeholder="اكتب ملاحظة سريعة للفريق..."
                        />
                        <InputError :message="noteForm.errors.note" />
                        <PrimaryButton :disabled="noteForm.processing">إضافة ملاحظة</PrimaryButton>
                    </form>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="note in active_notes"
                            :key="`sticky-${note.id}`"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 shadow-sm"
                        >
                            <p>{{ note.note }}</p>
                            <p class="mt-1 text-[11px] text-amber-700">
                                تنتهي: {{ formatDate(note.expires_at) }}
                            </p>
                        </div>
                        <p v-if="!active_notes?.length" class="text-sm text-gray-500">لا توجد ملاحظات حالية.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">المهام المرتبطة</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="task in tasks" :key="task.id" class="rounded border border-gray-100 px-3 py-2">
                            <span class="font-medium">{{ task.title }}</span>
                            <span class="text-gray-500"> · {{ task.column || '—' }}</span>
                        </li>
                        <li v-if="!tasks?.length" class="text-gray-500">لا توجد مهام مرتبطة.</li>
                    </ul>
                </div>

                <div class="ui-card p-6">
                    <h2 class="text-lg font-semibold">الاجتماعات القادمة</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="meeting in meetings" :key="meeting.id" class="rounded border border-gray-100 px-3 py-2">
                            <span class="font-medium">{{ meeting.title || 'اجتماع' }}</span>
                            <span class="text-gray-500"> · {{ formatDate(meeting.start_at) }}</span>
                        </li>
                        <li v-if="!meetings?.length" class="text-gray-500">لا توجد اجتماعات قادمة.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            v-if="showSalesModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showSalesModal = false"
        >
            <div class="glass-modal portal-light-modal w-full max-w-2xl p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">نافذة إضافة المبيعات</h3>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="showSalesModal = false">إغلاق</button>
                </div>
                <form class="mt-4 space-y-3" @submit.prevent="submitSales">
                    <div>
                        <InputLabel for="sales_date" value="تاريخ المبيعات" />
                        <TextInput id="sales_date" v-model="salesForm.sales_date" type="date" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="salesForm.errors.sales_date" />
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-800">حسب كل منتج</p>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="(item, index) in salesForm.items"
                                :key="`item-${item.product_id}`"
                                class="grid gap-2 rounded-md bg-gray-50 p-2 sm:grid-cols-[1fr,120px]"
                            >
                                <div class="text-sm">
                                    <p class="font-medium">{{ productsMap.get(Number(item.product_id))?.name }}</p>
                                    <p class="text-xs text-gray-500">السعر: {{ formatMoney(productsMap.get(Number(item.product_id))?.unit_price) }}</p>
                                </div>
                                <TextInput :id="`qty-${item.product_id}`" v-model="salesForm.items[index].quantity" type="number" min="0" class="block w-full" />
                            </div>
                        </div>
                        <InputError class="mt-2" :message="salesForm.errors.items" />
                        <p class="mt-2 text-xs text-gray-600">الإجمالي: {{ estimatedTotals.orders }} طلب · {{ formatMoney(estimatedTotals.revenue) }}</p>
                    </div>
                    <div>
                        <InputLabel for="notes" value="ملاحظات (اختياري)" />
                        <textarea id="notes" v-model="salesForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @click="showSalesModal = false">إلغاء</button>
                        <PrimaryButton :disabled="salesForm.processing || !products?.length">حفظ المبيعات</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showMeetingModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showMeetingModal = false"
        >
            <div class="glass-modal portal-light-modal w-full max-w-2xl p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">حجز اجتماع مع الفريق</h3>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="showMeetingModal = false">إغلاق</button>
                </div>
                <p class="mt-1 text-xs text-gray-600">
                    بيانات العميل تُربط تلقائيًا بملفه، ولا حاجة لإدخال الاسم والبريد كل مرة.
                </p>
                <form class="mt-4 space-y-3" @submit.prevent="submitMeeting">
                    <div>
                        <InputLabel for="meeting_team_slug" value="القسم" />
                        <select
                            id="meeting_team_slug"
                            v-model="meetingForm.team_slug"
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="meeting_start_at" value="موعد الاجتماع المتاح" />
                            <select
                                id="meeting_start_at"
                                v-model="meetingForm.start_at"
                                class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                                class="mt-1 block w-full"
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
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                        />
                        <InputError class="mt-1" :message="meetingForm.errors.reason" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @click="showMeetingModal = false">إلغاء</button>
                        <PrimaryButton :disabled="meetingForm.processing || !availableSlots.length">تأكيد الحجز</PrimaryButton>
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

.portal-light-modal,
.portal-light-modal :deep(*) {
    color: #111111;
}

.portal-light-modal :deep(input),
.portal-light-modal :deep(select),
.portal-light-modal :deep(textarea) {
    color: #111111 !important;
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
