<script setup>
import ChatRecordingWaveform from '@/Components/Chat/ChatRecordingWaveform.vue';
import InputError from '@/Components/InputError.vue';
import { pickRecorderMimeType, voiceFileFromBlob } from '@/utils/chatRecorder.js';
import { isVoiceFile } from '@/utils/chatVoiceAttachment.js';
import { useKeyboardViewportInset } from '@/composables/useKeyboardViewportInset.js';
import { isChatMobileViewport } from '@/utils/chatMobileViewport.js';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'اكتب رسالة…' },
    processing: { type: Boolean, default: false },
    attachment: { type: Object, default: null },
    bodyError: { type: String, default: '' },
    typingHint: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    /** عند false يعتمد الأب على visualViewport (شاشة دردشة جوال) */
    keyboardLift: { type: Boolean, default: true },
    /** أنماط إضافية من الأب (مثلاً safe-area ولوحة المفاتيح في الوضع الغامر) */
    footerStyle: { type: Object, default: null },
});

const emit = defineEmits([
    'update:modelValue',
    'submit',
    'typing',
    'attachment-change',
    'clear-attachment',
    'send-voice',
]);

const textareaRef = ref(null);
const { composerStyle: keyboardLiftStyle } = useKeyboardViewportInset(
    () => props.keyboardLift && isChatMobileViewport(),
);
const fileInputRef = ref(null);
const canSend = ref(false);
const isRecording = ref(false);
const recordSeconds = ref(0);
const recordError = ref('');
const waveformLevels = ref([]);

let mediaRecorder = null;
let recordStream = null;
let recordChunks = [];
let recordTimer = null;
let audioContext = null;
let analyser = null;
let analyserSource = null;
let waveformFrame = null;
let sendAfterStop = false;

const MAX_RECORD_SECONDS = 120;
const WAVEFORM_BARS = 28;

const isVoiceAttachment = computed(() => isVoiceFile(props.attachment));

const rootComposerStyle = computed(() => ({
    ...(props.footerStyle || {}),
    ...(keyboardLiftStyle.value || {}),
}));

const recordTimeLabel = computed(() => {
    const m = Math.floor(recordSeconds.value / 60);
    const s = recordSeconds.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});

watch(
    () => [props.modelValue, props.attachment, props.processing, props.disabled, isRecording.value],
    () => {
        canSend.value =
            !props.disabled &&
            !props.processing &&
            (isRecording.value ||
                String(props.modelValue || '').trim().length > 0 ||
                Boolean(props.attachment));
    },
    { immediate: true },
);

function resizeTextarea() {
    const el = textareaRef.value;
    if (!el) {
        return;
    }
    el.style.height = 'auto';
    const max = 128;
    el.style.height = `${Math.min(el.scrollHeight, max)}px`;
}

function onInput(event) {
    emit('update:modelValue', event.target.value);
    emit('typing');
    nextTick(resizeTextarea);
}

function onComposerFocus() {
    if (!props.keyboardLift) {
        return;
    }
    nextTick(() => {
        textareaRef.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
}

function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        trySubmit();
    }
}

function trySubmit() {
    if (props.disabled || props.processing) {
        return;
    }
    if (isRecording.value) {
        sendAfterStop = true;
        stopVoiceRecord();
        return;
    }
    if (!canSend.value) {
        return;
    }
    emit('submit');
    nextTick(() => {
        resizeTextarea();
    });
}

function openFilePicker() {
    fileInputRef.value?.click();
}

function onFileChange(event) {
    emit('attachment-change', event);
}

function cleanupRecordStream() {
    stopWaveformAnalyser();
    if (recordStream) {
        recordStream.getTracks().forEach((track) => track.stop());
        recordStream = null;
    }
}

function stopRecordTimer() {
    if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
    }
}

function stopWaveformAnalyser() {
    if (waveformFrame) {
        cancelAnimationFrame(waveformFrame);
        waveformFrame = null;
    }
    if (analyserSource) {
        try {
            analyserSource.disconnect();
        } catch {
            /* ignore */
        }
        analyserSource = null;
    }
    if (audioContext) {
        audioContext.close().catch(() => {});
        audioContext = null;
    }
    analyser = null;
    waveformLevels.value = [];
}

async function startWaveformAnalyser(stream) {
    stopWaveformAnalyser();
    if (typeof window === 'undefined') {
        return;
    }
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
        return;
    }

    try {
        audioContext = new AudioCtx();
        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;
        analyser.smoothingTimeConstant = 0.55;
        analyserSource = audioContext.createMediaStreamSource(stream);
        analyserSource.connect(analyser);

        const timeData = new Uint8Array(analyser.fftSize);

        const tick = () => {
            if (!analyser) {
                return;
            }
            analyser.getByteTimeDomainData(timeData);
            const levels = [];
            const segment = Math.floor(timeData.length / WAVEFORM_BARS);
            for (let i = 0; i < WAVEFORM_BARS; i += 1) {
                let sum = 0;
                const start = i * segment;
                for (let j = start; j < start + segment && j < timeData.length; j += 1) {
                    const sample = (timeData[j] - 128) / 128;
                    sum += sample * sample;
                }
                const rms = Math.sqrt(sum / segment);
                levels.push(Math.min(1, Math.max(0.12, rms * 3.2)));
            }
            waveformLevels.value = levels;
            waveformFrame = requestAnimationFrame(tick);
        };

        tick();
    } catch {
        waveformLevels.value = Array(WAVEFORM_BARS).fill(0.15);
    }
}

