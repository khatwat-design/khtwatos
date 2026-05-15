<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    mobileSheetBackdrop,
    mobileSheetForm,
    mobileSheetHeader,
    mobileSheetPanelLg,
    mobileSheetPanelMd,
} from '@/utils/mobileSheetClasses.js';
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

        <div class="mx-auto w-full min-w-0 max-w-6xl space-y-4 px-3 pb-8 sm:px-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full items-stretch justify-between gap-2 sm:w-auto sm:items-center">
                    <button
                        type="button"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        @click="showFilterModal = true"
                    >
                        فلترة
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-brand-700 sm:flex-none sm:px-3"
                        aria-label="إضافة عميل"
                        title="إضافة عميل"
                        @click="showCreateModal = true"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <span class="sm:hidden">إضافة عميل</span>
                    </button>
                </div>
            </div>

            <!-- موبايل: بطاقات بدون تمرير أفقي -->
            <div class="space-y-3 md:hidden">
                <Link
                    v-for="c in clients"
                    :key="`client-card-${c.id}`"
                    :href="route('clients.show', c.id)"
                    class="group block overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm ring-1 ring-gray-100/80 transition-all duration-200 active:scale-[0.99] hover:border-brand-100 hover:shadow-md hover:ring-brand-100/50"
                >
                    <div class="flex gap-3 p-4">
                        <div class="relative shrink-0">
                            <img
                                :src="c.logo_url || '/images/mobile-logo.png'"
                                alt=""
                                class="h-14 w-14 rounded-2xl border border-gray-100 object-cover shadow-inner ring-2 ring-white"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold leading-snug text-gray-900 group-hover:text-brand-700">
                                        {{ c.name }}
                                    </h3>
                                    <p v-if="c.company" class="mt-0.5 truncate text-xs text-gray-500">
                                        {{ c.company }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-gray-300 transition group-hover:text-brand-400" aria-hidden="true">
                                    <svg class="h-5 w-5 rtl:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                        <path
                                            fill-rule="evenodd"
                                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </span>
                            </div>
                            <p
                                v-if="c.current_stage?.label"
                                class="mt-2 inline-flex max-w-full items-center rounded-lg bg-gradient-to-l from-brand-50 to-white px-2.5 py-1 text-[11px] font-semibold leading-tight text-brand-800 ring-1 ring-brand-100/80"
                            >
                                {{ c.current_stage.label }}
                            </p>
                            <p v-else class="mt-2 text-[11px] font-medium text-gray-400">—</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-t border-gray-50 bg-gradient-to-b from-gray-50/40 to-white px-3 pb-3 pt-2">
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">مدير الحساب</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-800">{{ c.account_manager?.name || '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">مدير الحملة</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-800">{{ c.campaign_manager?.name || '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">مهام مفتوحة</p>
                            <p class="mt-0.5 text-xs font-bold tabular-nums text-gray-900">{{ c.open_tasks_count }}</p>
                        </div>
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">آخر تحديث</p>
                            <p class="mt-0.5 text-xs font-medium tabular-nums text-gray-600">
                                {{ new Date(c.updated_at).toLocaleDateString('ar-SA') }}
                            </p>
                        </div>
                    </div>
                </Link>
                <p v-if="!clients.length" class="rounded-2xl border border-dashed border-gray-200 bg-white/80 px-4 py-10 text-center text-sm text-gray-500">
                    لا يوجد عملاء بعد.
                </p>
            </div>

            <!-- شاشات أكبر: جدول كامل -->
            <div class="ui-card hidden overflow-x-auto md:block">
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
            :class="mobileSheetBackdrop"
            @click.self="showFilterModal = false"
        >
            <div :class="[mobileSheetPanelMd, 'p-4 sm:p-5']">
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
            :class="mobileSheetBackdrop"
            @click.self="showCreateModal = false"
        >
            <div :class="mobileSheetPanelLg">
                <div :class="mobileSheetHeader">
                    <h3 class="text-base font-bold text-gray-900 sm:text-lg">إضافة عميل</h3>
                    <button
                        type="button"
                        class="min-h-10 min-w-10 rounded-xl px-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
                        @click="showCreateModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <form :class="mobileSheetForm" @submit.prevent="submitCreateClient">
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
                    <div class="md:col-span-2 flex flex-col gap-2 pt-2 sm:flex-row sm:justify-end">
                        <PrimaryButton
                            :disabled="createForm.processing"
                            type="submit"
                            class="w-full justify-center sm:w-auto"
                        >
                            حفظ العميل
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
