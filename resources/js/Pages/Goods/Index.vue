<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    customers: Array,
    owners: Array,
    contacts: Array,
    status_options: Array,
    filters: Object,
});

const showCreateModal = ref(false);
const customerSearch = ref('');
const page = usePage();

const createForm = useForm({
    outside_contact_id: null,
    name: '',
    phone: '',
    company: '',
    status: props.status_options?.[0]?.value || 'lead',
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
    router.get(route('goods.index'), { status }, { preserveState: true, replace: true });
}

function submitCreate() {
    createForm.post(route('goods.customers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            createForm.status = props.status_options?.[0]?.value || 'lead';
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
    if (status === 'lead') return 'bg-amber-100 text-amber-700';
    if (status === 'prospect') return 'bg-blue-100 text-blue-700';
    if (status === 'active') return 'bg-emerald-100 text-emerald-700';
    if (status === 'paused') return 'bg-slate-100 text-slate-700';
    return 'bg-rose-100 text-rose-700';
}
</script>

<template>
    <Head title="البضاعة" />

    <AuthenticatedLayout>
        <template #title>البضاعة</template>

        <div class="mx-auto max-w-7xl space-y-4">
            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error || reminderForm.errors.goods_reminder" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {{ page.props.flash?.error || reminderForm.errors.goods_reminder }}
            </div>
            <div class="flex items-center justify-between gap-2">
                <div class="flex flex-1 items-center gap-2">
                    <select
                        class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm"
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
                        class="block w-full max-w-xs"
                        placeholder="بحث بالاسم/الهاتف/الشركة..."
                    />
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white hover:bg-brand-700"
                    title="إضافة عميل بضاعة"
                    @click="showCreateModal = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-white/20 bg-white/80 shadow">
                <table class="min-w-full text-sm">
                    <thead class="bg-white">
                        <tr class="border-b border-slate-200 text-right text-xs text-gray-500">
                            <th class="px-3 py-2">الاسم</th>
                            <th class="px-3 py-2">الهاتف</th>
                            <th class="px-3 py-2">الشركة</th>
                            <th class="px-3 py-2">الحالة</th>
                            <th class="px-3 py-2">المسؤول</th>
                            <th class="px-3 py-2">تحويل الحالة</th>
                            <th class="px-3 py-2">تذكير المبيعات</th>
                            <th class="px-3 py-2">استبيان أسبوعي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="customer in filteredCustomers" :key="customer.id" class="border-b border-slate-100">
                            <td class="px-3 py-2 font-semibold text-gray-900">{{ customer.name }}</td>
                            <td class="px-3 py-2" dir="ltr">{{ customer.phone || '—' }}</td>
                            <td class="px-3 py-2">{{ customer.company || '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusClass(customer.status)">
                                    {{ statusLabel(customer.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ customer.owner?.name || '—' }}</td>
                            <td class="px-3 py-2">
                                <select
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs"
                                    :value="customer.status"
                                    @change="updateStatus(customer, $event.target.value)"
                                >
                                    <option v-for="status in status_options" :key="`status-${customer.id}-${status.value}`" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-brand-300 bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100 disabled:opacity-60"
                                    :disabled="reminderForm.processing || !customer.contact?.id"
                                    @click="sendSalesReminder(customer)"
                                >
                                    إرسال تذكير
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                                    :disabled="reminderForm.processing || !customer.contact?.id"
                                    @click="sendWeeklySurvey(customer)"
                                >
                                    إرسال استبيان
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredCustomers?.length">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">لا يوجد عملاء مطابقون للبحث/الفلترة.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showCreateModal = false"
        >
            <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">إضافة عميل لقسم البضاعة</h3>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" @click="showCreateModal = false">
                        إغلاق
                    </button>
                </div>
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitCreate">
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
                    <div class="md:col-span-2 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing">حفظ</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

