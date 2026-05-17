<script setup>
import ChatCreateTaskSheet from '@/Components/Chat/ChatCreateTaskSheet.vue';
import ChatForwardModal from '@/Components/Chat/ChatForwardModal.vue';
import ChatMediaLightbox from '@/Components/Chat/ChatMediaLightbox.vue';
import ChatMessageActionsSheet from '@/Components/Chat/ChatMessageActionsSheet.vue';
import ChatUserAvatar from '@/Components/Chat/ChatUserAvatar.vue';
import TeamChatComposer from '@/Components/Chat/TeamChatComposer.vue';
import ChatCallLogRow from '@/Components/Chat/ChatCallLogRow.vue';
import TeamChatMessageRow from '@/Components/Chat/TeamChatMessageRow.vue';
import ChatReplyBar from '@/Components/Chat/ChatReplyBar.vue';
import ChatStickerPicker from '@/Components/Chat/ChatStickerPicker.vue';
import ChatMentionJumpButton from '@/Components/Chat/ChatMentionJumpButton.vue';
import ChatTeamMembersModal from '@/Components/Chat/ChatTeamMembersModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { employeeCallRealtimeEnabled, teamChatRealtimeEnabled } from '@/echo.js';
import { useEmployeeCall } from '@/composables/useEmployeeCall.js';
import { useChatImmersiveViewport } from '@/composables/useChatImmersiveViewport.js';
import { chatMobileChromeHidden } from '@/state/chatMobileChrome.js';
import { CHAT_MOBILE_MEDIA, isChatMobileViewport } from '@/utils/chatMobileViewport.js';
import { messageMentionsViewer } from '@/utils/chatMentions.js';
import { buildOptimisticAttachment, isVoiceFile } from '@/utils/chatVoiceAttachment.js';
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
    stickerPacks: { type: Array, default: () => [] },
    canManageTeamChatMembers: { type: Boolean, default: false },
    selectedTeamChatMembers: { type: Array, default: () => [] },
    mentionableUsers: { type: Array, default: () => [] },
    mentionInbox: {
        type: Object,
        default: () => ({ count: 0, message_ids: [], first_message_id: null }),
    },
    taskClients: { type: Array, default: () => [] },
});
const page = usePage();

function normalizeChatUnreadPayload(bundle = {}) {
    return {
        unreadCounts: { ...(bundle.unreadCounts || {}) },
        privateRoomUnreadCounts: { ...(bundle.privateRoomUnreadCounts || {}) },
        directUnreadCounts: { ...(bundle.directUnreadCounts || {}) },
        teamMentionUnreadCounts: { ...(bundle.teamMentionUnreadCounts || {}) },
        privateRoomMentionUnreadCounts: { ...(bundle.privateRoomMentionUnreadCounts || {}) },
        directMentionUnreadCounts: { ...(bundle.directMentionUnreadCounts || {}) },
        totalUnreadMessages: Number(bundle.totalUnreadMessages ?? 0),
    };
}

function normalizeMentionInbox(data = {}) {
    const messageIds = Array.isArray(data.message_ids)
        ? data.message_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
        : [];

    return {
        count: Number(data.count ?? messageIds.length ?? 0),
        message_ids: messageIds,
        first_message_id:
            data.first_message_id != null && Number(data.first_message_id) > 0
                ? Number(data.first_message_id)
                : messageIds[0] ?? null,
    };
}

const currentUserId = computed(() => page.props.auth?.user?.id || null);
const canDeleteRecords = computed(() => Boolean(page.props.auth?.can?.deleteRecords));
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const form = useForm({
    team_id: props.selectedTeam?.id ?? null,
    body: '',
    attachment: null,
    voice_note: false,
    reply_to_message_id: null,
    sticker_key: null,
});

const privateForm = useForm({
    body: '',
    attachment: null,
    voice_note: false,
    reply_to_message_id: null,
    sticker_key: null,
});

const directForm = useForm({
    body: '',
    attachment: null,
    voice_note: false,
    reply_to_message_id: null,
    sticker_key: null,
});

const replyTo = ref(null);
const pendingStickerKey = ref(null);
const stickerPickerOpen = ref(false);
const teamMembersModalOpen = ref(false);
const teamMembersSaving = ref(false);

const createRoomForm = useForm({
    name: '',
    member_ids: [],
});

const createRoomModalOpen = ref(false);
const messagesState = ref([...(props.messages || [])]);

function chatConversationKey() {
    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        return `team:${props.selectedTeam.id}`;
    }
    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        return `private:${props.selectedPrivateRoom.id}`;
    }
    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        return `direct:${props.selectedDirect.id}`;
    }
    return 'none';
}

function latestPersistedMessageId() {
    let max = 0;
    for (const row of messagesState.value) {
        const id = Number(row?.id);
        if (Number.isFinite(id) && id > max) {
            max = id;
        }
    }
    return max > 0 ? max : undefined;
}
const chatUnreadState = ref(
    normalizeChatUnreadPayload({
        ...(props.chatUnread || {}),
        unreadCounts: props.chatUnread?.unreadCounts ?? props.unreadCounts ?? {},
    }),
);
const mentionInboxState = ref(normalizeMentionInbox(props.mentionInbox || {}));
const mentionJumpLoading = ref(false);
const messagesContainerRef = ref(null);
const typingUsers = ref([]);
const searchTerm = ref('');
const teamSidebarFilter = ref('');
/** يحدّث ترتيب القائمة فور إرسال رسالة قبل استلام تحديث الخادم */
const activityOverrides = ref({});
const mobilePanel = ref('list');
const mobileSearchOpen = ref(false);
const mobileSearchInputRef = ref(null);

const hasActiveConversation = computed(
    () =>
        props.viewKind !== 'none' &&
        ((props.viewKind === 'team' && props.selectedTeam) ||
            (props.viewKind === 'private_room' && props.selectedPrivateRoom) ||
            (props.viewKind === 'direct' && props.selectedDirect)),
);

const isMobileChatOpen = computed(() => mobilePanel.value === 'chat');

const chatMobileViewport = ref(
    typeof window !== 'undefined' && window.matchMedia(CHAT_MOBILE_MEDIA).matches,
);

const mobileImmersiveChat = computed(
    () => isMobileChatOpen.value && hasActiveConversation.value && chatMobileViewport.value,
);

function toggleMobileSearch() {
    mobileSearchOpen.value = !mobileSearchOpen.value;
    if (mobileSearchOpen.value) {
        nextTick(() => mobileSearchInputRef.value?.focus());
    }
}

watch(
    mobileImmersiveChat,
    (immersive) => {
        chatMobileChromeHidden.value = immersive;
        if (typeof document !== 'undefined') {
            document.body.classList.toggle('chat-mobile-immersive', immersive);
        }
        nextTick(() => readImmersiveViewport());
    },
    { immediate: true },
);

const { read: readImmersiveViewport } = useChatImmersiveViewport(() => mobileImmersiveChat.value);

watch(isMobileChatOpen, (open) => {
    if (open) {
        nextTick(() => readImmersiveViewport());
    } else {
        mobileSearchOpen.value = false;
    }
});

watch(
    () => hasActiveConversation.value,
    (active) => {
        if (active && isChatMobileViewport()) {
            mobilePanel.value = 'chat';
        }
    },
    { immediate: true },
);
const selectedTeamSlug = ref(props.selectedTeam?.slug || '');
const editingMessageId = ref(null);
const editingBody = ref('');
const pendingMessages = ref([]);
const sendingMessage = ref(false);
const sendErrorBanner = ref('');
let pollingTimer = null;
let typingTimer = null;
let typingUsersTimer = null;
let echoSubscribedTeamId = null;
let summaryPollTimer = null;
let chatNotifPollTimer = null;
let readReceiptsTimer = null;

