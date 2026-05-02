<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    conversations: Array,
    users: Array,
    conversation_statuses: Array,
    can_delete_outside_contacts: {
        type: Boolean,
        default: false,
    },
});

const mobilePanel = ref('list');
const activeConversationId = ref(props.conversations?.[0]?.id || null);
const channelFilter = ref('all');
const conversationSearch = ref('');
const messagesEl = ref(null);
const composerEl = ref(null);
const page = usePage();
const conversationSettingsOpen = ref(false);

const authUser = computed(() => page.props.auth?.user || null);
const selfAvatarUrl = computed(() => authUser.value?.avatar_url || '/images/mobile-logo.png');
const selfName = computed(() => authUser.value?.name || 'أنت');

const messageForm = useForm({
    body: '',
});
const conversationForm = useForm({
    status: '',
    assigned_user_id: null,
});
const retryForm = useForm({});

const activeConversation = computed(() =>
    (props.conversations || []).find((item) => item.id === activeConversationId.value) || null,
);

function normalizedOutsideChannel(channel) {
    const c = String(channel || 'whatsapp').trim().toLowerCase();
    return c === 'instagram' ? 'instagram' : 'whatsapp';
}

function isInstagramChannel(channel) {
    return normalizedOutsideChannel(channel) === 'instagram';
}

const channelFilteredConversations = computed(() => {
    const list = props.conversations || [];
    if (channelFilter.value === 'all') {
        return list;
    }
    if (channelFilter.value === 'instagram') {
        return list.filter((c) => normalizedOutsideChannel(c.contact?.channel) === 'instagram');
    }
    return list.filter((c) => normalizedOutsideChannel(c.contact?.channel) === 'whatsapp');
});

const filteredConversations = computed(() => {
    const keyword = conversationSearch.value.trim().toLowerCase();
    const base = channelFilteredConversations.value;
    if (!keyword) {
        return base;
    }
    return base.filter((conversation) => {
        const name = String(conversation.contact?.name || '').toLowerCase();
        const phone = String(conversation.contact?.phone || '').toLowerCase();
        const ig = String(conversation.contact?.instagram_psid || '').toLowerCase();
        const preview = String(conversation.latest_message_preview || '').toLowerCase();
        return (
            name.includes(keyword) ||
            phone.includes(keyword) ||
            ig.includes(keyword) ||
            preview.includes(keyword)
        );
    });
});

const messageTimeline = computed(() => {
    const list = activeConversation.value?.messages || [];
    const out = [];
    let lastDay = '';
    for (const m of list) {
        const day = m.created_at ? new Date(m.created_at).toDateString() : '';
        if (day && day !== lastDay) {
            lastDay = day;
            out.push({ kind: 'date', key: `day-${day}`, label: formatDayDivider(m.created_at) });
        }
        out.push({ kind: 'message', key: `msg-${m.id}`, message: m });
    }
    return out;
});

const lastMessageSig = computed(() => {
    const m = activeConversation.value?.messages;
    if (!m?.length) {
        return '';
    }
    const last = m[m.length - 1];
    return `${last.id}-${last.created_at}-${last.body?.length || 0}`;
});

watch(
    activeConversation,
    (value) => {
        conversationForm.status = value?.status || 'new';
        conversationForm.assigned_user_id = value?.contact?.assigned_user_id || null;
    },
    { immediate: true },
);

watch(lastMessageSig, async () => {
    await nextTick();
    scrollMessagesToEnd();
});

watch(activeConversationId, async () => {
    await nextTick();
    scrollMessagesToEnd();
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
        conversationSettingsOpen.value = false;
    }
});

watch(
    () => props.conversations,
    (list) => {
        if (!list?.length) {
            activeConversationId.value = null;
            return;
        }
        const exists = list.some((c) => c.id === activeConversationId.value);
        if (!exists) {
            activeConversationId.value = list[0]?.id ?? null;
        }
    },
    { deep: true },
);

watch(
    filteredConversations,
    (list) => {
        if (!list?.length) {
            activeConversationId.value = null;
            return;
        }
        if (!list.some((c) => c.id === activeConversationId.value)) {
            activeConversationId.value = list[0].id;
        }
    },
    { immediate: true },
);

