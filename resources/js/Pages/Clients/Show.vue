<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    client: Object,
    history: Array,
    tasks: Array,
    tasks_by_team: Array,
    meetings: Array,
    task_status_history: Array,
    attachments: Array,
    stages: Array,
    metrics: Object,
    accountManagers: Array,
});
const page = usePage();
const canDeleteRecords = Boolean(page.props.auth?.can?.deleteRecords);
const clientAttachments = ref([...(props.attachments || [])]);
const attachmentInputRef = ref(null);
const attachmentUploading = ref(false);
const attachmentProgress = ref(0);
const attachmentError = ref('');
const attachmentFile = ref(null);
const maxAttachmentSizeBytes = 10 * 1024 * 1024;

const clientForm = useForm({
    name: props.client.name,
    company: props.client.company || '',
    email: props.client.email || '',
    phone: props.client.phone || '',
    notes: props.client.notes || '',
    account_manager_id: props.client.account_manager?.id ?? null,
});

const stageForm = useForm({
    current_pipeline_stage_id: props.client.current_stage?.id,
    note: '',
});

function saveClient() {
    clientForm.patch(route('clients.update', props.client.id));
}

function saveStage() {
    stageForm.patch(route('clients.stage', props.client.id), {
        preserveScroll: true,
        onSuccess: () => stageForm.reset('note'),
    });
}

function deleteClient() {
    if (!canDeleteRecords) {
        return;
    }
    if (!confirm('حذف هذا العميل؟ سيتم حذف كل البيانات المرتبطة به.')) {
        return;
    }
    router.delete(route('clients.destroy', props.client.id));
}

