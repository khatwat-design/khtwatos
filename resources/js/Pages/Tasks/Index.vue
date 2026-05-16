<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import OpsToolbar from '@/Components/ops/OpsToolbar.vue';
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
    revision: 'تعديل',
    modify: 'تعديل',
    done: 'تم',
    completed: 'تم',
    blocked: 'متوقفة',
};

/** Board column name aliases (Arabic defaults + occasional English labels). */
const COLUMN_REVIEW_ALIASES = ['مراجعة', 'Review', 'review'];
const COLUMN_REVISION_ALIASES = ['تعديل', 'Revision', 'revision'];
const COLUMN_DONE_ALIASES = ['تم', 'مكتمل', 'منجز', 'Done', 'Completed', 'done', 'completed'];

function columnMatchesAliases(columnName, aliases) {
    const raw = String(columnName || '').trim();
    const low = raw.toLowerCase();

    return aliases.some((a) => raw === a || low === String(a).toLowerCase());
}

function findBoardColumnId(aliases) {
    for (const alias of aliases) {
        const exact = boardState.columns.find((c) => c.name === alias);
        if (exact) {
            return exact.id;
        }
    }
    const lowered = new Map(boardState.columns.map((c) => [String(c.name || '').trim().toLowerCase(), c.id]));
    for (const alias of aliases) {
        const id = lowered.get(String(alias).trim().toLowerCase());
        if (id) {
            return id;
        }
    }

    return null;
}

function moveTaskToColumn(taskId, fromColumnId, toColumnId) {
    if (!toColumnId || Number(fromColumnId) === Number(toColumnId)) {
        return;
    }
    const sourceColumn = boardState.columns.find((column) => Number(column.id) === Number(fromColumnId));
    const targetColumn = boardState.columns.find((column) => Number(column.id) === Number(toColumnId));
    if (!sourceColumn || !targetColumn) {
        return;
    }

    const sourceIndex = sourceColumn.tasks.findIndex((task) => Number(task.id) === Number(taskId));
    if (sourceIndex < 0) {
        return;
    }

    const [task] = sourceColumn.tasks.splice(sourceIndex, 1);
    targetColumn.tasks.unshift(task);
    onDragEnd();
}

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
const isBoardLoading = ref(false);
const isBoardSyncing = ref(false);
const boardSyncOk = ref(false);
let boardSyncOkTimer;
const isMobileWorkspaceExpanded = ref(false);
const isTouchDevice =
    typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

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
        include_archived: props.filters?.include_archived ? 1 : undefined,
    });
}

function setClientFilterFromSelect(event) {
    const raw = event.target.value;
    const clientId = raw === '' ? undefined : Number(raw);
    isBoardLoading.value = true;
    router.get(
        route('tasks.index'),
        {
            team: props.team?.slug,
            client_id: clientId,
            include_archived: props.filters?.include_archived ? 1 : undefined,
        },
        {
            preserveState: true,
            replace: true,
            onFinish: () => {
                isBoardLoading.value = false;
            },
        },
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
        boardSyncOk.value = false;
        isBoardSyncing.value = true;
        window.axios
            .patch(
                route('task-boards.sync', boardState.id),
                {
                    columns: boardState.columns.map((c) => ({
                        id: c.id,
                        task_ids: c.tasks.map((t) => t.id),
                    })),
                },
                {
                    headers: { Accept: 'application/json' },
                },
            )
            .then(() => {
                taskMessageError.value = '';
                boardSyncOk.value = true;
                clearTimeout(boardSyncOkTimer);
                boardSyncOkTimer = setTimeout(() => {
                    boardSyncOk.value = false;
                }, 1400);
            })
            .catch(() => {
                boardSyncOk.value = false;
                // Keep user feedback minimal; backend remains source of truth on next load.
                taskMessageError.value = 'تعذر حفظ ترتيب المهام. حاول مرة أخرى.';
            })
            .finally(() => {
                isBoardSyncing.value = false;
            });
    }, 80);
}

function toggleMobileWorkspace() {
    isMobileWorkspaceExpanded.value = !isMobileWorkspaceExpanded.value;
}

function getNextColumnById(columnId) {
    const currentIndex = boardState.columns.findIndex((column) => Number(column.id) === Number(columnId));
    if (currentIndex < 0) {
        return null;
    }
    return boardState.columns[currentIndex + 1] || null;
}

function getAdvanceActionLabel(columnName) {
    const key = String(columnName || '')
        .trim()
        .toLowerCase();

    if (key === 'قائمة الانتظار' || key === 'todo' || key === 'to do' || key === 'backlog') {
        return 'بدء التنفيذ';
    }
    if (key === 'قيد التنفيذ' || key === 'doing' || key === 'in progress') {
        return 'إرسال للمراجعة';
    }
    if (columnMatchesAliases(columnName, COLUMN_REVISION_ALIASES)) {
        return 'إعادة للمراجعة';
    }
    if (columnMatchesAliases(columnName, COLUMN_REVIEW_ALIASES)) {
        return 'اعتماد أو تعديل';
    }

    return 'نقل للمرحلة التالية';
}