async function toggleVoiceRecord() {
    if (isRecording.value) {
        sendAfterStop = false;
        stopVoiceRecord();
        return;
    }
    await startVoiceRecord();
}

async function startVoiceRecord() {
    recordError.value = '';
    sendAfterStop = false;
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
        recordError.value = 'التسجيل الصوتي غير مدعوم على هذا الجهاز.';
        return;
    }
    if (typeof MediaRecorder === 'undefined') {
        recordError.value = 'المتصفح لا يدعم تسجيل الصوت.';
        return;
    }

    try {
        cleanupRecordStream();
        isRecording.value = true;
        recordSeconds.value = 0;
        waveformLevels.value = Array(WAVEFORM_BARS).fill(0.12);

        recordStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
            },
        });
        await startWaveformAnalyser(recordStream);

        const mimeType = pickRecorderMimeType();
        mediaRecorder = mimeType ? new MediaRecorder(recordStream, { mimeType }) : new MediaRecorder(recordStream);
        recordChunks = [];

        mediaRecorder.ondataavailable = (event) => {
            if (event.data?.size > 0) {
                recordChunks.push(event.data);
            }
        };

        mediaRecorder.onstop = () => {
            const recorderMime = mediaRecorder?.mimeType || mimeType || 'audio/webm';
            const blob = new Blob(recordChunks, { type: recorderMime.split(';')[0] });
            const shouldSend = sendAfterStop;
            sendAfterStop = false;
            cleanupRecordStream();
            mediaRecorder = null;
            recordChunks = [];

            if (!blob.size) {
                recordError.value = 'لم يُسجَّل صوت. تحقق من الميكروفون وحاول مرة أخرى.';
                return;
            }

            const file = voiceFileFromBlob(blob, recorderMime);

            if (shouldSend) {
                emit('send-voice', file);
                return;
            }

            emit('attachment-change', { target: { files: [file] } });
        };

        mediaRecorder.start(250);
        stopRecordTimer();
        recordTimer = setInterval(() => {
            recordSeconds.value += 1;
            if (recordSeconds.value >= MAX_RECORD_SECONDS) {
                sendAfterStop = true;
                stopVoiceRecord();
            }
        }, 1000);
    } catch {
        recordError.value = 'تعذّر الوصول للميكروفون. تحقق من الصلاحيات.';
        cleanupRecordStream();
        isRecording.value = false;
        stopRecordTimer();
    }
}

function stopVoiceRecord() {
    if (!isRecording.value) {
        return;
    }
    isRecording.value = false;
    stopRecordTimer();
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        try {
            mediaRecorder.requestData();
        } catch {
            /* ignore */
        }
        mediaRecorder.stop();
    } else {
        stopWaveformAnalyser();
        cleanupRecordStream();
    }
}

function cancelVoiceRecord() {
    recordError.value = '';
    sendAfterStop = false;
    isRecording.value = false;
    stopRecordTimer();
    stopWaveformAnalyser();
    recordChunks = [];
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.onstop = () => {
            cleanupRecordStream();
            mediaRecorder = null;
        };
        mediaRecorder.stop();
    } else {
        cleanupRecordStream();
        mediaRecorder = null;
    }
}

onMounted(() => {
    resizeTextarea();
});

onBeforeUnmount(() => {
    cancelVoiceRecord();
});

watch(
    () => props.modelValue,
    () => nextTick(resizeTextarea),
);
</script>

