<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    teams: Array,
    selectedTeam: Object,
    messages: Array,
    unreadCounts: Object,
});
const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id || null);
const canDeleteRecords = computed(() => Boolean(page.props.auth?.can?.deleteRecords));

const form = useForm({
    team_id: props.selectedTeam?.id ?? null,
    body: '',
    attachment: null,
});
const messagesState = ref([...(props.messages || [])]);
const unreadCountsState = ref({ ...(props.unreadCounts || {}) });
const attachmentInputRef = ref(null);
const messagesContainerRef = ref(null);
const typingUsers = ref([]);
const searchTerm = ref('');
const selectedTeamSlug = ref(props.selectedTeam?.slug || '');
const editingMessageId = ref(null);
const editingBody = ref('');
let pollingTimer = null;
let typingTimer = null;
let typingUsersTimer = null;

function submitMessage() {
    if (!form.team_id || (!form.body.trim() && !form.attachment)) {
        return;
    }

    form.post(route('chat.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.body = '';
            form.attachment = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            pullMessages();
        },
    });
}

function onTeamChange(event) {
    const slug = event.target?.value || '';
    if (!slug) {
        return;
    }

    router.get(route('chat.index'), { team: slug }, { preserveState: false });
}

function formatDt(iso) {
    return new Date(iso).toLocaleString('ar-SA', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function formatFileSize(size) {
    const value = Number(size || 0);
    if (value < 1024) {
        return `${value} B`;
    }
    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function onAttachmentChange(event) {
    const file = event.target?.files?.[0] || null;
    form.attachment = file;
}

function clearAttachment() {
    form.attachment = null;
    if (attachmentInputRef.value) {
        attachmentInputRef.value.value = '';
    }
}

function pullMessages() {
    if (!props.selectedTeam?.id) {
        return;
    }

    const afterId = messagesState.value.length
        ? messagesState.value[messagesState.value.length - 1].id
        : undefined;

    window.axios
        .get(route('chat.messages.index'), {
            params: {
                team_id: props.selectedTeam.id,
                after_id: afterId,
            },
            headers: {
                Accept: 'application/json',
            },
        })
        .then((res) => {
            const rows = res?.data?.messages || [];
            unreadCountsState.value = { ...(res?.data?.unreadCounts || unreadCountsState.value) };
            if (!rows.length) {
                return;
            }
            const shouldAuto = shouldAutoScroll();
            messagesState.value.push(...rows);
            if (shouldAuto) {
                nextTick(() => scrollToBottom());
            }
        })
        .catch(() => {});
}

function startPolling() {
    stopPolling();
    pollingTimer = setInterval(pullMessages, 4000);
    typingUsersTimer = setInterval(pullTypingUsers, 2500);
}

function stopPolling() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
    if (typingUsersTimer) {
        clearInterval(typingUsersTimer);
        typingUsersTimer = null;
    }
}

function notifyTyping() {
    if (!props.selectedTeam?.id || !form.body.trim()) {
        return;
    }

    if (typingTimer) {
        clearTimeout(typingTimer);
    }

    typingTimer = setTimeout(() => {
        window.axios
            .post(route('chat.typing.update'), { team_id: props.selectedTeam.id }, { headers: { Accept: 'application/json' } })
            .catch(() => {});
    }, 300);
}

function pullTypingUsers() {
    if (!props.selectedTeam?.id) {
        return;
    }

    window.axios
        .get(route('chat.typing.index'), {
            params: { team_id: props.selectedTeam.id },
            headers: { Accept: 'application/json' },
        })
        .then((res) => {
            typingUsers.value = res?.data?.users || [];
        })
        .catch(() => {});
}

function canManageMessage(msg) {
    if (!msg?.user?.id || !currentUserId.value) {
        return false;
    }

    return Number(msg.user.id) === Number(currentUserId.value) || canDeleteRecords.value;
}

function startEdit(msg) {
    if (!canManageMessage(msg)) {
        return;
    }

    editingMessageId.value = msg.id;
    editingBody.value = msg.body;
}

function cancelEdit() {
    editingMessageId.value = null;
    editingBody.value = '';
}

function saveEdit(msg) {
    if (!editingBody.value.trim()) {
        return;
    }

    router.patch(
        route('chat.messages.update', msg.id),
        { body: editingBody.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                msg.body = editingBody.value.trim();
                msg.edited_at = new Date().toISOString();
                cancelEdit();
            },
        },
    );
}

function removeMessage(msg) {
    if (!canManageMessage(msg)) {
        return;
    }
    if (!confirm('حذف هذه الرسالة؟')) {
        return;
    }

    router.delete(route('chat.messages.destroy', msg.id), {
        preserveScroll: true,
        onSuccess: () => {
            messagesState.value = messagesState.value.filter((row) => row.id !== msg.id);
        },
    });
}

function teamUnreadCount(teamId) {
    return Number(unreadCountsState.value?.[teamId] || 0);
}

function teamLabelWithUnread(team) {
    const count = teamUnreadCount(team.id);
    return count > 0 ? `${team.name} (${count})` : team.name;
}

function isMine(msg) {
    return Number(msg.user?.id) === Number(currentUserId.value);
}

function userInitial(name) {
    return String(name || '?').trim().charAt(0) || '?';
}

function shouldAutoScroll() {
    const container = messagesContainerRef.value;
    if (!container) {
        return true;
    }

    const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
    return distanceFromBottom < 90;
}

function scrollToBottom() {
    const container = messagesContainerRef.value;
    if (!container) {
        return;
    }

    container.scrollTop = container.scrollHeight;
}

const filteredMessages = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) {
        return messagesState.value;
    }

    return messagesState.value.filter((msg) => {
        const inBody = String(msg.body || '').toLowerCase().includes(term);
        const inName = String(msg.user?.name || '').toLowerCase().includes(term);
        return inBody || inName;
    });
});

