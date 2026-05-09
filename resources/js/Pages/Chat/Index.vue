<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { teamChatRealtimeEnabled } from '@/echo.js';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    chatTab: { type: String, default: 'all' },
    viewKind: { type: String, default: 'none' },
    teams: Array,
    selectedTeam: Object,
    privateRooms: { type: Array, default: () => [] },
    directPeers: { type: Array, default: () => [] },
    chatUsers: { type: Array, default: () => [] },
    selectedPrivateRoom: Object,
    selectedDirect: Object,
    messages: Array,
    unreadCounts: Object,
    chatUnread: { type: Object, default: () => ({}) },
});
const page = usePage();

function normalizeChatUnreadPayload(bundle = {}) {
    return {
        unreadCounts: { ...(bundle.unreadCounts || {}) },
        privateRoomUnreadCounts: { ...(bundle.privateRoomUnreadCounts || {}) },
        directUnreadCounts: { ...(bundle.directUnreadCounts || {}) },
        totalUnreadMessages: Number(bundle.totalUnreadMessages ?? 0),
    };
}

const currentUserId = computed(() => page.props.auth?.user?.id || null);
const canDeleteRecords = computed(() => Boolean(page.props.auth?.can?.deleteRecords));

const form = useForm({
    team_id: props.selectedTeam?.id ?? null,
    body: '',
    attachment: null,
});

const privateForm = useForm({
    body: '',
    attachment: null,
});

const directForm = useForm({
    body: '',
    attachment: null,
});

const createRoomForm = useForm({
    name: '',
    member_ids: [],
});

const createRoomModalOpen = ref(false);
const messagesState = ref([...(props.messages || [])]);
const chatUnreadState = ref(
    normalizeChatUnreadPayload({
        ...(props.chatUnread || {}),
        unreadCounts: props.chatUnread?.unreadCounts ?? props.unreadCounts ?? {},
    }),
);
const attachmentInputRef = ref(null);
const messagesContainerRef = ref(null);
const typingUsers = ref([]);
const searchTerm = ref('');
const teamSidebarFilter = ref('');
/** يحدّث ترتيب القائمة فور إرسال رسالة قبل استلام تحديث الخادم */
const activityOverrides = ref({});
const mobilePanel = ref('list');
const selectedTeamSlug = ref(props.selectedTeam?.slug || '');
const editingMessageId = ref(null);
const editingBody = ref('');
const pendingMessages = ref([]);
let pollingTimer = null;
let typingTimer = null;
let typingUsersTimer = null;
let echoSubscribedTeamId = null;
let summaryPollTimer = null;
let chatNotifPollTimer = null;

const chatNotificationsOpen = ref(false);
const chatNotificationsLoading = ref(false);
const chatNotificationsList = ref([]);
const chatNotificationsUnread = ref(Number(page.props.notifications?.chat_notifications_unread || 0));

function mergeChatUnreadFromResponse(data) {
    if (!data || typeof data !== 'object') {
        return;
    }
    if (
        data.unreadCounts == null &&
        data.privateRoomUnreadCounts == null &&
        data.directUnreadCounts == null &&
        data.totalUnreadMessages == null
    ) {
        return;
    }
    chatUnreadState.value = normalizeChatUnreadPayload({
        unreadCounts: data.unreadCounts ?? chatUnreadState.value.unreadCounts,
        privateRoomUnreadCounts:
            data.privateRoomUnreadCounts ?? chatUnreadState.value.privateRoomUnreadCounts,
        directUnreadCounts: data.directUnreadCounts ?? chatUnreadState.value.directUnreadCounts,
        totalUnreadMessages: data.totalUnreadMessages ?? chatUnreadState.value.totalUnreadMessages,
    });
}

async function pullUnreadSummary() {
    try {
        const res = await window.axios.get(route('chat.unread-summary'), {
            headers: { Accept: 'application/json' },
        });
        mergeChatUnreadFromResponse(res?.data);
    } catch {
        /* تجاهل في الخلفية */
    }
}

async function pullChatNotifications({ silent = true } = {}) {
    if (!silent) {
        chatNotificationsLoading.value = true;
    }
    try {
        const res = await window.axios.get(route('chat.notifications.index'), {
            headers: { Accept: 'application/json' },
        });
        chatNotificationsList.value = res?.data?.notifications || [];
        chatNotificationsUnread.value = Number(res?.data?.unread_count ?? 0);
    } finally {
        chatNotificationsLoading.value = false;
    }
}

async function toggleChatNotificationsPanel() {
    chatNotificationsOpen.value = !chatNotificationsOpen.value;
    if (chatNotificationsOpen.value) {
        await pullChatNotifications({ silent: false });
    }
}

async function openChatNotification(note) {
    if (!note?.id) {
        return;
    }
    await window.axios.post(route('notifications.read', note.id), {}, { headers: { Accept: 'application/json' } });
    await pullChatNotifications({ silent: true });
    if (note.link) {
        router.visit(note.link);
    }
    chatNotificationsOpen.value = false;
}

async function markAllChatNotificationsRead() {
    await window.axios.post(route('chat.notifications.read-all'), {}, { headers: { Accept: 'application/json' } });
    chatNotificationsUnread.value = 0;
    await pullChatNotifications({ silent: true });
}

const POLL_MS_REALTIME = 28000;
const POLL_MS_FALLBACK = 3800;
const TYPING_POLL_MS = 2600;

