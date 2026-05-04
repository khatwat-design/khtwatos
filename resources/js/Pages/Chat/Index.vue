<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { teamChatRealtimeEnabled } from '@/echo.js';
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
const teamSidebarFilter = ref('');
const mobilePanel = ref('list');
const selectedTeamSlug = ref(props.selectedTeam?.slug || '');
const editingMessageId = ref(null);
const editingBody = ref('');
const pendingMessages = ref([]);
let pollingTimer = null;
let typingTimer = null;
let typingUsersTimer = null;
let echoSubscribedTeamId = null;

const POLL_MS_REALTIME = 28000;
const POLL_MS_FALLBACK = 3800;
const TYPING_POLL_MS = 2600;

/** خلفية نمطية للخيط (نفس قسم الخارج) */
const threadBgStyle = {
    backgroundImage:
        'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23c8c8c8\' fill-opacity=\'0.14\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
};

const selfAvatarUrl = computed(() => page.props.auth?.user?.avatar_url || '/images/mobile-logo.png');
const selfName = computed(() => page.props.auth?.user?.name || 'أنت');

const filteredTeams = computed(() => {
    const kw = teamSidebarFilter.value.trim().toLowerCase();
    const list = props.teams || [];
    if (!kw) {
        return list;
    }
    return list.filter((t) => {
        const n = String(t.name || '').toLowerCase();
        const slug = String(t.slug || '').toLowerCase();
        const alias = String(t.name || '').trim() === 'خطوات' ? 'خارج المخزون' : '';
        return n.includes(kw) || slug.includes(kw) || alias.toLowerCase().includes(kw);
    });
});

function displayTeamName(team) {
    return String(team?.name || '').trim() === 'خطوات' ? 'خارج المخزون' : team?.name || 'غرفة';
}

function selectTeamSlug(slug) {
    if (!slug) {
        return;
    }
    const goChat =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;

    if (slug !== selectedTeamSlug.value) {
        router.get(route('chat.index'), { team: slug }, {
            preserveState: false,
            onSuccess: () => {
                selectedTeamSlug.value = slug;
                if (goChat) {
                    mobilePanel.value = 'chat';
                }
            },
        });
        return;
    }

    if (goChat) {
        mobilePanel.value = 'chat';
    }
}

function showTeamListPanel() {
    mobilePanel.value = 'list';
}

function submitMessage() {
    if (!form.team_id || (!form.body.trim() && !form.attachment)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: form.body,
        created_at: new Date().toISOString(),
        user: { id: currentUserId.value, name: page.props.auth?.user?.name || 'أنا' },
        attachment: form.attachment
            ? {
                  name: form.attachment.name,
                  mime: form.attachment.type || 'ملف',
                  size: form.attachment.size || 0,
                  is_image: String(form.attachment.type || '').startsWith('image/'),
              }
            : null,
        is_pending: true,
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    form.post(route('chat.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
            form.body = '';
            form.attachment = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            pullMessages();
        },
        onError: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
        },
    });
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

function clearOptimisticMatchingServerMessage(msg) {
    if (!msg?.user?.id) {
        return;
    }
    pendingMessages.value = pendingMessages.value.filter((p) => {
        if (!p.is_pending) {
            return true;
        }
        if (Number(p.user?.id) !== Number(msg.user.id)) {
            return true;
        }
        if (String(p.body || '').trim() !== String(msg.body || '').trim()) {
            return true;
        }
        const pName = p.attachment?.name || null;
        const mName = msg.attachment?.name || null;
        if (pName || mName) {
            return pName !== mName;
        }
        return false;
    });
}

function unsubscribeTeamEcho() {
    if (typeof window === 'undefined' || !window.Echo || !echoSubscribedTeamId) {
        echoSubscribedTeamId = null;
        return;
    }
    window.Echo.leave(`private-team-chat.${echoSubscribedTeamId}`);
    echoSubscribedTeamId = null;
}

