<script setup>
import ChatUserAvatar from '@/Components/Chat/ChatUserAvatar.vue';
import ChatVoicePlayer from '@/Components/Chat/ChatVoicePlayer.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { attachmentIsVoice } from '@/utils/chatVoiceAttachment.js';
import { computed, ref } from 'vue';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, default: false },
    viewKind: { type: String, default: 'team' },
    showSender: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    isEditing: { type: Boolean, default: false },
    editingBody: { type: String, default: '' },
    selfAvatarUrl: { type: String, default: '' },
    selfName: { type: String, default: 'أنت' },
});

const emit = defineEmits([
    'update:editingBody',
    'save-edit',
    'cancel-edit',
    'start-edit',
    'remove',
    'open-media',
    'open-actions',
    'reply',
]);

const hasForward = computed(() => Boolean(props.msg?.forward));
const hasReply = computed(() => Boolean(props.msg?.reply));
const isSticker = computed(
    () => Boolean(props.msg?.sticker?.url) || Boolean(props.msg?.sticker?.key),
);

const swipeX = ref(0);
const swiping = ref(false);
let touchStartX = 0;
let touchStartY = 0;

function onTouchStart(event) {
    if (props.isEditing || props.msg?.is_pending || props.msg?.kind === 'call') {
        return;
    }
    touchStartX = event.touches[0].clientX;
    touchStartY = event.touches[0].clientY;
    swiping.value = true;
}

function onTouchMove(event) {
    if (!swiping.value) {
        return;
    }
    const dx = event.touches[0].clientX - touchStartX;
    const dy = event.touches[0].clientY - touchStartY;
    if (Math.abs(dy) > Math.abs(dx) * 1.2) {
        swipeX.value = 0;
        return;
    }
    const max = 72;
    swipeX.value = Math.max(-max, Math.min(0, dx));
}

function onTouchEnd() {
    if (swipeX.value < -48) {
        emit('reply', props.msg);
    }
    swipeX.value = 0;
    swiping.value = false;
}
const isVoiceMessage = computed(() => attachmentIsVoice(props.msg?.attachment));
const forwardCaption = computed(() => {
    const f = props.msg?.forward;
    if (!f) {
        return '';
    }
    const parts = [];
    if (f.from_user_name) {
        parts.push(f.from_user_name);
    }
    if (f.from_context) {
        parts.push(f.from_context);
    }
    return parts.join(' · ');
});

const readReceiptLabel = computed(() => props.msg?.read_receipt?.label || '');
const readReceiptStatus = computed(() => props.msg?.read_receipt?.status || 'sent');
const showReadReceipt = computed(
    () => props.isMine && !props.msg?.is_pending && props.msg?.read_receipt && readReceiptLabel.value,
);

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

function userInitial(name) {
    return String(name || '?').trim().charAt(0) || '?';
}

function openImage() {
    if (!props.msg?.attachment?.is_image || !props.msg?.attachment?.url) {
        return;
    }
    emit('open-media', {
        url: props.msg.attachment.url,
        name: props.msg.attachment.name || 'صورة',
    });
}

function openFile() {
    if (!props.msg?.attachment?.url) {
        return;
    }
    if (props.msg.attachment.is_image) {
        openImage();
        return;
    }
    if (isVoiceMessage.value) {
        return;
    }
    window.open(props.msg.attachment.url, '_blank', 'noopener,noreferrer');
}

function onBubbleClick() {
    if (props.msg?.is_pending || props.isEditing) {
        return;
    }
    emit('open-actions', props.msg);
}
</script>

