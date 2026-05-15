<script setup>
import ChatUserAvatar from '@/Components/Chat/ChatUserAvatar.vue';
import { useEmployeeCall } from '@/composables/useEmployeeCall.js';
import { computed, ref, watch } from 'vue';

const {
    phase,
    contactPerson,
    call,
    error,
    isMuted,
    isVideoOff,
    localStream,
    remoteStream,
    callDuration,
    isActive,
    isIncoming,
    isOutgoing,
    isVideoCall,
    formatDuration,
    acceptCall,
    rejectCall,
    endCall,
    cancelOutgoing,
    toggleMute,
    toggleVideo,
    switchToVideo,
    switchToVoice,
    settleIdle,
} = useEmployeeCall();

const localVideoRef = ref(null);
const remoteVideoRef = ref(null);

const showRingingUi = computed(() => isIncoming.value || isOutgoing.value);
const showActiveControls = computed(() => phase.value === 'active' || phase.value === 'connecting');

const statusLabel = computed(() => {
    if (isIncoming.value) {
        return call.value?.type === 'video' ? 'مكالمة فيديو واردة' : 'مكالمة صوتية واردة';
    }
    if (isOutgoing.value) {
        return 'جاري الاتصال…';
    }
    if (phase.value === 'connecting') {
        return 'جاري التوصيل…';
    }
    if (phase.value === 'active') {
        return formatDuration(callDuration.value);
    }
    return '';
});

const incomingHint = computed(() => {
    const name = contactPerson.value?.name || 'موظف';
    return `${name} يتصل بك`;
});

watch(localStream, (stream) => {
    if (localVideoRef.value) {
        localVideoRef.value.srcObject = stream;
    }
});