function subscribeTeamEcho() {
    unsubscribeTeamEcho();
    if (!teamChatRealtimeEnabled() || !props.selectedTeam?.id) {
        return;
    }
    const teamId = props.selectedTeam.id;
    echoSubscribedTeamId = teamId;

    window.Echo.private(`team-chat.${teamId}`)
        .listen('.message.created', (payload) => {
            const msg = payload?.message;
            if (!msg?.id) {
                return;
            }
            if (messagesState.value.some((m) => Number(m.id) === Number(msg.id))) {
                return;
            }
            clearOptimisticMatchingServerMessage(msg);
            const snap = shouldAutoScroll();
            messagesState.value.push(msg);
            if (snap) {
                nextTick(() => scrollToBottom());
            }
        })
        .listen('.message.updated', (payload) => {
            const msg = payload?.message;
            if (!msg?.id) {
                return;
            }
            const idx = messagesState.value.findIndex((m) => Number(m.id) === Number(msg.id));
            if (idx === -1) {
                return;
            }
            messagesState.value[idx] = { ...messagesState.value[idx], ...msg };
        })
        .listen('.message.deleted', (payload) => {
            const mid = payload?.message_id;
            if (mid == null) {
                return;
            }
            messagesState.value = messagesState.value.filter((m) => Number(m.id) !== Number(mid));
        });
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
    const pollMs = teamChatRealtimeEnabled() ? POLL_MS_REALTIME : POLL_MS_FALLBACK;
    pollingTimer = setInterval(pullMessages, pollMs);
    typingUsersTimer = setInterval(pullTypingUsers, TYPING_POLL_MS);
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

const displayedMessages = computed(() => {
    const term = searchTerm.value.trim().toLowerCase();
    if (!term) {
        return [...filteredMessages.value, ...pendingMessages.value];
    }

    const pendingMatches = pendingMessages.value.filter((msg) => {
        const inBody = String(msg.body || '').toLowerCase().includes(term);
        const inName = String(msg.user?.name || '').toLowerCase().includes(term);
        return inBody || inName;
    });

    return [...filteredMessages.value, ...pendingMatches];
});

onMounted(() => {
    unreadCountsState.value = { ...(props.unreadCounts || {}) };
    selectedTeamSlug.value = props.selectedTeam?.slug || '';
    startPolling();
    subscribeTeamEcho();
    pullTypingUsers();
    nextTick(() => scrollToBottom());
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches && props.selectedTeam?.slug) {
        mobilePanel.value = 'chat';
    }
});

watch(
    () => props.selectedTeam?.slug,
    (s) => {
        if (s) {
            selectedTeamSlug.value = s;
        }
    },
);

watch(
    () => props.selectedTeam?.id,
    (id) => {
        if (id) {
            form.team_id = id;
        }
        subscribeTeamEcho();
        stopPolling();
        startPolling();
    },
    { immediate: true },
);

watch(
    () => props.messages,
    (list) => {
        messagesState.value = [...(list || [])];
    },
    { deep: true },
);

onBeforeUnmount(() => {
    stopPolling();
    unsubscribeTeamEcho();
    if (typingTimer) {
        clearTimeout(typingTimer);
    }
});

watch(
    () => [messagesState.value.length, pendingMessages.value.length],
    () => {
        nextTick(() => scrollToBottom());
    },
);
</script>