const mediaLightbox = ref({ open: false, url: '', name: '' });
const messageActions = ref({ open: false, msg: null });
const forwardModalOpen = ref(false);
const forwardProcessing = ref(false);
const forwardSourceMessage = ref(null);
const createTaskSheetOpen = ref(false);
const createTaskSourceMessage = ref(null);
const createTaskProcessing = ref(false);
const createTaskError = ref('');

const chatNotificationsOpen = ref(false);
const chatNotificationsLoading = ref(false);
const chatNotificationsList = ref([]);
const chatNotificationsUnread = ref(Number(page.props.notifications?.chat_notifications_unread || 0));

function mergeChatUnreadFromResponse(data) {
    mergeChatPayloadFromResponse(data);
}

function mergeChatPayloadFromResponse(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    const hasUnread =
        data.unreadCounts != null ||
        data.privateRoomUnreadCounts != null ||
        data.directUnreadCounts != null ||
        data.totalUnreadMessages != null ||
        data.teamMentionUnreadCounts != null ||
        data.privateRoomMentionUnreadCounts != null ||
        data.directMentionUnreadCounts != null;

    if (hasUnread) {
        chatUnreadState.value = normalizeChatUnreadPayload({
            unreadCounts: data.unreadCounts ?? chatUnreadState.value.unreadCounts,
            privateRoomUnreadCounts:
                data.privateRoomUnreadCounts ?? chatUnreadState.value.privateRoomUnreadCounts,
            directUnreadCounts: data.directUnreadCounts ?? chatUnreadState.value.directUnreadCounts,
            teamMentionUnreadCounts:
                data.teamMentionUnreadCounts ?? chatUnreadState.value.teamMentionUnreadCounts,
            privateRoomMentionUnreadCounts:
                data.privateRoomMentionUnreadCounts ??
                chatUnreadState.value.privateRoomMentionUnreadCounts,
            directMentionUnreadCounts:
                data.directMentionUnreadCounts ?? chatUnreadState.value.directMentionUnreadCounts,
            totalUnreadMessages: data.totalUnreadMessages ?? chatUnreadState.value.totalUnreadMessages,
        });
    }

    if (data.mentionInbox != null) {
        mentionInboxState.value = normalizeMentionInbox(data.mentionInbox);
    }
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

const selfAvatarUrl = computed(() => page.props.auth?.user?.avatar_url || '/images/mobile-logo.png');

const { startCall } = useEmployeeCall();
const employeeCallsEnabled = employeeCallRealtimeEnabled();

function startVoiceCallWithPeer() {
    const peerId = props.selectedDirect?.peer?.id;
    if (peerId) {
        startCall(peerId, 'voice');
    }
}

function startVideoCallWithPeer() {
    const peerId = props.selectedDirect?.peer?.id;
    if (peerId) {
        startCall(peerId, 'video');
    }
}
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

const chatHeaderAvatarUrl = computed(() => {
    if (props.viewKind === 'direct' && props.selectedDirect?.peer?.avatar_url) {
        return props.selectedDirect.peer.avatar_url;
    }
    return '';
});

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
    chatMobileChromeHidden.value = false;
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

function findStickerMeta(key) {
    for (const pack of props.stickerPacks || []) {
        const hit = (pack.stickers || []).find((s) => s.key === key);
        if (hit) {
            return { key: hit.key, url: hit.url, label: hit.label };
        }
    }
    return { key, url: '', label: 'ملصق' };
}

function resolveStickerUrl(key) {
    return findStickerMeta(key)?.url || '';
}

function enrichServerMessage(serverMsg, stickerKey, bodyText) {
    if (!serverMsg?.id) {
        return null;
    }
    const enriched = { ...serverMsg };
    if (stickerKey && !enriched.sticker?.url) {
        enriched.sticker_key = stickerKey;
        enriched.sticker = findStickerMeta(stickerKey);
    }
    if (!String(enriched.body || '').trim() && bodyText) {
        enriched.body = bodyText;
    }
    return enriched;
}

function dropPendingIfSynced(tempId, stickerKey, bodyText) {
    const synced = messagesState.value.some((m) => {
        const id = Number(m.id);
        if (!Number.isFinite(id) || id <= 0) {
            return false;
        }
        if (Number(m.user?.id) !== Number(currentUserId.value)) {
            return false;
        }
        if (stickerKey) {
            return stickerKeyForMessage(m) === stickerKey;
        }
        return String(m.body || '').trim() === String(bodyText || '').trim();
    });
    if (synced) {
        pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
    }
}

function applyChatSendResponse(data, tempId, stickerKey, bodyText) {
    mergeChatUnreadFromResponse(data);
    const serverMsg = enrichServerMessage(data?.message, stickerKey, bodyText);
    if (serverMsg?.id) {
        clearOptimisticMatchingServerMessage(serverMsg);
        mergeMessageRowsFromApi([serverMsg]);
        pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);
        return;
    }
    pullMessages().finally(() => dropPendingIfSynced(tempId, stickerKey, bodyText));
}

function handleChatSendFailure(err, tempId, stickerKey, bodyText, onFormReset) {
    const alreadySynced = messagesState.value.some((m) => {
        const id = Number(m.id);
        if (!Number.isFinite(id) || id <= 0) {
            return false;
        }
        if (Number(m.user?.id) !== Number(currentUserId.value)) {
            return false;
        }
        if (stickerKey) {
            return stickerKeyForMessage(m) === stickerKey;
        }
        return String(m.body || '').trim() === String(bodyText || '').trim();
    });

    pendingMessages.value = pendingMessages.value.filter((msg) => msg.id !== tempId);

    if (alreadySynced) {
        sendErrorBanner.value = '';
        if (typeof onFormReset === 'function') {
            onFormReset();
        }
        return;
    }

    sendErrorBanner.value =
        err?.response?.data?.message ||
        err?.response?.data?.errors?.body?.[0] ||
        err?.response?.data?.errors?.sticker_key?.[0] ||
        err?.response?.data?.errors?.attachment?.[0] ||
        err?.message === 'invalid_response'
            ? 'تعذّر قراءة رد السيرفر.'
            : 'تعذر إرسال الرسالة.';
}

function postChatForm(url, formData, tempId, stickerKey, bodyText, onFormReset) {
    sendErrorBanner.value = '';
    sendingMessage.value = true;

    return window.axios
        .post(url, formData, {
            headers: { Accept: 'application/json' },
        })
        .then((res) => {
            if (!res?.data || typeof res.data !== 'object') {
                throw new Error('invalid_response');
            }
            applyChatSendResponse(res.data, tempId, stickerKey, bodyText);
            if (typeof onFormReset === 'function') {
                onFormReset();
            }
            nextTick(() => scrollToBottom());
        })
        .catch((err) => handleChatSendFailure(err, tempId, stickerKey, bodyText, onFormReset))
        .finally(() => {
            sendingMessage.value = false;
        });
}

