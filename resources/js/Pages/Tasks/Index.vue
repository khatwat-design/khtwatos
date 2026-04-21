<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

const teamLabelMap = {
    writing: 'الكتابة',
    'media-buyer': 'الميديا باير',
    account: 'الأكاونت',
    sales: 'المبيعات',
    hr: 'الموارد البشرية',
    accounting: 'المحاسبة',
    'media buyer': 'الميديا باير',
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
const taskMessageSending = ref(false);
const taskMessageError = ref('');
const taskMessageBody = ref('');

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
    taskMessageBody.value = '';
    taskMessageError.value = '';
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

function closeEditModal() {
    editModalOpen.value = false;
    editingTask.value = null;
    taskMessages.value = [];
    taskMessageBody.value = '';
    taskMessageError.value = '';
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
                                    class="relative cursor-grab rounded-md border border-gray-100 bg-gray-50 p-2 pe-9 text-sm active:cursor-grabbing"
                                >
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
                                    v-for="c in clients || []"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
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
                                v-for="c in clients || []"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
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

                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:flex-wrap">
                        <PrimaryButton :disabled="editForm.processing" type="submit" class="w-full justify-center sm:w-auto">
                            حفظ
                        </PrimaryButton>
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
    </AuthenticatedLayout>
</template>
