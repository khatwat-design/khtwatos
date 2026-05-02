<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    conversations: Array,
    users: Array,
    conversation_statuses: Array,
    metrics: Object,
});

/** على الهاتف: قائمة | محادثة — على lg يظهر العمودان معاً */
const mobilePanel = ref('list');
const activeConversationId = ref(props.conversations?.[0]?.id || null);
const conversationSearch = ref('');
const messagesEl = ref(null);
const composerEl = ref(null);
const page = usePage();

const contactForm = useForm({
    name: '',
    phone: '',
});

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

const filteredConversations = computed(() => {
    const keyword = conversationSearch.value.trim().toLowerCase();
    if (!keyword) {
        return props.conversations || [];
    }
    return (props.conversations || []).filter((conversation) => {
        const name = String(conversation.contact?.name || '').toLowerCase();
        const phone = String(conversation.contact?.phone || '').toLowerCase();
        const preview = String(conversation.latest_message_preview || '').toLowerCase();
        return name.includes(keyword) || phone.includes(keyword) || preview.includes(keyword);
    });
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
        conversationForm.status = value?.status || 'open';
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
});

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

function submitContact() {
    contactForm.post(route('outside.contacts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            contactForm.reset();
        },
    });
}

function submitMessage() {
    if (!activeConversation.value?.id) {
        return;
    }
    messageForm.post(route('outside.messages.store', activeConversation.value.id), {
        preserveScroll: true,
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
    });
}

function retryMessage(messageId) {
    if (!messageId || retryForm.processing) {
        return;
    }
    retryForm.post(route('outside.messages.retry', messageId), {
        preserveScroll: true,
    });
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
        return `منذ ${min} د`;
    }
    const h = Math.round(min / 60);
    if (h < 24) {
        return `منذ ${h} س`;
    }
    const d = Math.round(h / 24);
    return `منذ ${d} يوم`;
}

function initials(name, phone) {
    const n = String(name || '').trim();
    if (n.length >= 2) {
        return n.slice(0, 2);
    }
    const p = String(phone || '').replace(/\D/g, '');
    return p.slice(-2) || '?';
}

function statusLabel(status) {
    return props.conversation_statuses?.find((item) => item.value === status)?.label || status;
}

function statusClass(status) {
    if (status === 'open') {
        return 'bg-emerald-500/15 text-emerald-800 ring-1 ring-emerald-500/25';
    }
    if (status === 'pending') {
        return 'bg-amber-500/15 text-amber-900 ring-1 ring-amber-500/25';
    }
    if (status === 'qualified') {
        return 'bg-blue-500/15 text-blue-900 ring-1 ring-blue-500/25';
    }
    return 'bg-slate-500/10 text-slate-700 ring-1 ring-slate-400/20';
}

/** ~١ ثانية — قريب من الفوري بدون WebSockets */
const OUTSIDE_POLL_MS = 1000;