function submitTeamMessage() {
    const stickerKey = pendingStickerKey.value;
    if (!form.team_id || (!form.body.trim() && !form.attachment && !stickerKey)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: form.body,
        sticker_key: stickerKey,
        sticker: stickerKey ? findStickerMeta(stickerKey) : null,
        reply: replyTo.value,
        created_at: new Date().toISOString(),
        user: {
            id: currentUserId.value,
            name: page.props.auth?.user?.name || 'أنا',
            avatar_url: page.props.auth?.user?.avatar_url || null,
        },
        attachment: form.attachment ? buildOptimisticAttachment(form.attachment) : null,
        is_pending: true,
        read_receipt: defaultSentReceipt(),
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    const fd = new FormData();
    fd.append('team_id', String(form.team_id));
    fd.append('body', form.body || '');
    if (stickerKey) {
        fd.append('sticker_key', stickerKey);
    }
    if (replyTo.value?.id) {
        fd.append('reply_to_message_id', String(replyTo.value.id));
    }
    if (form.attachment) {
        fd.append('attachment', form.attachment);
        fd.append('voice_note', form.voice_note ? '1' : '0');
    }

    postChatForm(route('chat.store'), fd, tempId, stickerKey, form.body, () => {
        form.body = '';
        form.attachment = null;
        form.voice_note = false;
        form.reply_to_message_id = null;
        form.sticker_key = null;
        pendingStickerKey.value = null;
        clearReply();
        if (props.selectedTeam?.id) {
            bumpInboxActivity(`team:${props.selectedTeam.id}`);
        }
    });
}

function submitPrivateMessage() {
    const stickerKey = pendingStickerKey.value;
    if (!props.selectedPrivateRoom?.id || (!privateForm.body.trim() && !privateForm.attachment && !stickerKey)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: privateForm.body,
        sticker_key: stickerKey,
        sticker: stickerKey ? findStickerMeta(stickerKey) : null,
        reply: replyTo.value,
        created_at: new Date().toISOString(),
        user: {
            id: currentUserId.value,
            name: page.props.auth?.user?.name || 'أنا',
            avatar_url: page.props.auth?.user?.avatar_url || null,
        },
        attachment: privateForm.attachment ? buildOptimisticAttachment(privateForm.attachment) : null,
        is_pending: true,
        read_receipt: defaultSentReceipt(),
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    const fd = new FormData();
    fd.append('body', privateForm.body || '');
    if (stickerKey) {
        fd.append('sticker_key', stickerKey);
    }
    if (replyTo.value?.id) {
        fd.append('reply_to_message_id', String(replyTo.value.id));
    }
    if (privateForm.attachment) {
        fd.append('attachment', privateForm.attachment);
        fd.append('voice_note', privateForm.voice_note ? '1' : '0');
    }

    postChatForm(
        route('chat.private-rooms.messages.store', props.selectedPrivateRoom.id),
        fd,
        tempId,
        stickerKey,
        privateForm.body,
        () => {
            privateForm.body = '';
            privateForm.attachment = null;
            privateForm.voice_note = false;
            privateForm.reply_to_message_id = null;
            privateForm.sticker_key = null;
            pendingStickerKey.value = null;
            clearReply();
            if (props.selectedPrivateRoom?.id) {
                bumpInboxActivity(`private:${props.selectedPrivateRoom.id}`);
            }
        },
    );
}

function submitDirectMessage() {
    const stickerKey = pendingStickerKey.value;
    if (!props.selectedDirect?.id || (!directForm.body.trim() && !directForm.attachment && !stickerKey)) {
        return;
    }

    const tempId = `pending-${Date.now()}`;
    const optimisticMessage = {
        id: tempId,
        body: directForm.body,
        sticker_key: stickerKey,
        sticker: stickerKey ? findStickerMeta(stickerKey) : null,
        reply: replyTo.value,
        created_at: new Date().toISOString(),
        user: {
            id: currentUserId.value,
            name: page.props.auth?.user?.name || 'أنا',
            avatar_url: page.props.auth?.user?.avatar_url || null,
        },
        attachment: directForm.attachment ? buildOptimisticAttachment(directForm.attachment) : null,
        is_pending: true,
        read_receipt: defaultSentReceipt(),
    };
    pendingMessages.value.push(optimisticMessage);
    nextTick(() => scrollToBottom());

    const fd = new FormData();
    fd.append('body', directForm.body || '');
    if (stickerKey) {
        fd.append('sticker_key', stickerKey);
    }
    if (replyTo.value?.id) {
        fd.append('reply_to_message_id', String(replyTo.value.id));
    }
    if (directForm.attachment) {
        fd.append('attachment', directForm.attachment);
        fd.append('voice_note', directForm.voice_note ? '1' : '0');
    }

    postChatForm(route('chat.direct.messages.store', props.selectedDirect.id), fd, tempId, stickerKey, directForm.body, () => {
        directForm.body = '';
        directForm.attachment = null;
        directForm.voice_note = false;
        directForm.reply_to_message_id = null;
        directForm.sticker_key = null;
        pendingStickerKey.value = null;
        clearReply();
        if (props.selectedDirect?.id) {
            bumpInboxActivity(`direct:${props.selectedDirect.id}`);
        }
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
    setActiveAttachment(file);
}

function clearAttachment() {
    form.attachment = null;
    form.voice_note = false;
    privateForm.attachment = null;
    privateForm.voice_note = false;
    directForm.attachment = null;
    directForm.voice_note = false;
}

function setActiveAttachment(file) {
    if (props.viewKind === 'team') {
        form.attachment = file;
        form.voice_note = isVoiceFile(file);
        return;
    }
    if (props.viewKind === 'private_room') {
        privateForm.attachment = file;
        privateForm.voice_note = isVoiceFile(file);
        return;
    }
    if (props.viewKind === 'direct') {
        directForm.attachment = file;
        directForm.voice_note = isVoiceFile(file);
    }
}

function onSendVoice(file) {
    setActiveAttachment(file);
    nextTick(() => submitMessage());
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
    if (sendingMessage.value) {
        return true;
    }
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

function stickerKeyForMessage(msg) {
    return msg?.sticker?.key || msg?.sticker_key || null;
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
        const pSticker = stickerKeyForMessage(p);
        const mSticker = stickerKeyForMessage(msg);
        if (pSticker || mSticker) {
            return pSticker !== mSticker;
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
            clearOptimisticMatchingServerMessage(msg);
            const snap = shouldAutoScroll();
            const idx = messagesState.value.findIndex((m) => Number(m.id) === Number(msg.id));
            if (idx === -1) {
                messagesState.value.push(msg);
            } else {
                messagesState.value[idx] = mergeMessageRow(messagesState.value[idx], msg);
            }
            bumpMentionInboxForMessage(msg);
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
    const afterId = latestPersistedMessageId();

    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        return window.axios
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
                mergeMessageRowsFromApi(rows);
            })
            .catch(() => {});
    }

    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        return window.axios
            .get(route('chat.private-rooms.messages.index', props.selectedPrivateRoom.id), {
                params: { after_id: afterId },
                headers: { Accept: 'application/json' },
            })
            .then((res) => {
                const rows = res?.data?.messages || [];
                mergeChatUnreadFromResponse(res?.data);
                mergeMessageRowsFromApi(rows);
            })
            .catch(() => {});
    }

    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        return window.axios
            .get(route('chat.direct.messages.index', props.selectedDirect.id), {
                params: { after_id: afterId },
                headers: { Accept: 'application/json' },
            })
            .then((res) => {
                const rows = res?.data?.messages || [];
                mergeChatUnreadFromResponse(res?.data);
                mergeMessageRowsFromApi(rows);
            })
            .catch(() => {});
    }

    return Promise.resolve();
}

function startPolling() {
    stopPolling();
    const pollMs = teamChatRealtimeEnabled() ? POLL_MS_REALTIME : POLL_MS_FALLBACK;
    pollingTimer = setInterval(pullMessages, pollMs);
    readReceiptsTimer = setInterval(pullReadReceipts, 5000);
    pullReadReceipts();
    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        typingUsersTimer = setInterval(pullTypingUsers, TYPING_POLL_MS);
    }
}

