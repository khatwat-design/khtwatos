<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    notebook: {
        type: Object,
        required: true,
    },
});

const hoveredPeek = ref(false);
const panelOpen = ref(false);
const activeSheet = ref('personal'); // personal | shared

const personalBody = ref(props.notebook.personal_body || '');
const sharedBody = ref(props.notebook.shared_body || '');
const savingPersonal = ref(false);
const savingShared = ref(false);

let personalTimer;
let sharedTimer;

function schedulePersonalSave() {
    window.clearTimeout(personalTimer);
    personalTimer = window.setTimeout(() => flushPersonalSave(), 900);
}

function scheduleSharedSave() {
    window.clearTimeout(sharedTimer);
    sharedTimer = window.setTimeout(() => flushSharedSave(), 900);
}

function flushPersonalSave() {
    window.clearTimeout(personalTimer);
    if (!props.notebook?.team_id) {
        return;
    }
    savingPersonal.value = true;
    router.patch(
        route('teams.notebook.personal.update', props.notebook.team_id),
        { body: personalBody.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                savingPersonal.value = false;
            },
        },
    );
}

function flushSharedSave() {
    window.clearTimeout(sharedTimer);
    if (!props.notebook?.team_id) {
        return;
    }
    savingShared.value = true;
    router.patch(
        route('teams.notebook.shared.update', props.notebook.team_id),
        { body: sharedBody.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                savingShared.value = false;
            },
        },
    );
}

watch(
    () => props.notebook,
    (nb) => {
        if (!nb) {
            return;
        }
        personalBody.value = nb.personal_body || '';
        sharedBody.value = nb.shared_body || '';
    },
    { deep: true },
);

/** لو انتقل المستخدم لفريق آخر أثناء الدفتر مفتوح */
watch(
    () => props.notebook?.team_id,
    () => {
        panelOpen.value = false;
        hoveredPeek.value = false;
    },
);

watch(panelOpen, (open) => {
    hoveredPeek.value = open;
});

function onKeydown(e) {
    if (e.key === 'Escape') {
        panelOpen.value = false;
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.clearTimeout(personalTimer);
    window.clearTimeout(sharedTimer);
});

const sharedHint = computed(() => {
    const meta = props.notebook.shared_meta;
    if (!meta?.updated_by_name && !meta?.updated_at) {
        return '';
    }
    const parts = [];
    if (meta.updated_by_name) {
        parts.push(`آخر تحديث: ${meta.updated_by_name}`);
    }
    if (meta.updated_at) {
        parts.push(
            new Intl.DateTimeFormat('ar-SA', {
                dateStyle: 'short',
                timeStyle: 'short',
            }).format(new Date(meta.updated_at)),
        );
    }
    return parts.join(' · ');
});
</script>