watch(remoteStream, (stream) => {
    if (remoteVideoRef.value) {
        remoteVideoRef.value.srcObject = stream;
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isActive"
            class="fixed inset-0 z-[200] flex flex-col bg-gradient-to-b from-slate-950 via-slate-900 to-brand-950 text-white"
            dir="rtl"
        >
            <!-- مكالمة نشطة بالفيديو -->
            <template v-if="isVideoCall && showActiveControls && !showRingingUi">
                <video
                    v-if="remoteStream"
                    ref="remoteVideoRef"
                    autoplay
                    playsinline
                    class="absolute inset-0 h-full w-full object-cover"
                />
                <div
                    v-if="localStream && !isVideoOff"
                    class="absolute bottom-40 end-4 z-10 h-36 w-28 overflow-hidden rounded-2xl border-2 border-white/40 shadow-2xl sm:h-44 sm:w-32"
                >
                    <video ref="localVideoRef" autoplay playsinline muted class="h-full w-full object-cover" />
                </div>
            </template>

            <!-- واجهة الرنين / الواردة / الصادرة -->
            <div class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 pb-36 pt-10">
                <div
                    v-if="showRingingUi"
                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                    aria-hidden="true"
                >
                    <span class="h-56 w-56 animate-ping rounded-full bg-brand-500/20 sm:h-72 sm:w-72" />
                    <span class="absolute h-44 w-44 animate-pulse rounded-full bg-brand-500/10 sm:h-56 sm:w-56" />
                </div>

                <div class="relative flex flex-col items-center text-center">
                    <ChatUserAvatar
                        :name="contactPerson?.name || 'موظف'"
                        :avatar-url="contactPerson?.avatar_url || ''"
                        size-class="h-32 w-32 sm:h-40 sm:w-40"
                        rounded-class="rounded-full"
                        gradient-class="from-brand-400 to-brand-700"
                        text-class="text-4xl sm:text-5xl"
                    />

                    <h2 class="mt-6 text-2xl font-bold sm:text-3xl">
                        {{ contactPerson?.name || 'موظف' }}
                    </h2>

                    <p v-if="isIncoming" class="mt-2 text-base text-white/80">
                        {{ incomingHint }}
                    </p>
                    <p v-else-if="isOutgoing" class="mt-2 text-base text-white/80">
                        في انتظار الرد…
                    </p>

                    <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-semibold text-brand-100">
                        <span v-if="call?.type === 'video'">📹 فيديو</span>
                        <span v-else>📞 صوت</span>
                        <span class="text-white/50">·</span>
                        <span>{{ statusLabel }}</span>
                    </p>

                    <p v-if="error" class="mt-4 max-w-xs text-sm text-rose-300">{{ error }}</p>
                </div>
            </div>

            <!-- أزرار التحكم -->
            <div class="relative z-20 shrink-0 border-t border-white/10 bg-black/50 px-6 py-8 backdrop-blur-lg">
                <!-- واردة: موافقة / رفض -->
                <div v-if="isIncoming" class="mx-auto flex max-w-md items-center justify-center gap-10">
                    <div class="flex flex-col items-center gap-2">
                        <button
                            type="button"
                            class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-rose-600 text-white shadow-xl shadow-rose-900/40 transition hover:scale-105 hover:bg-rose-500"
                            aria-label="رفض المكالمة"
                            @click="rejectCall"
                        >
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-white/90">رفض</span>
                    </div>

                    <div class="flex flex-col items-center gap-2">
                        <button
                            type="button"
                            class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-900/40 transition hover:scale-105 hover:bg-emerald-400"
                            aria-label="قبول المكالمة"
                            @click="acceptCall"
                        >
                            <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6.62 10.79a15.05 15.05 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 011 1V20a1 1 0 01-1 1C10.07 21 3 13.93 3 5a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.46.57 3.58a1 1 0 01-.24 1.01l-2.2 2.2z" />
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-white/90">موافقة</span>
                    </div>
                </div>

                <!-- صادرة: إلغاء -->
                <div v-else-if="isOutgoing" class="mx-auto flex flex-col items-center gap-3">
                    <button
                        type="button"
                        class="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-rose-600 text-white shadow-xl shadow-rose-900/40 transition hover:scale-105"
                        aria-label="إلغاء الاتصال"
                        @click="cancelOutgoing"
                    >
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-white/80">إلغاء</span>
                </div>

                <!-- نشطة -->
                <div v-else class="mx-auto flex max-w-lg flex-col items-center gap-4">
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <button
                            type="button"
                            class="rounded-full px-4 py-2 text-xs font-bold transition"
                            :class="isVideoCall ? 'bg-brand-500 text-white' : 'bg-white/15 text-white hover:bg-white/25'"
                            @click="switchToVideo"
                        >
                            📹 فيديو
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-4 py-2 text-xs font-bold transition"
                            :class="!isVideoCall ? 'bg-brand-500 text-white' : 'bg-white/15 text-white hover:bg-white/25'"
                            @click="switchToVoice"
                        >
                            📞 صوت
                        </button>
                    </div>

                    <div class="flex items-center gap-5">
                        <button
                            type="button"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-white/15 text-xl transition hover:bg-white/25"
                            :class="isMuted && 'ring-2 ring-rose-400'"
                            :aria-label="isMuted ? 'إلغاء كتم الصوت' : 'كتم الصوت'"
                            @click="toggleMute"
                        >
                            {{ isMuted ? '🔇' : '🎤' }}
                        </button>
                        <button
                            v-if="isVideoCall"
                            type="button"
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-white/15 text-xl transition hover:bg-white/25"
                            :class="isVideoOff && 'ring-2 ring-amber-400'"
                            aria-label="إيقاف الكاميرا"
                            @click="toggleVideo"
                        >
                            {{ isVideoOff ? '📷' : '📹' }}
                        </button>
                        <button
                            type="button"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-rose-600 text-2xl shadow-xl shadow-rose-900/50 transition hover:bg-rose-500"
                            aria-label="إنهاء المكالمة"
                            @click="endCall"
                        >
                            📵
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else-if="error"
            class="fixed bottom-4 start-4 end-4 z-[190] mx-auto max-w-md rounded-xl bg-rose-600 px-4 py-3 text-center text-sm font-semibold text-white shadow-lg sm:end-4 sm:start-auto"
        >
            {{ error }}
            <button type="button" class="ms-2 underline" @click="settleIdle()">إغلاق</button>
        </div>
    </Teleport>
</template>