function stopPolling() {
    if (pollingTimer) {
        clearInterval(pollingTimer);
        pollingTimer = null;
    }
    if (readReceiptsTimer) {
        clearInterval(readReceiptsTimer);
        readReceiptsTimer = null;
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
    if (msg?.kind === 'call') {
        return false;
    }
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

function teamMentionUnreadCount(teamId) {
    return Number(chatUnreadState.value.teamMentionUnreadCounts?.[teamId] || 0);
}

function privateRoomMentionUnreadCount(roomId) {
    return Number(chatUnreadState.value.privateRoomMentionUnreadCounts?.[roomId] || 0);
}

function directMentionUnreadCount(conversationId) {
    if (!conversationId) {
        return 0;
    }
    return Number(chatUnreadState.value.directMentionUnreadCounts?.[conversationId] || 0);
}

function activeChatContext() {
    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        return { type: 'team', id: Number(props.selectedTeam.id) };
    }
    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        return { type: 'private_room', id: Number(props.selectedPrivateRoom.id) };
    }
    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        return { type: 'direct', id: Number(props.selectedDirect.id) };
    }
    return null;
}

function bumpMentionInboxForMessage(msg) {
    if (!messageMentionsViewer(msg, currentUserId.value) || isMine(msg)) {
        return;
    }

    const messageId = Number(msg?.id);
    if (!Number.isFinite(messageId) || messageId <= 0) {
        return;
    }

    const ids = [...(mentionInboxState.value.message_ids || [])];
    if (!ids.includes(messageId)) {
        ids.push(messageId);
        ids.sort((a, b) => a - b);
    }

    mentionInboxState.value = {
        count: ids.length,
        message_ids: ids,
        first_message_id: mentionInboxState.value.first_message_id || messageId,
    };

    const ctx = activeChatContext();
    if (!ctx) {
        return;
    }

    const key =
        ctx.type === 'team'
            ? 'teamMentionUnreadCounts'
            : ctx.type === 'private_room'
              ? 'privateRoomMentionUnreadCounts'
              : 'directMentionUnreadCounts';

    const map = { ...(chatUnreadState.value[key] || {}) };
    map[ctx.id] = (Number(map[ctx.id]) || 0) + 1;
    chatUnreadState.value = { ...chatUnreadState.value, [key]: map };
}

async function acknowledgeMentionUpTo(messageId) {
    const ctx = activeChatContext();
    const upTo = Number(messageId);
    if (!ctx || !Number.isFinite(upTo) || upTo <= 0) {
        return;
    }

    try {
        const res = await window.axios.post(
            route('chat.mentions.acknowledge'),
            {
                context_type: ctx.type,
                context_id: ctx.id,
                up_to_message_id: upTo,
            },
            { headers: { Accept: 'application/json' } },
        );
        mergeChatPayloadFromResponse(res?.data);
    } catch {
        /* تجاهل */
    }
}

async function scrollToMessage(messageId) {
    await nextTick();
    const container = messagesContainerRef.value;
    if (!container) {
        return false;
    }

    const el = container.querySelector(`[data-message-id="${messageId}"]`);
    if (!el) {
        return false;
    }

    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el.classList.add('chat-mention-flash');
    window.setTimeout(() => el.classList.remove('chat-mention-flash'), 2200);
    return true;
}

async function fetchMessagesAround(messageId) {
    const anchor = Number(messageId);
    if (!Number.isFinite(anchor) || anchor <= 0) {
        return;
    }

    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        const res = await window.axios.get(route('chat.messages.index'), {
            params: { team_id: props.selectedTeam.id, around_message_id: anchor },
            headers: { Accept: 'application/json' },
        });
        messagesState.value = res?.data?.messages || [];
        mergeChatPayloadFromResponse(res?.data);
        return;
    }

    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        const res = await window.axios.get(
            route('chat.private-rooms.messages.index', props.selectedPrivateRoom.id),
            {
                params: { around_message_id: anchor },
                headers: { Accept: 'application/json' },
            },
        );
        messagesState.value = res?.data?.messages || [];
        mergeChatPayloadFromResponse(res?.data);
        return;
    }

    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        const res = await window.axios.get(route('chat.direct.messages.index', props.selectedDirect.id), {
            params: { around_message_id: anchor },
            headers: { Accept: 'application/json' },
        });
        messagesState.value = res?.data?.messages || [];
        mergeChatPayloadFromResponse(res?.data);
    }
}

async function jumpToUnreadMention() {
    const targetId = mentionInboxState.value.first_message_id;
    if (!targetId || mentionJumpLoading.value) {
        return;
    }

    mentionJumpLoading.value = true;
    try {
        let found = await scrollToMessage(targetId);
        if (!found) {
            await fetchMessagesAround(targetId);
            found = await scrollToMessage(targetId);
        }
        if (found) {
            await acknowledgeMentionUpTo(targetId);
        }
    } finally {
        mentionJumpLoading.value = false;
    }
}

const showMentionJumpButton = computed(
    () => hasActiveConversation.value && Number(mentionInboxState.value.count) > 0,
);

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
        const inCall = String(msg.call?.label || '').toLowerCase().includes(term);
        return inBody || inName || inCall;
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

const composerBody = computed({
    get() {
        if (props.viewKind === 'team') {
            return form.body;
        }
        if (props.viewKind === 'private_room') {
            return privateForm.body;
        }
        if (props.viewKind === 'direct') {
            return directForm.body;
        }
        return '';
    },
    set(value) {
        if (props.viewKind === 'team') {
            form.body = value;
            return;
        }
        if (props.viewKind === 'private_room') {
            privateForm.body = value;
            return;
        }
        if (props.viewKind === 'direct') {
            directForm.body = value;
        }
    },
});

const composerPlaceholder = computed(() => {
    const mentionHint = (props.mentionableUsers || []).length ? ' · @للمنشن' : '';
    if (props.viewKind === 'team') {
        return `رسالة للفريق…${mentionHint}`;
    }
    if (props.viewKind === 'private_room') {
        return `رسالة للغرفة…${mentionHint}`;
    }
    if (props.viewKind === 'direct') {
        return `رسالة خاصة…${mentionHint}`;
    }
    return 'اكتب رسالة…';
});

const composerTypingHint = computed(() => {
    if (props.viewKind !== 'team' || !typingUsers.value.length) {
        return '';
    }
    return `${typingUsers.value.map((u) => u.name).join('، ')} يكتب الآن…`;
});

const chatTimelineItems = computed(() => {
    const items = [];
    let lastDayKey = '';

    for (const msg of displayedMessages.value) {
        const d = new Date(msg.created_at);
        const dayKey = Number.isFinite(d.getTime())
            ? d.toLocaleDateString('ar-SA', { year: 'numeric', month: '2-digit', day: '2-digit' })
            : '';
        if (dayKey && dayKey !== lastDayKey) {
            const label = Number.isFinite(d.getTime())
                ? d.toLocaleDateString('ar-SA', {
                      weekday: 'long',
                      day: 'numeric',
                      month: 'long',
                  })
                : '';
            items.push({ type: 'separator', key: `sep-${dayKey}`, label });
            lastDayKey = dayKey;
        }
        items.push({ type: 'message', key: String(msg.id), msg });
    }

    return items;
});

function showSenderHeader(msg, index) {
    if (isMine(msg)) {
        return false;
    }
    const list = displayedMessages.value;
    const prev = list[index - 1];
    if (!prev) {
        return true;
    }
    return Number(prev.user?.id) !== Number(msg.user?.id);
}

function messageIndexForTimeline(msg) {
    return displayedMessages.value.findIndex((row) => row.id === msg.id);
}

function defaultSentReceipt() {
    return {
        status: 'sent',
        read_count: 0,
        total_recipients: 0,
        label: 'تم الإرسال',
    };
}

