<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    clients: Array,
    stages: Array,
    filters: Object,
});
const canDeleteRecords = Boolean(usePage().props.auth?.can?.deleteRecords);

function setStageFilter(event) {
    const raw = event.target.value;
    const stageId = raw === '' ? undefined : Number(raw);
    router.get(route('clients.index'), { stage_id: stageId }, { preserveState: true, replace: true });
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
                    <label class="text-sm text-gray-600" for="stage_filter">فلترة المرحلة:</label>
                    <select
                        id="stage_filter"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm sm:w-64"
                        :value="filters?.stage_id ?? ''"
                        @change="setStageFilter"
                    >
                        <option value="">كل المراحل</option>
                        <option v-for="stage in stages || []" :key="stage.id" :value="stage.id">
                            {{ stage.label }}
                        </option>
                    </select>
                </div>
                <Link :href="route('clients.create')">
                    <PrimaryButton>عميل جديد</PrimaryButton>
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                العميل
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                المرحلة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                مدير الحساب
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                مهام مفتوحة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                آخر تحديث
                            </th>
                            <th v-if="canDeleteRecords" class="px-4 py-2 text-start font-medium text-gray-600">
                                إجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in clients" :key="c.id">
                            <td class="px-4 py-2">
                                <Link
                                    :href="route('clients.show', c.id)"
                                    class="font-medium text-brand-600 hover:underline"
                                >
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
                            <td :colspan="canDeleteRecords ? 6 : 5" class="px-4 py-8 text-center text-gray-500">
                                لا يوجد عملاء بعد.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