onMounted(() => {
    unreadCountsState.value = { ...(props.unreadCounts || {}) };
    selectedTeamSlug.value = props.selectedTeam?.slug || '';
    startPolling();
    pullTypingUsers();
    nextTick(() => scrollToBottom());
});

onBeforeUnmount(() => {
    stopPolling();
    if (typingTimer) {
        clearTimeout(typingTimer);
    }
});

watch(
    () => messagesState.value.length,
    () => {
        nextTick(() => scrollToBottom());
    },
);
</script>

<template>
    <Head title="دردشة الفريق" />

    <AuthenticatedLayout>
        <template #title>دردشة الفريق</template>

        <div class="mx-auto max-w-5xl">
            <div class="rounded-xl bg-white p-4 shadow ring-1 ring-gray-200 lg:h-[78vh] lg:overflow-hidden lg:p-0">
                <div class="border-b border-gray-100 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex w-full flex-col gap-1 sm:w-auto">
                            <label for="team_select" class="text-xs text-gray-500">اختر غرفة الدردشة</label>
                            <select
                                id="team_select"
                                :value="selectedTeamSlug"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm sm:w-80"
                                @change="onTeamChange"
                            >
                                <option v-for="team in teams" :key="`opt-${team.id}`" :value="team.slug">
                                    {{ teamLabelWithUnread(team) }}
                                </option>
                            </select>
                        </div>
                        <div class="text-sm text-gray-600">
                            الغرفة الحالية:
                            <span class="font-semibold text-gray-900">{{ selectedTeam?.name }}</span>
                        </div>
                        <TextInput
                            v-model="searchTerm"
                            type="text"
                            class="w-full sm:w-72"
                            placeholder="بحث في الرسائل..."
                        />
                    </div>
                </div>

                <div ref="messagesContainerRef" class="h-[48vh] space-y-3 overflow-y-auto bg-gray-50 p-4 lg:h-[52vh]">
                    <div
                        v-for="msg in filteredMessages"
                        :key="msg.id"
                        :class="[
                            'flex items-start gap-2',
                            isMine(msg) ? 'justify-end' : 'justify-start',
                        ]"
                    >
                        <div v-if="!isMine(msg)" class="mt-1 h-7 w-7 shrink-0 rounded-full bg-gray-200 text-center text-xs font-bold leading-7 text-gray-700">
                            {{ userInitial(msg.user?.name) }}
                        </div>

                        <div
                            :class="[
                                'w-full max-w-[90%] rounded-xl p-3 text-sm shadow-sm ring-1',
                                isMine(msg)
                                    ? 'bg-brand-50 ring-brand-100'
                                    : 'bg-white ring-gray-200',
                            ]"
                        >
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="font-semibold text-gray-900">{{ msg.user?.name || 'عضو' }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-gray-500">
                                        {{ formatDt(msg.created_at) }}<span v-if="msg.edited_at"> (تم التعديل)</span>
                                    </span>
                                    <button
                                        v-if="canManageMessage(msg)"
                                        type="button"
                                        class="text-[11px] text-gray-500 hover:underline"
                                        @click="startEdit(msg)"
                                    >
                                        تعديل
                                    </button>
                                    <button
                                        v-if="canManageMessage(msg)"
                                        type="button"
                                        class="text-[11px] text-red-600 hover:underline"
                                        @click="removeMessage(msg)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>

                            <div v-if="editingMessageId === msg.id" class="mt-2 space-y-2">
                                <textarea
                                    v-model="editingBody"
                                    rows="3"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                />
                                <div class="flex gap-2">
                                    <PrimaryButton type="button" @click="saveEdit(msg)">حفظ</PrimaryButton>
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                                        @click="cancelEdit"
                                    >
                                        إلغاء
                                    </button>
                                </div>
                            </div>

                            <p v-if="msg.body" class="whitespace-pre-wrap text-gray-700">{{ msg.body }}</p>
                            <div v-if="msg.attachment" class="mt-2 rounded-md bg-gray-50 p-2 ring-1 ring-gray-200">
                                <a
                                    :href="msg.attachment.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-xs font-medium text-brand-600 hover:underline"
                                >
                                    📎 {{ msg.attachment.name || 'مرفق' }}
                                </a>
                                <p class="mt-1 text-[11px] text-gray-500">
                                    {{ msg.attachment.mime || 'ملف' }} • {{ formatFileSize(msg.attachment.size) }}
                                </p>
                                <img
                                    v-if="msg.attachment.is_image"
                                    :src="msg.attachment.url"
                                    alt="مرفق"
                                    class="mt-2 max-h-52 rounded border border-gray-200"
                                />
                            </div>
                        </div>
                    </div>

                    <p v-if="!filteredMessages?.length" class="text-center text-sm text-gray-500">
                        لا توجد رسائل بعد. ابدأ الدردشة مع فريقك.
                    </p>
                </div>

                <div class="border-t border-gray-100 p-4">
                    <p v-if="typingUsers.length" class="mb-2 text-xs text-gray-500">
                        {{ typingUsers.map((u) => u.name).join('، ') }} يكتب الآن...
                    </p>

                    <form class="space-y-2" @submit.prevent="submitMessage">
                        <textarea
                            v-model="form.body"
                            rows="3"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                            placeholder="اكتب رسالة للفريق..."
                            @input="notifyTyping"
                        />
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="w-full sm:w-auto">
                                <input
                                    ref="attachmentInputRef"
                                    type="file"
                                    class="block w-full text-xs text-gray-600 file:me-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-medium"
                                    @change="onAttachmentChange"
                                />
                                <div v-if="form.attachment" class="mt-1 flex items-center gap-2 text-[11px] text-gray-600">
                                    <span>المرفق: {{ form.attachment.name }}</span>
                                    <button type="button" class="text-red-600 hover:underline" @click="clearAttachment">إزالة</button>
                                </div>
                            </div>
                            <PrimaryButton :disabled="form.processing">إرسال</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