watch(
    () => {
        const c = activeConversation.value;
        if (!c || !Number(c.unread_count || 0)) {
            return null;
        }
        return c.id;
    },
    (conversationId) => {
        if (!conversationId) {
            return;
        }
        router.post(route('outside.conversations.read', conversationId), {}, {
            preserveScroll: true,
            only: ['conversations'],
        });
    },
    { immediate: true },
);

const deleteContactProcessing = ref(false);

function deleteActiveContact() {
    const contactId = activeConversation.value?.contact?.id;
    if (!contactId || !props.can_delete_outside_contacts || deleteContactProcessing.value) {
        return;
    }
    const label =
        activeConversation.value?.contact?.name ||
        activeConversation.value?.contact?.phone ||
        'هذه الجهة';
    if (!confirm(`حذف ${label} وجميع رسائل المحادثة؟ لا يمكن التراجع.`)) {
        return;
    }
    deleteContactProcessing.value = true;
    router.delete(route('outside.contacts.destroy', contactId), {
        preserveScroll: true,
        onFinish: () => {
            deleteContactProcessing.value = false;
        },
    });
}

function scrollMessagesToEnd() {
    const el = messagesEl.value;
    if (!el) {
        return;
    }
    el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
}

function selectConversation(id) {
    activeConversationId.value = id;
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 1023px)').matches) {
        mobilePanel.value = 'chat';
    }
}

function showConversationList() {
    mobilePanel.value = 'list';
}

function submitMessage() {
    if (!activeConversation.value?.id) {
        return;
    }
    messageForm.post(route('outside.messages.store', activeConversation.value.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['conversations'],
        onSuccess: () => {
            messageForm.reset();
            nextTick(() => scrollMessagesToEnd());
        },
    });
}

function useTemplate(template) {
    messageForm.body = template;
    nextTick(() => composerEl.value?.focus?.());
}

function submitConversationMeta() {
    if (!activeConversation.value?.id) {
        return;
    }
    conversationForm.patch(route('outside.conversations.update', activeConversation.value.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['conversations'],
    });
}

function retryMessage(messageId) {
    if (!messageId || retryForm.processing) {
        return;
    }
    retryForm.post(route('outside.messages.retry', messageId), {
        preserveScroll: true,
        preserveState: true,
        only: ['conversations'],
    });
}

function formatDayDivider(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    const now = new Date();
    const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startMsg = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const diffDays = Math.round((startToday - startMsg) / 86400000);
    if (diffDays === 0) {
        return 'اليوم';
    }
    if (diffDays === 1) {
        return 'أمس';
    }
    return new Intl.DateTimeFormat('ar-SA', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    }).format(d);
}

