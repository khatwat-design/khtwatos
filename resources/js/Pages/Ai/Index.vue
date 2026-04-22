<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    conversations: Array,
    selectedConversationId: Number,
    messages: Array,
});

const page = usePage();
const currentUserName = computed(() => page.props.auth?.user?.name || 'أنت');
const conversationsState = ref([...(props.conversations || [])]);
const selectedConversationId = ref(props.selectedConversationId || null);
const messages = ref(
    (props.messages || []).length
        ? [...props.messages]
        : [
            {
                id: 1,
                role: 'assistant',
                content: 'أنا خطوة. كيف أقدر أساعدك اليوم؟',
            },
        ],
);
const input = ref('');
const sending = ref(false);
const loadingConversation = ref(false);
const error = ref('');
const messagesContainer = ref(null);

function touchConversationTop(conversation) {
    const normalized = {
        id: conversation.id,
        title: conversation.title || 'محادثة جديدة',
        last_message_at: conversation.last_message_at || null,
    };

    const idx = conversationsState.value.findIndex((c) => c.id === normalized.id);
    if (idx >= 0) {
        conversationsState.value.splice(idx, 1);
    }
    conversationsState.value.unshift(normalized);
}

function currentConversationTitle() {
    const current = conversationsState.value.find((c) => c.id === selectedConversationId.value);
    return current?.title || 'محادثة جديدة';
}

function scrollToBottom() {
    const el = messagesContainer.value;
    if (!el) {
        return;
    }
    el.scrollTop = el.scrollHeight;
}

async function createConversation() {
    if (sending.value || loadingConversation.value) {
        return;
    }

    loadingConversation.value = true;
    error.value = '';
    try {
        const response = await window.axios.post(route('ai.conversations.store'), {}, {
            headers: { Accept: 'application/json' },
        });
        const conversation = response?.data?.conversation;
        if (!conversation?.id) {
            throw new Error('Invalid conversation');
        }

        selectedConversationId.value = conversation.id;
        touchConversationTop(conversation);
        messages.value = [
            {
                id: Date.now(),
                role: 'assistant',
                content: 'محادثة جديدة بدأت الآن. اكتب سؤالك.',
            },
        ];
        input.value = '';
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذر إنشاء محادثة جديدة.';
    } finally {
        loadingConversation.value = false;
    }
}

async function loadConversation(conversationId) {
    if (!conversationId || sending.value || loadingConversation.value) {
        return;
    }

    loadingConversation.value = true;
    error.value = '';
    try {
        const response = await window.axios.get(route('ai.conversations.show', conversationId), {
            headers: { Accept: 'application/json' },
        });

        const conversation = response?.data?.conversation;
        selectedConversationId.value = conversation?.id || null;
        touchConversationTop(conversation || { id: conversationId, title: 'محادثة جديدة' });
        messages.value = (response?.data?.messages || []).length
            ? [...response.data.messages]
            : [
                {
                    id: Date.now(),
                    role: 'assistant',
                    content: 'هذه المحادثة فارغة حالياً. اكتب رسالتك الأولى.',
                },
            ];
        await nextTick();
        scrollToBottom();
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذر تحميل المحادثة.';
    } finally {
        loadingConversation.value = false;
    }
}

async function sendMessage() {
    const text = String(input.value || '').trim();
    if (!text || sending.value || loadingConversation.value) {
        return;
    }

    error.value = '';
    const userMessage = {
        id: Date.now(),
        role: 'user',
        content: text,
    };
    messages.value.push(userMessage);
    input.value = '';
    await nextTick();
    scrollToBottom();

    sending.value = true;
    try {
        const response = await window.axios.post(
            route('ai.chat'),
            {
                conversation_id: selectedConversationId.value,
                message: text,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );

        const assistantMessage = response?.data?.message;
        const conversation = response?.data?.conversation;
        if (!assistantMessage?.content) {
            throw new Error('No reply');
        }

        if (conversation?.id) {
            selectedConversationId.value = conversation.id;
            touchConversationTop(conversation);
        }

        messages.value.push(assistantMessage);
        await nextTick();
        scrollToBottom();
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذر الوصول إلى خطوة حالياً.';
    } finally {
        sending.value = false;
    }
}

nextTick(() => scrollToBottom());
</script>

<template>
    <Head title="خطوة" />

    <AuthenticatedLayout>
        <template #title>خطوة</template>

        <div class="mx-auto max-w-5xl space-y-4">
            <div class="overflow-hidden rounded-2xl bg-gradient-to-l from-black to-neutral-800 p-4 text-white shadow ring-1 ring-black/10 md:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-lg font-black">خطوة</h2>
                    <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto">
                        <select
                            class="w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-sm text-white shadow-sm focus:ring-2 focus:ring-white/40 sm:min-w-72"
                            :value="selectedConversationId || ''"
                            :disabled="loadingConversation || sending"
                            @change="loadConversation($event.target.value ? Number($event.target.value) : null)"
                        >
                            <option value="" class="text-gray-900">اختر محادثة</option>
                            <option
                                v-for="conversation in conversationsState"
                                :key="`conv-${conversation.id}`"
                                :value="conversation.id"
                                class="text-gray-900"
                            >
                                {{ conversation.title }}
                            </option>
                        </select>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/20 disabled:opacity-50"
                            :disabled="loadingConversation || sending"
                            @click="createConversation"
                        >
                            محادثة جديدة
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-3 shadow ring-1 ring-gray-200 md:p-4">
                <div class="mb-2 text-xs font-semibold text-gray-500">
                    {{ currentConversationTitle() }}
                </div>
                <div ref="messagesContainer" class="h-[52vh] space-y-3 overflow-y-auto rounded-xl bg-gray-50 p-3 md:h-[56vh]">
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        :class="[
                            'flex',
                            msg.role === 'user' ? 'justify-end' : 'justify-start',
                        ]"
                    >
                        <div
                            :class="[
                                'max-w-[90%] rounded-2xl px-3 py-2 text-sm shadow-sm ring-1',
                                msg.role === 'user'
                                    ? 'bg-brand-50 text-gray-800 ring-brand-100'
                                    : 'bg-white text-gray-800 ring-gray-200',
                            ]"
                        >
                            <p class="mb-1 text-[11px] font-semibold text-gray-500">
                                {{ msg.role === 'user' ? currentUserName : 'خطوة' }}
                            </p>
                            <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                        </div>
                    </div>
                    <p v-if="loadingConversation" class="text-xs text-gray-500">جاري تحميل المحادثة...</p>
                    <p v-else-if="sending" class="text-xs text-gray-500">خطوة يكتب الآن...</p>
                </div>

                <div class="mt-3 space-y-2">
                    <textarea
                        v-model="input"
                        rows="3"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm"
                        placeholder="اكتب رسالتك إلى خطوة..."
                        :disabled="sending || loadingConversation"
                        @keydown.enter.exact.prevent="sendMessage"
                    />

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
                        <div class="flex items-center gap-2 sm:ms-auto">
                            <PrimaryButton type="button" :disabled="sending || loadingConversation || !input.trim()" @click="sendMessage">
                                {{ sending ? 'جاري الإرسال...' : 'إرسال إلى خطوة' }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

