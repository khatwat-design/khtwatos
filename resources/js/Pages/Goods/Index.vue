<script setup>
import GoodsMetaLeadsPanel from '@/Components/Goods/GoodsMetaLeadsPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    active_tab: { type: String, default: 'customers' },
    customers: Array,
    owners: Array,
    contacts: Array,
    status_options: Array,
    filters: Object,
    meta_leads: { type: Array, default: () => [] },
    meta_lead_status_options: { type: Array, default: () => [] },
    meta_filters: { type: Object, default: () => ({}) },
    meta_campaign_options: { type: Array, default: () => [] },
    meta_analytics: { type: Object, default: () => ({}) },
    meta_assignee_stats: { type: Array, default: () => [] },
    meta_leads_webhook_configured: { type: Boolean, default: false },
});

const showCreateModal = ref(false);
const customerSearch = ref('');
const page = usePage();

const createForm = useForm({
    outside_contact_id: null,
    name: '',
    phone: '',
    company: '',
    status: props.status_options?.[0]?.value || 'new',
    owner_user_id: null,
    notes: '',
});

const statusForm = useForm({
    status: '',
    note: '',
});
const reminderForm = useForm({
    body: '',
});
const filteredCustomers = computed(() => {
    const keyword = customerSearch.value.trim().toLowerCase();
    if (!keyword) return props.customers || [];
    return (props.customers || []).filter((customer) => {
        const name = String(customer.name || '').toLowerCase();
        const phone = String(customer.phone || '').toLowerCase();
        const company = String(customer.company || '').toLowerCase();
        return name.includes(keyword) || phone.includes(keyword) || company.includes(keyword);
    });
});

function applyStatusFilter(event) {
    const status = event.target.value || undefined;
    router.get(route('goods.index'), { tab: 'customers', status }, { preserveState: true, replace: true });
}

function switchTab(tab) {
    if (tab === props.active_tab) {
        return;
    }
    router.get(route('goods.index'), { tab }, { preserveState: true, replace: true });
}

function submitCreate() {
    createForm.post(route('goods.customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            createForm.status = props.status_options?.[0]?.value || 'new';
        },
    });
}

function updateStatus(customer, status) {
    if (!customer?.id || !status || customer.status === status) {
        return;
    }
    statusForm.status = status;
    statusForm.note = '';
    statusForm.patch(route('goods.customers.status', customer.id), {
        preserveScroll: true,
    });
}

function sendSalesReminder(customer) {
    if (!customer?.id) return;
    reminderForm.body = '';
    reminderForm.post(route('goods.customers.sales-reminder', customer.id), {
        preserveScroll: true,
    });
}

function sendWeeklySurvey(customer) {
    if (!customer?.id) return;
    reminderForm.body = '';
    reminderForm.post(route('goods.customers.weekly-survey', customer.id), {
        preserveScroll: true,
    });
}

function statusLabel(status) {
    return props.status_options?.find((item) => item.value === status)?.label || status;
}

function statusClass(status) {
    if (status === 'new') return 'bg-emerald-100 text-emerald-800';
    if (status === 'potential') return 'bg-amber-100 text-amber-800';
    if (status === 'unlikely') return 'bg-rose-100 text-rose-800';
    if (status === 'qualified') return 'bg-sky-100 text-sky-800';
    if (status === 'active') return 'bg-emerald-100 text-emerald-700';
    if (status === 'paused') return 'bg-slate-100 text-slate-700';
    return 'bg-rose-100 text-rose-700';
}
</script>

