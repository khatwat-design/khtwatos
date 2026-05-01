<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    conversations: Array,
    users: Array,
    conversation_statuses: Array,
    metrics: Object,
});

const activeConversationId = ref(props.conversations?.[0]?.id || null);
const conversationSearch = ref('');
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
    if (!keyword) return props.conversations || [];
    return (props.conversations || []).filter((conversation) => {
        const name = String(conversation.contact?.name || '').toLowerCase();
        const phone = String(conversation.contact?.phone || '').toLowerCase();
        const preview = String(conversation.latest_message_preview || '').toLowerCase();
        return name.includes(keyword) || phone.includes(keyword) || preview.includes(keyword);
    });
});

watch(
    activeConversation,
    (value) => {
        conversationForm.status = value?.status || 'open';
        conversationForm.assigned_user_id = value?.contact?.assigned_user_id || null;
    },
    { immediate: true },
);

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
        },
    });
}

function useTemplate(template) {
    messageForm.body = template;
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
    if (!messageId || retryForm.processing) return;
    retryForm.post(route('outside.messages.retry', messageId), {
        preserveScroll: true,
    });
}

function formatDt(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function statusLabel(status) {
    return props.conversation_statuses?.find((item) => item.value === status)?.label || status;
}

function statusClass(status) {
    if (status === 'open') return 'bg-emerald-100 text-emerald-700';
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    if (status === 'qualified') return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <Head title="الخارج" />

    <AuthenticatedLayout>
        <template #title>الخارج</template>

        <div class="mx-auto grid max-w-7xl gap-4 lg:grid-cols-[320px,1fr]">
            <aside class="rounded-2xl border border-white/25 bg-white/75 p-4 shadow">
                <div v-if="page.props.flash?.success" class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ page.props.flash.error }}
                </div>
                <h2 class="text-lg font-semibold text-gray-900">إضافة جهة تواصل</h2>
                <form class="mt-3 space-y-3" @submit.prevent="submitContact">
                    <div>
                        <InputLabel for="outside_contact_name" value="الاسم (اختياري)" />
                        <TextInput id="outside_contact_name" v-model="contactForm.name" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="contactForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="outside_contact_phone" value="رقم واتساب" />
                        <TextInput id="outside_contact_phone" v-model="contactForm.phone" dir="ltr" class="mt-1 block w-full" placeholder="+9647..." required />
                        <InputError class="mt-1" :message="contactForm.errors.phone" />
                    </div>
                    <PrimaryButton :disabled="contactForm.processing">حفظ وفتح محادثة</PrimaryButton>
                </form>

                <h3 class="mt-6 text-sm font-semibold text-gray-700">المحادثات</h3>
                <TextInput
                    v-model="conversationSearch"
                    class="mt-2 block w-full"
                    placeholder="بحث بالاسم أو الرقم..."
                />
                <div class="mt-2 max-h-[55vh] space-y-2 overflow-y-auto pe-1">
                    <button
                        v-for="conversation in filteredConversations"
                        :key="conversation.id"
                        type="button"
                        class="w-full rounded-xl border p-3 text-start transition hover:bg-slate-50"
                        :class="activeConversationId === conversation.id ? 'border-brand-300 bg-brand-50' : 'border-slate-200 bg-white'"
                        @click="activeConversationId = conversation.id"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate font-semibold text-gray-900">{{ conversation.contact?.name || conversation.contact?.phone }}</p>
                            <span v-if="conversation.unread_count" class="rounded-full bg-red-600 px-2 py-0.5 text-[11px] text-white">{{ conversation.unread_count }}</span>
                        </div>
                        <div class="mt-1">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusClass(conversation.status)">
                                {{ statusLabel(conversation.status) }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-gray-600">{{ conversation.latest_message_preview || 'لا توجد رسائل بعد' }}</p>
                    </button>
                    <p v-if="!filteredConversations?.length" class="text-xs text-gray-500">لا توجد نتائج مطابقة.</p>
                </div>
            </aside>

            <section class="rounded-2xl border border-white/25 bg-white/75 p-4 shadow">
                <div class="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                        <p class="text-gray-500">إجمالي المحادثات</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900">{{ metrics?.total_conversations || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                        <p class="text-gray-500">مفتوحة</p>
                        <p class="mt-1 text-lg font-semibold text-emerald-700">{{ metrics?.open_conversations || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                        <p class="text-gray-500">مغلقة</p>
                        <p class="mt-1 text-lg font-semibold text-slate-700">{{ metrics?.closed_conversations || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                        <p class="text-gray-500">صادر آخر 7 أيام</p>
                        <p class="mt-1 text-lg font-semibold text-blue-700">{{ metrics?.outbound_last_7_days || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs">
                        <p class="text-gray-500">فشل آخر 7 أيام</p>
                        <p class="mt-1 text-lg font-semibold text-rose-700">{{ metrics?.failed_last_7_days || 0 }}</p>
                    </div>
                </div>
                <template v-if="activeConversation">
                    <div class="border-b border-slate-200 pb-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">{{ activeConversation.contact?.name || 'بدون اسم' }}</h2>
                                <p class="text-sm text-gray-600">{{ activeConversation.contact?.phone }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusClass(activeConversation.status)">
                                {{ statusLabel(activeConversation.status) }}
                            </span>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div>
                                <InputLabel for="conversation_status" value="حالة المحادثة" />
                                <select
                                    id="conversation_status"
                                    v-model="conversationForm.status"
                                    class="mt-1 block w-full rounded-md border-gray-300"
                                    @change="submitConversationMeta"
                                >
                                    <option v-for="status in conversation_statuses" :key="`conv-status-${status.value}`" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="conversation_assignee" value="الموظف المسؤول" />
                                <select
                                    id="conversation_assignee"
                                    v-model="conversationForm.assigned_user_id"
                                    class="mt-1 block w-full rounded-md border-gray-300"
                                    @change="submitConversationMeta"
                                >
                                    <option :value="null">—</option>
                                    <option v-for="user in users" :key="`conv-user-${user.id}`" :value="user.id">{{ user.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 max-h-[52vh] space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3">
                        <div
                            v-for="message in activeConversation.messages"
                            :key="message.id"
                            class="max-w-[80%] rounded-xl px-3 py-2 text-sm"
                            :class="message.direction === 'outbound' ? 'ms-auto bg-brand-600 text-white' : 'bg-slate-100 text-gray-900'"
                        >
                            <p>{{ message.body || 'رسالة بدون نص' }}</p>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-[10px] opacity-80">{{ message.provider_status || 'sent' }}</span>
                                <button
                                    v-if="message.direction === 'outbound' && message.provider_status === 'failed'"
                                    type="button"
                                    class="rounded-md bg-white/20 px-2 py-0.5 text-[10px] hover:bg-white/30"
                                    @click="retryMessage(message.id)"
                                >
                                    إعادة محاولة
                                </button>
                            </div>
                            <p v-if="message.provider_error" class="mt-1 text-[10px] text-rose-100">{{ message.provider_error }}</p>
                            <p class="mt-1 text-[10px] opacity-75">{{ formatDt(message.created_at) }}</p>
                        </div>
                        <p v-if="!activeConversation.messages?.length" class="text-sm text-gray-500">لا توجد رسائل بعد.</p>
                    </div>

                    <form class="mt-3 space-y-2" @submit.prevent="submitMessage">
                        <TextInput
                            v-model="messageForm.body"
                            class="block w-full"
                            placeholder="اكتب رسالة للعميل..."
                            required
                        />
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="useTemplate('مرحبًا، نود متابعة طلبكم اليوم.')">
                                قالب متابعة
                            </button>
                            <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="useTemplate('تذكير سريع: يرجى تزويدنا بمبيعات اليوم.')">
                                قالب تذكير مبيعات
                            </button>
                            <button type="button" class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" @click="useTemplate('شكرًا لتواصلكم، سيتم الرد من الفريق خلال وقت قصير.')">
                                قالب رد سريع
                            </button>
                        </div>
                        <InputError :message="messageForm.errors.body" />
                        <div class="flex justify-end">
                            <PrimaryButton :disabled="messageForm.processing">إرسال</PrimaryButton>
                        </div>
                    </form>
                </template>
                <p v-else class="text-sm text-gray-500">اختر محادثة من القائمة أو أنشئ جهة تواصل جديدة.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

