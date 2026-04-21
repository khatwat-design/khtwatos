<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    meetings: Array,
    hosts: Array,
    clients: Array,
    filters: Object,
});

const statusLabels = {
    scheduled: 'مجدول',
    canceled: 'ملغى',
};

const sourceLabels = {
    internal: 'داخلي',
};

const employeeNameMap = {
    Mahmoud: 'محمود',
    Maha: 'مها',
    Laith: 'ليث',
    'Hussein Salam': 'حسين سلام',
    'Hussein Ali': 'حسين علي',
    'Ahmed Bashir': 'أحمد بشير',
    Admin: 'مدير النظام',
    Abdullah: 'عبدالله',
    Shatha: 'شذى',
    Noor: 'نور',
    Nabras: 'نبراس',
    'Mohammed Thaer': 'محمد ثائر',
    'Mohammed Khalid': 'محمد خالد',
};

function arabicEmployeeName(name) {
    return employeeNameMap[name] || name;
}

function goIndex(params) {
    router.get(route('meetings.index'), params, {
        preserveState: true,
        replace: true,
    });
}

function setHost(userId) {
    goIndex({
        user_id: userId || undefined,
        client_id: props.filters.client_id || undefined,
    });
}

function setHostFromSelect(event) {
    const raw = event.target.value;
    const userId = raw === '' ? undefined : Number(raw);
    setHost(userId);
}

function setClient(event) {
    const raw = event.target.value;
    const clientId = raw === '' ? undefined : Number(raw);
    goIndex({
        user_id: props.filters.user_id || undefined,
        client_id: clientId,
    });
}

function deleteMeeting(id) {
    if (!confirm('حذف هذا الاجتماع الداخلي؟')) {
        return;
    }
    router.delete(route('meetings.destroy', id), { preserveScroll: true });
}

function formatDt(iso) {
    return new Date(iso).toLocaleString('ar-SA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="الاجتماعات" />

    <AuthenticatedLayout>
        <template #title>الاجتماعات</template>

        <div class="mx-auto max-w-6xl space-y-4">
            <div
                class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow ring-1 ring-gray-200 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
            >
                <div class="grid w-full gap-3 sm:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600" for="host_filter">المضيف:</label>
                        <select
                            id="host_filter"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm"
                            :value="filters.user_id ?? ''"
                            @change="setHostFromSelect"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="h in hosts"
                                :key="h.id"
                                :value="h.id"
                            >
                                {{ arabicEmployeeName(h.name) }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-600" for="client_filter">العميل:</label>
                        <select
                            id="client_filter"
                            class="w-full rounded-md border-gray-300 text-sm shadow-sm"
                            :value="filters.client_id ?? ''"
                            @change="setClient"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="c in clients"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('meetings.create', { client_id: filters.client_id || undefined })"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    >
                        اجتماع داخلي جديد
                    </Link>
                </div>
            </div>

            <div class="space-y-3 md:hidden">
                <div
                    v-for="m in meetings"
                    :key="`mobile-${m.id}`"
                    class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-sm font-semibold text-gray-900">
                            {{ m.title || 'اجتماع' }}
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">
                            {{ statusLabels[m.status] || m.status }}
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-600">{{ formatDt(m.start_at) }}</div>
                    <div class="mt-2 text-sm text-gray-700">
                        المضيف: {{ m.host ? arabicEmployeeName(m.host.name) : '—' }}
                    </div>
                    <div class="mt-1 text-sm text-gray-700">
                        الضيف: {{ m.invitee_name || '—' }}
                    </div>
                    <div class="mt-1 text-sm text-gray-700">
                        العميل:
                        <template v-if="m.client">
                            <Link :href="route('clients.show', m.client.id)" class="text-brand-600 hover:underline">
                                {{ m.client.name }}
                            </Link>
                        </template>
                        <span v-else>{{ m.invitee_name || '—' }}</span>
                    </div>
                    <div v-if="m.reason" class="mt-2 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600">
                        {{ m.reason }}
                    </div>
                    <div v-if="m.source === 'internal'" class="mt-3 flex gap-3 text-sm">
                        <Link :href="route('meetings.edit', m.id)" class="text-brand-600 hover:underline">تعديل</Link>
                        <button type="button" class="text-red-600 hover:underline" @click="deleteMeeting(m.id)">حذف</button>
                    </div>
                </div>
                <div v-if="!meetings.length" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow ring-1 ring-gray-200">
                    لا توجد اجتماعات مطابقة للفلتر.
                </div>
            </div>

            <div class="hidden overflow-hidden rounded-lg bg-white shadow ring-1 ring-gray-200 md:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                الموعد
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                الضيف
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                المضيف
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                العميل
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                السبب
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                الحالة
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                المصدر
                            </th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">
                                إجراءات
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="m in meetings" :key="m.id">
                            <td class="whitespace-nowrap px-4 py-2 text-gray-800">
                                {{ formatDt(m.start_at) }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="font-medium text-gray-900">
                                    {{ m.invitee_name || '—' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ m.invitee_email || '' }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ m.host ? arabicEmployeeName(m.host.name) : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <Link
                                    v-if="m.client"
                                    :href="route('clients.show', m.client.id)"
                                    class="text-brand-600 hover:underline"
                                >
                                    {{ m.client.name }}
                                </Link>
                                <span v-else class="text-gray-500">{{ m.invitee_name || '—' }}</span>
                            </td>
                            <td class="max-w-xs truncate px-4 py-2 text-gray-600">
                                {{ m.reason || '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs"
                                >
                                    {{ statusLabels[m.status] || m.status }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ sourceLabels[m.source] || 'داخلي' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <div
                                    v-if="m.source === 'internal'"
                                    class="flex flex-wrap gap-3"
                                >
                                    <Link
                                        :href="route('meetings.edit', m.id)"
                                        class="text-brand-600 hover:underline"
                                    >
                                        تعديل
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-sm text-red-600 hover:underline"
                                        @click="deleteMeeting(m.id)"
                                    >
                                        حذف
                                    </button>
                                </div>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="!meetings.length">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <p>لا توجد اجتماعات مطابقة للفلتر.</p>
                                <p class="mt-2 text-sm">
                                    أنشئ اجتماعاً واربطه بموظف وعميل مباشرة من داخل النظام.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