<template>
    <div
        class="team-chat-composer shrink-0 border-t border-slate-200/80 bg-white/95 backdrop-blur-xl supports-[backdrop-filter]:bg-white/88"
        :class="!footerStyle && 'max-lg:pb-[env(safe-area-inset-bottom,0px)]'"
        :style="rootComposerStyle"
        aria-label="كتابة رسالة"
    >
        <p
            v-if="typingHint"
            class="border-b border-slate-100/90 px-4 py-1.5 text-center text-[11px] font-medium text-brand-700"
            dir="rtl"
        >
            {{ typingHint }}
        </p>

        <div
            v-if="isRecording"
            class="border-b border-rose-100 bg-gradient-to-l from-rose-50/95 to-white px-3 py-3 sm:px-4"
            dir="rtl"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-500 opacity-60" />
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-rose-600" />
                    </span>
                    <span class="text-sm font-bold tabular-nums text-rose-800">{{ recordTimeLabel }}</span>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold text-rose-800 hover:bg-rose-100"
                    @click="cancelVoiceRecord"
                >
                    إلغاء
                </button>
            </div>
            <ChatRecordingWaveform class="mt-2" :levels="waveformLevels" :active="isRecording" />
            <p class="mt-2 text-center text-[10px] font-medium text-rose-700/90">
                اضغط زر الإرسال لإرسال الرسالة الصوتية مباشرة
            </p>
        </div>

        <div
            v-else-if="attachment"
            class="flex items-center gap-2 border-b border-slate-100/90 px-3 py-2 sm:px-4"
            dir="rtl"
        >
            <span
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm ring-1"
                :class="isVoiceAttachment ? 'bg-violet-50 text-violet-700 ring-violet-200/60' : 'bg-brand-50 text-brand-700 ring-brand-200/60'"
                aria-hidden="true"
            >
                {{ isVoiceAttachment ? '🎤' : '📎' }}
            </span>
            <span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-800">
                {{ isVoiceAttachment ? 'رسالة صوتية — اضغط إرسال' : attachment.name }}
            </span>
            <button
                type="button"
                class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-600 hover:bg-rose-50"
                @click="emit('clear-attachment')"
            >
                إزالة
            </button>
        </div>

        <form class="px-2 pt-2 sm:px-3 sm:pt-2.5" @submit.prevent="trySubmit">
            <div class="flex items-end gap-1.5 sm:gap-2">
                <button
                    type="button"
                    class="mb-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-slate-500 transition hover:bg-slate-100 hover:text-brand-700 active:scale-95 disabled:opacity-40"
                    :disabled="disabled || processing || isRecording"
                    aria-label="إرفاق ملف"
                    @click="openFilePicker"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                </button>
                <input
                    ref="fileInputRef"
                    type="file"
                    accept="image/*,audio/*,.pdf,.webm,.ogg,.mp3,.m4a,.wav"
                    class="sr-only"
                    :disabled="disabled || processing || isRecording"
                    @change="onFileChange"
                >

                <button
                    type="button"
                    class="mb-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition active:scale-95 disabled:opacity-40"
                    :class="
                        isRecording
                            ? 'bg-rose-600 text-white shadow-lg shadow-rose-900/25'
                            : 'text-slate-500 hover:bg-violet-50 hover:text-violet-700'
                    "
                    :disabled="disabled || processing"
                    :aria-label="isRecording ? 'إيقاف التسجيل (معاينة)' : 'تسجيل رسالة صوتية'"
                    @click="toggleVoiceRecord"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 14a3 3 0 003-3V6a3 3 0 10-6 0v5a3 3 0 003 3zm0 0v4m-4 0h8"
                        />
                    </svg>
                </button>

                <div
                    class="mb-1 flex min-h-[44px] min-w-0 flex-1 items-end rounded-2xl border border-slate-200/90 bg-slate-50/80 shadow-inner ring-1 ring-black/[0.02] transition focus-within:border-brand-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-500/20 sm:min-h-[48px] sm:rounded-[1.25rem]"
                    :class="isRecording && 'opacity-50'"
                >
                    <textarea
                        ref="textareaRef"
                        :value="modelValue"
                        rows="1"
                        dir="rtl"
                        :disabled="disabled || processing || isRecording"
                        :placeholder="isRecording ? 'جاري التسجيل…' : placeholder"
                        class="max-h-32 min-h-[44px] w-full resize-none border-0 bg-transparent px-3.5 py-3 text-base leading-relaxed text-slate-900 placeholder:text-slate-400 focus:ring-0 disabled:opacity-60 sm:min-h-[48px] sm:py-3.5 sm:text-[15px]"
                        @input="onInput"
                        @keydown="onKeydown"
                        @focus="onComposerFocus"
                    />
                </div>

                <button
                    type="submit"
                    class="mb-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-lg shadow-brand-900/25 transition hover:from-brand-500 hover:to-brand-600 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none sm:h-12 sm:w-12"
                    :class="isRecording && 'ring-2 ring-rose-300 ring-offset-1'"
                    :disabled="!canSend"
                    :aria-label="isRecording ? 'إرسال الرسالة الصوتية' : 'إرسال'"
                >
                    <svg
                        v-if="!processing"
                        class="h-5 w-5 -translate-x-0.5 rtl:rotate-180"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2.2"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    <span v-else class="h-5 w-5 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                </button>
            </div>
            <InputError class="mt-1.5 px-1 pb-0.5" :message="bodyError || recordError" />
        </form>

        <p
            class="hidden px-4 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-0.5 text-center text-[10px] text-slate-400 sm:block"
            dir="rtl"
        >
            الميكروفون للتسجيل · زر الإرسال أثناء التسجيل يرسل الصوت مباشرة
        </p>
        <div v-if="keyboardLift" class="pb-[max(0.35rem,env(safe-area-inset-bottom))] sm:hidden" aria-hidden="true" />
    </div>
</template>