function parseIsoMs(iso) {
    if (!iso) {
        return 0;
    }
    const t = Date.parse(iso);
    return Number.isFinite(t) ? t : 0;
}

function inboxEffectiveMs(iso, activityKey) {
    return Math.max(parseIsoMs(iso), parseIsoMs(activityOverrides.value[activityKey]));
}

function bumpInboxActivity(activityKey) {
    activityOverrides.value = {
        ...activityOverrides.value,
        [activityKey]: new Date().toISOString(),
    };
}

function displayTeamName(team) {
    return String(team?.name || '').trim() === 'خطوات' ? 'خارج المخزون' : team?.name || 'غرفة';
}

/** خلفية نمطية للخيط (نفس قسم الخارج) */
const threadBgStyle = {
    backgroundImage:
        'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23c8c8c8\' fill-opacity=\'0.14\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
};

const selfAvatarUrl = computed(() => page.props.auth?.user?.avatar_url || '/images/mobile-logo.png');
const selfName = computed(() => page.props.auth?.user?.name || 'أنت');

const filteredPrivateRooms = computed(() => {
    const kw = teamSidebarFilter.value.trim().toLowerCase();
    let list = props.privateRooms || [];
    if (kw) {
        list = list.filter((r) => String(r.name || '').toLowerCase().includes(kw));
    }
    return [...list].sort((a, b) => {
        const tb = inboxEffectiveMs(b.last_activity_at, `private:${b.id}`);
        const ta = inboxEffectiveMs(a.last_activity_at, `private:${a.id}`);
        if (tb !== ta) {
            return tb - ta;
        }
        return String(a.name || '').localeCompare(String(b.name || ''), 'ar');
    });
});

const filteredDirectPeers = computed(() => {
    const kw = teamSidebarFilter.value.trim().toLowerCase();
    let list = props.directPeers || [];
    if (kw) {
        list = list.filter((p) => String(p.name || '').toLowerCase().includes(kw));
    }
    return [...list].sort((a, b) => {
        const keyB = b.conversation_id ? `direct:${b.conversation_id}` : `peer:${b.user_id}`;
        const keyA = a.conversation_id ? `direct:${a.conversation_id}` : `peer:${a.user_id}`;
        const tb = inboxEffectiveMs(b.last_activity_at, keyB);
        const ta = inboxEffectiveMs(a.last_activity_at, keyA);
        if (tb !== ta) {
            return tb - ta;
        }
        return String(a.name || '').localeCompare(String(b.name || ''), 'ar');
    });
});

const filteredAllInboxItems = computed(() => {
    const kw = teamSidebarFilter.value.trim().toLowerCase();
    const rows = [];

    for (const team of props.teams || []) {
        if (kw) {
            const n = displayTeamName(team).toLowerCase();
            const slug = String(team.slug || '').toLowerCase();
            const alias = String(team?.name || '').trim() === 'خطوات' ? 'خارج المخزون'.toLowerCase() : '';
            if (!n.includes(kw) && !slug.includes(kw) && !(alias && alias.includes(kw))) {
                continue;
            }
        }
        rows.push({
            kind: 'team',
            activityKey: `team:${team.id}`,
            last_activity_at: team.last_activity_at ?? null,
            team,
        });
    }

    for (const room of props.privateRooms || []) {
        if (kw && !String(room.name || '').toLowerCase().includes(kw)) {
            continue;
        }
        rows.push({
            kind: 'private_room',
            activityKey: `private:${room.id}`,
            last_activity_at: room.last_activity_at ?? null,
            room,
        });
    }

    for (const peer of props.directPeers || []) {
        if (kw && !String(peer.name || '').toLowerCase().includes(kw)) {
            continue;
        }
        rows.push({
            kind: 'direct',
            activityKey: peer.conversation_id ? `direct:${peer.conversation_id}` : `peer:${peer.user_id}`,
            last_activity_at: peer.last_activity_at ?? null,
            peer,
        });
    }

    rows.sort((a, b) => {
        const tb = inboxEffectiveMs(b.last_activity_at, b.activityKey);
        const ta = inboxEffectiveMs(a.last_activity_at, a.activityKey);
        if (tb !== ta) {
            return tb - ta;
        }
        const la =
            a.kind === 'team'
                ? displayTeamName(a.team)
                : a.kind === 'private_room'
                  ? a.room.name || ''
                  : a.peer.name || '';
        const lb =
            b.kind === 'team'
                ? displayTeamName(b.team)
                : b.kind === 'private_room'
                  ? b.room.name || ''
                  : b.peer.name || '';
        return la.localeCompare(lb, 'ar');
    });

    return rows;
});

const dmUserOptions = computed(() => (props.chatUsers || []).filter((u) => Number(u.id) !== Number(currentUserId.value)));

const chatHeaderTitle = computed(() => {
    if (props.viewKind === 'team' && props.selectedTeam) {
        return displayTeamName(props.selectedTeam);
    }
    if (props.viewKind === 'private_room' && props.selectedPrivateRoom) {
        return props.selectedPrivateRoom.name || 'غرفة';
    }
    if (props.viewKind === 'direct' && props.selectedDirect?.peer) {
        return props.selectedDirect.peer.name || 'محادثة';
    }
    return 'دردشة';
});