function applyReadReceipts(receipts) {
    if (!receipts || typeof receipts !== 'object') {
        return;
    }
    messagesState.value = messagesState.value.map((row) => {
        const patch = receipts[row.id];
        if (!patch) {
            return row;
        }
        return { ...row, read_receipt: patch };
    });
}

function mergeMessageRow(existing, incoming) {
    const merged = { ...existing, ...incoming };
    if (!incoming?.sticker?.url && existing?.sticker?.url) {
        merged.sticker = existing.sticker;
    }
    if (!incoming?.sticker_key && existing?.sticker_key) {
        merged.sticker_key = existing.sticker_key;
    }
    return merged;
}

function mergeMessageRowsFromApi(rows) {
    if (!rows?.length) {
        return;
    }
    const receipts = {};
    const shouldAuto = shouldAutoScroll();
    const next = [...messagesState.value];

    for (const row of rows) {
        if (!row?.id) {
            continue;
        }
        if (row?.read_receipt) {
            receipts[row.id] = row.read_receipt;
        }
        const idx = next.findIndex((m) => Number(m.id) === Number(row.id));
        if (idx === -1) {
            next.push(row);
        } else {
            next[idx] = mergeMessageRow(next[idx], row);
        }
    }

    messagesState.value = next;

    if (Object.keys(receipts).length) {
        applyReadReceipts(receipts);
    }
    if (shouldAuto) {
        nextTick(() => scrollToBottom());
    }
}

function readReceiptsRequestParams() {
    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        return { kind: 'team', team_id: props.selectedTeam.id };
    }
    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        return { kind: 'private_room', private_room_id: props.selectedPrivateRoom.id };
    }
    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        return { kind: 'direct', direct_conversation_id: props.selectedDirect.id };
    }
    return null;
}

async function pullReadReceipts() {
    const params = readReceiptsRequestParams();
    if (!params) {
        return;
    }
    try {
        const res = await window.axios.get(route('chat.read-receipts'), {
            params,
            headers: { Accept: 'application/json' },
        });
        applyReadReceipts(res?.data?.receipts);
    } catch {
        /* تجاهل في الخلفية */
    }
}

function openMediaLightbox(payload) {
    if (!payload?.url) {
        return;
    }
    mediaLightbox.value = {
        open: true,
        url: payload.url,
        name: payload.name || 'صورة',
    };
}

function closeMediaLightbox() {
    mediaLightbox.value = { open: false, url: '', name: '' };
}

const forwardTargets = computed(() => {
    const rows = [];

    for (const team of props.teams || []) {
        if (props.viewKind === 'team' && Number(props.selectedTeam?.id) === Number(team.id)) {
            continue;
        }
        const label = displayTeamName(team);
        rows.push({
            key: `team-${team.id}`,
            kind: 'team',
            id: team.id,
            label,
            hint: 'دردشة الفريق',
            initial: userInitial(label),
            badgeClass: 'bg-gradient-to-br from-slate-600 to-slate-800',
        });
    }

    for (const room of props.privateRooms || []) {
        if (props.viewKind === 'private_room' && Number(props.selectedPrivateRoom?.id) === Number(room.id)) {
            continue;
        }
        const label = room.name || 'غرفة';
        rows.push({
            key: `room-${room.id}`,
            kind: 'private_room',
            id: room.id,
            label,
            hint: 'غرفة خاصة',
            initial: userInitial(label),
            badgeClass: 'bg-gradient-to-br from-violet-600 to-indigo-800',
        });
    }

    for (const peer of props.directPeers || []) {
        if (!peer.conversation_id) {
            continue;
        }
        if (props.viewKind === 'direct' && Number(props.selectedDirect?.id) === Number(peer.conversation_id)) {
            continue;
        }
        const label = peer.name || 'موظف';
        rows.push({
            key: `direct-${peer.conversation_id}`,
            kind: 'direct',
            id: peer.conversation_id,
            label,
            hint: 'رسالة خاصة',
            initial: userInitial(label),
            badgeClass: 'bg-gradient-to-br from-emerald-600 to-teal-800',
        });
    }

    return rows.sort((a, b) => String(a.label).localeCompare(String(b.label), 'ar'));
});

function buildReplyPreview(msg) {
    if (!msg) {
        return null;
    }
    let preview = String(msg.body || '').trim();
    if (!preview && msg.sticker?.label) {
        preview = msg.sticker.label;
    }
    if (!preview && msg.attachment) {
        preview = 'مرفق';
    }
    return {
        id: msg.id,
        user_name: msg.user?.name || 'عضو',
        preview: preview || 'رسالة',
    };
}

function startReply(msg) {
    if (!msg || msg.is_pending || msg.kind === 'call') {
        return;
    }
    replyTo.value = buildReplyPreview(msg);
    closeMessageActions();
}

function clearReply() {
    replyTo.value = null;
}

function openStickerPicker() {
    stickerPickerOpen.value = true;
}

function onStickerSelected(sticker) {
    if (!sticker?.key) {
        return;
    }
    pendingStickerKey.value = sticker.key;
    submitMessage();
}

async function saveTeamChatMembers(memberIds) {
    if (!props.selectedTeam?.id) {
        return;
    }
    teamMembersSaving.value = true;
    try {
        await window.axios.put(
            route('chat.teams.members.update', props.selectedTeam.id),
            { member_ids: memberIds },
            { headers: { Accept: 'application/json' } },
        );
        teamMembersModalOpen.value = false;
        router.reload({ only: ['teams', 'selectedTeamChatMembers', 'messages'] });
    } catch {
        /* يمكن إظهار تنبيه لاحقًا */
    } finally {
        teamMembersSaving.value = false;
    }
}

function stripMentionTokens(text) {
    return String(text || '')
        .replace(/@\[[\d]+\]|@[a-zA-Z0-9][a-zA-Z0-9._-]{1,31}/gu, '')
        .replace(/\s+/gu, ' ')
        .trim();
}

function titleFromChatMessage(msg) {
    const plain = stripMentionTokens(msg?.body || '');
    if (plain.length <= 120) {
        return plain;
    }
    return `${plain.slice(0, 117)}…`;
}

function assigneeIdsFromChatMessage(msg) {
    const ids = new Set();
    for (const mention of msg?.mentions || []) {
        const uid = Number(mention?.user?.id ?? mention?.user_id ?? 0);
        if (uid > 0) {
            ids.add(uid);
        }
    }
    return [...ids];
}

const createTaskRequireTeamPick = computed(() => props.viewKind !== 'team');
const createTaskDefaultTeamSlug = computed(() => props.selectedTeam?.slug || '');
const createTaskInitial = computed(() => {
    const msg = createTaskSourceMessage.value;
    let description = '';
    if (msg?.body && replyTo.value?.id) {
        description = `الرد:\n${replyTo.value.body || ''}\n\nالأصل:\n${msg.body}`;
    } else if (msg?.body) {
        description = String(msg.body);
    }

    return {
        title: msg ? titleFromChatMessage(msg) : '',
        description,
        clientId: null,
        assigneeIds: msg ? assigneeIdsFromChatMessage(msg) : [],
    };
});

function openCreateTaskSheet(sourceMessage = null) {
    if (props.viewKind === 'none') {
        return;
    }
    createTaskSourceMessage.value = sourceMessage;
    createTaskError.value = '';
    createTaskSheetOpen.value = true;
    closeMessageActions();
}

