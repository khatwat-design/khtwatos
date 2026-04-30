<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    clients: Array,
    stages: Array,
    accountManagers: Array,
    campaignManagers: Array,
    filters: Object,
});
const canDeleteRecords = Boolean(usePage().props.auth?.can?.deleteRecords);
const showFilterModal = ref(false);
const showCreateModal = ref(false);

const filterForm = useForm({
    stage_id: props.filters?.stage_id ?? '',
});

const createForm = useForm({
    name: '',
    company: '',
    email: '',
    phone: '',
    notes: '',
    account_manager_id: null,
    campaign_manager_id: null,
    current_pipeline_stage_id: props.stages?.[0]?.id ?? null,
});

function setStageFilter(event) {
    const raw = event.target.value;
    const stageId = raw === '' ? undefined : Number(raw);
    router.get(route('clients.index'), { stage_id: stageId }, { preserveState: true, replace: true });
}

function applyFilterModal() {
    const stageId = filterForm.stage_id === '' ? undefined : Number(filterForm.stage_id);
    router.get(route('clients.index'), { stage_id: stageId }, { preserveState: true, replace: true });
    showFilterModal.value = false;
}

function submitCreateClient() {
    createForm.post(route('clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.current_pipeline_stage_id = props.stages?.[0]?.id ?? null;
            showCreateModal.value = false;
        },
    });
}

function deleteClient(clientId) {
    if (!canDeleteRecords) {
        return;
    }
    if (!confirm('حذف هذا العميل؟ سيتم حذف كل بياناته المرتبطة.')) {
        return;
    }
    router.delete(route('clients.destroy', clientId), { preserveScroll: true });
}
</script>

<template>
    <Head title="العملاء" />

    <AuthenticatedLayout>
        <template #title>العملاء</template>

        <div class="mx-auto max-w-6xl space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        @click="showFilterModal = true"
                    >
                        فلترة
                    </button>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white hover:bg-brand-700"
                    title="إضافة عميل"
                    @click="showCreateModal = true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            </div>

            <div class="ui-card overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white/90">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                العميل
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                المرحلة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                مدير الحساب
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                مدير الحملة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                مهام مفتوحة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-900">
                                آخر تحديث
                            </th>
                            <th v-if="canDeleteRecords" class="px-4 py-2 text-start font-medium text-gray-900">
                                إجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in clients" :key="c.id">
                            <td class="px-4 py-2">
                                <Link
                                    :href="route('clients.show', c.id)"
                                    class="inline-flex items-center gap-2 font-medium text-brand-600 hover:underline"
                                >
                                    <img
                                        :src="c.logo_url || '/images/mobile-logo.png'"
                                        alt="شعار العميل"
                                        class="h-8 w-8 rounded-lg border border-gray-200 object-cover"
                                    />
                                    {{ c.name }}
                                </Link>
                                <div v-if="c.company" class="text-xs text-gray-500">
                                    {{ c.company }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ c.current_stage?.label || '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ c.account_manager?.name || '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ c.campaign_manager?.name || '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-800">
                                {{ c.open_tasks_count }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                {{
                                    new Date(c.updated_at).toLocaleDateString(
                                        'ar-SA',
                                    )
                                }}
                            </td>
                            <td v-if="canDeleteRecords" class="whitespace-nowrap px-4 py-2">
                                <button
                                    type="button"
                                    class="text-sm text-red-700 hover:underline"
                                    @click="deleteClient(c.id)"
                                >
                                    حذف
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!clients.length">
                            <td :colspan="canDeleteRecords ? 7 : 6" class="px-4 py-8 text-center text-gray-500">
                                لا يوجد عملاء بعد.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="showFilterModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showFilterModal = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">فلترة العملاء</h3>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" @click="showFilterModal = false">
                        إغلاق
                    </button>
                </div>
                <form class="space-y-3" @submit.prevent="applyFilterModal">
                    <div>
                        <InputLabel for="stage_filter_modal" value="المرحلة" />
                        <select
                            id="stage_filter_modal"
                            v-model="filterForm.stage_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-black shadow-sm"
                        >
                            <option value="">كل المراحل</option>
                            <option v-for="stage in stages || []" :key="`f-stage-${stage.id}`" :value="stage.id">
                                {{ stage.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <PrimaryButton type="submit">تطبيق</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showCreateModal = false"
        >
            <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">إضافة عميل</h3>
                    <button type="button" class="rounded-lg px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" @click="showCreateModal = false">
                        إغلاق
                    </button>
                </div>
                <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitCreateClient">
                    <div class="md:col-span-2">
                        <InputLabel for="new_client_name" value="الاسم" />
                        <TextInput id="new_client_name" v-model="createForm.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="createForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="new_client_company" value="الشركة" />
                        <TextInput id="new_client_company" v-model="createForm.company" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="new_client_email" value="البريد الإلكتروني" />
                        <TextInput id="new_client_email" v-model="createForm.email" type="email" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="new_client_phone" value="الهاتف" />
                        <TextInput id="new_client_phone" v-model="createForm.phone" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel for="new_client_stage" value="المرحلة الابتدائية" />
                        <select
                            id="new_client_stage"
                            v-model="createForm.current_pipeline_stage_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-black shadow-sm"
                            required
                        >
                            <option v-for="s in stages || []" :key="`c-stage-${s.id}`" :value="s.id">{{ s.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="createForm.errors.current_pipeline_stage_id" />
                    </div>
                    <div>
                        <InputLabel for="new_client_am" value="مدير الحساب" />
                        <select
                            id="new_client_am"
                            v-model="createForm.account_manager_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-black shadow-sm"
                        >
                            <option :value="null">—</option>
                            <option v-for="u in accountManagers || []" :key="`am-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="new_client_cm" value="مدير الحملة" />
                        <select
                            id="new_client_cm"
                            v-model="createForm.campaign_manager_id"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-black shadow-sm"
                        >
                            <option :value="null">—</option>
                            <option v-for="u in campaignManagers || []" :key="`cm-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="new_client_notes" value="ملاحظات" />
                        <textarea
                            id="new_client_notes"
                            v-model="createForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-black shadow-sm"
                        />
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <PrimaryButton :disabled="createForm.processing" type="submit">حفظ العميل</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
