<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    isMine: { type: Boolean, default: false },
});

const audioRef = ref(null);
const playing = ref(false);
const duration = ref(0);
const current = ref(0);
const ready = ref(false);
const loadError = ref('');

const progress = computed(() => {
    if (!duration.value) {
        return 0;
    }
    return Math.min(100, (current.value / duration.value) * 100);
});

const timeLabel = computed(() => {
    const secs = playing.value || current.value > 0 ? current.value : duration.value;
    return formatTime(secs || 0);
});

function formatTime(totalSeconds) {
    const s = Math.max(0, Math.floor(totalSeconds));
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${m}:${String(r).padStart(2, '0')}`;
}

function syncDuration() {
    const el = audioRef.value;
    if (!el) {
        return;
    }
    if (Number.isFinite(el.duration) && el.duration > 0) {
        duration.value = el.duration;
        ready.value = true;
        loadError.value = '';
    }
}

async function togglePlay() {
    const el = audioRef.value;
    if (!el || loadError.value) {
        return;
    }
    try {
        if (el.paused) {
            if (el.readyState < 2) {
                el.load();
            }
            await el.play();
        } else {
            el.pause();
        }
    } catch {
        loadError.value = 'تعذّر تشغيل الصوت على هذا الجهاز.';
    }
}

function onLoaded() {
    syncDuration();
}

function onTimeUpdate() {
    const el = audioRef.value;
    if (!el) {
        return;
    }
    current.value = el.currentTime;
    syncDuration();
}

function onPlay() {
    playing.value = true;
    loadError.value = '';
}

function onPause() {
    playing.value = false;
}

function onEnded() {
    playing.value = false;
    current.value = 0;
    const el = audioRef.value;
    if (el) {
        el.currentTime = 0;
    }
}

function onAudioError() {
    loadError.value = 'تعذّر تحميل الرسالة الصوتية.';
    ready.value = false;
    playing.value = false;
}

function resetPlayer() {
    playing.value = false;
    current.value = 0;
    duration.value = 0;
    ready.value = false;
    loadError.value = '';
    const el = audioRef.value;
    if (el) {
        el.pause();
        el.load();
    }
}

watch(
    () => props.url,
    () => resetPlayer(),
);

onBeforeUnmount(() => {
    audioRef.value?.pause();
});
</script>

<template>
    <div
        class="flex min-w-[min(100%,11.5rem)] max-w-full items-center gap-2.5 rounded-2xl px-2 py-2"
        :class="isMine ? 'bg-white/10' : 'bg-white ring-1 ring-slate-200/90'"
        dir="rtl"
        @click.stop
    >
        <button
            type="button"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full shadow-sm transition active:scale-95 disabled:opacity-50"
            :class="isMine ? 'bg-white text-brand-700' : 'bg-brand-600 text-white'"
            :disabled="Boolean(loadError)"
            :aria-label="playing ? 'إيقاف' : 'تشغيل'"
            @click="togglePlay"
        >
            <svg v-if="!playing" class="h-5 w-5 translate-x-px" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 5v14l11-7z" />
            </svg>
            <svg v-else class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 5h4v14H6V5zm8 0h4v14h-4V5z" />
            </svg>
        </button>

        <div class="min-w-0 flex-1">
            <div class="mb-1 h-1.5 overflow-hidden rounded-full" :class="isMine ? 'bg-white/25' : 'bg-slate-200'">
                <div
                    class="h-full rounded-full transition-[width] duration-100"
                    :class="isMine ? 'bg-white' : 'bg-brand-600'"
                    :style="{ width: `${progress}%` }"
                />
            </div>
            <p
                class="text-[10px] font-semibold tabular-nums"
                :class="loadError ? 'text-rose-500' : isMine ? 'text-white/85' : 'text-slate-500'"
            >
                {{ loadError || (ready ? timeLabel : '…') }}
            </p>
        </div>

        <span
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm"
            :class="isMine ? 'bg-white/15 text-white' : 'bg-brand-50 text-brand-700'"
            aria-hidden="true"
        >
            🎤
        </span>

        <audio
            ref="audioRef"
            :src="url"
            preload="auto"
            playsinline
            class="sr-only"
            @loadedmetadata="onLoaded"
            @durationchange="onLoaded"
            @canplay="onLoaded"
            @timeupdate="onTimeUpdate"
            @play="onPlay"
            @pause="onPause"
            @ended="onEnded"
            @error="onAudioError"
        />
    </div>
</template>