async function submitCreateTaskFromChat(payload) {
    if (!payload?.title?.trim() || !payload?.team_slug) {
        createTaskError.value = 'عنوان المهمة والفريق مطلوبان.';
        return;
    }

    const body = {
        context: props.viewKind,
        team_slug: payload.team_slug,
        title: payload.title,
        description: payload.description || null,
        client_id: payload.client_id,
        assignee_ids: payload.assignee_ids,
        source_message_id: createTaskSourceMessage.value?.id || null,
    };

    if (props.viewKind === 'team' && props.selectedTeam?.id) {
        body.team_id = props.selectedTeam.id;
    }
    if (props.viewKind === 'private_room' && props.selectedPrivateRoom?.id) {
        body.private_room_id = props.selectedPrivateRoom.id;
    }
    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        body.direct_conversation_id = props.selectedDirect.id;
    }

    createTaskProcessing.value = true;
    createTaskError.value = '';
    try {
        const res = await window.axios.post(route('chat.tasks-from-message.store'), body, {
            headers: { Accept: 'application/json' },
        });
        mergeChatUnreadFromResponse(res?.data);
        if (res?.data?.message) {
            mergeMessageRowsFromApi([res.data.message]);
        }
        createTaskSheetOpen.value = false;
        createTaskSourceMessage.value = null;
    } catch (err) {
        const errors = err?.response?.data?.errors;
        const firstField = errors && typeof errors === 'object' ? Object.values(errors).flat()[0] : null;
        createTaskError.value = String(firstField || err?.response?.data?.message || 'تعذر إنشاء المهمة.');
    } finally {
        createTaskProcessing.value = false;
    }
}

function onMessageActionCreateTask() {
    const msg = messageActions.value.msg;
    closeMessageActions();
    if (msg) {
        openCreateTaskSheet(msg);
    }
}

function openMessageActions(msg) {
    if (!msg || msg.is_pending || msg.kind === 'call') {
        return;
    }
    messageActions.value = { open: true, msg };
}

function closeMessageActions() {
    messageActions.value = { open: false, msg: null };
}

async function copyMessageText(msg) {
    const chunks = [];
    if (msg?.forward) {
        const from = [msg.forward.from_user_name, msg.forward.from_context].filter(Boolean).join(' · ');
        if (from) {
            chunks.push(`[محوّلة من ${from}]`);
        }
    }
    if (msg?.body) {
        chunks.push(String(msg.body));
    }
    if (msg?.attachment?.url) {
        chunks.push(String(msg.attachment.url));
    }
    const text = chunks.join('\n').trim();
    if (!text) {
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        /* تجاهل */
    }
    closeMessageActions();
}

function openForwardFromActions() {
    forwardSourceMessage.value = messageActions.value.msg;
    closeMessageActions();
    forwardModalOpen.value = true;
}

function closeForwardModal() {
    forwardModalOpen.value = false;
    forwardSourceMessage.value = null;
}

async function submitForwardTarget(target) {
    const msg = forwardSourceMessage.value;
    if (!msg?.id || !target?.kind || !target?.id) {
        return;
    }
    forwardProcessing.value = true;
    try {
        const res = await window.axios.post(
            route('chat.forward'),
            {
                source_kind: props.viewKind,
                source_message_id: msg.id,
                target_kind: target.kind,
                target_id: target.id,
            },
            { headers: { Accept: 'application/json' } },
        );
        closeForwardModal();
        const url = res?.data?.redirect_url;
        if (url) {
            router.visit(url);
        }
    } finally {
        forwardProcessing.value = false;
    }
}

function onMessageActionEdit() {
    const msg = messageActions.value.msg;
    closeMessageActions();
    if (msg) {
        startEdit(msg);
    }
}

function onMessageActionRemove() {
    const msg = messageActions.value.msg;
    closeMessageActions();
    if (msg) {
        removeMessage(msg);
    }
}

function onEmployeeCallSettled() {
    if (props.viewKind === 'direct' && props.selectedDirect?.id) {
        pullMessages();
    }
}

let chatMobileMq = null;
let syncChatMobileViewport = null;

