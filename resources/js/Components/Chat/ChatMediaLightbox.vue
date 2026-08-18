<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    url: { type: String, default: '' },
    name: { type: String, default: 'صورة' },
});

const emit = defineEmits(['close']);

const downloadName = computed(() => {
    const base = String(props.name || 'image').trim() || 'image';
    if (base.includes('.')) {
        return base;
    }
    return `${base}.jpg`;
});

function onKeydown(event) {
    if (!props.open) {
        return;
    }
    if (event.key === 'Escape') {
        emit('close');
    }
}

async function downloadImage() {
    if (!props.url) {
        return;
    }
    try {
        const res = await fetch(props.url, { credentials: 'same-origin' });
        const blob = await res.blob();
        const objectUrl = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = objectUrl;
        anchor.download = downloadName.value;
        anchor.rel = 'noopener';
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(objectUrl);
    } catch {
        window.open(props.url, '_blank', 'noopener,noreferrer');
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document === 'undefined') {
            return;
        }
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && url"
                class="fixed inset-0 z-[120] flex flex-col bg-slate-950/95 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                :aria-label="name || 'عرض الصورة'"
                @click.self="emit('close')"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-2 border-b border-white/10 px-3 py-2.5 pt-[max(0.5rem,env(safe-area-inset-top))] sm:px-4"
                    dir="rtl"
                >
                    <p class="min-w-0 truncate text-sm font-semibold text-white">
                        {{ name || 'صورة' }}
                    </p>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-white/10 px-3 text-xs font-bold text-white transition hover:bg-white/20"
                            @click="downloadImage"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                            </svg>
                            تحميل
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"
                            aria-label="إغلاق والعودة للمحادثة"
                            title="تصغير"
                            @click="emit('close')"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </header>

                <div class="flex min-h-0 flex-1 items-center justify-center p-3 sm:p-6">
                    <img
                        :src="url"
                        :alt="name || 'صورة'"
                        class="max-h-full max-w-full rounded-lg object-contain shadow-2xl ring-1 ring-white/10"
                        @click.stop
                    >
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