function formsBusy() {
    return (
        contactForm.processing ||
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
        only: ['conversations', 'metrics'],
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

onMounted(() => {
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
</script>

<template>
    <Head title="الخارج" />

    <AuthenticatedLayout>
        <template #title>الخارج</template>

        <div class="mx-auto flex max-w-[1680px] flex-col gap-3 px-1 sm:px-0">
            <!-- شريط المؤشرات -->
            <div
                class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] lg:grid lg:grid-cols-5 lg:gap-3 lg:overflow-visible [&::-webkit-scrollbar]:hidden"
            >
                <div
                    class="min-w-[140px] shrink-0 snap-start rounded-2xl border border-white/40 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-sm sm:min-w-0"
                >
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">المحادثات</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ metrics?.total_conversations ?? 0 }}</p>
                </div>
                <div
                    class="min-w-[140px] shrink-0 snap-start rounded-2xl border border-emerald-200/60 bg-emerald-50/90 px-4 py-3 shadow-sm backdrop-blur-sm sm:min-w-0"
                >
                    <p class="text-[11px] font-medium text-emerald-800/80">مفتوحة</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-800">{{ metrics?.open_conversations ?? 0 }}</p>
                </div>
                <div
                    class="min-w-[140px] shrink-0 snap-start rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-sm sm:min-w-0"
                >
                    <p class="text-[11px] font-medium text-slate-500">مغلقة</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-800">{{ metrics?.closed_conversations ?? 0 }}</p>
                </div>
                <div
                    class="min-w-[140px] shrink-0 snap-start rounded-2xl border border-blue-200/60 bg-blue-50/90 px-4 py-3 shadow-sm backdrop-blur-sm sm:min-w-0"
                >
                    <p class="text-[11px] font-medium text-blue-900/70">صادر ٧ أيام</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-blue-900">{{ metrics?.outbound_last_7_days ?? 0 }}</p>
                </div>
                <div
                    class="min-w-[140px] shrink-0 snap-start rounded-2xl border border-rose-200/60 bg-rose-50/90 px-4 py-3 shadow-sm backdrop-blur-sm sm:min-w-0"
                >
                    <p class="text-[11px] font-medium text-rose-900/70">فشل ٧ أيام</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-rose-900">{{ metrics?.failed_last_7_days ?? 0 }}</p>
                </div>
            </div>

            <p class="flex items-center gap-2 text-center text-[11px] text-slate-500 lg:text-start">
                <span class="relative flex h-2 w-2 shrink-0">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                </span>
                مزامنة سريعة مع واتساب (تحديث كل ثانية تقريباً، وفوري عند العودة للصفحة)
            </p>

            <div class="flex min-h-[min(720px,calc(100dvh-14rem))] flex-1 flex-col gap-3 lg:grid lg:min-h-[calc(100dvh-12rem)] lg:grid-cols-[minmax(300px,380px)_1fr] lg:gap-4">
                <!-- القائمة -->
                <aside
                    :class="[
                        'flex min-h-0 flex-col overflow-hidden rounded-2xl border border-white/30 bg-white/80 shadow-lg shadow-slate-900/5 backdrop-blur-md',
                        mobilePanel === 'chat' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <div class="border-b border-slate-200/80 bg-gradient-to-l from-brand-50/50 to-white px-4 py-3">
                        <h2 class="text-sm font-bold text-slate-900">المحادثات</h2>
                        <TextInput
                            v-model="conversationSearch"
                            class="mt-2 block w-full rounded-xl border-slate-200 text-sm"
                            placeholder="بحث سريع بالاسم أو الرقم…"
                        />
                    </div>

                    <div class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
                        <button
                            v-for="conversation in filteredConversations"
                            :key="conversation.id"
                            type="button"
                            class="flex w-full gap-3 rounded-2xl border px-3 py-3 text-start transition-all duration-150"
                            :class="
                                activeConversationId === conversation.id
                                    ? 'border-brand-400 bg-brand-50/90 shadow-md shadow-brand-900/10 ring-2 ring-brand-500/20'
                                    : 'border-slate-200/90 bg-white hover:border-slate-300 hover:bg-slate-50/80'
                            "
                            @click="selectConversation(conversation.id)"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-inner"
                            >
                                {{ initials(conversation.contact?.name, conversation.contact?.phone) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate font-semibold text-slate-900">
                                        {{ conversation.contact?.name || conversation.contact?.phone }}
                                    </p>
                                    <span
                                        v-if="conversation.unread_count"
                                        class="shrink-0 rounded-full bg-brand-600 px-2 py-0.5 text-[11px] font-bold text-white"
                                    >
                                        {{ conversation.unread_count }}
                                    </span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="statusClass(conversation.status)"
                                    >
                                        {{ statusLabel(conversation.status) }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">{{ formatRelative(conversation.updated_at) }}</span>
                                </div>
                                <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-600">
                                    {{ conversation.latest_message_preview || 'لا توجد رسائل بعد' }}
                                </p>
                            </div>
                        </button>
                        <p v-if="!filteredConversations?.length" class="py-8 text-center text-sm text-slate-500">لا توجد نتائج.</p>
                    </div>

                    <div class="border-t border-slate-200/80 bg-slate-50/80 p-4">
                        <p class="mb-2 text-xs font-semibold text-slate-700">إضافة جهة</p>
                        <div v-if="page.props.flash?.success" class="mb-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                            {{ page.props.flash.success }}
                        </div>
                        <div v-if="page.props.flash?.error" class="mb-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                            {{ page.props.flash.error }}
                        </div>
                        <form class="space-y-2" @submit.prevent="submitContact">
                            <TextInput v-model="contactForm.name" class="block w-full rounded-xl text-sm" placeholder="الاسم (اختياري)" />
                            <InputError :message="contactForm.errors.name" />
                            <TextInput
                                v-model="contactForm.phone"
                                dir="ltr"
                                class="block w-full rounded-xl text-sm"
                                placeholder="+9647…"
                                required
                            />
                            <InputError :message="contactForm.errors.phone" />
                            <PrimaryButton class="w-full justify-center rounded-xl py-2.5 text-sm" :disabled="contactForm.processing">
                                حفظ وفتح محادثة
                            </PrimaryButton>
                        </form>
                    </div>
                </aside>

                <!-- المحادثة -->
                <section
                    :class="[
                        'flex min-h-0 min-h-[70dvh] flex-col overflow-hidden rounded-2xl border border-white/30 bg-white/85 shadow-xl shadow-slate-900/10 backdrop-blur-md lg:min-h-0',
                        mobilePanel === 'list' ? 'hidden lg:flex' : 'flex',
                    ]"
                >
                    <template v-if="activeConversation">
                        <!-- رأس المحادثة -->
                        <header class="flex shrink-0 items-center gap-3 border-b border-slate-200/90 bg-gradient-to-l from-slate-50 to-white px-3 py-3 sm:px-4">
                            <button
                                type="button"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm lg:hidden"
                                aria-label="عودة للقائمة"
                                @click="showConversationList"
                            >
                                <svg class="h-5 w-5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-md"
                            >
                                {{ initials(activeConversation.contact?.name, activeConversation.contact?.phone) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-base font-bold text-slate-900 sm:text-lg">
                                    {{ activeConversation.contact?.name || 'بدون اسم' }}
                                </h2>
                                <p dir="ltr" class="truncate text-xs text-slate-500 sm:text-sm">{{ activeConversation.contact?.phone }}</p>
                            </div>
                            <span
                                class="hidden shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold sm:inline-flex"
                                :class="statusClass(activeConversation.status)"
                            >
                                {{ statusLabel(activeConversation.status) }}
                            </span>
                        </header>

                        <!-- إعدادات المحادثة -->
                        <div class="grid shrink-0 gap-2 border-b border-slate-100 bg-slate-50/50 px-3 py-3 sm:grid-cols-2 sm:px-4">
                            <div>
                                <InputLabel for="conversation_status" value="حالة المحادثة" class="text-[11px]" />
                                <select
                                    id="conversation_status"
                                    v-model="conversationForm.status"
                                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    @change="submitConversationMeta"
                                >
                                    <option v-for="status in conversation_statuses" :key="`conv-status-${status.value}`" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="conversation_assignee" value="الموظف المسؤول" class="text-[11px]" />
                                <select
                                    id="conversation_assignee"
                                    v-model="conversationForm.assigned_user_id"
                                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                                    @change="submitConversationMeta"
                                >
                                    <option :value="null">—</option>
                                    <option v-for="user in users" :key="`conv-user-${user.id}`" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- فقاعات الرسائل -->
                        <div
                            ref="messagesEl"
                            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto bg-[linear-gradient(180deg,#f8fafc_0%,#f1f5f9_100%)] px-3 py-4 sm:px-5"
                        >
                            <div
                                v-for="message in activeConversation.messages"
                                :key="message.id"
                                class="flex w-full"
                                :class="message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                            >
                                <div
                                    class="max-w-[min(92%,28rem)] rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                                    :class="
                                        message.direction === 'outbound'
                                            ? 'rounded-br-md bg-gradient-to-br from-brand-600 to-brand-700 text-white'
                                            : 'rounded-bl-md border border-slate-200/80 bg-white text-slate-900'
                                    "
                                >
                                    <p class="whitespace-pre-wrap break-words">{{ message.body || 'رسالة بدون نص' }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-white/10 pt-2 text-[10px] opacity-90">
                                        <span
                                            :class="
                                                message.direction === 'outbound'
                                                    ? 'text-white/85'
                                                    : 'text-slate-500'
                                            "
                                        >
                                            {{ message.direction === 'outbound' ? 'صادر' : 'وارد' }}
                                            · {{ message.provider_status || '—' }}
                                        </span>
                                        <button
                                            v-if="message.direction === 'outbound' && message.provider_status === 'failed'"
                                            type="button"
                                            class="rounded-lg bg-white/20 px-2 py-0.5 font-medium hover:bg-white/30"
                                            @click="retryMessage(message.id)"
                                        >
                                            إعادة المحاولة
                                        </button>
                                    </div>
                                    <p v-if="message.provider_error" class="mt-1 text-[10px] text-rose-200">{{ message.provider_error }}</p>
                                    <p
                                        class="mt-1 text-[10px]"
                                        :class="message.direction === 'outbound' ? 'text-white/70' : 'text-slate-400'"
                                    >
                                        {{ formatDt(message.created_at) }}
                                    </p>
                                </div>
                            </div>
                            <p v-if="!activeConversation.messages?.length" class="py-12 text-center text-sm text-slate-500">
                                لا توجد رسائل بعد. أرسل أول رسالة من الأسفل.
                            </p>
                        </div>

                        <!-- إدخال الرسالة -->
                        <div class="shrink-0 border-t border-slate-200/90 bg-white p-3 sm:p-4">
                            <form class="space-y-2" @submit.prevent="submitMessage">
                                <textarea
                                    ref="composerEl"
                                    v-model="messageForm.body"
                                    rows="2"
                                    required
                                    class="block w-full resize-none rounded-2xl border-slate-200 px-4 py-3 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:rows-3"
                                    placeholder="اكتب رسالة للعميل…"
                                    @keydown.ctrl.enter.prevent="submitMessage"
                                    @keydown.meta.enter.prevent="submitMessage"
                                />
                                <InputError :message="messageForm.errors.body" />
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-white"
                                        @click="useTemplate('مرحبًا، نود متابعة طلبكم اليوم.')"
                                    >
                                        متابعة
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-white"
                                        @click="useTemplate('تذكير سريع: يرجى تزويدنا بمبيعات اليوم.')"
                                    >
                                        تذكير مبيعات
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-white"
                                        @click="useTemplate('شكرًا لتواصلكم، سيتم الرد من الفريق خلال وقت قصير.')"
                                    >
                                        رد سريع
                                    </button>
                                </div>
                                <div class="flex justify-between gap-2 sm:items-center">
                                    <p class="hidden text-[10px] text-slate-400 sm:block">Ctrl+Enter للإرسال السريع</p>
                                    <PrimaryButton
                                        class="ms-auto rounded-xl px-6 py-2.5 text-sm font-semibold shadow-md shadow-brand-900/15"
                                        :disabled="messageForm.processing"
                                    >
                                        إرسال
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </template>

                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center gap-4 bg-slate-50/80 px-6 py-16 text-center"
                    >
                        <div class="rounded-3xl bg-white p-6 shadow-lg ring-1 ring-slate-200/80">
                            <p class="text-base font-semibold text-slate-800">اختر محادثة</p>
                            <p class="mt-2 max-w-sm text-sm text-slate-500">من القائمة على اليسار، أو أضف جهة تواصل جديدة من أسفل القائمة.</p>
                            <button
                                type="button"
                                class="mt-6 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md lg:hidden"
                                @click="showConversationList"
                            >
                                عرض المحادثات
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