<template>
    <Head title="البضاعة" />

    <AuthenticatedLayout>
        <template #title>البضاعة</template>

        <div class="mx-auto w-full min-w-0 max-w-7xl space-y-4 px-3 pb-8 sm:px-4">
            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error || reminderForm.errors.goods_reminder" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {{ page.props.flash?.error || reminderForm.errors.goods_reminder }}
            </div>

            <div class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="active_tab === 'customers' ? 'bg-brand-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                    @click="switchTab('customers')"
                >
                    عملاء البضاعة
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition"
                    :class="active_tab === 'meta_leads' ? 'bg-brand-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                    @click="switchTab('meta_leads')"
                >
                    ليدز ميتا
                </button>
            </div>

            <template v-if="active_tab === 'customers'">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-stretch sm:justify-between sm:gap-2">
                <div class="flex w-full flex-col gap-2 sm:flex-1 sm:flex-row sm:items-center sm:gap-2">
                    <select
                        class="min-h-11 w-full shrink-0 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm sm:max-w-[11rem]"
                        :value="filters?.status || ''"
                        @change="applyStatusFilter"
                    >
                        <option value="">كل الحالات</option>
                        <option v-for="status in status_options" :key="`filter-${status.value}`" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                    <TextInput
                        v-model="customerSearch"
                        class="block min-h-11 w-full flex-1 rounded-xl border-gray-300 text-sm"
                        placeholder="بحث بالاسم/الهاتف/الشركة..."
                    />
                </div>
                <button
                    type="button"
                    class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 sm:w-auto sm:px-3"
                    title="إضافة عميل بضاعة"
                    @click="showCreateModal = true"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    <span class="sm:hidden">إضافة عميل بضاعة</span>
                </button>
            </div>

            <!-- موبايل: بطاقات (نفس أسلوب العملاء) -->
            <div class="space-y-3 md:hidden">
                <article
                    v-for="customer in filteredCustomers"
                    :key="`goods-card-${customer.id}`"
                    class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm ring-1 ring-gray-100/80"
                >
                    <div class="flex gap-3 p-4">
                        <div class="relative shrink-0">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-100 bg-gradient-to-br from-brand-50 to-white text-lg font-black text-brand-700 shadow-inner ring-2 ring-white"
                            >
                                {{ String(customer.name || '?').trim().charAt(0) || '؟' }}
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold leading-snug text-gray-900">
                                        {{ customer.name }}
                                    </h3>
                                    <p v-if="customer.company" class="mt-0.5 truncate text-xs text-gray-500">
                                        {{ customer.company }}
                                    </p>
                                    <p class="mt-1 font-mono text-xs text-gray-600" dir="ltr">{{ customer.phone || '—' }}</p>
                                </div>
                            </div>
                            <span
                                class="mt-2 inline-flex max-w-full items-center rounded-lg px-2.5 py-1 text-[11px] font-semibold leading-tight ring-1"
                                :class="statusClass(customer.status)"
                            >
                                {{ statusLabel(customer.status) }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-t border-gray-50 bg-gradient-to-b from-gray-50/40 to-white px-3 pb-2 pt-2">
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">المسؤول</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-800">{{ customer.owner?.name || '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">الحالة</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-800">{{ statusLabel(customer.status) }}</p>
                        </div>
                    </div>
                    <div class="space-y-3 border-t border-gray-100 px-3 pb-4 pt-3">
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-gray-600">تحويل الحالة</label>
                            <select
                                class="min-h-11 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm"
                                :value="customer.status"
                                @change="updateStatus(customer, $event.target.value)"
                            >
                                <option v-for="status in status_options" :key="`m-status-${customer.id}-${status.value}`" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <button
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-brand-300 bg-brand-50 px-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100 disabled:opacity-60"
                                :disabled="reminderForm.processing || !customer.contact?.id"
                                @click="sendSalesReminder(customer)"
                            >
                                إرسال تذكير مبيعات
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-3 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100 disabled:opacity-60"
                                :disabled="reminderForm.processing || !customer.contact?.id"
                                @click="sendWeeklySurvey(customer)"
                            >
                                إرسال استبيان أسبوعي
                            </button>
                        </div>
                        <Link
                            v-if="customer.client?.id"
                            :href="route('clients.show', customer.client.id)"
                            class="inline-flex min-h-10 w-full items-center justify-center rounded-xl border border-gray-200 bg-white text-sm font-semibold text-brand-700 transition hover:bg-gray-50"
                        >
                            فتح ملف العميل
                        </Link>
                    </div>
                </article>
                <p
                    v-if="!filteredCustomers?.length"
                    class="rounded-2xl border border-dashed border-gray-200 bg-white/80 px-4 py-10 text-center text-sm text-gray-500"
                >
                    لا يوجد عملاء بضاعة مطابقون للبحث أو الفلترة.
                </p>
            </div>

            <!-- سطح المكتب والتابلت: جدول -->
            <div class="ui-card hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white/90">
                        <tr>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">الاسم</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">الهاتف</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">الشركة</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">الحالة</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">المسؤول</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">تحويل الحالة</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">تذكير المبيعات</th>
                            <th class="px-4 py-2.5 text-start text-xs font-medium text-gray-500">استبيان أسبوعي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="customer in filteredCustomers" :key="customer.id" class="align-top">
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ customer.name }}
                                <Link
                                    v-if="customer.client?.id"
                                    :href="route('clients.show', customer.client.id)"
                                    class="mt-1 inline-block text-xs font-medium text-brand-600 underline decoration-brand-600/30 hover:text-brand-700"
                                >
                                    ملف العميل
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-gray-700" dir="ltr">{{ customer.phone || '—' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ customer.company || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusClass(customer.status)">
                                    {{ statusLabel(customer.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ customer.owner?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <select
                                    class="min-h-9 w-full max-w-[10rem] rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-gray-900"
                                    :value="customer.status"
                                    @change="updateStatus(customer, $event.target.value)"
                                >
                                    <option v-for="status in status_options" :key="`status-${customer.id}-${status.value}`" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    class="rounded-lg border border-brand-300 bg-brand-50 px-2 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 disabled:opacity-60"
                                    :disabled="reminderForm.processing || !customer.contact?.id"
                                    @click="sendSalesReminder(customer)"
                                >
                                    إرسال تذكير
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    class="rounded-lg border border-indigo-300 bg-indigo-50 px-2 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                                    :disabled="reminderForm.processing || !customer.contact?.id"
                                    @click="sendWeeklySurvey(customer)"
                                >
                                    إرسال استبيان
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredCustomers?.length">
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                لا يوجد عملاء بضاعة مطابقون للبحث أو الفلترة.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </template>

            <GoodsMetaLeadsPanel
                v-else
                :meta_leads="meta_leads"
                :meta_lead_status_options="meta_lead_status_options"
                :meta_filters="meta_filters"
                :meta_campaign_options="meta_campaign_options"
                :meta_analytics="meta_analytics"
                :meta_assignee_stats="meta_assignee_stats"
                :owners="owners"
                :meta_leads_webhook_configured="meta_leads_webhook_configured"
            />
        </div>

        <div
            v-if="showCreateModal && active_tab === 'customers'"
            class="mobile-sheet-backdrop"
            @click.self="showCreateModal = false"
        >
            <div
                class="mobile-sheet-panel mobile-sheet-panel--md sm:p-5"
            >
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3 sm:border-0 sm:px-0 sm:pb-4 sm:pt-0">
                    <h3 class="text-base font-bold text-gray-900 sm:text-lg">إضافة عميل لقسم البضاعة</h3>
                    <button
                        type="button"
                        class="min-h-10 rounded-xl px-3 text-sm font-medium text-gray-600 hover:bg-gray-100"
                        @click="showCreateModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <form class="grid grid-cols-1 gap-3 px-4 pb-6 pt-2 sm:px-0 sm:pb-0 sm:pt-0 md:grid-cols-2" @submit.prevent="submitCreate">
                    <div class="md:col-span-2">
                        <InputLabel for="goods_contact_id" value="ربط بجهة من قسم الخارج (اختياري)" />
                        <select
                            id="goods_contact_id"
                            v-model="createForm.outside_contact_id"
                            class="mt-1 block w-full rounded-md border-gray-300"
                        >
                            <option :value="null">بدون ربط</option>
                            <option v-for="contact in contacts" :key="`contact-${contact.id}`" :value="contact.id">
                                {{ contact.name || 'بدون اسم' }} - {{ contact.phone }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="createForm.errors.outside_contact_id" />
                    </div>
                    <div>
                        <InputLabel for="goods_name" value="الاسم" />
                        <TextInput id="goods_name" v-model="createForm.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="createForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="goods_phone" value="الهاتف" />
                        <TextInput id="goods_phone" v-model="createForm.phone" class="mt-1 block w-full" dir="ltr" />
                    </div>
                    <div>
                        <InputLabel for="goods_company" value="الشركة" />
                        <TextInput id="goods_company" v-model="createForm.company" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="goods_status" value="الحالة الابتدائية" />
                        <select id="goods_status" v-model="createForm.status" class="mt-1 block w-full rounded-md border-gray-300">
                            <option v-for="status in status_options" :key="`create-status-${status.value}`" :value="status.value">
                                {{ status.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="createForm.errors.status" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="goods_owner" value="المسؤول" />
                        <select id="goods_owner" v-model="createForm.owner_user_id" class="mt-1 block w-full rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <option v-for="owner in owners" :key="`owner-${owner.id}`" :value="owner.id">{{ owner.name }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="goods_notes" value="ملاحظات" />
                        <textarea id="goods_notes" v-model="createForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300" />
                    </div>
                    <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:justify-end md:col-span-2">
                        <PrimaryButton class="w-full justify-center sm:w-auto" :disabled="createForm.processing">حفظ</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