<template>
    <Teleport to="body">
        <!-- خلفية عند الفتح الكامل -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            leave-active-class="transition-opacity duration-150 ease-in"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="panelOpen"
                class="fixed inset-0 z-[65] bg-slate-900/35 backdrop-blur-[2px] md:bg-slate-900/25"
                aria-hidden="true"
                @click="panelOpen = false"
            />
        </Transition>

        <!-- الدفتر — ثابت من جهة اليسار مع إخراج جزئي -->
        <div
            class="team-notebook-dock pointer-events-none fixed left-0 z-[70] max-md:bottom-[calc(4.75rem+env(safe-area-inset-bottom,0px))] max-md:top-auto md:top-1/2 md:-translate-y-1/2"
        >
            <div
                class="relative flex flex-row-reverse transition-[transform,filter] duration-300 ease-out motion-reduce:transition-none"
                :style="
                    panelOpen
                        ? { transform: 'translateX(0)' }
                        : hoveredPeek
                          ? { transform: 'translateX(calc(-100% + 5.25rem))' }
                          : { transform: 'translateX(calc(-100% + 1.35rem))' }
                "
            >
                <!-- غلاف / عمود الكتاب -->
                <button
                    type="button"
                    class="pointer-events-auto relative flex h-[min(22rem,55vh)] w-14 shrink-0 cursor-pointer flex-col items-center justify-between rounded-r-2xl border border-amber-900/35 bg-gradient-to-b from-[#3d2918] via-[#5c3d26] to-[#2a1b11] py-5 shadow-[0_18px_50px_-14px_rgba(0,0,0,0.55)] ring-2 ring-amber-200/25 md:h-[min(26rem,62vh)]"
                    aria-label="دفتر الفريق"
                    @mouseenter="hoveredPeek = true"
                    @mouseleave="hoveredPeek = panelOpen"
                    @click="panelOpen = true"
                >
                    <span
                        class="pointer-events-none absolute inset-x-1 top-3 h-px bg-gradient-to-r from-transparent via-amber-200/35 to-transparent"
                    />
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-[0.35em] text-amber-100/75">Notes</span>
                        <svg
                            class="h-9 w-9 text-amber-100/90 drop-shadow"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            aria-hidden="true"
                        >
                            <path d="M6 4h9l3 3v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                            <path d="M14 4v4h4M8 11h8M8 15h6" />
                        </svg>
                    </div>
                    <span class="rotate-180 text-[11px] font-bold tracking-wide text-amber-50/85 [writing-mode:vertical-rl]">
                        دفتر الفريق
                    </span>
                    <span
                        class="pointer-events-none absolute inset-x-1 bottom-3 h-px bg-gradient-to-r from-transparent via-amber-200/25 to-transparent"
                    />
                </button>

                <!-- الصفحات — ظهر الكتاب -->
                <div
                    class="relative flex h-[min(22rem,55vh)] flex-col rounded-l-2xl border-y border-l border-amber-200/35 bg-[linear-gradient(115deg,#fffdf8_0%,#f8f4ec_42%,#fdfbf7_100%)] shadow-[inset_0_1px_0_rgba(255,255,255,0.85),12px_0_34px_-16px_rgba(41,37,36,0.35)] transition-[width] duration-300 ease-out motion-reduce:transition-none md:h-[min(26rem,62vh)]"
                    :class="[
                        panelOpen ? 'pointer-events-auto w-[min(92vw,34rem)]' : 'pointer-events-none w-[min(18rem,calc(100vw-4rem))]',
                    ]"
                    role="dialog"
                    :aria-modal="panelOpen"
                    :aria-hidden="!panelOpen"
                >
                    <div class="pointer-events-none absolute inset-0 opacity-[0.07] [background-image:linear-gradient(#78716c_1px,transparent_1px)] [background-size:100%_1.35rem]" />
                    <!-- حافة ورق -->
                    <div
                        class="pointer-events-none absolute inset-y-4 left-6 w-px bg-red-300/35 motion-reduce:opacity-0"
                        aria-hidden="true"
                    />

                    <div class="relative flex min-h-0 flex-1 flex-col px-4 pb-3 pt-4 md:px-5 md:pb-4 md:pt-5">
                        <div class="flex items-start justify-between gap-2 border-b border-amber-900/10 pb-3">
                            <div>
                                <p class="text-[13px] font-black text-slate-900">دفتر المهام</p>
                                <p class="mt-0.5 text-[11px] leading-snug text-slate-600">ملاحظات خاصة + ورقة عامة للفريق</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl bg-slate-900/[0.05] px-2 py-1 text-[11px] font-bold text-slate-600 ring-1 ring-slate-900/10 hover:bg-white"
                                @click="panelOpen = false"
                            >
                                إغلاق
                            </button>
                        </div>

                        <!-- تبويب الورقتين -->
                        <div class="mt-3 grid grid-cols-2 gap-1 rounded-xl bg-slate-900/[0.04] p-1 ring-1 ring-slate-900/[0.06]">
                            <button
                                type="button"
                                class="rounded-lg px-2 py-2 text-[11px] font-bold transition-colors duration-150"
                                :class="
                                    activeSheet === 'personal'
                                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10'
                                        : 'text-slate-600 hover:bg-white/70'
                                "
                                @click="activeSheet = 'personal'"
                            >
                                خاصّتي
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2 py-2 text-[11px] font-bold transition-colors duration-150"
                                :class="
                                    activeSheet === 'shared'
                                        ? 'bg-emerald-50 text-emerald-950 shadow-sm ring-1 ring-emerald-800/15'
                                        : 'text-slate-600 hover:bg-white/70'
                                "
                                @click="activeSheet = 'shared'"
                            >
                                عامّة للفريق
                            </button>
                        </div>

                        <div class="mt-3 flex min-h-0 flex-1 flex-col">
                            <div v-if="activeSheet === 'personal'" class="flex min-h-0 flex-1 flex-col gap-2">
                                <p class="text-[10px] text-slate-500">لا يراها أحد سواك داخل هذا الفريق.</p>
                                <textarea
                                    v-model="personalBody"
                                    rows="8"
                                    class="min-h-[11rem] w-full flex-1 resize-none rounded-xl border border-slate-200/90 bg-white/85 px-3 py-2 text-[13px] leading-relaxed text-slate-900 shadow-inner outline-none ring-1 ring-slate-900/[0.04] placeholder:text-slate-400 focus:border-brand-300 focus:ring-2 focus:ring-brand-200"
                                    placeholder="اكتب ملاحظاتك الشخصية…"
                                    @input="schedulePersonalSave"
                                    @blur="flushPersonalSave"
                                />
                                <p class="text-[10px] text-slate-400 tabular-nums">
                                    {{ savingPersonal ? 'جاري الحفظ…' : 'يُحفظ تلقائيًا' }}
                                </p>
                            </div>

                            <div v-else class="flex min-h-0 flex-1 flex-col gap-2">
                                <p class="text-[10px] text-slate-500">
                                    يقرؤها كل أعضاء الفريق في لوحة المهام والدردشة.
                                </p>
                                <textarea
                                    v-model="sharedBody"
                                    rows="8"
                                    class="min-h-[11rem] w-full flex-1 resize-none rounded-xl border border-emerald-900/15 bg-emerald-50/40 px-3 py-2 text-[13px] leading-relaxed text-slate-900 shadow-inner outline-none ring-1 ring-emerald-900/10 placeholder:text-slate-500 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-200"
                                    placeholder="ملاحظات ومتابعات عامة للفريق…"
                                    @input="scheduleSharedSave"
                                    @blur="flushSharedSave"
                                />
                                <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-500">
                                    <span>{{ savingShared ? 'جاري الحفظ…' : 'يُحفظ تلقائيًا' }}</span>
                                    <span v-if="sharedHint" class="max-w-full truncate text-slate-400">{{ sharedHint }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    .team-notebook-dock * {
        transition-duration: 0.01ms !important;
    }
}
</style>
