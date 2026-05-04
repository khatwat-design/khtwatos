<script setup>
defineProps({
    show: { type: Boolean, default: false },
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            leave-active-class="transition duration-300 ease-in"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="show"
                class="app-page-loader pointer-events-auto fixed inset-0 z-[200] flex items-center justify-center px-4"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div
                    class="absolute inset-0 bg-gradient-to-b from-slate-900/25 via-slate-900/15 to-white/30 backdrop-blur-[3px] motion-reduce:from-slate-900/20 motion-reduce:backdrop-blur-none"
                    aria-hidden="true"
                />
                <div
                    class="relative flex max-w-[min(100%,20rem)] flex-col items-center gap-5 overflow-hidden rounded-[1.75rem] border border-white/70 bg-white/[0.97] px-8 py-9 shadow-[0_24px_80px_-24px_rgba(15,23,42,0.35)] backdrop-blur-xl ring-1 ring-slate-900/[0.06]"
                >
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-brand-500/[0.07] to-transparent"
                        aria-hidden="true"
                    />
                    <div class="relative flex flex-col items-center gap-4">
                        <div class="relative flex h-[5.5rem] w-[5.5rem] items-center justify-center">
                            <div
                                class="app-loader-orbit absolute inset-0 rounded-[1.35rem] border-2 border-brand-200/55 border-t-brand-600 motion-reduce:border-brand-300 motion-reduce:border-t-brand-500"
                                aria-hidden="true"
                            />
                            <div
                                class="relative flex h-[4.25rem] w-[4.25rem] items-center justify-center rounded-2xl bg-white shadow-[inset_0_1px_2px_rgba(255,255,255,0.9)] ring-1 ring-slate-900/[0.07]"
                            >
                                <img
                                    src="/images/mobile-logo.png"
                                    alt=""
                                    width="56"
                                    height="56"
                                    class="app-loader-logo h-14 w-14 object-contain"
                                    draggable="false"
                                >
                            </div>
                        </div>
                        <div class="space-y-1 text-center">
                            <p class="text-[13px] font-semibold tracking-tight text-slate-800">
                                خارج المخزون
                            </p>
                            <p class="text-xs font-medium text-slate-500">
                                جاري التحميل…
                            </p>
                        </div>
                        <div class="flex gap-1.5 motion-reduce:hidden" aria-hidden="true">
                            <span
                                class="inline-flex h-1.5 w-1.5 animate-bounce rounded-full bg-brand-600/90"
                                style="animation-delay: 0ms"
                            />
                            <span
                                class="inline-flex h-1.5 w-1.5 animate-bounce rounded-full bg-brand-500/80"
                                style="animation-delay: 150ms"
                            />
                            <span
                                class="inline-flex h-1.5 w-1.5 animate-bounce rounded-full bg-brand-400/70"
                                style="animation-delay: 300ms"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.app-loader-orbit {
    animation: app-loader-spin 1.05s linear infinite;
}

.app-loader-logo {
    animation: app-loader-breathe 1.85s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .app-loader-orbit,
    .app-loader-logo {
        animation: none;
    }
}

@keyframes app-loader-spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes app-loader-breathe {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.97);
        opacity: 0.9;
    }
}
</style>