const hasActiveConversation = computed(
    () =>
        props.viewKind !== 'none' &&
        ((props.viewKind === 'team' && props.selectedTeam) ||
            (props.viewKind === 'private_room' && props.selectedPrivateRoom) ||
            (props.viewKind === 'direct' && props.selectedDirect)),
);

function setChatTab(tab) {
    router.get(route('chat.index'), { tab }, { preserveState: false });
}

function openCreateRoomModal() {
    createRoomForm.reset();
    createRoomForm.clearErrors();
    createRoomForm.member_ids = [];
    createRoomModalOpen.value = true;
}

function closeCreateRoomModal() {
    createRoomModalOpen.value = false;
}

function submitCreateRoom() {
    createRoomForm.post(route('chat.private-rooms.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateRoomModal(),
    });
}

function deletePrivateRoom() {
    if (!props.selectedPrivateRoom?.id) {
        return;
    }
    if (!confirm('حذف هذه الغرفة وجميع رسائلها نهائيًا؟')) {
        return;
    }
    router.delete(route('chat.private-rooms.destroy', props.selectedPrivateRoom.id));
}

function leavePrivateRoom() {
    if (!props.selectedPrivateRoom?.id) {
        return;
    }
    if (!confirm('مغادرة هذه الغرفة؟')) {
        return;
    }
    router.post(route('chat.private-rooms.leave', props.selectedPrivateRoom.id));
}

function toggleCreateRoomMember(userId) {
    const id = Number(userId);
    const arr = [...(createRoomForm.member_ids || [])].map(Number);
    const idx = arr.indexOf(id);
    if (idx === -1) {
        arr.push(id);
    } else {
        arr.splice(idx, 1);
    }
    createRoomForm.member_ids = arr;
}

function isCreateRoomMemberPicked(userId) {
    return (createRoomForm.member_ids || []).map(Number).includes(Number(userId));
}

function selectTeamSlug(slug) {
    if (!slug) {
        return;
    }
    const goChat =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;

    if (slug !== selectedTeamSlug.value) {
        router.get(route('chat.index'), { tab: props.chatTab, team: slug }, {
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

function selectPrivateRoomId(roomId) {
    const goChat =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
    router.get(route('chat.index'), { tab: props.chatTab, private_room: roomId }, {
        preserveState: false,
        onSuccess: () => {
            if (goChat) {
                mobilePanel.value = 'chat';
            }
        },
    });
}

function selectDirectId(conversationId) {
    const goChat =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
    router.get(route('chat.index'), { tab: props.chatTab, direct: conversationId }, {
        preserveState: false,
        onSuccess: () => {
            if (goChat) {
                mobilePanel.value = 'chat';
            }
        },
    });
}

function isDirectPeerSelected(peer) {
    if (props.viewKind !== 'direct' || !props.selectedDirect || !peer) {
        return false;
    }
    if (props.selectedDirect.peer?.id != null && Number(props.selectedDirect.peer.id) === Number(peer.user_id)) {
        return true;
    }
    if (
        peer.conversation_id &&
        props.selectedDirect.id != null &&
        Number(props.selectedDirect.id) === Number(peer.conversation_id)
    ) {
        return true;
    }
    return false;
}

function openDirectWithUser(peer) {
    const goChat =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
    if (peer.conversation_id) {
        selectDirectId(peer.conversation_id);
        return;
    }
    router.post(route('chat.direct.open'), { user_id: peer.user_id, tab: props.chatTab }, {
        preserveState: false,
        onSuccess: () => {
            if (goChat) {
                mobilePanel.value = 'chat';
            }
        },
    });
}

function allInboxItemSelected(item) {
    if (item.kind === 'team') {
        return props.viewKind === 'team' && selectedTeamSlug.value === item.team.slug;
    }
    if (item.kind === 'private_room') {
        return props.viewKind === 'private_room' && props.selectedPrivateRoom?.id === item.room.id;
    }
    return isDirectPeerSelected(item.peer);
}

function onAllInboxClick(item) {
    if (item.kind === 'team') {
        selectTeamSlug(item.team.slug);
        return;
    }
    if (item.kind === 'private_room') {
        selectPrivateRoomId(item.room.id);
        return;
    }
    openDirectWithUser(item.peer);
}

function showTeamListPanel() {
    mobilePanel.value = 'list';
}

function submitMessage() {
    if (props.viewKind === 'team') {
        submitTeamMessage();
        return;
    }
    if (props.viewKind === 'private_room') {
        submitPrivateMessage();
        return;
    }
    if (props.viewKind === 'direct') {
        submitDirectMessage();
    }
}

function submitTeamMessage() {
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
            if (props.selectedTeam?.id) {
                bumpInboxActivity(`team:${props.selectedTeam.id}`);
            }
            pullMessages();
        },
        onError: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
        },
    });
}

function submitPrivateMessage() {
    if (!props.selectedPrivateRoom?.id || (!privateForm.body.trim() && !privateForm.attachment)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: privateForm.body,
        created_at: new Date().toISOString(),
        user: { id: currentUserId.value, name: page.props.auth?.user?.name || 'أنا' },
        attachment: privateForm.attachment
            ? {
                  name: privateForm.attachment.name,
                  mime: privateForm.attachment.type || 'ملف',
                  size: privateForm.attachment.size || 0,
                  is_image: String(privateForm.attachment.type || '').startsWith('image/'),
              }
            : null,
        is_pending: true,
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    privateForm.post(route('chat.private-rooms.messages.store', props.selectedPrivateRoom.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
            privateForm.body = '';
            privateForm.attachment = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            if (props.selectedPrivateRoom?.id) {
                bumpInboxActivity(`private:${props.selectedPrivateRoom.id}`);
            }
            pullMessages();
        },
        onError: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
        },
    });
}