function formatFileSize(size) {
    const value = Number(size || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function onAttachmentChange(event) {
    const selected = event.target?.files?.[0] || null;
    if (!selected) {
        attachmentFile.value = null;
        return;
    }

    if (Number(selected.size || 0) > maxAttachmentSizeBytes) {
        attachmentFile.value = null;
        attachmentError.value = 'حجم الملف أكبر من 10MB. اختر ملفًا أصغر.';
        if (attachmentInputRef.value) {
            attachmentInputRef.value.value = '';
        }
        return;
    }

    attachmentError.value = '';
    attachmentFile.value = selected;
}

async function uploadClientAttachment() {
    if (!attachmentFile.value || attachmentUploading.value) {
        return;
    }

    attachmentUploading.value = true;
    attachmentProgress.value = 0;
    attachmentError.value = '';

    try {
        const payload = new FormData();
        payload.append('file', attachmentFile.value);

        const response = await window.axios.post(
            route('clients.attachments.store', props.client.id),
            payload,
            {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'multipart/form-data',
                },
                timeout: 120000,
                onUploadProgress: (event) => {
                    const total = Number(event?.total || 0);
                    if (!total) {
                        return;
                    }
                    attachmentProgress.value = Math.min(100, Math.round((Number(event.loaded || 0) / total) * 100));
                },
            },
        );

        if (response?.data?.attachment) {
            clientAttachments.value.unshift(response.data.attachment);
            attachmentFile.value = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            attachmentProgress.value = 0;
        }
    } catch (error) {
        attachmentError.value = error?.response?.data?.message || 'تعذر رفع المرفق، حاول مرة أخرى.';
    } finally {
        attachmentUploading.value = false;
    }
}

function canDeleteAttachment(attachment) {
    if (canDeleteRecords) {
        return true;
    }
    const currentUserId = page.props.auth?.user?.id;
    return Number(attachment?.uploaded_by?.id || 0) === Number(currentUserId || 0);
}

function deleteClientAttachment(attachment) {
    if (!attachment?.id || !confirm('حذف هذا المرفق؟')) {
        return;
    }

    router.delete(route('clients.attachments.destroy', attachment.id), {
        preserveScroll: true,
        onSuccess: () => {
            clientAttachments.value = clientAttachments.value.filter((item) => item.id !== attachment.id);
        },
        onError: () => {
            attachmentError.value = 'تعذر حذف المرفق.';
        },
    });
}

function formatDt(iso) {
    return new Date(iso).toLocaleString('ar-SA', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const sourceLabels = {
    internal: 'داخلي',
    calendly: 'Calendly',
};

const teamLabelMap = {
    writing: 'فريق الكتابة',
    'media-buyer': 'مدراء الحملات',
    account: 'مدراء الحسابات',
    sales: 'المبيعات',
    hr: 'الموارد البشرية',
    accounting: 'المحاسبة',
    'media buyer': 'مدراء الحملات',
};

const columnLabelMap = {
    backlog: 'المتراكم',
    todo: 'للعمل',
    'to do': 'للعمل',
    doing: 'قيد التنفيذ',
    'in progress': 'قيد التنفيذ',
    review: 'مراجعة',
    done: 'تم',
    completed: 'تم',
    blocked: 'متوقفة',
};

function localizeTeamName(name) {
    const key = String(name || '')
        .trim()
        .toLowerCase();

    return teamLabelMap[key] || name;
}

function localizeColumnName(name) {
    const key = String(name || '')
        .trim()
        .toLowerCase();

    return columnLabelMap[key] || name;
}
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <template #title>{{ client.name }}</template>

        <div class="mx-auto max-w-5xl space-y-6">
            <Link
                :href="route('clients.index')"
                class="text-sm text-brand-600 hover:underline"
            >
                ← العودة للعملاء
            </Link>

            <div
                class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow ring-1 ring-gray-200"
            >
                <Link
                    :href="route('tasks.index', { client_id: client.id })"
                    class="inline-flex items-center justify-center rounded-md border border-transparent bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"
                >
                    لوحة مهام هذا العميل
                </Link>
                <Link
                    :href="route('meetings.index', { client_id: client.id })"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    اجتماعات العميل
                </Link>
                <Link
                    :href="route('meetings.create', { client_id: client.id })"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    جدولة اجتماع داخلي
                </Link>
                <button
                    v-if="canDeleteRecords"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-50"
                    @click="deleteClient"
                >
                    حذف العميل
                </button>
            </div>

            <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">مرفقات ومرجع العميل</h2>
                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,280px]">
                    <div class="space-y-2">
                        <div
                            v-for="attachment in clientAttachments"
                            :key="`client-attachment-${attachment.id}`"
                            class="rounded-md border border-gray-200 bg-gray-50 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a
                                        :href="attachment.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="truncate text-sm font-semibold text-brand-600 hover:underline"
                                    >
                                        {{ attachment.name }}
                                    </a>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ attachment.mime || 'ملف' }} • {{ formatFileSize(attachment.size) }}
                                        <span v-if="attachment.uploaded_by"> • بواسطة {{ attachment.uploaded_by.name }}</span>
                                    </p>
                                </div>
                                <button
                                    v-if="canDeleteAttachment(attachment)"
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="deleteClientAttachment(attachment)"
                                >
                                    حذف
                                </button>
                            </div>
                            <img
                                v-if="attachment.is_image"
                                :src="attachment.url"
                                alt="مرفق عميل"
                                class="mt-2 max-h-44 rounded border border-gray-200"
                            />
                        </div>
                        <p v-if="!clientAttachments.length" class="text-sm text-gray-500">
                            لا توجد مرفقات بعد لهذا العميل.
                        </p>
                    </div>

                    <div class="space-y-3 rounded-md border border-gray-200 p-3">
                        <InputLabel for="client_attachment_file" value="رفع ملف مرجعي" />
                        <input
                            id="client_attachment_file"
                            ref="attachmentInputRef"
                            type="file"
                            class="block w-full text-xs text-gray-600 file:me-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-medium"
                            @change="onAttachmentChange"
                        />
                        <p class="text-[11px] text-gray-500">الحد الأقصى: 10MB</p>
                        <div v-if="attachmentUploading" class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div
                                class="h-full rounded-full bg-brand-600 transition-all"
                                :style="{ width: `${attachmentProgress}%` }"
                            />
                        </div>
                        <InputError class="mt-1" :message="attachmentError" />
                        <PrimaryButton
                            type="button"
                            :disabled="attachmentUploading || !attachmentFile"
                            @click="uploadClientAttachment"
                        >
                            {{ attachmentUploading ? 'جاري الرفع...' : 'رفع المرفق' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
                    <p class="text-xs font-medium text-gray-500">مهام مفتوحة</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.open_tasks }}
                    </p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
                    <p class="text-xs font-medium text-gray-500">
                        اجتماعات قادمة
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.upcoming_meetings }}
                    </p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
                    <p class="text-xs font-medium text-gray-500">
                        أيام منذ آخر تحديث
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.days_since_update }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">البيانات</h2>
                    <form class="mt-4 space-y-3" @submit.prevent="saveClient">
                        <div>
                            <InputLabel for="name" value="الاسم" />
                            <TextInput
                                id="name"
                                v-model="clientForm.name"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel for="company" value="الشركة" />
                            <TextInput
                                id="company"
                                v-model="clientForm.company"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="email" value="البريد" />
                            <TextInput
                                id="email"
                                v-model="clientForm.email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="phone" value="الهاتف" />
                            <TextInput
                                id="phone"
                                v-model="clientForm.phone"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="am" value="مدير الحساب" />
                            <select
                                id="am"
                                v-model="clientForm.account_manager_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option :value="null">—</option>
                                <option
                                    v-for="u in accountManagers"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="notes" value="ملاحظات" />
                            <textarea
                                id="notes"
                                v-model="clientForm.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <PrimaryButton :disabled="clientForm.processing"
                            >حفظ</PrimaryButton
                        >
                    </form>
                </div>

                <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">المسار</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        الحالية:
                        <span class="font-medium text-gray-900">{{
                            client.current_stage?.label
                        }}</span>
                    </p>
                    <form class="mt-4 space-y-3" @submit.prevent="saveStage">
                        <div>
                            <InputLabel for="stage" value="نقل إلى مرحلة" />
                            <select
                                id="stage"
                                v-model="stageForm.current_pipeline_stage_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                required
                            >
                                <option
                                    v-for="s in stages"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="snote" value="ملاحظة (اختياري)" />
                            <TextInput
                                id="snote"
                                v-model="stageForm.note"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <PrimaryButton :disabled="stageForm.processing"
                            >تحديث المرحلة</PrimaryButton
                        >
                    </form>

                    <h3 class="mt-6 text-sm font-semibold text-gray-800">السجل</h3>
                    <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto text-sm">
                        <li
                            v-for="h in history"
                            :key="h.id"
                            class="rounded border border-gray-100 bg-gray-50 px-2 py-1"
                        >
                            <span class="font-medium">{{ h.stage }}</span>
                            <span class="text-gray-500"> — {{ h.user || '—' }}</span>
                            <div class="text-xs text-gray-400">
                                {{ formatDt(h.at) }}
                                <span v-if="h.note"> · {{ h.note }}</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">المهام حسب الفريق</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div
                        v-for="block in tasks_by_team"
                        :key="block.team"
                        class="rounded border border-gray-100 p-3"
                    >
                        <h3 class="text-sm font-semibold text-gray-800">
                            {{ localizeTeamName(block.team) }}
                        </h3>
                        <ul class="mt-2 text-sm text-gray-600">
                            <li v-for="t in block.tasks" :key="t.id">
                                {{ t.title }}
                                <span class="text-gray-400"> · {{ localizeColumnName(t.column) || '—' }}</span>
                                <span v-if="(t.assignees || []).length" class="text-gray-400">
                                    — {{ t.assignees.join('، ') }}</span
                                >
                                <span v-else-if="t.assignee" class="text-gray-400">
                                    — {{ t.assignee }}</span
                                >
                            </li>
                            <li v-if="!block.tasks.length" class="text-gray-400">
                                لا توجد مهام
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">تغيّر حالات المهام</h2>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    <li
                        v-for="entry in task_status_history"
                        :key="entry.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2"
                    >
                        <div class="text-gray-700">
                            <span class="font-medium text-gray-900">{{ entry.task_title }}</span>
                            <span class="mx-1 text-gray-400">{{ localizeColumnName(entry.from) || '—' }}</span>
                            <span class="text-gray-400">←</span>
                            <span class="mx-1">{{ localizeColumnName(entry.to) || '—' }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ entry.by || '—' }} · {{ formatDt(entry.at) }}
                        </div>
                    </li>
                    <li v-if="!task_status_history?.length" class="py-4 text-gray-500">
                        لا يوجد تغيّر حالات مسجل بعد.
                    </li>
                </ul>
            </div>

            <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">الاجتماعات</h2>
                    <Link
                        :href="route('meetings.index', { client_id: client.id })"
                        class="text-sm text-brand-600 hover:underline"
                    >
                        فتح القائمة الكاملة
                    </Link>
                </div>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    <li
                        v-for="m in meetings"
                        :key="m.id"
                        class="flex flex-wrap justify-between gap-2 py-2"
                    >
                        <span class="font-medium text-gray-800">{{
                            m.title || 'اجتماع'
                        }}</span>
                        <span class="text-gray-600">{{ formatDt(m.start_at) }}</span>
                        <span class="text-gray-500">{{ m.invitee_name }}</span>
                        <span class="text-gray-400">{{ m.host }}</span>
                        <span class="text-xs text-gray-400">{{ sourceLabels[m.source] || m.source }}</span>
                    </li>
                    <li v-if="!meetings.length" class="py-4 text-gray-500">
                        لا توجد اجتماعات مرتبطة.
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