function getAdvanceIconType(columnName) {
    const key = String(columnName || '')
        .trim()
        .toLowerCase();

    if (key === 'قائمة الانتظار' || key === 'todo' || key === 'to do' || key === 'backlog') {
        return 'play';
    }
    if (key === 'قيد التنفيذ' || key === 'doing' || key === 'in progress') {
        return 'review';
    }
    if (columnMatchesAliases(columnName, COLUMN_REVISION_ALIASES)) {
        return 'review';
    }
    if (columnMatchesAliases(columnName, COLUMN_REVIEW_ALIASES)) {
        return 'next';
    }

    return 'next';
}

function moveTaskToNextColumn(taskId, fromColumnId) {
    const sourceColumn = boardState.columns.find((column) => Number(column.id) === Number(fromColumnId));
    const targetColumn = getNextColumnById(fromColumnId);
    if (!sourceColumn || !targetColumn) {
        return;
    }

    const sourceIndex = sourceColumn.tasks.findIndex((task) => Number(task.id) === Number(taskId));
    if (sourceIndex < 0) {
        return;
    }

    const [task] = sourceColumn.tasks.splice(sourceIndex, 1);
    targetColumn.tasks.unshift(task);
    onDragEnd();
}

const editModalOpen = ref(false);
const editingTask = ref(null);
const taskDetailsLoading = ref(false);
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