function submitDirectMessage() {
    if (!props.selectedDirect?.id || (!directForm.body.trim() && !directForm.attachment)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: directForm.body,
        created_at: new Date().toISOString(),
        user: { id: currentUserId.value, name: page.props.auth?.user?.name || 'أنا' },
        attachment: directForm.attachment
            ? {
                  name: directForm.attachment.name,
                  mime: directForm.attachment.type || 'ملف',
                  size: directForm.attachment.size || 0,
                  is_image: String(directForm.attachment.type || '').startsWith('image/'),
              }
            : null,
        is_pending: true,
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    directForm.post(route('chat.direct.messages.store', props.selectedDirect.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
            directForm.body = '';
            directForm.attachment = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            if (props.selectedDirect?.id) {
                bumpInboxActivity(`direct:${props.selectedDirect.id}`);
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
    if (props.viewKind === 'team') {
        form.attachment = file;
    } else if (props.viewKind === 'private_room') {
        privateForm.attachment = file;
    } else if (props.viewKind === 'direct') {
        directForm.attachment = file;
    }
}

function clearAttachment() {
    form.attachment = null;
    privateForm.attachment = null;
    directForm.attachment = null;
    if (attachmentInputRef.value) {
        attachmentInputRef.value.value = '';
    }
}

function activeComposerAttachment() {
    if (props.viewKind === 'team') {
        return form.attachment;
    }
    if (props.viewKind === 'private_room') {
        return privateForm.attachment;
    }
    if (props.viewKind === 'direct') {
        return directForm.attachment;
    }
    return null;
}

function activeComposerErrors() {
    if (props.viewKind === 'team') {
        return form.errors;
    }
    if (props.viewKind === 'private_room') {
        return privateForm.errors;
    }
    if (props.viewKind === 'direct') {
        return directForm.errors;
    }
    return {};
}

function composerProcessing() {
    if (props.viewKind === 'team') {
        return form.processing;
    }
    if (props.viewKind === 'private_room') {
        return privateForm.processing;
    }
    if (props.viewKind === 'direct') {
        return directForm.processing;
    }
    return false;
}

function notifyComposerTyping() {
    if (props.viewKind !== 'team') {
        return;
    }
    notifyTyping();
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
    const afterId = messagesState.value.length
        ? messagesState.value[messagesState.value.length - 1].id
        : undefined;

    if (props.viewKind === 'team' && props.selectedTeam?.id) {
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
                mergeChatUnreadFromResponse(res?.data);
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
        return;
    }

    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        window.axios
            .get(route('chat.private-rooms.messages.index', props.selectedPrivateRoom.id), {
                params: { after_id: afterId },
                headers: { Accept: 'application/json' },
            })
            .then((res) => {
                const rows = res?.data?.messages || [];
                mergeChatUnreadFromResponse(res?.data);
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
        return;
    }

    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        window.axios
            .get(route('chat.direct.messages.index', props.selectedDirect.id), {
                params: { after_id: afterId },
                headers: { Accept: 'application/json' },
            })
            .then((res) => {
                const rows = res?.data?.messages || [];
                mergeChatUnreadFromResponse(res?.data);
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
}

function startPolling() {
    stopPolling();
    const pollMs = teamChatRealtimeEnabled() ? POLL_MS_REALTIME : POLL_MS_FALLBACK;
    pollingTimer = setInterval(pullMessages, pollMs);
    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        typingUsersTimer = setInterval(pullTypingUsers, TYPING_POLL_MS);
    }
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
    if (props.viewKind !== 'team') {
        return;
    }
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
    if (props.viewKind !== 'team') {
        return;
    }
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
    if (props.viewKind !== 'team') {
        return;
    }
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
    return Number(chatUnreadState.value.unreadCounts?.[teamId] || 0);
}

function privateRoomUnreadCount(roomId) {
    return Number(chatUnreadState.value.privateRoomUnreadCounts?.[roomId] || 0);
}

function directUnreadCount(conversationId) {
    if (!conversationId) {
        return 0;
    }
    return Number(chatUnreadState.value.directUnreadCounts?.[conversationId] || 0);
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
    chatUnreadState.value = normalizeChatUnreadPayload({
        ...(props.chatUnread || {}),
        unreadCounts: props.chatUnread?.unreadCounts ?? props.unreadCounts ?? {},
    });
    chatNotificationsUnread.value = Number(page.props.notifications?.chat_notifications_unread || 0);
    selectedTeamSlug.value = props.selectedTeam?.slug || '';
    const mobile =
        typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches;
    if (mobile && hasActiveConversation.value) {
        mobilePanel.value = 'chat';
    }
    pullUnreadSummary();
    pullChatNotifications();
    summaryPollTimer = window.setInterval(pullUnreadSummary, 7000);
    chatNotifPollTimer = window.setInterval(() => pullChatNotifications({ silent: true }), 6000);
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
    () => [
        props.viewKind,
        props.selectedTeam?.id,
        props.selectedPrivateRoom?.id,
        props.selectedDirect?.id,
    ],
    () => {
        form.team_id = props.selectedTeam?.id ?? null;
        subscribeTeamEcho();
        stopPolling();
        typingUsers.value = [];

        const active =
            (props.viewKind === 'team' && props.selectedTeam?.id) ||
            (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) ||
            (props.viewKind === 'direct' && props.selectedDirect?.id);

        if (active) {
            startPolling();
            if (props.viewKind === 'team') {
                pullTypingUsers();
            }
            nextTick(() => scrollToBottom());
        } else {
            messagesState.value = [];
            pendingMessages.value = [];
            unsubscribeTeamEcho();
        }
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

watch(
    () => [props.chatUnread, props.unreadCounts],
    () => {
        chatUnreadState.value = normalizeChatUnreadPayload({
            ...(props.chatUnread || {}),
            unreadCounts: props.chatUnread?.unreadCounts ?? props.unreadCounts ?? {},
        });
    },
    { deep: true },
);

watch(
    () => page.props.notifications?.chat_notifications_unread,
    (n) => {
        if (n != null) {
            chatNotificationsUnread.value = Number(n);
        }
    },
);

onBeforeUnmount(() => {
    stopPolling();
    unsubscribeTeamEcho();
    if (typingTimer) {
        clearTimeout(typingTimer);
    }
    if (summaryPollTimer) {
        window.clearInterval(summaryPollTimer);
        summaryPollTimer = null;
    }
    if (chatNotifPollTimer) {
        window.clearInterval(chatNotifPollTimer);
        chatNotifPollTimer = null;
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
                    <div
                        class="sticky top-0 z-20 shrink-0 border-b border-slate-200/80 bg-white/95 px-3 py-3 backdrop-blur-md supports-[backdrop-filter]:bg-white/85 sm:px-4 sm:py-3.5"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold tracking-tight text-slate-900 sm:text-base">
                                المحادثات
                            </h2>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition-colors duration-200 hover:bg-slate-50"
                                    title="إشعارات الرسائل"
                                    aria-label="إشعارات الدردشة"
                                    @click="toggleChatNotificationsPanel"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"
                                        />
                                    </svg>
                                    <span
                                        v-if="chatNotificationsUnread > 0"
                                        class="absolute -end-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-emerald-600 px-0.5 text-[9px] font-bold leading-none text-white shadow-sm ring-2 ring-white"
                                    >
                                        {{ chatNotificationsUnread > 99 ? '99+' : chatNotificationsUnread }}
                                    </span>
                                </button>
                                <div class="flex h-9 w-9 items-center justify-center">
                                    <button
                                        v-show="chatTab === 'rooms'"
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-lg font-bold leading-none text-white shadow-md ring-1 ring-brand-700/30 transition-colors duration-200 ease-out hover:bg-brand-700"
                                        title="غرفة جديدة"
                                        aria-label="إنشاء غرفة خاصة"
                                        @click="openCreateRoomModal"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2.5 flex gap-1 rounded-xl bg-slate-100/90 p-1 ring-1 ring-slate-200/80">
                            <button
                                type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-all duration-200 ease-out sm:text-xs"
                                :class="chatTab === 'all' ? 'scale-[1.02] bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10' : 'text-slate-600 hover:bg-white/70'"
                                @click="setChatTab('all')"
                            >
                                الكل
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-all duration-200 ease-out sm:text-xs"
                                :class="chatTab === 'rooms' ? 'scale-[1.02] bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10' : 'text-slate-600 hover:bg-white/70'"
                                @click="setChatTab('rooms')"
                            >
                                غرف الدردشة
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-all duration-200 ease-out sm:text-xs"
                                :class="chatTab === 'direct' ? 'scale-[1.02] bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10' : 'text-slate-600 hover:bg-white/70'"
                                @click="setChatTab('direct')"
                            >
                                الدردشات
                            </button>
                        </div>
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
                                placeholder="بحث…"
                            >
                        </div>
                    </div>

                    <div
                        class="relative min-h-0 min-w-0 w-full flex-1 overflow-y-auto overflow-x-hidden overscroll-y-contain [-webkit-overflow-scrolling:touch]"
                    >
                        <Transition name="chat-tab" mode="out-in">
                            <div
                                :key="chatTab"
                                class="touch-pan-y space-y-1 px-2 py-2 sm:px-2.5 sm:py-2.5"
                            >
                                <template v-if="chatTab === 'all'">
                                    <button
                                        v-for="item in filteredAllInboxItems"
                                        :key="
                                            item.kind === 'team'
                                                ? `all-t-${item.team.id}`
                                                : item.kind === 'private_room'
                                                  ? `all-p-${item.room.id}`
                                                  : `all-d-${item.peer.user_id}`
                                        "
                                        type="button"
                                        class="group flex w-full gap-3 rounded-2xl px-2.5 py-3 text-start transition-all duration-200 sm:gap-3.5 sm:px-3 sm:py-3.5"
                                        :class="
                                            allInboxItemSelected(item)
                                                ? 'bg-brand-50 ring-2 ring-brand-500/25 shadow-md shadow-brand-900/[0.07]'
                                                : 'hover:bg-white/90 hover:shadow-sm'
                                        "
                                        @click="onAllInboxClick(item)"
                                    >
                                        <div class="relative shrink-0">
                                            <div
                                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                                :class="
                                                    item.kind === 'team'
                                                        ? 'from-slate-600 to-slate-800'
                                                        : item.kind === 'private_room'
                                                          ? 'from-violet-600 to-indigo-800'
                                                          : 'from-emerald-600 to-teal-800'
                                                "
                                            >
                                                {{
                                                    userInitial(
                                                        item.kind === 'team'
                                                            ? displayTeamName(item.team)
                                                            : item.kind === 'private_room'
                                                              ? item.room.name
                                                              : item.peer.name,
                                                    )
                                                }}
                                            </div>
                                            <span
                                                v-if="item.kind === 'team' && teamUnreadCount(item.team.id) > 0"
                                                class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                            >
                                                {{ teamUnreadCount(item.team.id) > 99 ? '99+' : teamUnreadCount(item.team.id) }}
                                            </span>
                                            <span
                                                v-if="item.kind === 'private_room' && privateRoomUnreadCount(item.room.id) > 0"
                                                class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                            >
                                                {{
                                                    privateRoomUnreadCount(item.room.id) > 99
                                                        ? '99+'
                                                        : privateRoomUnreadCount(item.room.id)
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    item.kind === 'direct' &&
                                                    item.peer.conversation_id &&
                                                    directUnreadCount(item.peer.conversation_id) > 0
                                                "
                                                class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                            >
                                                {{
                                                    directUnreadCount(item.peer.conversation_id) > 99
                                                        ? '99+'
                                                        : directUnreadCount(item.peer.conversation_id)
                                                }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[13px] font-semibold leading-tight text-slate-900 sm:text-sm">
                                                {{
                                                    item.kind === 'team'
                                                        ? displayTeamName(item.team)
                                                        : item.kind === 'private_room'
                                                          ? item.room.name
                                                          : item.peer.name
                                                }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] text-slate-500">
                                                <template v-if="item.kind === 'team'">دردشة الفريق العامة</template>
                                                <template v-else-if="item.kind === 'private_room'">
                                                    غرفة خاصة · يظهرها الأعضاء فقط
                                                </template>
                                                <template v-else>
                                                    {{
                                                        item.peer.conversation_id
                                                            ? 'رسائل خاصة بينكما'
                                                            : 'اضغط لبدء المحادثة'
                                                    }}
                                                </template>
                                            </p>
                                        </div>
                                    </button>
                                    <div
                                        v-if="!filteredAllInboxItems.length"
                                        class="flex flex-col items-center justify-center px-4 py-14 text-center"
                                    >
                                        <p class="text-sm font-medium text-slate-600">
                                            {{ teamSidebarFilter.trim() ? 'لا توجد نتائج مطابقة للبحث' : 'لا توجد عناصر للعرض' }}
                                        </p>
                                        <p class="mt-1 max-w-[260px] text-xs text-slate-500">
                                            {{ teamSidebarFilter.trim() ? 'جرّب تعديل عبارة البحث.' : 'جرّب التبويبات الأخرى أو إنشاء غرفة.' }}
                                        </p>
                                    </div>
                                </template>

                                <template v-else-if="chatTab === 'rooms'">
                            <button
                                v-for="room in filteredPrivateRooms"
                                :key="`pr-${room.id}`"
                                type="button"
                                class="group flex w-full gap-3 rounded-2xl px-2.5 py-3 text-start transition-all duration-200 sm:gap-3.5 sm:px-3 sm:py-3.5"
                                :class="
                                    selectedPrivateRoom?.id === room.id && viewKind === 'private_room'
                                        ? 'bg-brand-50 ring-2 ring-brand-500/25 shadow-md shadow-brand-900/[0.07]'
                                        : 'hover:bg-white/90 hover:shadow-sm'
                                "
                                @click="selectPrivateRoomId(room.id)"
                            >
                                <div class="relative shrink-0">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-800 text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                    >
                                        {{ userInitial(room.name) }}
                                    </div>
                                    <span
                                        v-if="privateRoomUnreadCount(room.id) > 0"
                                        class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                    >
                                        {{ privateRoomUnreadCount(room.id) > 99 ? '99+' : privateRoomUnreadCount(room.id) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold leading-tight text-slate-900 sm:text-sm">
                                        {{ room.name }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        غرفة خاصة · يظهرها الأعضاء فقط
                                    </p>
                                </div>
                            </button>
                            <div
                                v-if="!filteredPrivateRooms?.length"
                                class="flex flex-col items-center justify-center px-4 py-14 text-center"
                            >
                                <p class="text-sm font-medium text-slate-600">لا توجد غرف بعد</p>
                                <p class="mt-1 max-w-[260px] text-xs text-slate-500">
                                    اضغط + لإنشاء غرفة ودعوة زملائك.
                                </p>
                            </div>
                                </template>

                                <template v-else>
                                    <button
                                        v-for="peer in filteredDirectPeers"
                                        :key="`dm-${peer.user_id}`"
                                        type="button"
                                        class="group flex w-full gap-3 rounded-2xl px-2.5 py-3 text-start transition-all duration-200 sm:gap-3.5 sm:px-3 sm:py-3.5"
                                        :class="
                                            isDirectPeerSelected(peer)
                                                ? 'bg-brand-50 ring-2 ring-brand-500/25 shadow-md shadow-brand-900/[0.07]'
                                                : 'hover:bg-white/90 hover:shadow-sm'
                                        "
                                        @click="openDirectWithUser(peer)"
                                    >
                                        <div class="relative shrink-0">
                                            <div
                                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-800 text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                            >
                                                {{ userInitial(peer.name) }}
                                            </div>
                                            <span
                                                v-if="
                                                    peer.conversation_id && directUnreadCount(peer.conversation_id) > 0
                                                "
                                                class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                            >
                                                {{
                                                    directUnreadCount(peer.conversation_id) > 99
                                                        ? '99+'
                                                        : directUnreadCount(peer.conversation_id)
                                                }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[13px] font-semibold leading-tight text-slate-900 sm:text-sm">
                                                {{ peer.name || 'موظف' }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] text-slate-500">
                                                {{
                                                    peer.conversation_id
                                                        ? 'رسائل خاصة بينكما'
                                                        : 'اضغط لبدء المحادثة'
                                                }}
                                            </p>
                                        </div>
                                    </button>
                                    <div
                                        v-if="!filteredDirectPeers?.length"
                                        class="flex flex-col items-center justify-center px-4 py-14 text-center"
                                    >
                                        <p class="text-sm font-medium text-slate-600">
                                            {{
                                                (directPeers || []).length
                                                    ? 'لا توجد نتائج مطابقة للبحث'
                                                    : 'لا يوجد موظفون آخرون للعرض'
                                            }}
                                        </p>
                                        <p class="mt-1 max-w-[260px] text-xs text-slate-500">
                                            {{
                                                (directPeers || []).length
                                                    ? 'جرّب تعديل عبارة البحث.'
                                                    : 'عند إضافة مستخدمين سيظهرون هنا تلقائيًا.'
                                            }}
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </Transition>
                    </div>
                </aside>

                <!-- المحادثة -->
                <section
                    :class="[
                        'flex h-full min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden bg-[#e5ddd5]',
                        mobilePanel === 'list' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <template v-if="hasActiveConversation">
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
                                {{ userInitial(chatHeaderTitle) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-[15px] font-bold text-slate-900 sm:text-lg">
                                    {{ chatHeaderTitle }}
                                </h2>
                                <p v-if="viewKind === 'team'" class="truncate text-[11px] text-slate-500 sm:text-xs">
                                    رسائل الفريق الداخلية
                                </p>
                                <p v-else-if="viewKind === 'private_room'" class="truncate text-[11px] text-slate-500 sm:text-xs">
                                    غرفة خاصة · للمدعوين فقط
                                </p>
                                <p v-else class="truncate text-[11px] text-slate-500 sm:text-xs">
                                    محادثة خاصة بين الموظفين
                                </p>
                            </div>
                            <div v-if="viewKind === 'private_room' && selectedPrivateRoom" class="flex shrink-0 flex-col gap-1 sm:flex-row sm:items-center">
                                <button
                                    v-if="selectedPrivateRoom.is_creator"
                                    type="button"
                                    class="rounded-lg bg-rose-600 px-2 py-1 text-[10px] font-bold text-white shadow-sm hover:bg-rose-700 sm:px-2.5 sm:text-[11px]"
                                    @click="deletePrivateRoom"
                                >
                                    حذف الغرفة
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 sm:px-2.5 sm:text-[11px]"
                                    @click="leavePrivateRoom"
                                >
                                    مغادرة
                                </button>
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
                                            <div v-if="viewKind === 'team' && !msg.is_pending && editingMessageId === msg.id" class="mb-2 space-y-2">
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
                                                    v-if="viewKind === 'team' && !msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-brand-700 hover:underline"
                                                    @click="startEdit(msg)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    v-if="viewKind === 'team' && !msg.is_pending && canManageMessage(msg)"
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
                                            <div v-if="viewKind === 'team' && !msg.is_pending && editingMessageId === msg.id" class="mb-2 space-y-2">
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
                                                    v-if="viewKind === 'team' && !msg.is_pending && canManageMessage(msg)"
                                                    type="button"
                                                    class="font-semibold text-emerald-900 hover:underline"
                                                    @click="startEdit(msg)"
                                                >
                                                    تعديل
                                                </button>
                                                <button
                                                    v-if="viewKind === 'team' && !msg.is_pending && canManageMessage(msg)"
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
                            <p v-if="typingUsers.length && viewKind === 'team'" class="mb-2 text-center text-[11px] text-slate-600" dir="rtl">
                                {{ typingUsers.map((u) => u.name).join('، ') }} يكتب الآن…
                            </p>
                            <form class="flex flex-col gap-2 sm:gap-2.5" @submit.prevent="submitMessage">
                                <div class="relative flex items-end gap-2 rounded-[1.35rem] border border-slate-200/90 bg-white p-1.5 shadow-sm focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/15 sm:p-2">
                                    <textarea
                                        v-if="viewKind === 'team'"
                                        v-model="form.body"
                                        rows="1"
                                        dir="rtl"
                                        class="max-h-36 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-transparent px-3 py-2.5 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:min-h-[48px] sm:text-sm"
                                        placeholder="رسالة للفريق…"
                                        @input="notifyComposerTyping"
                                    />
                                    <textarea
                                        v-else-if="viewKind === 'private_room'"
                                        v-model="privateForm.body"
                                        rows="1"
                                        dir="rtl"
                                        class="max-h-36 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-transparent px-3 py-2.5 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:min-h-[48px] sm:text-sm"
                                        placeholder="رسالة للغرفة…"
                                    />
                                    <textarea
                                        v-else-if="viewKind === 'direct'"
                                        v-model="directForm.body"
                                        rows="1"
                                        dir="rtl"
                                        class="max-h-36 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-transparent px-3 py-2.5 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:min-h-[48px] sm:text-sm"
                                        placeholder="رسالة خاصة…"
                                    />
                                    <PrimaryButton
                                        type="submit"
                                        class="mb-0.5 h-10 shrink-0 rounded-xl px-4 text-xs font-bold shadow-md sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm"
                                        :disabled="composerProcessing()"
                                    >
                                        <span class="hidden sm:inline">إرسال</span>
                                        <svg class="h-5 w-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </PrimaryButton>
                                </div>
                                <InputError :message="activeComposerErrors().body" />
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <input
                                            ref="attachmentInputRef"
                                            type="file"
                                            class="block w-full text-[11px] text-slate-600 file:me-2 file:rounded-xl file:border-0 file:bg-white file:px-2 file:py-1 file:text-[11px] file:font-medium file:shadow-sm"
                                            @change="onAttachmentChange"
                                        >
                                        <div v-if="activeComposerAttachment()" class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                                            <span class="truncate">المرفق: {{ activeComposerAttachment()?.name }}</span>
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
                                اختر عنصرًا من التبويبات (الكل · غرف الدردشة · الدردشات) ثم افتح محادثة.
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

        <Modal max-width="lg" :show="createRoomModalOpen" @close="closeCreateRoomModal">
            <div class="space-y-4 p-6" dir="rtl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">غرفة خاصة جديدة</h3>
                        <p class="mt-1 text-sm text-slate-600">حدد الاسم والأعضاء. لا يراها إلا من تمت دعوتهم معك.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100" aria-label="إغلاق" @click="closeCreateRoomModal">
                        ✕
                    </button>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-800">اسم الغرفة</label>
                    <input
                        v-model="createRoomForm.name"
                        type="text"
                        maxlength="120"
                        class="block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-inner focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                        placeholder="مثال: متابعة عميل X"
                    >
                    <InputError class="mt-1" :message="createRoomForm.errors.name" />
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-800">أعضاء الغرفة</p>
                    <div class="max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <label
                            v-for="u in dmUserOptions"
                            :key="`mbr-${u.id}`"
                            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-white"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                :checked="isCreateRoomMemberPicked(u.id)"
                                @change="toggleCreateRoomMember(u.id)"
                            >
                            <span class="text-sm text-slate-800">{{ u.name }}</span>
                        </label>
                        <p v-if="!dmUserOptions.length" class="px-2 py-4 text-center text-sm text-slate-500">
                            لا يوجد موظفون آخرون للاختيار.
                        </p>
                    </div>
                    <InputError class="mt-1" :message="createRoomForm.errors.member_ids" />
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeCreateRoomModal"
                    >
                        إلغاء
                    </button>
                    <PrimaryButton type="button" :disabled="createRoomForm.processing" @click="submitCreateRoom">
                        إنشاء
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Teleport to="body">
            <div
                v-if="chatNotificationsOpen"
                class="fixed inset-0 z-[92] flex items-start justify-center bg-black/40 px-2 pb-[env(safe-area-inset-bottom)] pt-14 backdrop-blur-[2px] md:items-stretch md:justify-end md:px-0 md:pb-0 md:pt-16"
                role="dialog"
                aria-modal="true"
                aria-label="إشعارات الدردشة"
                @click.self="chatNotificationsOpen = false"
            >
                <div
                    class="flex max-h-[min(85vh,640px)] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl md:mt-0 md:h-full md:max-h-none md:max-w-sm md:rounded-none md:border-s md:border-y-0 md:border-e-0"
                    dir="rtl"
                    @click.stop
                >
                    <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-4 py-3">
                        <div class="text-sm font-bold text-slate-900">إشعارات الرسائل</div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="text-[11px] font-semibold text-emerald-700 hover:underline"
                                @click="markAllChatNotificationsRead"
                            >
                                تعليم الكل كمقروء
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1 text-slate-500 hover:bg-slate-100"
                                aria-label="إغلاق"
                                @click="chatNotificationsOpen = false"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <div v-if="chatNotificationsLoading" class="shrink-0 p-4 text-xs text-slate-500">جاري التحميل…</div>
                    <template v-else>
                        <div v-if="!chatNotificationsList.length" class="shrink-0 p-4 text-xs text-slate-500">
                            لا توجد إشعارات رسائل حالياً.
                        </div>
                        <div v-else class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                            <button
                                v-for="note in chatNotificationsList"
                                :key="note.id"
                                type="button"
                                class="mb-1 w-full rounded-xl border px-3 py-2.5 text-start transition hover:bg-slate-50"
                                :class="
                                    note.read_at
                                        ? 'border-slate-200 bg-white text-slate-700'
                                        : 'border-emerald-200 bg-emerald-50/80 text-slate-900'
                                "
                                @click="openChatNotification(note)"
                            >
                                <div class="text-[12px] font-semibold">{{ note.title }}</div>
                                <div class="mt-0.5 text-[11px] leading-snug text-slate-600">{{ note.preview || note.body }}</div>
                                <div class="mt-1 text-[10px] text-slate-400">{{ note.created_at ? formatDt(note.created_at) : '' }}</div>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.chat-tab-enter-active,
.chat-tab-leave-active {
    transition:
        opacity 0.22s ease,
        transform 0.26s cubic-bezier(0.22, 1, 0.36, 1);
}
.chat-tab-enter-from,
.chat-tab-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>

