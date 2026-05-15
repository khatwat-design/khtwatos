<script setup>
import ChatUserAvatar from '@/Components/Chat/ChatUserAvatar.vue';
import { computed } from 'vue';

const props = defineProps({
    msg: { type: Object, required: true },
    isMine: { type: Boolean, default: false },
    showSender: { type: Boolean, default: false },
    selfAvatarUrl: { type: String, default: '' },
    selfName: { type: String, default: 'أنت' },
});

const call = computed(() => props.msg?.call || {});
const label = computed(() => call.value.label || 'مكالمة');
const isVideo = computed(() => call.value.type === 'video');
const isCompleted = computed(() => call.value.status === 'completed');
const isRejected = computed(() => call.value.status === 'rejected');
const isMissed = computed(() => call.value.status === 'missed');
const isIncoming = computed(() => call.value.direction === 'incoming');

const accentClass = computed(() => {
    if (isCompleted.value) {
        return props.isMine ? 'text-emerald-200' : 'text-emerald-600';
    }
    if (isRejected.value) {
        return props.isMine ? 'text-amber-100' : 'text-amber-700';
    }
    if (isMissed.value && isIncoming.value) {
        return 'text-red-500';
    }
    if (isMissed.value) {
        return props.isMine ? 'text-red-200' : 'text-red-600';
    }
    return props.isMine ? 'text-white/90' : 'text-slate-600';
});

const bubbleClass = computed(() => {
    if (isMissed.value && isIncoming.value && !props.isMine) {
        return 'rounded-bs-md border border-red-200/80 bg-red-50 text-red-900 shadow-sm';
    }
    if (props.isMine) {
        return 'rounded-be-md bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-md shadow-brand-900/20';
    }
    return 'rounded-bs-md border border-slate-200/90 bg-white text-slate-900 shadow-sm';
});

const phoneFlipClass = computed(() => {
    if (props.isMine) {
        return isMissed.value || isRejected.value ? 'rotate-[135deg]' : '-rotate-45';
    }
    return isMissed.value ? '-rotate-[135deg]' : 'rotate-45';
});

function formatTime(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (!Number.isFinite(d.getTime())) {
        return '';
    }
    return d.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });
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
                    class="flex items-center gap-2.5 rounded-2xl px-3.5 py-2.5 sm:px-4 sm:py-3"
                    :class="bubbleClass"
                >
                    <span class="shrink-0" :class="[accentClass, phoneFlipClass]" aria-hidden="true">
                        <svg
                            v-if="isVideo"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
                            />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-[13px] font-semibold leading-snug sm:text-sm" :class="accentClass">
                            {{ label }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] tabular-nums opacity-80"
                            :class="isMine ? 'text-white/75' : 'text-slate-500'"
                        >
                            {{ formatTime(msg.created_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
