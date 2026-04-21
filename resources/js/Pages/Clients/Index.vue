<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    clients: Array,
});
</script>

<template>
    <Head title="العملاء" />

    <AuthenticatedLayout>
        <template #title>العملاء</template>

        <div class="mx-auto max-w-6xl space-y-4">
            <div class="flex justify-end">
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
                                أكاونت
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                مهام مفتوحة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                آخر تحديث
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
                        </tr>
                        <tr v-if="!clients.length">
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                لا يوجد عملاء بعد.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
