<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const props = defineProps({
    teams: Array,
    team: Object,
    board: Object,
    clients: Array,
    users: Array,
    filters: Object,
    filterClient: Object,
});
const page = usePage();
const canDeleteRecords = computed(() => Boolean(page.props.auth?.can?.deleteRecords));
const maxAttachmentSizeBytes = 10 * 1024 * 1024;

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

function localizeTeamName(name, slug = null) {
    if (slug && teamLabelMap[slug]) {
        return teamLabelMap[slug];
    }

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

const boardState = reactive({
    id: null,
    name: '',
    columns: [],
});

const clientOptions = computed(() =>
    (props.clients || []).map((client) => {
        const base = client?.name || client?.company || `عميل #${client?.id}`;
        return {
            id: client.id,
            label: base,
        };
    }),
);

watch(
    () => props.board,
    (b) => {
        if (!b) {
            boardState.id = null;
            boardState.name = '';
            boardState.columns = [];
            return;
        }
        boardState.id = b.id;
        boardState.name = b.name;
        boardState.columns = b.columns.map((c) => ({
            id: c.id,
            name: c.name,
            tasks: [...c.tasks],
        }));
    },
    { immediate: true },
);

const taskForm = useForm({
    board_column_id: null,
    title: '',
    description: '',
    assignee_ids: [],
    client_id: props.filters?.client_id ?? null,
});

watch(
    () => boardState.columns[0]?.id,
    (id) => {
        if (id && !taskForm.board_column_id) {
            taskForm.board_column_id = id;
        }
    },
    { immediate: true },
);

watch(
    () => props.filters?.client_id,
    (cid) => {
        taskForm.client_id = cid ?? null;
    },
);

const createModalOpen = ref(false);
const createDescriptionRef = ref(null);
const editDescriptionRef = ref(null);

function openCreateModal() {
    taskForm.clearErrors();
    taskForm.board_column_id = boardState.columns[0]?.id ?? null;
    taskForm.client_id = props.filters?.client_id ?? null;
    taskForm.assignee_ids = [];
    taskForm.title = '';
    taskForm.description = '';
    createMention.open = false;
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
    createMention.open = false;
}

function teamHref(slug) {
    return route('tasks.index', {
        team: slug,
        client_id: props.filters?.client_id || undefined,
    });
}

function setClientFilterFromSelect(event) {
    const raw = event.target.value;
    const clientId = raw === '' ? undefined : Number(raw);
    router.get(
        route('tasks.index'),
        {
            team: props.team?.slug,
            client_id: clientId,
        },
        { preserveState: true, replace: true },
    );
}

function submitTask() {
    if (!taskForm.board_column_id) {
        return;
    }

    taskForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
}

function deleteTask(taskId) {
    if (!canDeleteRecords.value) {
        return;
    }

    if (!confirm('حذف هذه المهمة؟ لا يمكن التراجع.')) {
        return;
    }

    router.delete(route('tasks.destroy', taskId), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingTask.value?.id === taskId) {
                closeEditModal();
            }
        },
    });
}

let syncTimer;
function onDragEnd() {
    if (!boardState.id) {
        return;
    }
    clearTimeout(syncTimer);
    syncTimer = setTimeout(() => {
        router.patch(
            route('task-boards.sync', boardState.id),
            {
                columns: boardState.columns.map((c) => ({
                    id: c.id,
                    task_ids: c.tasks.map((t) => t.id),
                })),
            },
            { preserveScroll: true },
        );
    }, 80);
}

const editModalOpen = ref(false);
const editingTask = ref(null);
const taskMessages = ref([]);
const taskAttachments = ref([]);
const taskMessageSending = ref(false);
const taskMessageError = ref('');
const taskMessageBody = ref('');
const taskAttachmentUploading = ref(false);
const taskAttachmentProgress = ref(0);
const taskAttachmentError = ref('');
const taskAttachmentFile = ref(null);
const attachmentInputRef = ref(null);
const quickActionModalOpen = ref(false);
const quickActionTask = ref(null);
const quickActionForm = useForm({
    assigned_to_id: '',
    due_at: '',
    note: '',
    checklist_titles: [''],
});