<template>
    <div class="flex w-full max-w-full" :class="isMine ? 'justify-end' : 'justify-start'">
        <div
            class="flex max-w-[min(100%,20rem)] items-end gap-2 sm:max-w-[min(100%,26rem)]"
            :class="isMine ? 'flex-row-reverse' : 'flex-row'"
        >
            <div v-if="!isMine" class="shrink-0 pb-1">
                <ChatUserAvatar
                    v-if="showSender"
                    :name="msg.user?.name"
                    :avatar-url="msg.user?.avatar_url || ''"
                />
                <div v-else class="h-8 w-8 sm:h-9 sm:w-9" aria-hidden="true" />
            </div>

            <ChatUserAvatar
                v-if="isMine"
                :name="selfName"
                :avatar-url="selfAvatarUrl"
                gradient-class="from-brand-500 to-brand-700"
            />

            <div class="min-w-0 flex-1" dir="rtl">
                <p v-if="showSender && !isMine" class="mb-1 px-1 text-[11px] font-semibold text-slate-600">
                    {{ msg.user?.name || 'عضو' }}
                </p>

                <div
                    class="relative cursor-pointer rounded-2xl px-3.5 py-2.5 text-[13px] leading-relaxed transition active:scale-[0.99] sm:px-4 sm:py-3 sm:text-sm"
                    :class="
                        isMine
                            ? 'rounded-be-md bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-md shadow-brand-900/20'
                            : 'rounded-bs-md border border-slate-200/90 bg-white text-slate-900 shadow-sm'
                    "
                    role="button"
                    tabindex="0"
                    :style="swipeX ? { transform: `translateX(${swipeX}px)` } : undefined"
                    @click="onBubbleClick"
                    @keydown.enter.prevent="onBubbleClick"
                    @touchstart.passive="onTouchStart"
                    @touchmove.passive="onTouchMove"
                    @touchend="onTouchEnd"
                    @touchcancel="onTouchEnd"
                >
                    <span
                        v-if="swipeX < -20"
                        class="pointer-events-none absolute end-full top-1/2 me-2 -translate-y-1/2 text-[10px] font-bold text-brand-600"
                    >
                        رد
                    </span>
                    <div
                        v-if="hasReply"
                        class="mb-2 rounded-lg border-s-2 px-2 py-1.5 text-[10px] leading-snug"
                        :class="
                            isMine
                                ? 'border-white/40 bg-white/10 text-white/95'
                                : 'border-slate-300 bg-slate-50 text-slate-700'
                        "
                    >
                        <p class="font-bold">{{ msg.reply.user_name }}</p>
                        <p class="mt-0.5 truncate opacity-90">{{ msg.reply.preview }}</p>
                    </div>
                    <div
                        v-if="hasForward"
                        class="mb-2 rounded-lg border-s-2 px-2 py-1.5 text-[10px] leading-snug"
                        :class="
                            isMine
                                ? 'border-white/40 bg-white/10 text-white/95'
                                : 'border-brand-400/70 bg-brand-50/80 text-brand-900'
                        "
                    >
                        <p class="font-bold">رسالة محوّلة</p>
                        <p v-if="forwardCaption" class="mt-0.5 opacity-90">{{ forwardCaption }}</p>
                    </div>

                    <div
                        v-if="viewKind === 'team' && !msg.is_pending && isEditing"
                        class="mb-2 space-y-2"
                    >
                        <textarea
                            :value="editingBody"
                            rows="3"
                            class="block w-full rounded-xl border-slate-200 text-sm text-slate-900 shadow-sm"
                            @input="emit('update:editingBody', $event.target.value)"
                            @click.stop
                        />
                        <div class="flex flex-wrap gap-2" @click.stop>
                            <PrimaryButton type="button" class="text-xs" @click.stop="emit('save-edit')">حفظ</PrimaryButton>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                @click.stop="emit('cancel-edit')"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>

                    <img
                        v-if="isSticker"
                        :src="msg.sticker.url"
                        :alt="msg.sticker.label || 'ملصق'"
                        class="mx-auto h-28 w-28 select-none object-contain sm:h-32 sm:w-32"
                        loading="lazy"
                        draggable="false"
                    />
                    <p v-else-if="msg.body" class="whitespace-pre-wrap break-words">{{ msg.body }}</p>

                    <p
                        v-if="msg.is_pending"
                        class="mt-1 inline-flex items-center gap-1.5 text-[10px]"
                        :class="isMine ? 'text-white/80' : 'text-brand-700'"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-pulse rounded-full"
                            :class="isMine ? 'bg-white' : 'bg-brand-600'"
                        />
                        جار الإرسال…
                    </p>

                    <div
                        v-if="msg.attachment"
                        class="mt-2 overflow-hidden rounded-xl ring-1"
                        :class="isMine ? 'bg-white/15 ring-white/20' : 'bg-slate-50 ring-slate-200/70'"
                    >
                        <ChatVoicePlayer
                            v-if="isVoiceMessage && msg.attachment.url"
                            :url="msg.attachment.url"
                            :is-mine="isMine"
                            class="p-1"
                            @click.stop
                        />

                        <button
                            v-else-if="msg.attachment.is_image && msg.attachment.url"
                            type="button"
                            class="group relative block w-full text-start"
                            @click.stop="openImage"
                        >
                            <img
                                :src="msg.attachment.url"
                                :alt="msg.attachment.name || 'صورة'"
                                class="max-h-56 w-full cursor-zoom-in object-cover transition group-hover:brightness-95 sm:max-h-64"
                                loading="lazy"
                            >
                            <span
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent px-2 py-2 text-[10px] font-semibold text-white"
                            >
                                اضغط للتكبير داخل التطبيق
                            </span>
                        </button>

                        <button
                            v-else-if="msg.attachment.url"
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2.5 text-start transition hover:opacity-90"
                            @click.stop="openFile"
                        >
                            <span
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/20 text-lg"
                                :class="!isMine && 'bg-brand-50 text-brand-700'"
                                aria-hidden="true"
                            >
                                📎
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-semibold" :class="isMine ? 'text-white' : 'text-brand-700'">
                                    {{ msg.attachment.name || 'مرفق' }}
                                </span>
                                <span class="mt-0.5 block text-[10px]" :class="isMine ? 'text-white/70' : 'text-slate-500'">
                                    {{ msg.attachment.mime || 'ملف' }} · {{ formatFileSize(msg.attachment.size) }}
                                </span>
                            </span>
                        </button>
                    </div>

                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px]"
                        :class="isMine ? 'text-white/75' : 'text-slate-500'"
                    >
                        <span>{{ formatDt(msg.created_at) }}<span v-if="msg.edited_at"> · معدّلة</span></span>
                        <template v-if="viewKind === 'team' && !msg.is_pending && canManage && !isEditing">
                            <button
                                type="button"
                                class="font-semibold hover:underline"
                                :class="isMine ? 'text-white' : 'text-brand-700'"
                                @click.stop="emit('start-edit')"
                            >
                                تعديل
                            </button>
                            <button
                                type="button"
                                class="font-semibold text-rose-400 hover:underline"
                                :class="!isMine && 'text-rose-600'"
                                @click.stop="emit('remove')"
                            >
                                حذف
                            </button>
                        </template>
                    </div>
                </div>

                <p
                    v-if="showReadReceipt"
                    class="mt-1 flex items-center justify-end gap-1 px-1 text-[10px] font-medium"
                    :class="readReceiptStatus === 'read' ? 'text-sky-600' : 'text-slate-500'"
                    :title="readReceiptLabel"
                >
                    <span class="tracking-tight" dir="ltr">{{ readReceiptStatus === 'read' ? '✓✓' : readReceiptStatus === 'partial' ? '✓✓' : '✓' }}</span>
                    <span>{{ readReceiptLabel }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