async function openEdit(task) {
    editingTask.value = task;
    taskDetailsLoading.value = true;
    taskMessages.value = [];
    taskAttachments.value = [];
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

    try {
        const response = await window.axios.get(route('tasks.details', task.id), {
            headers: { Accept: 'application/json' },
        });
        const details = response?.data?.task || {};
        editingTask.value = { ...editingTask.value, ...details };
        taskMessages.value = [...(details.messages || [])];
        taskAttachments.value = [...(details.attachments || [])];
    } catch {
        taskMessageError.value = 'تعذر تحميل تفاصيل المهمة، حاول مرة أخرى.';
    } finally {
        taskDetailsLoading.value = false;
    }
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
    taskDetailsLoading.value = false;
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

async function toggleChecklistItem(item) {
    try {
        await window.axios.patch(
            route('tasks.checklist-items.toggle', item.id),
            { is_done: !item.is_done },
            { headers: { Accept: 'application/json' } },
        );
        item.is_done = !item.is_done;
    } catch {
        taskMessageError.value = 'تعذر تحديث عنصر قائمة التحقق.';
    }
}
</script>

<template>
    <Head title="المهام" />

    <AuthenticatedLayout>
        <template #title>المهام</template>

        <div
            :class="[
                'mx-auto w-full min-w-0',
                isMobileWorkspaceExpanded ? 'max-w-none px-0 sm:px-0' : 'max-w-[1760px] px-3 sm:px-4 lg:px-6',
            ]"
        >
            <OpsToolbar dense class="mb-4" data-tour="tasks-toolbar">
                <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <span class="shrink-0 text-sm font-medium text-slate-600">العميل</span>
                    <select
                        class="w-full rounded-xl border-slate-200/80 bg-white/80 text-sm shadow-sm transition-all duration-200 ease-out focus:border-brand-300 focus:ring-brand-200 sm:w-auto"
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
                    <span v-if="filterClient" class="text-sm text-slate-700">
                        عرض مهام:
                        <strong>{{ filterClient.name }}</strong>
                    </span>
                </div>
            </OpsToolbar>

            <div
                class="mb-3 flex flex-col gap-2 border-b border-slate-200/80 pb-2 sm:mb-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3 sm:pb-3 lg:mb-5 lg:gap-4 lg:pb-4"
            >
                <div
                    class="-mx-0.5 flex min-w-0 snap-x snap-mandatory gap-1.5 overflow-x-auto overscroll-x-contain px-0.5 pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:max-w-none sm:flex-1 sm:gap-2 sm:px-0 lg:gap-2.5 [&::-webkit-scrollbar]:hidden"
                    dir="rtl"
                >
                    <Link
                        v-for="t in teams"
                        :key="t.id"
                        :href="teamHref(t.slug)"
                        :class="[
                            'snap-start shrink-0 rounded-lg px-2.5 py-1.5 text-center text-[11px] font-medium leading-snug transition-all duration-200 ease-out sm:rounded-xl sm:px-3 sm:py-2 sm:text-sm sm:font-semibold lg:px-4 lg:py-2.5 lg:text-[15px]',
                            'max-w-[10.5rem] sm:max-w-none',
                            team && team.slug === t.slug
                                ? 'bg-brand-600 text-white shadow-sm ring-1 ring-brand-500/25'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200/80 hover:bg-slate-50 active:scale-[0.98]',
                        ]"
                    >
                        {{ localizeTeamName(t.name, t.slug) }}
                    </Link>
                </div>

                <PrimaryButton
                    type="button"
                    :disabled="!board"
                    class="hidden shrink-0 items-center justify-center gap-2 sm:inline-flex sm:rounded-xl sm:px-4 sm:py-2 sm:text-sm sm:font-semibold lg:px-5 lg:py-2.5 lg:text-[15px]"
                    @click="openCreateModal"
                >
                    <span>إضافة مهمة</span>
                </PrimaryButton>
            </div>

            <div
                v-if="!board"
                class="ui-card p-8 text-center text-slate-600"
            >
                اختر فريقاً لعرض لوحة المهام.
            </div>

            <div v-else class="space-y-4">
                <div class="flex min-h-6 flex-wrap items-center gap-2">
                    <div
                        v-if="isBoardSyncing"
                        class="inline-flex items-center gap-2 rounded-full border border-brand-200/70 bg-brand-50/85 px-3 py-1 text-[11px] font-medium text-brand-700"
                    >
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-600" />
                        جار حفظ ترتيب المهام...
                    </div>
                    <div
                        v-else-if="boardSyncOk"
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200/80 bg-emerald-50/90 px-3 py-1 text-[11px] font-medium text-emerald-800"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600" />
                        تم حفظ اللوحة
                    </div>
                </div>
                <div v-if="isBoardLoading" class="flex gap-3 overflow-x-auto pb-2 lg:gap-4">
                    <div v-for="n in 3" :key="`task-skeleton-${n}`" class="ui-card w-[85vw] max-w-sm shrink-0 p-3 sm:w-72 lg:w-80 xl:w-[22rem]">
                        <div class="skeleton mb-3 h-4 w-32 rounded" />
                        <div class="space-y-2">
                            <div class="skeleton h-16 rounded-xl" />
                            <div class="skeleton h-16 rounded-xl" />
                            <div class="skeleton h-16 rounded-xl" />
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    data-tour="tasks-board"
                    class="task-workspace flex snap-x snap-mandatory overflow-x-auto pb-2 lg:min-h-[28rem] lg:gap-4 lg:pb-3"
                    :class="isMobileWorkspaceExpanded ? 'task-workspace-expanded gap-2 px-1' : 'gap-3'"
                    style="min-height: 320px"
                >
                    <div
                        v-for="col in boardState.columns"
                        :key="col.id"
                        class="ui-card task-column-dropzone shrink-0 snap-start p-3 transition-all duration-200 ease-out lg:p-4"
                        :class="
                            isMobileWorkspaceExpanded
                                ? 'task-column-compact w-[66vw] max-w-[260px] sm:w-72 lg:w-80'
                                : 'w-[85vw] max-w-sm sm:w-72 lg:w-80 xl:w-[22rem]'
                        "
                    >
                        <h3
                            class="sticky top-0 z-[5] -mx-3 mb-2 border-b border-slate-200/70 bg-white/95 px-3 pb-2 pt-0 text-sm font-semibold text-slate-800 backdrop-blur-sm lg:mb-3 lg:pb-2.5 lg:text-base"
                        >
                            {{ localizeColumnName(col.name) }}
                        </h3>
                        <draggable
                            v-model="col.tasks"
                            group="tasks"
                            item-key="id"
                            class="task-drop-area min-h-44 space-y-2 rounded-xl px-1 py-2 lg:min-h-[18rem] lg:space-y-2.5 lg:px-1.5 lg:py-2.5"
                            ghost-class="task-ghost"
                            chosen-class="task-chosen"
                            drag-class="task-drag"
                            :animation="180"
                            :delay="isTouchDevice ? 80 : 0"
                            :delay-on-touch-only="true"
                            :touch-start-threshold="3"
                            :fallback-tolerance="4"
                            :empty-insert-threshold="28"
                            :scroll="true"
                            :scroll-sensitivity="90"
                            :scroll-speed="18"
                            @end="onDragEnd"
                        >
                            <template #item="{ element }">
                                <div
                                    class="relative flex cursor-grab flex-col gap-0 rounded-xl border border-white/40 bg-white/80 p-3 text-sm shadow-sm ring-1 ring-slate-200/60 transition-all duration-200 ease-out hover:shadow-md active:cursor-grabbing sm:p-2 sm:pe-24 sm:hover:scale-[1.01] lg:flex-row lg:items-start lg:gap-3 lg:p-3.5 lg:pe-3.5 lg:hover:scale-[1.005]"
                                >
                                    <div class="min-w-0 flex-1 lg:min-w-0">
                                    <p class="font-medium leading-snug text-slate-900 lg:text-[15px] lg:leading-snug">
                                        {{ element.title }}
                                    </p>
                                    <p v-if="element.archived_at" class="mt-1 text-[10px] font-semibold text-slate-500">
                                        مؤرشفة تلقائيًا
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="u in (element.assignees?.length ? element.assignees : (element.assignee ? [element.assignee] : []))"
                                            :key="`task-assignee-${element.id}-${u.id}`"
                                            class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] text-brand-800 ring-1 ring-brand-200"
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
                                        class="mt-1 max-h-16 overflow-hidden text-xs text-slate-600"
                                        v-html="renderTaskDescription(element.description)"
                                    />
                                    <p v-if="element.due_at" class="mt-1 text-[11px] text-orange-700">
                                        الاستحقاق: {{ formatMessageTime(element.due_at) }}
                                    </p>
                                    <p
                                        v-if="element.checklist_total_count"
                                        class="mt-1 text-[11px] text-gray-500"
                                    >
                                        قائمة التحقق: {{ element.checklist_done_count }}/{{ element.checklist_total_count }}
                                    </p>
                                    <p v-if="element.attachments_count" class="mt-1 text-[11px] text-blue-700">
                                        مرفقات: {{ element.attachments_count }}
                                    </p>
                                    </div>

                                    <div
                                        class="relative z-[4] mt-3 flex w-full flex-wrap items-stretch justify-center gap-2 border-t border-slate-200/80 pt-3 sm:absolute sm:mt-0 sm:w-auto sm:flex-nowrap sm:justify-end sm:border-0 sm:bg-transparent sm:pt-0 sm:end-1 sm:top-1.5 sm:gap-0.5 lg:static lg:z-10 lg:mt-0 lg:w-auto lg:shrink-0 lg:flex-col lg:items-center lg:justify-start lg:gap-1.5 lg:self-stretch lg:border lg:border-slate-200/70 lg:bg-slate-50/95 lg:px-1.5 lg:py-2.5 lg:shadow-sm"
                                        @pointerdown.stop
                                        @touchstart.stop
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 w-[30%] max-w-[5.5rem] flex-none items-center justify-center rounded-xl bg-slate-50 text-slate-600 ring-1 ring-slate-200/80 transition-colors hover:bg-slate-100 active:bg-slate-200 sm:min-h-0 sm:h-8 sm:w-8 sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-white/90 lg:ring-1 lg:ring-slate-200/80 lg:hover:bg-slate-50"
                                            title="تعديل"
                                            @click.stop="openEdit(element)"
                                        >
                                            <span class="sr-only">تعديل</span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
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
                                        <template v-if="columnMatchesAliases(col.name, COLUMN_REVIEW_ALIASES)">
                                            <button
                                                v-if="findBoardColumnId(COLUMN_REVISION_ALIASES)"
                                                type="button"
                                                class="inline-flex min-h-11 min-w-0 flex-1 basis-[44%] items-center justify-center rounded-xl bg-orange-50 text-orange-900 ring-1 ring-orange-200/80 transition-colors hover:bg-orange-100 active:bg-orange-100 sm:min-h-0 sm:h-8 sm:w-8 sm:flex-none sm:basis-auto sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-orange-50/95 lg:ring-1 lg:ring-orange-200/80 lg:hover:bg-orange-100"
                                                title="يتطلب تعديلاً — نقل إلى تعديل"
                                                @click.stop="
                                                    moveTaskToColumn(
                                                        element.id,
                                                        col.id,
                                                        findBoardColumnId(COLUMN_REVISION_ALIASES),
                                                    )
                                                "
                                            >
                                                <span class="sr-only">يتطلب تعديلاً</span>
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                                    />
                                                </svg>
                                            </button>
                                            <button
                                                v-if="findBoardColumnId(COLUMN_DONE_ALIASES)"
                                                type="button"
                                                class="inline-flex min-h-11 min-w-0 flex-1 basis-[44%] items-center justify-center rounded-xl bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200/80 transition-colors hover:bg-emerald-100 active:bg-emerald-100 sm:min-h-0 sm:h-8 sm:w-8 sm:flex-none sm:basis-auto sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-emerald-50/95 lg:ring-1 lg:ring-emerald-200/80 lg:hover:bg-emerald-100"
                                                title="اعتماد — نقل إلى تم"
                                                @click.stop="
                                                    moveTaskToColumn(
                                                        element.id,
                                                        col.id,
                                                        findBoardColumnId(COLUMN_DONE_ALIASES),
                                                    )
                                                "
                                            >
                                                <span class="sr-only">اعتماد وإتمام</span>
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </template>
                                        <button
                                            v-else-if="
                                                columnMatchesAliases(col.name, COLUMN_REVISION_ALIASES) &&
                                                findBoardColumnId(COLUMN_REVIEW_ALIASES)
                                            "
                                            type="button"
                                            class="inline-flex min-h-11 w-[30%] max-w-[5.5rem] flex-none items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-200/70 transition-colors hover:bg-brand-100 active:bg-brand-100 sm:min-h-0 sm:h-8 sm:w-8 sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-brand-50/95 lg:ring-1 lg:ring-brand-200/80 lg:hover:bg-brand-100"
                                            title="إعادة للمراجعة"
                                            @click.stop="
                                                moveTaskToColumn(
                                                    element.id,
                                                    col.id,
                                                    findBoardColumnId(COLUMN_REVIEW_ALIASES),
                                                )
                                            "
                                        >
                                            <span class="sr-only">إعادة للمراجعة</span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-else-if="getNextColumnById(col.id)"
                                            type="button"
                                            class="inline-flex min-h-11 w-[30%] max-w-[5.5rem] flex-none items-center justify-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-200/70 transition-colors hover:bg-brand-100 active:bg-brand-100 sm:min-h-0 sm:h-8 sm:w-8 sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-brand-50/95 lg:ring-1 lg:ring-brand-200/80 lg:hover:bg-brand-100"
                                            :title="getAdvanceActionLabel(col.name)"
                                            @click.stop="moveTaskToNextColumn(element.id, col.id)"
                                        >
                                            <span class="sr-only">{{ getAdvanceActionLabel(col.name) }}</span>
                                            <svg
                                                v-if="getAdvanceIconType(col.name) === 'play'"
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path d="M7 5v10l8-5-8-5z" />
                                            </svg>
                                            <svg
                                                v-else-if="getAdvanceIconType(col.name) === 'review'"
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                            </svg>
                                            <svg
                                                v-else-if="getAdvanceIconType(col.name) === 'done'"
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg
                                                v-else
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-11 w-[30%] max-w-[5.5rem] flex-none items-center justify-center rounded-xl bg-slate-50 text-slate-700 ring-1 ring-slate-200/80 transition-colors hover:bg-slate-100 active:bg-slate-200 sm:min-h-0 sm:h-8 sm:w-8 sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-white/90 lg:ring-1 lg:ring-slate-200/80 lg:hover:bg-slate-50"
                                            title="إعادة تعيين / قائمة تحقق"
                                            @click.stop="openQuickAction(element)"
                                        >
                                            <span class="sr-only">إضافة متابعة</span>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="canDeleteRecords"
                                            type="button"
                                            class="hidden min-h-11 w-[30%] max-w-[5.5rem] flex-none items-center justify-center rounded-xl bg-red-50 text-red-600 ring-1 ring-red-200/70 transition-colors hover:bg-red-100 active:bg-red-100 sm:inline-flex sm:min-h-0 sm:h-8 sm:w-8 sm:max-w-none sm:rounded-lg sm:bg-transparent sm:p-1 sm:ring-0 lg:h-9 lg:w-9 lg:rounded-lg lg:bg-red-50/90 lg:ring-1 lg:ring-red-200/80 lg:hover:bg-red-100"
                                            title="حذف المهمة"
                                            @click.stop="deleteTask(element.id)"
                                        >
                                            <span class="sr-only">حذف</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-4 sm:w-4 lg:h-[1.15rem] lg:w-[1.15rem]" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2h.293l.91 10.11A2 2 0 007.196 18h5.608a2 2 0 001.993-1.89L15.707 6H16a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zm-1 5a1 1 0 012 0v7a1 1 0 11-2 0V7zm4-1a1 1 0 00-1 1v7a1 1 0 102 0V7a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </draggable>
                    </div>
                </div>
            </div>
        </div>
        <button
            v-if="board"
            type="button"
            class="fixed bottom-20 left-3 z-40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white/95 text-slate-700 shadow-md backdrop-blur transition-all duration-200 ease-out hover:bg-white active:scale-[0.97] sm:hidden"
            :title="isMobileWorkspaceExpanded ? 'عرض عادي' : 'عرض موسّع للوركسبيس'"
            @click="toggleMobileWorkspace"
        >
            <span class="sr-only">{{ isMobileWorkspaceExpanded ? 'عرض عادي' : 'عرض موسّع للوركسبيس' }}</span>
            <svg
                v-if="!isMobileWorkspaceExpanded"
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 00-2 2v3m16 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M5 16v3a2 2 0 002 2h3" />
            </svg>
            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6m0-6l-7 7M9 21H3v-6m0 6l7-7M21 15v6h-6m6 0l-7-7M3 9V3h6M3 3l7 7" />
            </svg>
        </button>

        <button
            v-if="board"
            type="button"
            class="fixed bottom-20 right-3 z-40 inline-flex h-10 w-10 items-center justify-center rounded-full border border-brand-200 bg-brand-600 text-white shadow-md transition-all duration-200 ease-out hover:bg-brand-700 active:scale-[0.97] sm:hidden"
            aria-label="إضافة مهمة"
            title="إضافة مهمة"
            @click="openCreateModal"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                    fill-rule="evenodd"
                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <Modal :show="createModalOpen" max-width="task" @close="closeCreateModal">
            <div class="glass-modal light-modal p-3 sm:p-4" @click.stop>
                <h2 class="text-base font-semibold text-slate-900">إضافة مهمة جديدة</h2>
                <form class="mt-2.5 space-y-2" @submit.prevent="submitTask">
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
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <InputLabel for="ct_client" value="العميل" />
                            <select
                                id="ct_client"
                                v-model="taskForm.client_id"
                                class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                                size="3"
                                class="mt-1 max-h-[4.5rem] w-full overflow-y-auto rounded-lg border-slate-200 text-xs shadow-sm sm:max-h-32 sm:text-sm"
                            >
                                <option
                                    v-for="u in users || []"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                            <p class="mt-0.5 text-[11px] leading-snug text-gray-500">يمكن اختيار أكثر من موظف.</p>
                            <InputError class="mt-1" :message="taskForm.errors.assignee_ids" />
                        </div>
                    </div>

                    <div class="relative">
                        <InputLabel for="ct_desc" value="ملاحظات (يدعم منشن وروابط)" />
                        <textarea
                            id="ct_desc"
                            ref="createDescriptionRef"
                            v-model="taskForm.description"
                            rows="3"
                            class="mt-1 block min-h-[4.5rem] w-full rounded-lg border-slate-200 text-sm shadow-sm"
                            placeholder="اكتب ملاحظات المهمة... واستخدم @ للمنشن"
                            @input="onCreateDescriptionInput"
                            @keydown="onCreateDescriptionKeydown"
                            @blur="setTimeout(() => (createMention.open = false), 120)"
                        />
                        <InputError class="mt-1" :message="taskForm.errors.description" />

                        <div
                            v-if="createMention.open && createMentionSuggestions.length"
                            class="absolute z-20 mt-1 w-full rounded-xl border border-white/30 bg-white/90 shadow-xl backdrop-blur"
                        >
                            <button
                                v-for="u in createMentionSuggestions"
                                :key="`create-mention-${u.id}`"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-start text-sm transition-colors duration-200 hover:bg-slate-50"
                                @mousedown.prevent="insertMention(taskForm, createMention, createDescriptionRef, u)"
                            >
                                <span>{{ u.name }}</span>
                                <span class="text-xs text-gray-400">@</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-1.5 pt-1.5 sm:flex-row sm:flex-wrap">
                        <PrimaryButton :disabled="taskForm.processing" type="submit" class="w-full justify-center py-2 text-sm sm:w-auto">
                            إضافة
                        </PrimaryButton>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 sm:w-auto"
                            @click="closeCreateModal"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="editModalOpen" max-width="task" @close="closeEditModal">
            <div class="glass-modal p-3 sm:p-4" @click.stop>
                <h2 class="text-base font-semibold text-gray-900">تعديل المهمة</h2>
                <p v-if="taskDetailsLoading" class="mt-0.5 text-[11px] text-gray-500">جاري تحميل التفاصيل...</p>
                <form class="mt-2.5 space-y-2" @submit.prevent="saveTaskEdit">
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
                            rows="2"
                            class="mt-1 block min-h-[3.25rem] w-full rounded-lg border-slate-200 text-sm shadow-sm"
                            placeholder="استخدم @ للمنشن"
                            @input="onEditDescriptionInput"
                            @keydown="onEditDescriptionKeydown"
                            @blur="setTimeout(() => (editMention.open = false), 120)"
                        />
                        <InputError class="mt-1" :message="editForm.errors.description" />

                        <div
                            v-if="editMention.open && editMentionSuggestions.length"
                            class="absolute z-20 mt-1 w-full rounded-xl border border-white/30 bg-white/90 shadow-xl backdrop-blur"
                        >
                            <button
                                v-for="u in editMentionSuggestions"
                                :key="`edit-mention-${u.id}`"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-start text-sm transition-colors duration-200 hover:bg-slate-50"
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
                            class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
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
                            size="3"
                            class="mt-1 max-h-[4.5rem] w-full overflow-y-auto rounded-lg border-gray-300 text-xs shadow-sm sm:max-h-28 sm:text-sm"
                        >
                            <option
                                v-for="u in users || []"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                        <p class="mt-0.5 text-[11px] leading-snug text-gray-500">يمكن اختيار أكثر من موظف.</p>
                        <InputError class="mt-1" :message="editForm.errors.assignee_ids" />
                    </div>

                    <div>
                        <InputLabel for="et_due" value="تاريخ الاستحقاق" />
                        <TextInput
                            id="et_due"
                            v-model="editForm.due_at"
                            type="datetime-local"
                            dir="ltr"
                            class="mt-1 block w-full text-start font-mono"
                        />
                        <InputError class="mt-1" :message="editForm.errors.due_at" />
                    </div>

                    <details
                        class="task-modal-fold overflow-hidden rounded-lg border border-slate-200/80 bg-white/75"
                        :open="taskMessages.length > 0"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-2 px-2 py-1.5 text-xs font-semibold text-gray-800 marker:hidden [&::-webkit-details-marker]:hidden"
                        >
                            <span>محادثة المهمة</span>
                            <span class="shrink-0 font-normal text-[10px] text-gray-500">{{ taskMessages.length }} رسالة</span>
                        </summary>
                        <div class="space-y-1.5 border-t border-slate-100/90 px-2 pb-2 pt-1">
                            <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg bg-slate-50/90 p-1.5">
                                <div
                                    v-for="msg in taskMessages"
                                    :key="`task-message-${msg.id}`"
                                    class="rounded-lg bg-white px-1.5 py-1 text-xs ring-1 ring-slate-200/70"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-gray-900">{{ msg.user?.name || 'عضو' }}</span>
                                        <span class="text-[10px] text-gray-500">{{ formatMessageTime(msg.created_at) }}</span>
                                    </div>
                                    <p class="mt-0.5 whitespace-pre-wrap text-[11px] leading-snug text-gray-700">{{ msg.body }}</p>
                                </div>
                                <p v-if="!taskMessages.length" class="py-1 text-center text-[11px] text-gray-500">
                                    لا توجد رسائل بعد.
                                </p>
                            </div>

                            <textarea
                                v-model="taskMessageBody"
                                rows="2"
                                class="block w-full rounded-lg border-gray-300 text-xs shadow-sm"
                                placeholder="رسالة جديدة..."
                            />
                            <div class="flex flex-wrap items-center justify-between gap-1.5">
                                <p v-if="taskMessageError" class="text-[11px] text-red-600">{{ taskMessageError }}</p>
                                <button
                                    type="button"
                                    class="ms-auto inline-flex items-center rounded-md bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                                    :disabled="taskMessageSending"
                                    @click="sendTaskMessage"
                                >
                                    {{ taskMessageSending ? 'جاري الإرسال...' : 'إرسال' }}
                                </button>
                            </div>
                        </div>
                    </details>

                    <details
                        class="task-modal-fold overflow-hidden rounded-lg border border-slate-200/80 bg-white/75"
                        :open="taskAttachments.length > 0 || !!taskAttachmentFile || taskAttachmentUploading"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-2 px-2 py-1.5 text-xs font-semibold text-gray-800 marker:hidden [&::-webkit-details-marker]:hidden"
                        >
                            <span>مرفقات المهمة</span>
                            <span class="shrink-0 font-normal text-[10px] text-gray-500">{{ taskAttachments.length }} مرفق</span>
                        </summary>
                        <div class="space-y-1.5 border-t border-slate-100/90 px-2 pb-2 pt-1">
                            <div class="max-h-28 space-y-1.5 overflow-y-auto rounded-lg bg-slate-50/90 p-1.5">
                                <div
                                    v-for="attachment in taskAttachments"
                                    :key="`task-attachment-${attachment.id}`"
                                    class="rounded-lg bg-white p-1.5 ring-1 ring-slate-200/70"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <a
                                                :href="attachment.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="truncate text-[11px] font-semibold text-brand-600 hover:underline"
                                            >
                                                {{ attachment.name }}
                                            </a>
                                            <p class="mt-0.5 text-[10px] text-gray-500">
                                                {{ attachment.mime || 'ملف' }} • {{ formatFileSize(attachment.size) }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="canDeleteRecords"
                                            type="button"
                                            class="text-[11px] text-red-600 hover:underline"
                                            @click="deleteTaskAttachment(attachment)"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                    <img
                                        v-if="attachment.is_image"
                                        :src="attachment.url"
                                        alt="مرفق المهمة"
                                        class="mt-1.5 max-h-24 rounded border border-gray-200"
                                    />
                                </div>
                                <p v-if="!taskAttachments.length" class="py-1 text-center text-[11px] text-gray-500">
                                    لا توجد مرفقات بعد.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <input
                                    ref="attachmentInputRef"
                                    type="file"
                                    class="block w-full text-[11px] text-gray-600 file:me-2 file:rounded file:border-0 file:bg-gray-100 file:px-1.5 file:py-0.5 file:text-[11px] file:font-medium"
                                    @change="onTaskAttachmentSelected"
                                />
                                <p class="text-[10px] text-gray-500">حد أقصى 10MB</p>
                                <div v-if="taskAttachmentUploading" class="h-1 overflow-hidden rounded-full bg-gray-100">
                                    <div
                                        class="h-full rounded-full bg-blue-600 transition-all"
                                        :style="{ width: `${taskAttachmentProgress}%` }"
                                    />
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <p v-if="taskAttachmentError" class="text-[11px] text-red-600">{{ taskAttachmentError }}</p>
                                    <button
                                        type="button"
                                        class="ms-auto inline-flex items-center rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="taskAttachmentUploading || !taskAttachmentFile"
                                        @click="uploadTaskAttachment"
                                    >
                                        {{ taskAttachmentUploading ? 'جاري الرفع...' : 'رفع' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </details>

                    <details
                        class="task-modal-fold overflow-hidden rounded-lg border border-slate-200/80 bg-white/75"
                        :open="(editingTask?.reassignments || []).length > 0"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-2 px-2 py-1.5 text-xs font-semibold text-gray-800 marker:hidden [&::-webkit-details-marker]:hidden"
                        >
                            <span>سجل إعادة التعيين</span>
                            <span class="shrink-0 font-normal text-[10px] text-gray-500">{{ (editingTask?.reassignments || []).length }} سجل</span>
                        </summary>
                        <div class="border-t border-slate-100/90 px-2 pb-2 pt-1">
                            <div class="max-h-24 space-y-1 overflow-y-auto rounded-lg bg-slate-50/90 p-1.5">
                                <div
                                    v-for="row in (editingTask?.reassignments || [])"
                                    :key="`reassign-${row.id}`"
                                    class="rounded-lg bg-white px-1.5 py-0.5 text-[11px] ring-1 ring-slate-200/70"
                                >
                                    <div class="text-gray-700">
                                        {{ row.assigned_by?.name || '—' }} → {{ row.assigned_to?.name || '—' }}
                                    </div>
                                    <div class="text-[10px] text-gray-500">
                                        {{ row.due_at ? `استحقاق: ${formatMessageTime(row.due_at)}` : 'بدون استحقاق' }}
                                    </div>
                                </div>
                                <p v-if="!(editingTask?.reassignments || []).length" class="py-1 text-center text-[11px] text-gray-500">
                                    لا يوجد سجل بعد.
                                </p>
                            </div>
                        </div>
                    </details>

                    <details
                        class="task-modal-fold overflow-hidden rounded-lg border border-slate-200/80 bg-white/75"
                        :open="(editingTask?.checklist_items || []).length > 0"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-2 px-2 py-1.5 text-xs font-semibold text-gray-800 marker:hidden [&::-webkit-details-marker]:hidden"
                        >
                            <span>قائمة التحقق</span>
                            <span class="shrink-0 font-normal text-[10px] text-gray-500">{{ (editingTask?.checklist_items || []).length }} عنصر</span>
                        </summary>
                        <div class="border-t border-slate-100/90 px-2 pb-2 pt-1">
                            <div class="max-h-28 space-y-1 overflow-y-auto rounded-lg bg-slate-50/90 p-1.5">
                                <label
                                    v-for="item in (editingTask?.checklist_items || [])"
                                    :key="`check-${item.id}`"
                                    class="flex items-center gap-1.5 text-[11px] text-gray-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-brand-600"
                                        :checked="item.is_done"
                                        @change="toggleChecklistItem(item)"
                                    />
                                    <span :class="item.is_done ? 'line-through text-gray-400' : ''">{{ item.title }}</span>
                                </label>
                                <p v-if="!(editingTask?.checklist_items || []).length" class="py-1 text-center text-[11px] text-gray-500">
                                    لا توجد عناصر بعد.
                                </p>
                            </div>
                        </div>
                    </details>

                    <div class="flex flex-col-reverse gap-1.5 border-t border-slate-200/50 pt-2 sm:flex-row sm:flex-wrap">
                        <PrimaryButton :disabled="editForm.processing" type="submit" class="w-full justify-center py-2 text-sm sm:w-auto">
                            حفظ
                        </PrimaryButton>
                        <button
                            v-if="canDeleteRecords && editingTask"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 sm:w-auto"
                            @click="deleteTask(editingTask.id)"
                        >
                            حذف المهمة
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:w-auto"
                            @click="closeEditModal"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="quickActionModalOpen" max-width="md" @close="closeQuickActionModal">
            <div class="glass-modal p-3 sm:p-4">
                <h2 class="text-base font-semibold text-gray-900">إضافة متابعة للمهمة</h2>
                <p class="mt-0.5 text-xs text-gray-600">{{ quickActionTask?.title || '' }}</p>

                <form class="mt-3 space-y-3" @submit.prevent="submitReassignment">
                    <div class="rounded-lg border border-white/30 bg-white/70 p-2.5 backdrop-blur">
                        <h3 class="text-xs font-semibold text-gray-800">إعادة تعيين لموظف + استحقاق</h3>
                        <div class="mt-1.5 space-y-1.5">
                            <div>
                                <InputLabel for="qa_assignee" value="الموظف" />
                                <select
                                    id="qa_assignee"
                                    v-model="quickActionForm.assigned_to_id"
                                    class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm"
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
                                    class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm"
                                />
                            </div>
                            <InputError class="mt-1" :message="quickActionForm.errors.assigned_to_id || quickActionForm.errors.due_at || quickActionForm.errors.note" />
                            <PrimaryButton :disabled="quickActionForm.processing" type="submit">
                                حفظ إعادة التعيين
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <form class="mt-3 space-y-2 rounded-lg border border-white/30 bg-white/70 p-2.5 backdrop-blur" @submit.prevent="submitChecklistItem">
                    <h3 class="text-xs font-semibold text-gray-800">إضافة قائمة تحقق متعددة</h3>
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
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 text-lg font-semibold text-gray-700 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-gray-50"
                                title="إضافة حقل جديد"
                                @click="addChecklistField"
                            >
                                +
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 text-lg font-semibold text-red-700 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-red-50"
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

<style scoped>
.task-ghost {
    opacity: 0.5;
    transform: scale(0.98);
}

.task-chosen {
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.18);
}

.task-drag {
    transform: rotate(1deg);
}

.task-column-dropzone {
    scroll-margin-inline: 16px;
}

.task-drop-area {
    border: 1px dashed rgba(148, 163, 184, 0.35);
    background: rgba(255, 255, 255, 0.18);
    transition: background-color 180ms ease, border-color 180ms ease;
}

.task-drop-area:hover {
    border-color: rgba(148, 163, 184, 0.55);
    background: rgba(255, 255, 255, 0.26);
}

.task-workspace-expanded {
    width: 100%;
}

.task-column-compact {
    padding: 0.65rem;
}

.task-column-compact :deep(.task-drop-area) {
    min-height: 11rem;
}

.task-column-compact :deep(.task-drop-area > div) {
    padding: 0.5rem;
    font-size: 0.78rem;
}

.glass-modal {
    border-radius: 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(12px);
    animation: modal-in 240ms ease-out;
}

.skeleton {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.4);
    background: rgba(241, 245, 249, 0.85);
}

.skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    animation: shimmer 1.15s ease-in-out infinite;
}

@keyframes shimmer {
    to {
        transform: translateX(100%);
    }
}

.light-modal {
    color: #111111;
}

.light-modal :deep(.text-gray-900),
.light-modal :deep(.text-slate-900),
.light-modal :deep(.text-gray-800),
.light-modal :deep(.text-slate-800),
.light-modal :deep(.text-gray-700),
.light-modal :deep(.text-slate-700),
.light-modal :deep(.text-gray-600),
.light-modal :deep(.text-slate-600),
.light-modal :deep(.text-gray-500),
.light-modal :deep(.text-slate-500) {
    color: #111111 !important;
}

.light-modal :deep(label),
.light-modal :deep(p),
.light-modal :deep(span) {
    color: #111111 !important;
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
