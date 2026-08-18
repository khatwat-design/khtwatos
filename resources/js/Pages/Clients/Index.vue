<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import MobileSheet from '@/Components/MobileSheet.vue';
import { mobileSheetForm } from '@/utils/mobileSheetClasses.js';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    clients: Array,
    stages: Array,
    accountManagers: Array,
    campaignManagers: Array,
    filters: Object,
    can_toggle_client_active: Boolean,
});
const canDeleteRecords = Boolean(usePage().props.auth?.can?.deleteRecords);
const showFilterModal = ref(false);
const showCreateModal = ref(false);

const searchInput = ref(props.filters?.search ?? '');
let searchTimer = null;

watch(searchInput, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('clients.index'), {
            search: val || undefined,
            stage_id: props.filters?.stage_id || undefined,
            active: activeParam(),
        }, { preserveState: true, replace: true });
    }, 300);
});

const currentActiveFilter = ref(props.filters?.active);

watch(currentActiveFilter, (val) => {
    router.get(route('clients.index'), {
        active: val === true ? '1' : val === false ? '0' : '',
        search: searchInput.value || undefined,
        stage_id: props.filters?.stage_id || undefined,
    }, { preserveState: true, replace: true });
});

const activeFilterOptions = [
    { value: null, label: 'الكل' },
    { value: true, label: 'النشطين' },
    { value: false, label: 'غير النشطين' },
];

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

function activeParam() {
    const v = currentActiveFilter.value;
    return v === true ? '1' : v === false ? '0' : '';
}

function setStageFilter(event) {
    const raw = event.target.value;
    const stageId = raw === '' ? undefined : Number(raw);
    router.get(route('clients.index'), {
        search: searchInput.value || undefined,
        stage_id: stageId,
        active: activeParam(),
    }, { preserveState: true, replace: true });
}

function applyFilterModal() {
    const stageId = filterForm.stage_id === '' ? undefined : Number(filterForm.stage_id);
    router.get(route('clients.index'), {
        search: searchInput.value || undefined,
        stage_id: stageId,
        active: activeParam(),
    }, { preserveState: true, replace: true });
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

function toggleActive(client) {
    router.post(route('clients.toggle-active', client.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="العملاء" />

    <AuthenticatedLayout>
        <template #title>العملاء</template>

        <div class="mx-auto w-full min-w-0 max-w-6xl space-y-4 px-3 pb-8 sm:px-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:w-72">
                    <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="بحث بالاسم، الشركة، البريد أو الهاتف…"
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white pe-4 ps-10 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                    />
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute end-2 top-1/2 -translate-y-1/2 rounded-lg p-1 text-gray-400 hover:text-gray-600"
                        @click="searchInput = ''; router.get(route('clients.index'), { search: undefined, stage_id: props.filters?.stage_id || undefined, active: activeParam() }, { preserveState: true, replace: true })"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
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

            <div class="flex items-center gap-1.5 rounded-xl bg-gray-50/80 p-1 ring-1 ring-gray-200">
                <button
                    v-for="opt in activeFilterOptions"
                    :key="String(opt.value)"
                    type="button"
                    class="flex-1 rounded-lg px-3 py-1.5 text-center text-xs font-semibold transition"
                    :class="currentActiveFilter === opt.value ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-200' : 'text-gray-500 hover:text-gray-700'"
                    @click="currentActiveFilter = opt.value"
                >
                    {{ opt.label }}
                </button>
            </div>

            <!-- موبايل: بطاقات بدون تمرير أفقي -->
            <div class="space-y-3 md:hidden">
                <Link
                    v-for="c in clients"
                    :key="`client-card-${c.id}`"
                    :href="route('clients.show', c.id)"
                    class="group relative block overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm ring-1 ring-gray-100/80 transition-all duration-200 active:scale-[0.99] hover:border-brand-100 hover:shadow-md hover:ring-brand-100/50"
                >
                    <div class="absolute end-2 top-2 z-10">
                        <span
                            class="inline-flex h-2.5 w-2.5 rounded-full"
                            :class="c.is_active ? 'bg-emerald-500' : 'bg-red-400'"
                            :title="c.is_active ? 'نشط' : 'غير نشط'"
                        />
                    </div>
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
                    <div v-if="can_toggle_client_active" class="border-t border-gray-50 px-3 py-2">
                        <button
                            type="button"
                            class="w-full rounded-lg py-1.5 text-xs font-semibold transition"
                            :class="c.is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                            @click.prevent="toggleActive(c)"
                        >
                            {{ c.is_active ? 'تعطيل العميل' : 'تفعيل العميل' }}
                        </button>
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
                                <span class="sr-only">الحالة</span>
                            </th>
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
                            <th v-if="canDeleteRecords || can_toggle_client_active" class="px-4 py-2 text-start font-medium text-gray-900">
                                إجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in clients" :key="c.id">
                            <td class="px-4 py-2">
                                <span
                                    class="inline-flex h-2.5 w-2.5 rounded-full"
                                    :class="c.is_active ? 'bg-emerald-500' : 'bg-red-400'"
                                    :title="c.is_active ? 'نشط' : 'غير نشط'"
                                />
                            </td>
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
                            <td v-if="canDeleteRecords || can_toggle_client_active" class="whitespace-nowrap px-4 py-2">
                                <button
                                    v-if="can_toggle_client_active"
                                    type="button"
                                    class="ml-2 rounded px-2 py-1 text-xs font-semibold transition"
                                    :class="c.is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                    @click="toggleActive(c)"
                                >
                                    {{ c.is_active ? 'تعطيل' : 'تفعيل' }}
                                </button>
                                <button
                                    v-if="canDeleteRecords"
                                    type="button"
                                    class="text-sm text-red-700 hover:underline"
                                    @click="deleteClient(c.id)"
                                >
                                    حذف
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!clients.length">
                            <td :colspan="canDeleteRecords || can_toggle_client_active ? 9 : 8" class="px-4 py-8 text-center text-gray-500">
                                لا يوجد عملاء بعد.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <MobileSheet :show="showFilterModal" size="md" @close="showFilterModal = false">
            <template #title>فلترة العملاء</template>
            <form class="space-y-3 px-3.5 py-2 sm:px-4" @submit.prevent="applyFilterModal">
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
            </form>
            <template #footer>
                <PrimaryButton type="button" class="w-full justify-center sm:w-auto" @click="applyFilterModal">
                    تطبيق
                </PrimaryButton>
            </template>
        </MobileSheet>

        <MobileSheet :show="showCreateModal" size="lg" @close="showCreateModal = false">
            <template #title>إضافة عميل</template>
            <form id="create-client-form" :class="mobileSheetForm" @submit.prevent="submitCreateClient">
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
            </form>
            <template #footer>
                <PrimaryButton
                    form="create-client-form"
                    :disabled="createForm.processing"
                    type="submit"
                    class="w-full justify-center sm:w-auto"
                >
                    حفظ العميل
                </PrimaryButton>
            </template>
        </MobileSheet>

    </AuthenticatedLayout>
</template>