<template>
    <Head title="دردشة الفريق" />

    <AuthenticatedLayout>
        <template #title>دردشة الفريق</template>

        <div
            class="team-chat-inbox flex min-h-0 w-full min-w-0 max-w-full flex-1 flex-col self-stretch overflow-x-hidden overflow-y-hidden max-md:h-[calc(100dvh-10.5rem)] max-md:max-h-[calc(100dvh-10.5rem)] md:max-h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-9.5rem)] lg:mx-auto lg:h-[min(42rem,calc(100svh-13.5rem))] lg:max-h-[min(42rem,calc(100svh-13.5rem))] lg:w-full lg:max-w-7xl xl:h-[min(46rem,calc(100svh-12.5rem))] xl:max-h-[min(46rem,calc(100svh-12.5rem))]"
        >
            <div
                class="grid h-full min-h-0 min-w-0 w-full max-w-full flex-1 grid-cols-1 grid-rows-1 overflow-hidden rounded-2xl border border-app-surface-border/90 bg-app-surface/90 shadow-xl shadow-slate-900/[0.06] ring-1 ring-black/[0.03] backdrop-blur-md auto-rows-[minmax(0,1fr)] sm:rounded-3xl lg:grid-cols-[minmax(260px,1fr)_minmax(0,2.2fr)] xl:grid-cols-[320px_minmax(0,1fr)]"
            >
                <!-- قائمة الغرف (مثل صندوق الوارد في الخارج) -->
                <aside
                    :class="[
                        'flex h-full min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden border-app-surface-border/80 bg-gradient-to-b from-white/98 to-slate-50/90 lg:border-e',
                        mobilePanel === 'chat' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <div class="shrink-0 border-b border-slate-200/80 bg-white/90 px-3 py-3 sm:px-4 sm:py-3.5">
                        <h2 class="text-sm font-bold tracking-tight text-slate-900 sm:text-base">
                            غرف الفريق
                        </h2>
                        <div class="relative mt-2.5">
                            <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="teamSidebarFilter"
                                type="search"
                                autocomplete="off"
                                class="block w-full rounded-xl border-slate-200/90 bg-slate-50/80 py-2.5 ps-9 pe-3 text-sm text-slate-900 shadow-inner placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                                placeholder="بحث باسم الغرفة…"
                            >
                        </div>
                    </div>

                    <div
                        class="min-h-0 min-w-0 w-full flex-1 touch-pan-y space-y-1 overflow-y-auto overflow-x-hidden overscroll-y-contain px-2 py-2 [-webkit-overflow-scrolling:touch] sm:px-2.5 sm:py-2.5"
                    >
                        <button
                            v-for="team in filteredTeams"
                            :key="`team-${team.id}`"
                            type="button"
                            class="group flex w-full gap-3 rounded-2xl px-2.5 py-3 text-start transition-all duration-200 sm:gap-3.5 sm:px-3 sm:py-3.5"
                            :class="
                                selectedTeamSlug === team.slug
                                    ? 'bg-brand-50 ring-2 ring-brand-500/25 shadow-md shadow-brand-900/[0.07]'
                                    : 'hover:bg-white/90 hover:shadow-sm'
                            "
                            @click="selectTeamSlug(team.slug)"
                        >
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                >
                                    {{ userInitial(displayTeamName(team)) }}
                                </div>
                                <span
                                    v-if="teamUnreadCount(team.id) > 0"
                                    class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                >
                                    {{ teamUnreadCount(team.id) > 99 ? '99+' : teamUnreadCount(team.id) }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[13px] font-semibold leading-tight text-slate-900 sm:text-sm">
                                    {{ displayTeamName(team) }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    دردشة داخلية للفريق
                                </p>
                            </div>
                        </button>
                        <div
                            v-if="!filteredTeams?.length"
                            class="flex flex-col items-center justify-center px-4 py-14 text-center"
                        >
                            <p class="text-sm font-medium text-slate-600">لا توجد غرف مطابقة</p>
                            <p class="mt-1 max-w-[260px] text-xs text-slate-500">
                                جرّب تعديل عبارة البحث.
                            </p>
                        </div>
                    </div>
                </aside>

                <!-- المحادثة -->
                <section
                    :class="[
                        'flex h-full min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden bg-[#e5ddd5]',
                        mobilePanel === 'list' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <template v-if="selectedTeam">
                        <header class="flex shrink-0 items-center gap-2 border-b border-slate-200/60 bg-[#f0f2f5] px-2 py-2.5 shadow-sm sm:gap-3 sm:px-4 sm:py-3">
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 lg:hidden"
                                aria-label="عودة لقائمة الغرف"
                                @click="showTeamListPanel"
                            >
                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-12 sm:w-12"
                            >
                                {{ userInitial(displayTeamName(selectedTeam)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-[15px] font-bold text-slate-900 sm:text-lg">
                                    {{ displayTeamName(selectedTeam) }}
                                </h2>
                                <p class="truncate text-[11px] text-slate-500 sm:text-xs">
                                    رسائل الفريق الداخلية
                                </p>
                            </div>
                        </header>

                        <div class="shrink-0 border-b border-slate-200/50 bg-[#f0f2f5]/95 px-3 py-2.5 sm:px-4">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="searchTerm"
                                    type="search"
                                    autocomplete="off"
                                    class="block w-full rounded-xl border-slate-200/90 bg-white py-2.5 ps-9 pe-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                    placeholder="بحث في الرسائل…"
                                >
                            </div>
                        </div>

                        <div
                            ref="messagesContainerRef"
                            dir="ltr"
                            class="outside-thread flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overscroll-y-contain px-2 py-3 touch-pan-y [-webkit-overflow-scrolling:touch] sm:px-4 sm:py-4"
                            :style="threadBgStyle"
                        >
                            <div
                                v-for="msg in displayedMessages"
                                :key="msg.id"
                                class="flex w-full max-w-full items-end gap-2"
                                :class="isMine(msg) ? 'justify-end' : 'justify-start'"
                            >
                                <template v-if="!isMine(msg)">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-[11px] font-bold text-white shadow-md ring-2 ring-white sm:h-10 sm:w-10 sm:text-xs"
                                    >
                                        {{ userInitial(msg.user?.name) }}
                                    </div>
                                    <div class="max-w-[min(100%,18.5rem)] sm:max-w-[min(100%,24rem)]">
                                        <div class="mb-1 flex items-center gap-1.5" dir="rtl">
                                            <span class="text-[10px] font-semibold text-slate-600">{{ msg.user?.name || 'عضو' }}</span>
                                        </div>
                                        <div
                                            class="rounded-2xl rounded-tl-sm border border-black/[0.06] bg-white px-3.5 py-2.5 text-[13px] leading-relaxed text-slate-900 shadow-sm sm:px-4 sm:py-3 sm:text-sm"
                                            dir="rtl"
                                        >
                                            <div v-if="!msg.is_pending && editingMessageId === msg.id" class="mb-2 space-y-2">
                                                <textarea
                                                    v-model="editingBody"
                                                    rows="3"
                                                    class="block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                                                />
                                                <div class="flex flex-wrap gap-2">
                                                    <PrimaryButton type="button" class="text-xs" @click="saveEdit(msg)">حفظ</PrimaryButton>
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                                        @click="cancelEdit"
                                                    >
                                                        إلغاء
                                                    </button>
                                                </div>
                                            </div>
                                            <p v-if="msg.body" class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                                            <p v-if="msg.is_pending" class="mt-1 inline-flex items-center gap-1 text-[10px] text-sky-700">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-sky-600" />
                                                جار الإرسال...
                                            </p>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2 text-[10px] text-slate-500">
                                                <span>{{ formatDt(msg.created_at) }}<span v-if="msg.edited_at"> · تم التعديل</span></span>
                                                <button
                                                    v-if="!msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-brand-700 hover:underline"
                                                    @click="startEdit(msg)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    v-if="!msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-rose-600 hover:underline"
                                                    @click="removeMessage(msg)"
                                                >
                                                    حذف
                                                </button>
                                            </div>
                                            <div v-if="msg.attachment" class="mt-2 rounded-xl bg-slate-50 p-2 ring-1 ring-slate-200/70">
                                                <a
                                                    :href="msg.attachment.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-xs font-medium text-brand-600 hover:underline"
                                                >
                                                    📎 {{ msg.attachment.name || 'مرفق' }}
                                                </a>
                                                <p class="mt-1 text-[10px] text-slate-500">
                                                    {{ msg.attachment.mime || 'ملف' }} · {{ formatFileSize(msg.attachment.size) }}
                                                </p>
                                                <img
                                                    v-if="msg.attachment.is_image && msg.attachment.url"
                                                    :src="msg.attachment.url"
                                                    alt="مرفق"
                                                    class="mt-2 max-h-52 rounded-xl border border-slate-200/70"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="max-w-[min(100%,18.5rem)] sm:max-w-[min(100%,24rem)]">
                                        <div class="mb-1 flex items-center justify-end gap-1.5" dir="rtl">
                                            <span class="text-[10px] font-semibold text-slate-600">{{ selfName }}</span>
                                        </div>
                                        <div
                                            class="rounded-2xl rounded-tr-sm bg-gradient-to-br from-[#d9fdd3] to-[#c8f5c0] px-3.5 py-2.5 text-[13px] leading-relaxed text-slate-900 shadow-sm ring-1 ring-black/[0.04] sm:px-4 sm:py-3 sm:text-sm"
                                            dir="rtl"
                                        >
                                            <div v-if="!msg.is_pending && editingMessageId === msg.id" class="mb-2 space-y-2">
                                                <textarea
                                                    v-model="editingBody"
                                                    rows="3"
                                                    class="block w-full rounded-xl border-emerald-900/20 text-sm shadow-sm"
                                                />
                                                <div class="flex flex-wrap gap-2">
                                                    <PrimaryButton type="button" class="text-xs" @click="saveEdit(msg)">حفظ</PrimaryButton>
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center rounded-xl border border-emerald-900/15 bg-white/80 px-3 py-1.5 text-xs text-slate-800 hover:bg-white"
                                                        @click="cancelEdit"
                                                    >
                                                        إلغاء
                                                    </button>
                                                </div>
                                            </div>
                                            <p v-if="msg.body" class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                                            <p v-if="msg.is_pending" class="mt-1 inline-flex items-center gap-1 text-[10px] text-emerald-900/80">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-700" />
                                                جار الإرسال...
                                            </p>
                                            <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-emerald-900/10 pt-2 text-[10px] text-emerald-900/70">
                                                <span>{{ formatDt(msg.created_at) }}<span v-if="msg.edited_at"> · تم التعديل</span></span>
                                                <button
                                                    v-if="!msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-emerald-900 hover:underline"
                                                    @click="startEdit(msg)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    v-if="!msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-rose-700 hover:underline"
                                                    @click="removeMessage(msg)"
                                                >
                                                    حذف
                                                </button>
                                            </div>
                                            <div v-if="msg.attachment" class="mt-2 rounded-xl bg-white/60 p-2 ring-1 ring-emerald-900/10">
                                                <a
                                                    :href="msg.attachment.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-xs font-medium text-emerald-900 hover:underline"
                                                >
                                                    📎 {{ msg.attachment.name || 'مرفق' }}
                                                </a>
                                                <p class="mt-1 text-[10px] text-emerald-900/70">
                                                    {{ msg.attachment.mime || 'ملف' }} · {{ formatFileSize(msg.attachment.size) }}
                                                </p>
                                                <img
                                                    v-if="msg.attachment.is_image && msg.attachment.url"
                                                    :src="msg.attachment.url"
                                                    alt="مرفق"
                                                    class="mt-2 max-h-52 rounded-xl border border-emerald-900/10"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <img
                                        :src="selfAvatarUrl"
                                        :alt="selfName"
                                        class="h-9 w-9 shrink-0 rounded-full object-cover shadow-md ring-2 ring-white sm:h-10 sm:w-10"
                                    >
                                </template>
                            </div>

                            <p
                                v-if="!displayedMessages?.length"
                                dir="rtl"
                                class="flex flex-1 flex-col items-center justify-center py-16 text-center text-sm text-slate-600"
                            >
                                لا توجد رسائل بعد. ابدأ من الأسفل.
                            </p>
                        </div>

                        <div
                            class="shrink-0 border-t border-slate-200/70 bg-[#f0f2f5] px-2 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] shadow-[0_-4px_24px_-2px_rgba(15,23,42,0.06)] sm:px-4 sm:py-3"
                        >
                            <p v-if="typingUsers.length" class="mb-2 text-center text-[11px] text-slate-600" dir="rtl">
                                {{ typingUsers.map((u) => u.name).join('، ') }} يكتب الآن…
                            </p>
                            <form class="flex flex-col gap-2 sm:gap-2.5" @submit.prevent="submitMessage">
                                <div class="relative flex items-end gap-2 rounded-[1.35rem] border border-slate-200/90 bg-white p-1.5 shadow-sm focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/15 sm:p-2">
                                    <textarea
                                        v-model="form.body"
                                        rows="1"
                                        dir="rtl"
                                        class="max-h-36 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-transparent px-3 py-2.5 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:min-h-[48px] sm:text-sm"
                                        placeholder="رسالة للفريق…"
                                        @input="notifyTyping"
                                    />
                                    <PrimaryButton
                                        type="submit"
                                        class="mb-0.5 h-10 shrink-0 rounded-xl px-4 text-xs font-bold shadow-md sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm"
                                        :disabled="form.processing"
                                    >
                                        <span class="hidden sm:inline">إرسال</span>
                                        <svg class="h-5 w-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </PrimaryButton>
                                </div>
                                <InputError :message="form.errors.body" />
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <input
                                            ref="attachmentInputRef"
                                            type="file"
                                            class="block w-full text-[11px] text-slate-600 file:me-2 file:rounded-xl file:border-0 file:bg-white file:px-2 file:py-1 file:text-[11px] file:font-medium file:shadow-sm"
                                            @change="onAttachmentChange"
                                        >
                                        <div v-if="form.attachment" class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                                            <span class="truncate">المرفق: {{ form.attachment.name }}</span>
                                            <button type="button" class="shrink-0 text-rose-600 hover:underline" @click="clearAttachment">
                                                إزالة
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex min-h-[50dvh] flex-1 flex-col items-center justify-center bg-[#e5ddd5] px-6 py-12 text-center lg:min-h-0"
                    >
                        <div class="max-w-md rounded-3xl bg-white p-8 shadow-xl ring-1 ring-black/5">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <p class="mt-5 text-lg font-bold text-slate-900" dir="rtl">دردشة الفريق</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600" dir="rtl">
                                اختر غرفة من القائمة لعرض الرسائل والمشاركة مع الفريق.
                            </p>
                            <button
                                type="button"
                                class="mt-6 w-full rounded-xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 lg:hidden"
                                @click="showTeamListPanel"
                            >
                                عرض الغرف
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