onMounted(() => {
    chatUnreadState.value = normalizeChatUnreadPayload({
        ...(props.chatUnread || {}),
        unreadCounts: props.chatUnread?.unreadCounts ?? props.unreadCounts ?? {},
    });
    chatNotificationsUnread.value = Number(page.props.notifications?.chat_notifications_unread || 0);
    selectedTeamSlug.value = props.selectedTeam?.slug || '';
    if (typeof window !== 'undefined') {
        chatMobileMq = window.matchMedia(CHAT_MOBILE_MEDIA);
        syncChatMobileViewport = () => {
            chatMobileViewport.value = chatMobileMq.matches;
        };
        syncChatMobileViewport();
        chatMobileMq.addEventListener('change', syncChatMobileViewport);
    }
    if (isChatMobileViewport() && hasActiveConversation.value) {
        mobilePanel.value = 'chat';
    }
    pullUnreadSummary();
    pullChatNotifications();
    summaryPollTimer = window.setInterval(pullUnreadSummary, 7000);
    chatNotifPollTimer = window.setInterval(() => pullChatNotifications({ silent: true }), 6000);
    window.addEventListener('employee-call-settled', onEmployeeCallSettled);
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
    () => chatConversationKey(),
    () => {
        messagesState.value = [...(props.messages || [])];
        pendingMessages.value = [];
        mentionInboxState.value = normalizeMentionInbox(props.mentionInbox || {});
    },
    { immediate: true },
);

watch(
    () => props.mentionInbox,
    (inbox) => {
        mentionInboxState.value = normalizeMentionInbox(inbox || {});
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
    window.removeEventListener('employee-call-settled', onEmployeeCallSettled);
    chatMobileChromeHidden.value = false;
    if (typeof document !== 'undefined') {
        document.body.classList.remove('chat-mobile-immersive');
    }
    if (chatMobileMq && syncChatMobileViewport) {
        chatMobileMq.removeEventListener('change', syncChatMobileViewport);
    }
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
            class="team-chat-inbox flex min-h-0 w-full min-w-0 max-w-full flex-1 flex-col self-stretch overflow-x-hidden overflow-y-hidden lg:mx-auto lg:h-[min(42rem,calc(100svh-13.5rem))] lg:max-h-[min(42rem,calc(100svh-13.5rem))] lg:w-full lg:max-w-7xl xl:h-[min(46rem,calc(100svh-12.5rem))] xl:max-h-[min(46rem,calc(100svh-12.5rem))]"
            :class="
                mobileImmersiveChat
                    ? 'max-lg:h-[100dvh] max-lg:max-h-[100dvh]'
                    : 'max-lg:max-h-[calc(100dvh-8.25rem)] max-lg:h-[calc(100dvh-8.25rem)]'
            "
        >
            <div
                class="grid h-full min-h-0 min-w-0 w-full max-w-full flex-1 grid-cols-1 grid-rows-1 overflow-hidden rounded-2xl border border-app-surface-border/90 bg-app-surface/90 shadow-xl shadow-slate-900/[0.06] ring-1 ring-black/[0.03] backdrop-blur-md auto-rows-[minmax(0,1fr)] sm:rounded-3xl max-lg:rounded-none max-lg:border-0 max-lg:shadow-none max-lg:ring-0 lg:grid-cols-[minmax(260px,1fr)_minmax(0,2.2fr)] xl:grid-cols-[320px_minmax(0,1fr)]"
                :class="mobilePanel === 'chat' ? 'max-lg:h-full' : ''"
            >
                <!-- قائمة الغرف (مثل صندوق الوارد في الخارج) -->
                <aside
                    data-tour="chat-inbox"
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
                                            <ChatUserAvatar
                                                v-if="item.kind === 'direct'"
                                                :name="item.peer.name"
                                                :avatar-url="item.peer.avatar_url || ''"
                                                size-class="h-12 w-12 sm:h-[52px] sm:w-[52px]"
                                                rounded-class="rounded-2xl"
                                                gradient-class="from-emerald-600 to-teal-800"
                                                text-class="text-sm"
                                            />
                                            <div
                                                v-else
                                                class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                                :class="
                                                    item.kind === 'team'
                                                        ? 'from-slate-600 to-slate-800'
                                                        : 'from-violet-600 to-indigo-800'
                                                "
                                            >
                                                {{
                                                    userInitial(
                                                        item.kind === 'team'
                                                            ? displayTeamName(item.team)
                                                            : item.room.name,
                                                    )
                                                }}
                                            </div>
                                            <span
                                                v-if="item.kind === 'team' && teamMentionUnreadCount(item.team.id) > 0"
                                                class="absolute -top-0.5 -start-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] font-bold leading-none text-white shadow-md"
                                                aria-hidden="true"
                                                title="منشن غير مقروء"
                                            >@</span>
                                            <span
                                                v-if="item.kind === 'team' && teamUnreadCount(item.team.id) > 0"
                                                class="absolute -bottom-1 -end-1 flex h-6 min-w-[1.25rem] items-center justify-center rounded-full border-2 border-white bg-brand-600 px-1 text-[10px] font-bold text-white shadow-md"
                                            >
                                                {{ teamUnreadCount(item.team.id) > 99 ? '99+' : teamUnreadCount(item.team.id) }}
                                            </span>
                                            <span
                                                v-if="item.kind === 'private_room' && privateRoomMentionUnreadCount(item.room.id) > 0"
                                                class="absolute -top-0.5 -start-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] font-bold leading-none text-white shadow-md"
                                                aria-hidden="true"
                                                title="منشن غير مقروء"
                                            >@</span>
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
                                                    directMentionUnreadCount(item.peer.conversation_id) > 0
                                                "
                                                class="absolute -top-0.5 -start-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] font-bold leading-none text-white shadow-md"
                                                aria-hidden="true"
                                                title="منشن غير مقروء"
                                            >@</span>
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
                                        v-if="privateRoomMentionUnreadCount(room.id) > 0"
                                        class="absolute -top-0.5 -start-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] font-bold leading-none text-white shadow-md"
                                        aria-hidden="true"
                                        title="منشن غير مقروء"
                                    >@</span>
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
                                            <ChatUserAvatar
                                                :name="peer.name"
                                                :avatar-url="peer.avatar_url || ''"
                                                size-class="h-12 w-12 sm:h-[52px] sm:w-[52px]"
                                                rounded-class="rounded-2xl"
                                                gradient-class="from-emerald-600 to-teal-800"
                                                text-class="text-sm"
                                            />
                                            <span
                                                v-if="
                                                    peer.conversation_id &&
                                                    directMentionUnreadCount(peer.conversation_id) > 0
                                                "
                                                class="absolute -top-0.5 -start-0.5 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-sky-500 text-[10px] font-bold leading-none text-white shadow-md"
                                                aria-hidden="true"
                                                title="منشن غير مقروء"
                                            >@</span>
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
                        'team-chat-thread flex min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden bg-gradient-to-b from-slate-100 via-slate-50 to-white',
                        mobilePanel === 'list' ? 'hidden lg:flex' : 'flex',
                        mobileImmersiveChat
                            ? 'team-chat-thread--immersive max-lg:z-[100] max-lg:flex max-lg:min-h-0 max-lg:flex-col max-lg:overflow-hidden max-lg:shadow-2xl'
                            : 'flex h-full flex-col',
                    ]"
                >
                    <template v-if="hasActiveConversation">
                        <header
                            class="flex shrink-0 items-center gap-1.5 border-b border-slate-200/80 bg-white/90 px-2 py-1.5 shadow-sm backdrop-blur-md max-md:pt-[max(0.35rem,env(safe-area-inset-top,0px))] sm:gap-3 sm:px-4 sm:py-3"
                        >
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
                            <ChatUserAvatar
                                :name="chatHeaderTitle"
                                :avatar-url="chatHeaderAvatarUrl"
                                size-class="h-10 w-10 sm:h-12 sm:w-12"
                                rounded-class="rounded-2xl"
                                gradient-class="from-brand-500 to-brand-700"
                                text-class="text-sm"
                            />
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-[15px] font-bold text-slate-900 sm:text-lg">
                                    {{ chatHeaderTitle }}
                                </h2>
                                <p v-if="viewKind === 'team'" class="hidden truncate text-[11px] text-slate-500 sm:block sm:text-xs">
                                    رسائل الفريق الداخلية
                                </p>
                                <p v-else-if="viewKind === 'private_room'" class="hidden truncate text-[11px] text-slate-500 sm:block sm:text-xs">
                                    غرفة خاصة · للمدعوين فقط
                                </p>
                                <p v-else class="hidden truncate text-[11px] text-slate-500 sm:block sm:text-xs">
                                    محادثة خاصة بين الموظفين
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 lg:hidden"
                                    :class="mobileSearchOpen ? 'border-brand-400 bg-brand-50 text-brand-700' : ''"
                                    title="بحث في الرسائل"
                                    aria-label="بحث في الرسائل"
                                    :aria-expanded="mobileSearchOpen"
                                    @click="toggleMobileSearch"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </button>
                                <button
                                    v-if="viewKind === 'team' && canManageTeamChatMembers && selectedTeam"
                                    type="button"
                                    class="hidden rounded-lg border border-slate-300 bg-white px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50 sm:inline-flex sm:px-2.5 sm:text-[11px]"
                                    @click="teamMembersModalOpen = true"
                                >
                                    إدارة الأعضاء
                                </button>
                                <button
                                    v-if="viewKind === 'direct' && selectedDirect?.peer && employeeCallsEnabled"
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-white shadow-md shadow-emerald-900/25 transition hover:bg-emerald-500 active:scale-95 sm:h-11 sm:w-auto sm:gap-2 sm:px-4"
                                    title="اتصال صوتي"
                                    aria-label="اتصال صوتي"
                                    @click="startVoiceCallWithPeer"
                                >
                                    <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C10.07 21 3 13.93 3 5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.46.57 3.58a1 1 0 01-.24 1.01l-2.2 2.2z" />
                                    </svg>
                                    <span class="hidden sm:inline">اتصال</span>
                                </button>
                                <button
                                    v-if="viewKind === 'direct' && selectedDirect?.peer && employeeCallsEnabled"
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-violet-200 bg-violet-50 text-violet-700 shadow-sm transition hover:bg-violet-100 sm:h-11 sm:w-11"
                                    title="مكالمة فيديو"
                                    aria-label="مكالمة فيديو"
                                    @click="startVideoCallWithPeer"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 8h8a2 2 0 012 2v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4a2 2 0 012-2z" />
                                    </svg>
                                </button>
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

                        <div
                            v-if="mobileSearchOpen"
                            class="shrink-0 border-b border-slate-200/60 bg-white/95 px-2 py-1.5 lg:hidden"
                        >
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    ref="mobileSearchInputRef"
                                    v-model="searchTerm"
                                    type="search"
                                    autocomplete="off"
                                    class="block w-full rounded-xl border-slate-200/90 bg-slate-50/90 py-2 ps-9 pe-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                                    placeholder="بحث في الرسائل…"
                                >
                            </div>
                        </div>

                        <div class="hidden shrink-0 border-b border-slate-200/60 bg-white/80 px-3 py-2 sm:px-4 sm:py-2.5 lg:block">
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
                                    class="block w-full rounded-xl border-slate-200/90 bg-slate-50/90 py-2 ps-9 pe-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                                    placeholder="بحث في الرسائل…"
                                >
                            </div>
                        </div>

                        <div class="relative min-h-0 flex-1 overflow-hidden">
                            <div
                                ref="messagesContainerRef"
                                dir="ltr"
                                class="team-chat-messages absolute inset-0 flex flex-col gap-3 overflow-y-auto overscroll-y-contain px-3 py-4 touch-pan-y [-webkit-overflow-scrolling:touch] sm:gap-3.5 sm:px-5 sm:py-5"
                            >
                            <template v-for="item in chatTimelineItems" :key="item.key">
                            <div
                                v-if="item.type === 'separator'"
                                class="flex justify-center py-1"
                                dir="rtl"
                            >
                                <span
                                    class="rounded-full bg-white/90 px-3 py-1 text-[10px] font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200/80"
                                >
                                    {{ item.label }}
                                </span>
                            </div>
                            <div
                                v-else-if="item.msg?.kind === 'call'"
                                :data-message-id="item.msg.id"
                                class="mention-scroll-target"
                            >
                                <ChatCallLogRow
                                    :msg="item.msg"
                                    :is-mine="isMine(item.msg)"
                                    :show-sender="showSenderHeader(item.msg, messageIndexForTimeline(item.msg))"
                                    :self-avatar-url="selfAvatarUrl"
                                    :self-name="selfName"
                                />
                            </div>
                            <div
                                v-else
                                :data-message-id="item.msg.id"
                                class="mention-scroll-target"
                            >
                                <TeamChatMessageRow
                                    :msg="item.msg"
                                    :resolve-sticker-url="resolveStickerUrl"
                                    :is-mine="isMine(item.msg)"
                                    :view-kind="viewKind"
                                    :show-sender="showSenderHeader(item.msg, messageIndexForTimeline(item.msg))"
                                    :can-manage="canManageMessage(item.msg)"
                                    :is-editing="editingMessageId === item.msg.id"
                                    v-model:editing-body="editingBody"
                                    :self-avatar-url="selfAvatarUrl"
                                    :self-name="selfName"
                                    @save-edit="saveEdit(item.msg)"
                                    @cancel-edit="cancelEdit"
                                    @start-edit="startEdit(item.msg)"
                                    @remove="removeMessage(item.msg)"
                                    @open-media="openMediaLightbox"
                                    @open-actions="openMessageActions"
                                    @reply="startReply"
                                />
                            </div>
                            </template>

                            <p
                                v-if="!displayedMessages?.length"
                                dir="rtl"
                                class="flex flex-1 flex-col items-center justify-center py-16 text-center text-sm text-slate-600"
                            >
                                لا توجد رسائل بعد. ابدأ من الأسفل.
                            </p>
                        </div>

                            <div
                                v-if="showMentionJumpButton"
                                class="pointer-events-none absolute bottom-4 end-4 z-20 flex justify-end"
                            >
                                <ChatMentionJumpButton
                                    class="pointer-events-auto"
                                    :count="mentionInboxState.count"
                                    @click="jumpToUnreadMention"
                                />
                            </div>
                        </div>

                        <ChatReplyBar v-if="replyTo" :reply="replyTo" @clear="clearReply" />

                        <div data-tour="chat-composer" class="relative z-30 shrink-0">
                        <TeamChatComposer
                            v-model="composerBody"
                            class="team-chat-composer-bar"
                            :keyboard-lift="false"
                            :placeholder="composerPlaceholder"
                            :processing="composerProcessing()"
                            :attachment="activeComposerAttachment()"
                            :body-error="activeComposerErrors().body || sendErrorBanner || ''"
                            :typing-hint="composerTypingHint"
                            :stickers-enabled="(stickerPacks || []).length > 0"
                            :mention-candidates="mentionableUsers"
                            :current-user-id="currentUserId"
                            @focusin="readImmersiveViewport"
                            @submit="submitMessage"
                            @typing="notifyComposerTyping"
                            @attachment-change="onAttachmentChange"
                            @clear-attachment="clearAttachment"
                            @send-voice="onSendVoice"
                            @open-stickers="openStickerPicker"
                            @open-create-task="openCreateTaskSheet()"
                        />
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex min-h-[50dvh] flex-1 flex-col items-center justify-center bg-gradient-to-b from-slate-100 to-slate-50 px-6 py-12 text-center lg:min-h-0"
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

        <ChatMediaLightbox
            :open="mediaLightbox.open"
            :url="mediaLightbox.url"
            :name="mediaLightbox.name"
            @close="closeMediaLightbox"
        />

        <ChatMessageActionsSheet
            :open="messageActions.open"
            :msg="messageActions.msg"
            :view-kind="viewKind"
            :can-manage="messageActions.msg ? canManageMessage(messageActions.msg) : false"
            @close="closeMessageActions"
            @copy="copyMessageText(messageActions.msg)"
            @forward="openForwardFromActions"
            @reply="startReply(messageActions.msg)"
            @create-task="onMessageActionCreateTask"
            @start-edit="onMessageActionEdit"
            @remove="onMessageActionRemove"
        />

        <ChatCreateTaskSheet
            :open="createTaskSheetOpen"
            :processing="createTaskProcessing"
            :error="createTaskError"
            :teams="teams"
            :clients="taskClients"
            :mentionable-users="mentionableUsers"
            :default-team-slug="createTaskDefaultTeamSlug"
            :require-team-pick="createTaskRequireTeamPick"
            :initial-title="createTaskInitial.title"
            :initial-description="createTaskInitial.description"
            :initial-client-id="createTaskInitial.clientId"
            :initial-assignee-ids="createTaskInitial.assigneeIds"
            @close="createTaskSheetOpen = false"
            @submit="submitCreateTaskFromChat"
        />

        <ChatStickerPicker
            :open="stickerPickerOpen"
            :packs="stickerPacks"
            @close="stickerPickerOpen = false"
            @select="onStickerSelected"
        />

        <ChatTeamMembersModal
            :show="teamMembersModalOpen"
            :team="selectedTeam"
            :team-display-name="selectedTeam ? displayTeamName(selectedTeam) : ''"
            :users="chatUsers"
            :initial-member-ids="(selectedTeamChatMembers || []).map((m) => m.id)"
            :processing="teamMembersSaving"
            @close="teamMembersModalOpen = false"
            @save="saveTeamChatMembers"
        />

        <ChatForwardModal
            :open="forwardModalOpen"
            :targets="forwardTargets"
            :processing="forwardProcessing"
            @close="closeForwardModal"
            @select="submitForwardTarget"
        />
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

.team-chat-thread {
    background-image:
        radial-gradient(ellipse 120% 80% at 50% -30%, rgb(var(--color-brand-500) / 0.09), transparent 55%),
        linear-gradient(180deg, rgb(248 250 252) 0%, rgb(241 245 249) 100%);
}

.team-chat-messages {
    scroll-behavior: smooth;
}

.team-chat-messages :deep(.chat-mention-flash) {
    animation: chat-mention-flash 2.2s ease-out;
}

@keyframes chat-mention-flash {
    0%,
    35% {
        box-shadow: 0 0 0 3px rgb(14 165 233 / 0.45);
        border-radius: 1rem;
    }
    100% {
        box-shadow: none;
    }
}

</style>

<style>
body.chat-mobile-immersive {
    position: fixed;
    width: 100%;
    overflow: hidden;
    overscroll-behavior: none;
    touch-action: manipulation;
}

body.chat-mobile-immersive .team-chat-thread--immersive {
    position: fixed;
    left: 0;
    right: 0;
    top: var(--chat-vv-top, 0px);
    height: var(--chat-vv-height, 100dvh);
    max-height: var(--chat-vv-height, 100dvh);
    width: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

body.chat-mobile-immersive .team-chat-composer-bar,
body.chat-mobile-immersive .team-chat-composer {
    flex-shrink: 0;
}

body.chat-mobile-immersive .team-chat-thread--immersive .team-chat-messages {
    scroll-behavior: auto;
}
</style>