const editForm = useForm({
    title: '',
    description: '',
    assignee_ids: [],
    client_id: null,
    due_at: '',
});

function toDatetimeLocal(iso) {
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function openEdit(task) {
    editingTask.value = task;
    taskMessages.value = [...(task.messages || [])];
    taskAttachments.value = [...(task.attachments || [])];
    taskMessageBody.value = '';
    taskMessageError.value = '';
    taskAttachmentError.value = '';
    taskAttachmentProgress.value = 0;
    taskAttachmentFile.value = null;
    if (attachmentInputRef.value) {
        attachmentInputRef.value.value = '';
    }
    editForm.title = task.title;
    editForm.description = task.description || '';
    editForm.assignee_ids = (task.assignees || []).map((u) => u.id);
    if (!editForm.assignee_ids.length && task.assignee?.id) {
        editForm.assignee_ids = [task.assignee.id];
    }
    editForm.client_id = task.client?.id ?? null;
    editForm.due_at = task.due_at ? toDatetimeLocal(task.due_at) : '';
    editForm.clearErrors();
    editMention.open = false;
    editModalOpen.value = true;
}

function openQuickAction(task) {
    quickActionTask.value = task;
    quickActionForm.reset();
    quickActionForm.checklist_titles = [''];
    quickActionForm.clearErrors();
    quickActionModalOpen.value = true;
}

function closeQuickActionModal() {
    quickActionModalOpen.value = false;
    quickActionTask.value = null;
}

function closeEditModal() {
    editModalOpen.value = false;
    editingTask.value = null;
    taskMessages.value = [];
    taskAttachments.value = [];
    taskMessageBody.value = '';
    taskMessageError.value = '';
    taskAttachmentError.value = '';
    taskAttachmentProgress.value = 0;
    taskAttachmentFile.value = null;
    editMention.open = false;
}

function saveTaskEdit() {
    if (!editingTask.value) {
        return;
    }
    editForm
        .transform((data) => ({
            ...data,
            due_at: data.due_at || null,
        }))
        .patch(route('tasks.update', editingTask.value.id), {
            preserveScroll: true,
            onSuccess: closeEditModal,
        });
}

const createMention = reactive({
    open: false,
    start: 0,
    end: 0,
    query: '',
});

const editMention = reactive({
    open: false,
    start: 0,
    end: 0,
    query: '',
});

function updateMentionState(value, cursorPos, state) {
    const beforeCursor = value.slice(0, cursorPos);
    const atIndex = beforeCursor.lastIndexOf('@');

    if (atIndex < 0) {
        state.open = false;
        return;
    }

    const token = beforeCursor.slice(atIndex + 1);
    if (!token || /[\s\n\r\t]/.test(token)) {
        state.open = false;
        return;
    }

    state.open = true;
    state.start = atIndex;
    state.end = cursorPos;
    state.query = token.toLowerCase();
}

const createMentionSuggestions = computed(() => {
    if (!createMention.open) {
        return [];
    }

    return (props.users || [])
        .filter((u) => u.name.toLowerCase().includes(createMention.query))
        .slice(0, 8);
});

const editMentionSuggestions = computed(() => {
    if (!editMention.open) {
        return [];
    }

    return (props.users || [])
        .filter((u) => u.name.toLowerCase().includes(editMention.query))
        .slice(0, 8);
});

function insertMention(form, state, textareaRef, user) {
    const before = form.description.slice(0, state.start);
    const after = form.description.slice(state.end);
    const mentionText = `@[${user.name}] `;
    form.description = `${before}${mentionText}${after}`;
    state.open = false;

    nextTick(() => {
        const el = textareaRef.value;
        if (!el) {
            return;
        }

        const pos = before.length + mentionText.length;
        el.focus();
        el.setSelectionRange(pos, pos);
    });
}

function onCreateDescriptionInput(event) {
    updateMentionState(taskForm.description || '', event.target.selectionStart, createMention);
}

function onEditDescriptionInput(event) {
    updateMentionState(editForm.description || '', event.target.selectionStart, editMention);
}

function onCreateDescriptionKeydown(event) {
    if (event.key === 'Escape') {
        createMention.open = false;
        return;
    }

    if (event.key === 'Enter' && createMention.open && createMentionSuggestions.value.length) {
        event.preventDefault();
        insertMention(taskForm, createMention, createDescriptionRef, createMentionSuggestions.value[0]);
    }
}

function onEditDescriptionKeydown(event) {
    if (event.key === 'Escape') {
        editMention.open = false;
        return;
    }

    if (event.key === 'Enter' && editMention.open && editMentionSuggestions.value.length) {
        event.preventDefault();
        insertMention(editForm, editMention, editDescriptionRef, editMentionSuggestions.value[0]);
    }
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function renderTaskDescription(text) {
    if (!text) {
        return '';
    }

    let content = escapeHtml(text);
    content = content.replace(
        /(https?:\/\/[^\s<]+)/g,
        '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-brand-600 underline">$1</a>',
    );
    content = content.replace(
        /@\[([^\]]+)\]/g,
        '<span class="rounded bg-brand-50 px-1 text-brand-700">@$1</span>',
    );

    return content.replaceAll('\n', '<br>');
}

function formatMessageTime(iso) {
    return new Date(iso).toLocaleString('ar-SA', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

async function sendTaskMessage() {
    if (!editingTask.value || !taskMessageBody.value.trim() || taskMessageSending.value) {
        return;
    }

    taskMessageSending.value = true;
    taskMessageError.value = '';

    try {
        const response = await window.axios.post(
            route('tasks.messages.store', editingTask.value.id),
            { body: taskMessageBody.value },
            { headers: { Accept: 'application/json' } },
        );

        if (response?.data?.message) {
            taskMessages.value.push(response.data.message);
            editingTask.value.messages = [...taskMessages.value];
            taskMessageBody.value = '';
        }
    } catch (error) {
        taskMessageError.value = error?.response?.data?.message || 'تعذر إرسال الرسالة، حاول مرة أخرى.';
    } finally {
        taskMessageSending.value = false;
    }
}

function formatFileSize(size) {
    const value = Number(size || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function onTaskAttachmentSelected(event) {
    const selected = event.target?.files?.[0] || null;
    if (!selected) {
        taskAttachmentFile.value = null;
        return;
    }

    if (Number(selected.size || 0) > maxAttachmentSizeBytes) {
        taskAttachmentFile.value = null;
        taskAttachmentError.value = 'حجم الملف أكبر من 10MB. اختر ملفًا أصغر.';
        if (attachmentInputRef.value) {
            attachmentInputRef.value.value = '';
        }
        return;
    }

    taskAttachmentError.value = '';
    taskAttachmentFile.value = selected;
}

async function uploadTaskAttachment() {
    if (!editingTask.value || !taskAttachmentFile.value || taskAttachmentUploading.value) {
        return;
    }

    taskAttachmentUploading.value = true;
    taskAttachmentProgress.value = 0;
    taskAttachmentError.value = '';

    try {
        const payload = new FormData();
        payload.append('file', taskAttachmentFile.value);

        const response = await window.axios.post(
            route('tasks.attachments.store', editingTask.value.id),
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
                    taskAttachmentProgress.value = Math.min(100, Math.round((Number(event.loaded || 0) / total) * 100));
                },
            },
        );

        if (response?.data?.attachment) {
            taskAttachments.value.unshift(response.data.attachment);
            editingTask.value.attachments = [...taskAttachments.value];
            taskAttachmentFile.value = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            taskAttachmentProgress.value = 0;
        }
    } catch (error) {
        taskAttachmentError.value = error?.response?.data?.message || 'تعذر رفع المرفق، حاول مرة أخرى.';
    } finally {
        taskAttachmentUploading.value = false;
    }
}

async function deleteTaskAttachment(attachment) {
    if (!attachment?.id || !confirm('حذف هذا المرفق؟')) {
        return;
    }

    try {
        await window.axios.delete(route('tasks.attachments.destroy', attachment.id), {
            headers: { Accept: 'application/json' },
        });
        taskAttachments.value = taskAttachments.value.filter((item) => item.id !== attachment.id);
        if (editingTask.value) {
            editingTask.value.attachments = [...taskAttachments.value];
        }
    } catch {
        taskAttachmentError.value = 'تعذر حذف المرفق، حاول مرة أخرى.';
    }
}

function submitReassignment() {
    if (!quickActionTask.value) {
        return;
    }

    quickActionForm
        .transform((data) => ({
            assigned_to_id: data.assigned_to_id || null,
            due_at: data.due_at || null,
            note: data.note || null,
        }))
        .post(route('tasks.reassignments.store', quickActionTask.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeQuickActionModal();
            },
        });
}

function submitChecklistItem() {
    if (!quickActionTask.value) {
        return;
    }

    const titles = (quickActionForm.checklist_titles || [])
        .map((line) => String(line || '').trim())
        .filter((line) => line.length);

    if (!titles.length) {
        return;
    }

    quickActionForm
        .transform((data) => ({
            titles: (data.checklist_titles || [])
                .map((line) => String(line || '').trim())
                .filter((line) => line.length),
        }))
        .post(route('tasks.checklist-items.store', quickActionTask.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                quickActionForm.checklist_titles = [''];
            },
        });
}

function addChecklistField() {
    quickActionForm.checklist_titles.push('');
}

function removeChecklistField(index) {
    if (quickActionForm.checklist_titles.length <= 1) {
        quickActionForm.checklist_titles = [''];
        return;
    }
    quickActionForm.checklist_titles.splice(index, 1);
}

function toggleChecklistItem(item) {
    router.patch(
        route('tasks.checklist-items.toggle', item.id),
        { is_done: !item.is_done },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="المهام" />

    <AuthenticatedLayout>
        <template #title>المهام</template>

        <div class="mx-auto max-w-[1600px]">
            <div
                class="mb-4 flex flex-col gap-2 rounded-lg bg-white p-3 shadow ring-1 ring-gray-200 sm:flex-row sm:flex-wrap sm:items-center"
            >
                <span class="text-sm text-gray-600">العميل:</span>
                <select
                    class="w-full rounded-md border-gray-300 text-sm shadow-sm sm:w-auto"
                    :value="filters?.client_id ?? ''"
                    @change="setClientFilterFromSelect"
                >
                    <option value="">الكل</option>
                    <option
                        v-for="c in clients || []"
                        :key="c.id"
                        :value="c.id"
                    >
                        {{ c.name }}
                    </option>
                </select>
                <span v-if="filterClient" class="text-sm text-gray-700">
                    عرض مهام:
                    <strong>{{ filterClient.name }}</strong>
                </span>
            </div>

            <div class="mb-4 flex flex-col gap-3 border-b border-gray-200 pb-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div class="flex gap-2 overflow-x-auto whitespace-nowrap pb-1">
                    <Link
                        v-for="t in teams"
                        :key="t.id"
                        :href="teamHref(t.slug)"
                        :class="[
                            'rounded-full px-3 py-1 text-sm font-medium',
                            team && team.slug === t.slug
                                ? 'bg-brand-600 text-white'
                                : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50',
                        ]"
                    >
                        {{ localizeTeamName(t.name, t.slug) }}
                    </Link>
                </div>

                <PrimaryButton
                    type="button"
                    :disabled="!board"
                    class="w-full justify-center sm:w-auto"
                    @click="openCreateModal"
                >
                    إضافة مهمة
                </PrimaryButton>
            </div>

            <div
                v-if="!board"
                class="rounded-lg bg-white p-8 text-center text-gray-600 shadow"
            >
                اختر فريقاً لعرض لوحة المهام.
            </div>

            <div v-else class="space-y-4">
                <div
                    class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2"
                    style="min-height: 320px"
                >
                    <div
                        v-for="col in boardState.columns"
                        :key="col.id"
                        class="w-[85vw] max-w-sm shrink-0 snap-start rounded-lg bg-white p-3 shadow ring-1 ring-gray-200 sm:w-72"
                    >
                        <h3
                            class="mb-2 border-b border-gray-100 pb-2 text-sm font-semibold text-gray-800"
                        >
                            {{ localizeColumnName(col.name) }}
                        </h3>
                        <draggable
                            v-model="col.tasks"
                            group="tasks"
                            item-key="id"
                            class="min-h-40 space-y-2"
                            ghost-class="opacity-50"
                            @end="onDragEnd"
                        >
                            <template #item="{ element }">
                                <div
                                    class="relative cursor-grab rounded-md border border-gray-100 bg-gray-50 p-2 pe-16 text-sm active:cursor-grabbing"
                                >
                                    <button
                                        v-if="canDeleteRecords"
                                        type="button"
                                        class="absolute end-14 top-1 rounded p-1 text-red-500 hover:bg-red-50 hover:text-red-700"
                                        title="حذف المهمة"
                                        @click.stop="deleteTask(element.id)"
                                    >
                                        <span class="sr-only">حذف</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2h.293l.91 10.11A2 2 0 007.196 18h5.608a2 2 0 001.993-1.89L15.707 6H16a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zm-1 5a1 1 0 012 0v7a1 1 0 11-2 0V7zm4-1a1 1 0 00-1 1v7a1 1 0 102 0V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="absolute end-7 top-1 rounded p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
                                        title="إعادة تعيين / قائمة تحقق"
                                        @click.stop="openQuickAction(element)"
                                    >
                                        <span class="sr-only">إضافة متابعة</span>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="absolute end-1 top-1 rounded p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-700"
                                        title="تعديل"
                                        @click.stop="openEdit(element)"
                                    >
                                        <span class="sr-only">تعديل</span>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                            />
                                        </svg>
                                    </button>
                                    <p class="font-medium text-gray-900">
                                        {{ element.title }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="u in (element.assignees?.length ? element.assignees : (element.assignee ? [element.assignee] : []))"
                                            :key="`task-assignee-${element.id}-${u.id}`"
                                            class="rounded-full bg-gray-200 px-2 py-0.5 text-[11px] text-gray-700"
                                        >
                                            {{ u.name }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="element.client"
                                        class="text-xs text-brand-600"
                                    >
                                        {{ element.client.name }}
                                    </p>
                                    <p
                                        v-if="element.description"
                                        class="mt-1 max-h-16 overflow-hidden text-xs text-gray-600"
                                        v-html="renderTaskDescription(element.description)"
                                    />
                                    <p v-if="element.due_at" class="mt-1 text-[11px] text-orange-700">
                                        الاستحقاق: {{ formatMessageTime(element.due_at) }}
                                    </p>
                                    <p
                                        v-if="element.checklist_items?.length"
                                        class="mt-1 text-[11px] text-gray-500"
                                    >
                                        قائمة التحقق: {{ element.checklist_items.filter((i) => i.is_done).length }}/{{ element.checklist_items.length }}
                                    </p>
                                    <p v-if="element.attachments?.length" class="mt-1 text-[11px] text-blue-700">
                                        مرفقات: {{ element.attachments.length }}
                                    </p>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="createModalOpen" @close="closeCreateModal">
            <div class="p-4 sm:p-6" @click.stop>
                <h2 class="text-lg font-semibold text-gray-900">إضافة مهمة جديدة</h2>
                <form class="mt-4 space-y-3" @submit.prevent="submitTask">
                    <div>
                        <InputLabel for="ct_title" value="عنوان المهمة" />
                        <TextInput
                            id="ct_title"
                            v-model="taskForm.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="taskForm.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="ct_column" value="الحالة" />
                        <select
                            id="ct_column"
                            v-model="taskForm.board_column_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            required
                        >
                            <option
                                v-for="col in boardState.columns"
                                :key="col.id"
                                :value="col.id"
                            >
                                {{ localizeColumnName(col.name) }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="taskForm.errors.board_column_id" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="ct_client" value="العميل" />
                            <select
                                id="ct_client"
                                v-model="taskForm.client_id"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            >
                                <option :value="null">—</option>
                                <option
                                    v-for="c in clientOptions"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.label }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="taskForm.errors.client_id" />
                        </div>

                        <div>
                            <InputLabel for="ct_assignee" value="المكلفون" />
                            <select
                                id="ct_assignee"
                                v-model="taskForm.assignee_ids"
                                multiple
                                size="4"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            >
                                <option
                                    v-for="u in users || []"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">يمكن اختيار أكثر من موظف.</p>
                            <InputError class="mt-1" :message="taskForm.errors.assignee_ids" />
                        </div>
                    </div>

                    <div class="relative">
                        <InputLabel for="ct_desc" value="ملاحظات (يدعم منشن وروابط)" />
                        <textarea
                            id="ct_desc"
                            ref="createDescriptionRef"
                            v-model="taskForm.description"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            placeholder="اكتب ملاحظات المهمة... واستخدم @ للمنشن"
                            @input="onCreateDescriptionInput"
                            @keydown="onCreateDescriptionKeydown"
                            @blur="setTimeout(() => (createMention.open = false), 120)"
                        />
                        <InputError class="mt-1" :message="taskForm.errors.description" />

                        <div
                            v-if="createMention.open && createMentionSuggestions.length"
                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg"
                        >
                            <button
                                v-for="u in createMentionSuggestions"
                                :key="`create-mention-${u.id}`"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-start text-sm hover:bg-gray-50"
                                @mousedown.prevent="insertMention(taskForm, createMention, createDescriptionRef, u)"
                            >
                                <span>{{ u.name }}</span>
                                <span class="text-xs text-gray-400">@</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:flex-wrap">
                        <PrimaryButton :disabled="taskForm.processing" type="submit" class="w-full justify-center sm:w-auto">
                            إضافة
                        </PrimaryButton>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 sm:w-auto"
                            @click="closeCreateModal"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="editModalOpen" @close="closeEditModal">
            <div class="p-4 sm:p-6" @click.stop>
                <h2 class="text-lg font-semibold text-gray-900">تعديل المهمة</h2>
                <form class="mt-4 space-y-3" @submit.prevent="saveTaskEdit">
                    <div>
                        <InputLabel for="et_title" value="العنوان" />
                        <TextInput
                            id="et_title"
                            v-model="editForm.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="editForm.errors.title" />
                    </div>

                    <div class="relative">
                        <InputLabel for="et_desc" value="ملاحظات المهمة" />
                        <textarea
                            id="et_desc"
                            ref="editDescriptionRef"
                            v-model="editForm.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            placeholder="استخدم @ للمنشن"
                            @input="onEditDescriptionInput"
                            @keydown="onEditDescriptionKeydown"
                            @blur="setTimeout(() => (editMention.open = false), 120)"
                        />
                        <InputError class="mt-1" :message="editForm.errors.description" />

                        <div
                            v-if="editMention.open && editMentionSuggestions.length"
                            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg"
                        >
                            <button
                                v-for="u in editMentionSuggestions"
                                :key="`edit-mention-${u.id}`"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-start text-sm hover:bg-gray-50"
                                @mousedown.prevent="insertMention(editForm, editMention, editDescriptionRef, u)"
                            >
                                <span>{{ u.name }}</span>
                                <span class="text-xs text-gray-400">@</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="et_client" value="العميل" />
                        <select
                            id="et_client"
                            v-model="editForm.client_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        >
                            <option :value="null">—</option>
                            <option
                                v-for="c in clientOptions"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.label }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="editForm.errors.client_id" />
                    </div>

                    <div>
                        <InputLabel for="et_assignee" value="المكلفون" />
                        <select
                            id="et_assignee"
                            v-model="editForm.assignee_ids"
                            multiple
                            size="4"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        >
                            <option
                                v-for="u in users || []"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">يمكن اختيار أكثر من موظف.</p>
                        <InputError class="mt-1" :message="editForm.errors.assignee_ids" />
                    </div>

                    <div>
                        <InputLabel for="et_due" value="تاريخ الاستحقاق" />
                        <TextInput
                            id="et_due"
                            v-model="editForm.due_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="editForm.errors.due_at" />
                    </div>

                    <div class="space-y-2 rounded-md border border-gray-200 p-3">
                        <div class="text-sm font-semibold text-gray-800">محادثة المهمة</div>
                        <div class="max-h-48 space-y-2 overflow-y-auto rounded bg-gray-50 p-2">
                            <div
                                v-for="msg in taskMessages"
                                :key="`task-message-${msg.id}`"
                                class="rounded bg-white px-2 py-1.5 text-sm ring-1 ring-gray-200"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-gray-900">{{ msg.user?.name || 'عضو' }}</span>
                                    <span class="text-xs text-gray-500">{{ formatMessageTime(msg.created_at) }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ msg.body }}</p>
                            </div>
                            <p v-if="!taskMessages.length" class="text-center text-xs text-gray-500">
                                لا توجد رسائل بعد داخل هذه المهمة.
                            </p>
                        </div>

                        <textarea
                            v-model="taskMessageBody"
                            rows="2"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            placeholder="اكتب رسالة داخل المهمة..."
                        />
                        <div class="flex items-center justify-between gap-2">
                            <p v-if="taskMessageError" class="text-xs text-red-600">{{ taskMessageError }}</p>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                                :disabled="taskMessageSending"
                                @click="sendTaskMessage"
                            >
                                {{ taskMessageSending ? 'جاري الإرسال...' : 'إرسال رسالة' }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-md border border-gray-200 p-3">
                        <div class="text-sm font-semibold text-gray-800">مرفقات المهمة</div>
                        <div class="max-h-44 space-y-2 overflow-y-auto rounded bg-gray-50 p-2">
                            <div
                                v-for="attachment in taskAttachments"
                                :key="`task-attachment-${attachment.id}`"
                                class="rounded bg-white p-2 ring-1 ring-gray-200"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <a
                                            :href="attachment.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="truncate text-xs font-semibold text-brand-600 hover:underline"
                                        >
                                            {{ attachment.name }}
                                        </a>
                                        <p class="mt-0.5 text-[11px] text-gray-500">
                                            {{ attachment.mime || 'ملف' }} • {{ formatFileSize(attachment.size) }}
                                        </p>
                                    </div>
                                    <button
                                        v-if="canDeleteRecords"
                                        type="button"
                                        class="text-xs text-red-600 hover:underline"
                                        @click="deleteTaskAttachment(attachment)"
                                    >
                                        حذف
                                    </button>
                                </div>
                                <img
                                    v-if="attachment.is_image"
                                    :src="attachment.url"
                                    alt="مرفق المهمة"
                                    class="mt-2 max-h-40 rounded border border-gray-200"
                                />
                            </div>
                            <p v-if="!taskAttachments.length" class="text-center text-xs text-gray-500">
                                لا توجد مرفقات بعد.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <input
                                ref="attachmentInputRef"
                                type="file"
                                class="block w-full text-xs text-gray-600 file:me-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-medium"
                                @change="onTaskAttachmentSelected"
                            />
                            <p class="text-[11px] text-gray-500">الحد الأقصى لحجم المرفق: 10MB</p>
                            <div v-if="taskAttachmentUploading" class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full bg-blue-600 transition-all"
                                    :style="{ width: `${taskAttachmentProgress}%` }"
                                />
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <p v-if="taskAttachmentError" class="text-xs text-red-600">{{ taskAttachmentError }}</p>
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                                    :disabled="taskAttachmentUploading || !taskAttachmentFile"
                                    @click="uploadTaskAttachment"
                                >
                                    {{ taskAttachmentUploading ? 'جاري الرفع...' : 'رفع مرفق' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-md border border-gray-200 p-3">
                        <div class="text-sm font-semibold text-gray-800">سجل إعادة التعيين</div>
                        <div class="max-h-36 space-y-2 overflow-y-auto rounded bg-gray-50 p-2">
                            <div
                                v-for="row in (editingTask?.reassignments || [])"
                                :key="`reassign-${row.id}`"
                                class="rounded bg-white px-2 py-1 text-xs ring-1 ring-gray-200"
                            >
                                <div class="text-gray-700">
                                    {{ row.assigned_by?.name || '—' }} → {{ row.assigned_to?.name || '—' }}
                                </div>
                                <div class="text-gray-500">
                                    {{ row.due_at ? `استحقاق: ${formatMessageTime(row.due_at)}` : 'بدون استحقاق' }}
                                </div>
                            </div>
                            <p v-if="!(editingTask?.reassignments || []).length" class="text-center text-xs text-gray-500">
                                لا يوجد سجل إعادة تعيين بعد.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 rounded-md border border-gray-200 p-3">
                        <div class="text-sm font-semibold text-gray-800">قائمة تحقق المهمة</div>
                        <div class="max-h-36 space-y-1 overflow-y-auto rounded bg-gray-50 p-2">
                            <label
                                v-for="item in (editingTask?.checklist_items || [])"
                                :key="`check-${item.id}`"
                                class="flex items-center gap-2 text-xs text-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-300 text-brand-600"
                                    :checked="item.is_done"
                                    @change="toggleChecklistItem(item)"
                                />
                                <span :class="item.is_done ? 'line-through text-gray-400' : ''">{{ item.title }}</span>
                            </label>
                            <p v-if="!(editingTask?.checklist_items || []).length" class="text-center text-xs text-gray-500">
                                لا توجد قائمة تحقق بعد.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:flex-wrap">
                        <PrimaryButton :disabled="editForm.processing" type="submit" class="w-full justify-center sm:w-auto">
                            حفظ
                        </PrimaryButton>
                        <button
                            v-if="canDeleteRecords && editingTask"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-50 sm:w-auto"
                            @click="deleteTask(editingTask.id)"
                        >
                            حذف المهمة
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 sm:w-auto"
                            @click="closeEditModal"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="quickActionModalOpen" @close="closeQuickActionModal">
            <div class="p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">إضافة متابعة للمهمة</h2>
                <p class="mt-1 text-sm text-gray-600">{{ quickActionTask?.title || '' }}</p>

                <form class="mt-4 space-y-4" @submit.prevent="submitReassignment">
                    <div class="rounded-md border border-gray-200 p-3">
                        <h3 class="text-sm font-semibold text-gray-800">إعادة تعيين لموظف + استحقاق</h3>
                        <div class="mt-2 space-y-2">
                            <div>
                                <InputLabel for="qa_assignee" value="الموظف" />
                                <select
                                    id="qa_assignee"
                                    v-model="quickActionForm.assigned_to_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                    required
                                >
                                    <option value="">اختر موظف</option>
                                    <option v-for="u in users || []" :key="`qa-user-${u.id}`" :value="u.id">
                                        {{ u.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="qa_due" value="تاريخ الاستحقاق (اختياري)" />
                                <TextInput
                                    id="qa_due"
                                    v-model="quickActionForm.due_at"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel for="qa_note" value="ملاحظة (اختياري)" />
                                <textarea
                                    id="qa_note"
                                    v-model="quickActionForm.note"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                />
                            </div>
                            <InputError class="mt-1" :message="quickActionForm.errors.assigned_to_id || quickActionForm.errors.due_at || quickActionForm.errors.note" />
                            <PrimaryButton :disabled="quickActionForm.processing" type="submit">
                                حفظ إعادة التعيين
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <form class="mt-4 space-y-3 rounded-md border border-gray-200 p-3" @submit.prevent="submitChecklistItem">
                    <h3 class="text-sm font-semibold text-gray-800">إضافة قائمة تحقق متعددة</h3>
                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in quickActionForm.checklist_titles"
                            :key="`checklist-input-${index}`"
                            class="flex items-center gap-2"
                        >
                            <TextInput
                                :id="`qa_checklist_${index}`"
                                v-model="quickActionForm.checklist_titles[index]"
                                type="text"
                                class="block w-full"
                                placeholder="اكتب عنصر قائمة التحقق"
                            />
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 text-lg font-semibold text-gray-700 hover:bg-gray-50"
                                title="إضافة حقل جديد"
                                @click="addChecklistField"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-red-200 text-lg font-semibold text-red-700 hover:bg-red-50"
                                title="حذف هذا الحقل"
                                @click="removeChecklistField(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">استخدم زر + لإضافة عناصر جديدة بنفس الوقت.</p>
                    <InputError class="mt-1" :message="quickActionForm.errors.titles || quickActionForm.errors.title" />
                    <PrimaryButton :disabled="quickActionForm.processing" type="submit">
                        إضافة العناصر
                    </PrimaryButton>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