function formatDt(value) {
    if (!value) {
        return '—';
    }
    return new Intl.DateTimeFormat('ar-SA', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatRelative(iso) {
    if (!iso) {
        return '';
    }
    const t = new Date(iso).getTime();
    const sec = Math.max(0, Math.round((Date.now() - t) / 1000));
    if (sec < 45) {
        return 'الآن';
    }
    const min = Math.round(sec / 60);
    if (min < 60) {
        return `${min} د`;
    }
    const h = Math.round(min / 60);
    if (h < 24) {
        return `${h} س`;
    }
    const d = Math.round(h / 24);
    return `${d}ي`;
}

function initials(name, phone) {
    const n = String(name || '').trim();
    if (n.length >= 2) {
        return n.slice(0, 2);
    }
    const raw = String(phone || '');
    if (raw.startsWith('ig:')) {
        const psid = raw.slice(3).replace(/\D/g, '');
        return psid.slice(-2) || 'IG';
    }
    const p = raw.replace(/\D/g, '');
    return p.slice(-2) || '?';
}

function contactSubtitle(contact) {
    if (!contact) {
        return '';
    }
    if (isInstagramChannel(contact.channel)) {
        return contact.instagram_psid ? `IG · ${contact.instagram_psid}` : 'إنستغرام';
    }
    return contact.phone || '';
}

function contactDisplayName(contact) {
    return String(contact?.name || '').trim() || contactSubtitle(contact) || 'جهة اتصال';
}

function statusLabel(status) {
    return props.conversation_statuses?.find((item) => item.value === status)?.label || status;
}

function statusClass(status) {
    if (status === 'new') {
        return 'bg-emerald-500/12 text-emerald-800 ring-1 ring-emerald-500/20';
    }
    if (status === 'potential') {
        return 'bg-amber-500/12 text-amber-900 ring-1 ring-amber-500/25';
    }
    if (status === 'unlikely') {
        return 'bg-rose-500/12 text-rose-900 ring-1 ring-rose-500/22';
    }
    if (status === 'qualified') {
        return 'bg-sky-500/12 text-sky-900 ring-1 ring-sky-500/25';
    }
    return 'bg-slate-500/10 text-slate-700 ring-1 ring-slate-400/18';
}

function channelMeta(ch) {
    if (normalizedOutsideChannel(ch) === 'instagram') {
        return {
            label: 'Instagram',
            avatarClass: 'from-[#f09433] via-[#e6683c] to-[#bc1888]',
        };
    }
    return {
        label: 'WhatsApp',
        avatarClass: 'from-[#25D366] to-[#128C7E]',
    };
}

const OUTSIDE_POLL_MS = 1000;

function formsBusy() {
    return (
        messageForm.processing ||
        conversationForm.processing ||
        retryForm.processing
    );
}

function reloadOutsideDataQuietly() {
    if (document.visibilityState !== 'visible') {
        return;
    }
    if (formsBusy()) {
        return;
    }
    router.reload({
        only: ['conversations'],
        preserveScroll: true,
        preserveState: true,
    });
}

let pollTimer;

function onVisibilityOrFocus() {
    if (document.visibilityState === 'visible') {
        reloadOutsideDataQuietly();
    }
}

/** روابط من صفحة العميل: ?client=&channel=&conversation= */
function applyDeepLinkFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const convParam = params.get('conversation');
    const clientParam = params.get('client');
    const channelParam = params.get('channel');
    if (channelParam === 'instagram' || channelParam === 'whatsapp') {
        channelFilter.value = channelParam;
    }
    nextTick(() => {
        const convId = convParam ? Number(convParam) : NaN;
        if (!Number.isNaN(convId) && convId > 0 && props.conversations.some((c) => c.id === convId)) {
            activeConversationId.value = convId;
            mobilePanel.value = 'chat';
        } else if (clientParam) {
            const match = filteredConversations.value.find(
                (c) => String(c.contact?.client_id ?? '') === String(clientParam),
            );
            if (match) {
                activeConversationId.value = match.id;
                mobilePanel.value = 'chat';
            }
        }
        nextTick(() => scrollMessagesToEnd());
    });
}

onMounted(() => {
    applyDeepLinkFromQuery();
    pollTimer = window.setInterval(reloadOutsideDataQuietly, OUTSIDE_POLL_MS);
    document.addEventListener('visibilitychange', onVisibilityOrFocus);
    window.addEventListener('focus', onVisibilityOrFocus);
    nextTick(() => scrollMessagesToEnd());
});

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
    document.removeEventListener('visibilitychange', onVisibilityOrFocus);
    window.removeEventListener('focus', onVisibilityOrFocus);
});

/** خلفية نمطية خفيفة (واتساب-ستايل) بدون تعارض مع علامات الاقتباس في القالب */
const threadBgStyle = {
    backgroundImage: 'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23c8c8c8\' fill-opacity=\'0.14\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")',
};
</script>

<template>
    <Head title="الخارج" />

    <AuthenticatedLayout>
        <template #title>الخارج</template>

        <div
            class="outside-inbox flex min-h-0 w-full min-w-0 max-w-full flex-1 flex-col self-stretch overflow-x-hidden overflow-y-hidden max-md:h-[calc(100dvh-10.5rem)] max-md:max-h-[calc(100dvh-10.5rem)] md:max-h-[calc(100dvh-9.5rem)] md:h-[calc(100dvh-9.5rem)] lg:mx-auto lg:h-[min(42rem,calc(100svh-13.5rem))] lg:max-h-[min(42rem,calc(100svh-13.5rem))] lg:w-full lg:max-w-7xl xl:h-[min(46rem,calc(100svh-12.5rem))] xl:max-h-[min(46rem,calc(100svh-12.5rem))]"
        >
            <div class="mb-3 w-full min-w-0 shrink-0 space-y-2 sm:mb-4">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-xl border border-rose-200/80 bg-rose-50 px-3 py-2 text-sm text-rose-900"
                >
                    {{ page.props.flash.error }}
                </div>
            </div>

            <div
                class="grid h-full min-h-0 min-w-0 w-full max-w-full flex-1 grid-cols-1 grid-rows-1 overflow-hidden rounded-2xl border border-app-surface-border/90 bg-app-surface/90 shadow-xl shadow-slate-900/[0.06] ring-1 ring-black/[0.03] backdrop-blur-md auto-rows-[minmax(0,1fr)] sm:rounded-3xl lg:grid-cols-[minmax(260px,1fr)_minmax(0,2.2fr)] xl:grid-cols-[380px_minmax(0,1fr)] 2xl:grid-cols-[420px_minmax(0,1fr)]"
            >
                <aside
                    :class="[
                        'flex h-full min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden border-app-surface-border/80 bg-gradient-to-b from-white/98 to-slate-50/90 lg:border-e',
                        mobilePanel === 'chat' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <div class="shrink-0 border-b border-slate-200/80 bg-white/90 px-3 py-3 sm:px-4 sm:py-3.5">
                        <h2 class="text-sm font-bold tracking-tight text-slate-900 sm:text-base">
                            صندوق الوارد
                        </h2>
                        <div class="relative mt-2.5">
                            <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                v-model="conversationSearch"
                                type="search"
                                autocomplete="off"
                                class="block w-full rounded-xl border-slate-200/90 bg-slate-50/80 py-2.5 ps-9 pe-3 text-sm text-slate-900 shadow-inner placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"
                                placeholder="بحث بالاسم، الرقم، أو المعرف…"
                            >
                        </div>
                        <div
                            class="mt-3 flex flex-wrap gap-1.5"
                            role="group"
                            aria-label="تصفية حسب القناة"
                        >
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-1.5 text-[11px] font-bold transition sm:text-xs"
                                :class="
                                    channelFilter === 'all'
                                        ? 'border-black bg-black !text-white shadow-sm ring-0'
                                        : 'border-slate-200/90 bg-white text-slate-700 hover:border-brand-300 hover:bg-brand-50/50'
                                "
                                @click="channelFilter = 'all'"
                            >
                                الكل
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border px-3 py-1.5 text-[11px] font-bold transition sm:text-xs"
                                :class="
                                    channelFilter === 'whatsapp'
                                        ? 'border-emerald-500/40 bg-emerald-600 text-white shadow-sm ring-1 ring-emerald-600/20'
                                        : 'border-slate-200/90 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/60'
                                "
                                @click="channelFilter = 'whatsapp'"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                </svg>
                                واتساب
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border px-3 py-1.5 text-[11px] font-bold transition sm:text-xs"
                                :class="
                                    channelFilter === 'instagram'
                                        ? 'border-pink-500/50 bg-gradient-to-r from-[#f09433] via-[#e6683c] to-[#bc1888] text-white shadow-sm'
                                        : 'border-slate-200/90 bg-white text-slate-700 hover:border-pink-300 hover:bg-pink-50/50'
                                "
                                @click="channelFilter = 'instagram'"
                            >
                                <svg
                                    class="h-3.5 w-3.5 shrink-0"
                                    :class="channelFilter === 'instagram' ? 'text-white' : 'text-pink-600'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill="currentColor"
                                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"
                                    />
                                </svg>
                                <span :class="channelFilter === 'instagram' ? 'text-white' : 'text-pink-700'">إنستغرام</span>
                            </button>
                        </div>
                    </div>

                    <div
                        class="outside-scroll min-h-0 min-w-0 w-full flex-1 touch-pan-y space-y-1 overflow-y-auto overflow-x-hidden overscroll-y-contain px-2 py-2 [-webkit-overflow-scrolling:touch] sm:px-2.5 sm:py-2.5"
                    >
                        <button
                            v-for="conversation in filteredConversations"
                            :key="conversation.id"
                            type="button"
                            class="group flex w-full gap-3 rounded-2xl px-2.5 py-3 text-start transition-all duration-200 sm:gap-3.5 sm:px-3 sm:py-3.5"
                            :class="
                                activeConversationId === conversation.id
                                    ? 'bg-brand-50 ring-2 ring-brand-500/25 shadow-md shadow-brand-900/[0.07]'
                                    : 'hover:bg-white/90 hover:shadow-sm'
                            "
                            @click="selectConversation(conversation.id)"
                        >
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-2xl text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-[52px] sm:w-[52px]"
                                    :class="`bg-gradient-to-br ${channelMeta(conversation.contact?.channel).avatarClass}`"
                                >
                                    {{ initials(conversation.contact?.name, conversation.contact?.phone) }}
                                </div>
                                <span
                                    class="absolute -bottom-1 -end-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-white shadow-md"
                                    :title="channelMeta(conversation.contact?.channel).label"
                                >
                                    <!-- WhatsApp -->
                                    <svg
                                        v-if="!isInstagramChannel(conversation.contact?.channel)"
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                    </svg>
                                    <!-- Instagram -->
                                        <svg
                                        v-else
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <defs>
                                            <linearGradient :id="'igGradList-'+conversation.id" x1="0%" y1="100%" x2="100%" y2="0%">
                                                <stop offset="0%" style="stop-color:#f09433" />
                                                <stop offset="50%" style="stop-color:#e6683c" />
                                                <stop offset="100%" style="stop-color:#bc1888" />
                                            </linearGradient>
                                        </defs>
                                        <path :fill="'url(#igGradList-'+conversation.id+')'" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-[13px] font-semibold leading-tight text-slate-900 sm:text-sm">
                                        {{ conversation.contact?.name || contactSubtitle(conversation.contact) }}
                                    </p>
                                    <div class="flex shrink-0 flex-col items-end gap-0.5">
                                        <time
                                            class="text-[10px] font-medium tabular-nums text-slate-400 sm:text-[11px]"
                                            :datetime="conversation.updated_at"
                                        >{{ formatRelative(conversation.updated_at) }}</time>
                                        <span
                                            v-if="conversation.unread_count"
                                            class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white shadow-sm"
                                        >
                                            {{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}
                                        </span>
                                    </div>
                                </div>
                                <p class="mt-0.5 line-clamp-2 text-[12px] leading-snug text-slate-600 sm:text-[13px]">
                                    {{ conversation.latest_message_preview || 'لا توجد رسائل بعد' }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="statusClass(conversation.status)"
                                    >
                                        {{ statusLabel(conversation.status) }}
                                    </span>
                                </div>
                            </div>
                        </button>
                        <div
                            v-if="!filteredConversations?.length"
                            class="flex flex-col items-center justify-center px-4 py-14 text-center"
                        >
                            <div class="rounded-2xl bg-slate-100/80 p-4 text-slate-400">
                                <svg class="mx-auto h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <p class="mt-3 text-sm font-medium text-slate-600">لا توجد محادثات مطابقة</p>
                            <p class="mt-1 max-w-[260px] text-xs text-slate-500">
                                جرّب البحث أو تصفية القناة (واتساب / إنستغرام). تُنشأ المحادثات تلقائياً عند وصول رسائل.
                            </p>
                        </div>
                    </div>
                </aside>

                <section
                    :class="[
                        'flex h-full min-h-0 min-w-0 w-full max-w-full flex-col overflow-hidden bg-[#e5ddd5]',
                        mobilePanel === 'list' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <template v-if="activeConversation">
                        <header class="flex shrink-0 items-center gap-2 border-b border-slate-200/60 bg-[#f0f2f5] px-2 py-2.5 shadow-sm sm:gap-3 sm:px-4 sm:py-3">
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 lg:hidden"
                                aria-label="عودة للقائمة"
                                @click="showConversationList"
                            >
                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <div
                                class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-md ring-2 ring-white sm:h-12 sm:w-12 sm:text-base"
                                :class="`bg-gradient-to-br ${channelMeta(activeConversation.contact?.channel).avatarClass}`"
                            >
                                {{ initials(activeConversation.contact?.name, activeConversation.contact?.phone) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-[15px] font-bold text-slate-900 sm:text-lg">
                                        {{ contactDisplayName(activeConversation.contact) }}
                                    </h2>
                                    <span
                                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-200/80 sm:text-[11px]"
                                    >
                                        <svg
                                            v-if="!isInstagramChannel(activeConversation.contact?.channel)"
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <defs>
                                                <linearGradient id="igGradHead" x1="0%" y1="100%" x2="100%" y2="0%">
                                                    <stop offset="0%" style="stop-color:#f09433" />
                                                    <stop offset="50%" style="stop-color:#e6683c" />
                                                    <stop offset="100%" style="stop-color:#bc1888" />
                                                </linearGradient>
                                            </defs>
                                            <path fill="url(#igGradHead)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                        </svg>
                                        {{ channelMeta(activeConversation.contact?.channel).label }}
                                    </span>
                                    <span
                                        class="hidden rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline-flex md:hidden lg:inline-flex"
                                        :class="statusClass(activeConversation.status)"
                                    >
                                        {{ statusLabel(activeConversation.status) }}
                                    </span>
                                </div>
                                <p dir="ltr" class="truncate text-[11px] text-slate-500 sm:text-sm">
                                    {{ contactSubtitle(activeConversation.contact) }}
                                </p>
                            </div>
                            <button
                                v-if="can_delete_outside_contacts && activeConversation.contact?.id"
                                type="button"
                                class="hidden h-10 shrink-0 items-center gap-1.5 rounded-xl border border-rose-200/90 bg-rose-50 px-3 text-xs font-semibold text-rose-800 transition hover:bg-rose-100 disabled:opacity-50 sm:flex"
                                :disabled="deleteContactProcessing"
                                @click="deleteActiveContact"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                حذف
                            </button>
                            <button
                                type="button"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200/80 bg-white text-slate-600 shadow-sm lg:hidden"
                                :aria-expanded="conversationSettingsOpen"
                                aria-label="إعدادات المحادثة"
                                @click="conversationSettingsOpen = !conversationSettingsOpen"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                </svg>
                            </button>
                        </header>

                        <div
                            class="shrink-0 border-b border-slate-200/40 bg-[#f0f2f5]/95 px-3 py-3 backdrop-blur-sm sm:px-4"
                            :class="{ 'hidden lg:block': !conversationSettingsOpen }"
                        >
                            <div class="grid gap-3 sm:grid-cols-2 lg:gap-4">
                                <div>
                                    <InputLabel for="conversation_status" value="حالة المحادثة" class="text-[11px] font-semibold text-slate-600" />
                                    <select
                                        id="conversation_status"
                                        v-model="conversationForm.status"
                                        class="mt-1.5 block h-11 w-full rounded-xl border-slate-200/90 bg-white text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500/20"
                                        @change="submitConversationMeta"
                                    >
                                        <option v-for="status in conversation_statuses" :key="`conv-status-${status.value}`" :value="status.value">
                                            {{ status.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="conversation_assignee" value="الموظف المسؤول" class="text-[11px] font-semibold text-slate-600" />
                                    <select
                                        id="conversation_assignee"
                                        v-model="conversationForm.assigned_user_id"
                                        class="mt-1.5 block h-11 w-full rounded-xl border-slate-200/90 bg-white text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500/20"
                                        @change="submitConversationMeta"
                                    >
                                        <option :value="null">— غير محدد —</option>
                                        <option v-for="user in users" :key="`conv-user-${user.id}`" :value="user.id">{{ user.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="can_delete_outside_contacts && activeConversation.contact?.id" class="mt-3 lg:hidden">
                                <SecondaryButton
                                    class="h-10 w-full justify-center rounded-xl border-rose-200 text-rose-800 hover:bg-rose-50"
                                    :disabled="deleteContactProcessing"
                                    @click="deleteActiveContact"
                                >
                                    حذف جهة الاتصال
                                </SecondaryButton>
                            </div>
                        </div>

                        <!-- محادثة: اتجاه LTR لمحاذاة فقاعات مثل تطبيقات الدردشة العالمية -->
                        <div
                            ref="messagesEl"
                            dir="ltr"
                            class="outside-thread outside-scroll flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overscroll-y-contain px-2 py-3 touch-pan-y [-webkit-overflow-scrolling:touch] sm:px-4 sm:py-4"
                            :style="threadBgStyle"
                        >
                            <template v-for="item in messageTimeline" :key="item.key">
                                <div
                                    v-if="item.kind === 'date'"
                                    class="flex justify-center py-2"
                                >
                                    <span class="rounded-lg bg-white/95 px-3 py-1 text-[11px] font-semibold text-slate-600 shadow-sm ring-1 ring-black/5">
                                        {{ item.label }}
                                    </span>
                                </div>
                                <div
                                    v-else
                                    class="flex w-full max-w-full items-end gap-2"
                                    :class="item.message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                                >
                                    <!-- وارد: أفاتار الطرف + فقاعة -->
                                    <template v-if="item.message.direction !== 'outbound'">
                                        <div
                                            class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white shadow-md ring-2 ring-white sm:h-10 sm:w-10 sm:text-xs"
                                            :class="`bg-gradient-to-br ${channelMeta(item.message.channel).avatarClass}`"
                                        >
                                            {{ initials(activeConversation.contact?.name, activeConversation.contact?.phone) }}
                                        </div>
                                        <div class="max-w-[min(100%,18.5rem)] sm:max-w-[min(100%,24rem)]">
                                            <div
                                                class="mb-1 flex items-center gap-1.5"
                                                dir="rtl"
                                            >
                                                <span
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-white shadow-sm ring-1 ring-black/5"
                                                    :title="channelMeta(item.message.channel).label"
                                                >
                                                    <svg
                                                        v-if="!isInstagramChannel(item.message.channel)"
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        aria-hidden="true"
                                                    >
                                                        <path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                                    </svg>
                                                    <svg
                                                        v-else
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        aria-hidden="true"
                                                    >
                                                        <defs>
                                                            <linearGradient :id="'igBubbleIn-'+item.message.id" x1="0%" y1="100%" x2="100%" y2="0%">
                                                                <stop offset="0%" style="stop-color:#f09433" />
                                                                <stop offset="50%" style="stop-color:#e6683c" />
                                                                <stop offset="100%" style="stop-color:#bc1888" />
                                                            </linearGradient>
                                                        </defs>
                                                        <path :fill="'url(#igBubbleIn-'+item.message.id+')'" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                                    </svg>
                                                </span>
                                                <span class="text-[10px] font-semibold text-slate-600">{{ contactDisplayName(activeConversation.contact) }}</span>
                                            </div>
                                            <div
                                                class="rounded-2xl rounded-tl-sm border border-black/[0.06] bg-white px-3.5 py-2.5 text-[13px] leading-relaxed text-slate-900 shadow-sm sm:px-4 sm:py-3 sm:text-sm"
                                                dir="rtl"
                                            >
                                                <p class="whitespace-pre-wrap break-words">{{ item.message.body || 'رسالة بدون نص' }}</p>
                                                <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2 text-[10px] text-slate-500">
                                                    <span>وارد · {{ item.message.provider_status || '—' }}</span>
                                                </div>
                                                <p class="mt-1 text-[10px] tabular-nums text-slate-400">
                                                    {{ formatDt(item.message.created_at) }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- صادر: فقاعة + أفاتار الموظف -->
                                    <template v-else>
                                        <div class="max-w-[min(100%,18.5rem)] sm:max-w-[min(100%,24rem)]">
                                            <div
                                                class="mb-1 flex items-center justify-end gap-1.5"
                                                dir="rtl"
                                            >
                                                <span class="text-[10px] font-semibold text-slate-600">{{ selfName }}</span>
                                                <span
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-white shadow-sm ring-1 ring-black/5"
                                                    :title="isInstagramChannel(activeConversation.contact?.channel) ? 'Instagram' : 'WhatsApp'"
                                                >
                                                    <svg
                                                        v-if="!isInstagramChannel(activeConversation.contact?.channel)"
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        aria-hidden="true"
                                                    >
                                                        <path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                                    </svg>
                                                    <svg
                                                        v-else
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        aria-hidden="true"
                                                    >
                                                        <defs>
                                                            <linearGradient :id="'igOut-'+item.message.id" x1="0%" y1="100%" x2="100%" y2="0%">
                                                                <stop offset="0%" style="stop-color:#f09433" />
                                                                <stop offset="50%" style="stop-color:#e6683c" />
                                                                <stop offset="100%" style="stop-color:#bc1888" />
                                                            </linearGradient>
                                                        </defs>
                                                        <path :fill="'url(#igOut-'+item.message.id+')'" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <div
                                                class="rounded-2xl rounded-tr-sm bg-gradient-to-br from-[#d9fdd3] to-[#c8f5c0] px-3.5 py-2.5 text-[13px] leading-relaxed text-slate-900 shadow-sm ring-1 ring-black/[0.04] sm:px-4 sm:py-3 sm:text-sm"
                                                dir="rtl"
                                            >
                                                <p class="whitespace-pre-wrap break-words">{{ item.message.body || 'رسالة بدون نص' }}</p>
                                                <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-emerald-900/10 pt-2 text-[10px] text-emerald-900/70">
                                                    <span>صادر · {{ item.message.provider_status || '—' }}</span>
                                                    <button
                                                        v-if="item.message.provider_status === 'failed'"
                                                        type="button"
                                                        class="rounded-md bg-rose-500/15 px-2 py-0.5 font-semibold text-rose-800 hover:bg-rose-500/25"
                                                        @click="retryMessage(item.message.id)"
                                                    >
                                                        إعادة المحاولة
                                                    </button>
                                                </div>
                                                <p
                                                    v-if="item.message.provider_error"
                                                    class="mt-1 text-[10px] font-medium text-rose-700"
                                                >
                                                    {{ item.message.provider_error }}
                                                </p>
                                                <p class="mt-1 text-[10px] tabular-nums text-emerald-900/50">
                                                    {{ formatDt(item.message.created_at) }}
                                                </p>
                                            </div>
                                        </div>
                                        <img
                                            :src="selfAvatarUrl"
                                            :alt="selfName"
                                            class="h-9 w-9 shrink-0 rounded-full object-cover shadow-md ring-2 ring-white sm:h-10 sm:w-10"
                                        >
                                    </template>
                                </div>
                            </template>
                            <p
                                v-if="!activeConversation.messages?.length"
                                dir="rtl"
                                class="flex flex-1 flex-col items-center justify-center py-16 text-center text-sm text-slate-600"
                            >
                                لا توجد رسائل بعد. ابدأ من الأسفل.
                            </p>
                        </div>

                        <div
                            class="shrink-0 border-t border-slate-200/70 bg-[#f0f2f5] px-2 py-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] shadow-[0_-4px_24px_-2px_rgba(15,23,42,0.06)] sm:px-4 sm:py-3"
                        >
                            <form class="flex flex-col gap-2 sm:gap-2.5" @submit.prevent="submitMessage">
                                <div class="relative flex items-end gap-2 rounded-[1.35rem] border border-slate-200/90 bg-white p-1.5 shadow-sm focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-500/15 sm:p-2">
                                    <textarea
                                        ref="composerEl"
                                        v-model="messageForm.body"
                                        rows="1"
                                        required
                                        dir="rtl"
                                        class="max-h-36 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-transparent px-3 py-2.5 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 sm:min-h-[48px] sm:text-sm"
                                        placeholder="رسالة…"
                                        @keydown.ctrl.enter.prevent="submitMessage"
                                        @keydown.meta.enter.prevent="submitMessage"
                                    />
                                    <PrimaryButton
                                        type="submit"
                                        class="mb-0.5 h-10 shrink-0 rounded-xl px-4 text-xs font-bold shadow-md sm:h-11 sm:rounded-2xl sm:px-5 sm:text-sm"
                                        :disabled="messageForm.processing"
                                    >
                                        <span class="hidden sm:inline">إرسال</span>
                                        <svg class="h-5 w-5 sm:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </PrimaryButton>
                                </div>
                                <InputError :message="messageForm.errors.body" />
                                <div class="flex snap-x snap-mandatory gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                    <button
                                        type="button"
                                        class="snap-start shrink-0 rounded-full border border-slate-200/90 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/50 sm:text-xs"
                                        @click="useTemplate('مرحبًا، نود متابعة طلبكم اليوم.')"
                                    >
                                        متابعة
                                    </button>
                                    <button
                                        type="button"
                                        class="snap-start shrink-0 rounded-full border border-slate-200/90 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/50 sm:text-xs"
                                        @click="useTemplate('تذكير سريع: يرجى تزويدنا بمبيعات اليوم.')"
                                    >
                                        تذكير مبيعات
                                    </button>
                                    <button
                                        type="button"
                                        class="snap-start shrink-0 rounded-full border border-slate-200/90 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/50 sm:text-xs"
                                        @click="useTemplate('شكرًا لتواصلكم، سيتم الرد من الفريق خلال وقت قصير.')"
                                    >
                                        رد سريع
                                    </button>
                                </div>
                                <p class="hidden text-[10px] text-slate-500 sm:block" dir="rtl">
                                    <kbd class="rounded border border-slate-200 bg-white px-1 font-mono text-[10px]">Ctrl</kbd>
                                    +
                                    <kbd class="rounded border border-slate-200 bg-white px-1 font-mono text-[10px]">Enter</kbd>
                                    للإرسال
                                </p>
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
                            <p class="mt-5 text-lg font-bold text-slate-900" dir="rtl">صندوق وارد موحّد</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600" dir="rtl">
                                اختر محادثة من القائمة. تُفتح المحادثات تلقائياً عند وصول رسائل من واتساب أو إنستغرام.
                            </p>
                            <SecondaryButton
                                type="button"
                                class="mt-6 w-full justify-center rounded-xl py-3 lg:hidden"
                                @click="showConversationList"
                            >
                                عرض المحادثات
                            </SecondaryButton>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
