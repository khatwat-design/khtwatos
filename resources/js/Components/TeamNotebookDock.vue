<script setup>
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    notebook: {
        type: Object,
        required: true,
    },
});

/** مرّ على العمود أو مفتوح */
const peekExpanded = ref(false);
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

watch(
    () => props.notebook?.team_id,
    () => {
        panelOpen.value = false;
        peekExpanded.value = false;
    },
);

watch(panelOpen, async (open) => {
    if (!open) {
        peekExpanded.value = false;
        teardownVisualViewportWatch();
    } else if (typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches) {
        await nextTick();
        readVisualViewport();
        setupVisualViewportWatch();
    }
});

/** ضبط إطار اللوحة داخل المساحة المرئية (فوق لوحة مفاتيح الجوال) */
const visualViewportRect = ref({
    offsetTop: 0,
    height: typeof window !== 'undefined' ? window.innerHeight : 640,
});

function readVisualViewport() {
    if (typeof window === 'undefined') {
        return;
    }
    const vv = window.visualViewport;
    if (vv) {
        visualViewportRect.value = { offsetTop: vv.offsetTop, height: vv.height };
    } else {
        visualViewportRect.value = { offsetTop: 0, height: window.innerHeight };
    }
}

let vvCleanup = null;

function setupVisualViewportWatch() {
    teardownVisualViewportWatch();
    const vv = window.visualViewport;
    if (!vv) {
        return;
    }
    const handler = () => readVisualViewport();
    vv.addEventListener('resize', handler);
    vv.addEventListener('scroll', handler);
    vvCleanup = () => {
        vv.removeEventListener('resize', handler);
        vv.removeEventListener('scroll', handler);
        vvCleanup = null;
    };
}

function teardownVisualViewportWatch() {
    if (vvCleanup) {
        vvCleanup();
    }
}

const dockAnchorStyle = computed(() => {
    if (!panelOpen.value || typeof window === 'undefined') {
        return undefined;
    }
    if (window.matchMedia('(min-width: 768px)').matches) {
        return undefined;
    }
    const { offsetTop, height } = visualViewportRect.value;
    return {
        top: `${offsetTop}px`,
        height: `${height}px`,
    };
});

const dockAnchorTransitionClass =
    'notebook-dock-anchor transition-[top,height,bottom] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none motion-reduce:duration-150';

function onSpineEnter() {
    if (!panelOpen.value) {
        peekExpanded.value = true;
    }
}

function onSpineLeave() {
    if (!panelOpen.value) {
        peekExpanded.value = false;
    }
}

function onPaperPeekEnter() {
    if (!panelOpen.value) {
        onSpineEnter();
    }
}

function onPaperPeekLeave() {
    if (!panelOpen.value) {
        onSpineLeave();
    }
}

/**
 * نستخدم pointerdown بدل click حتى لا يُلغَى النقر إذا تحرّك العنصر بعد hover،
 * ونفتح عند الضغط على الورقة الظاهرة في وضع peek وليس على العمود فقط.
 */
function onDockPointerDownCapture(e) {
    if (e.button !== 0) {
        return;
    }
    const spine = typeof e.target?.closest === 'function' ? e.target.closest('.notebook-spine') : null;
    if (spine) {
        e.preventDefault();
        panelOpen.value = !panelOpen.value;
        if (panelOpen.value) {
            peekExpanded.value = true;
        }
        return;
    }
    if (!panelOpen.value) {
        e.preventDefault();
        panelOpen.value = true;
        peekExpanded.value = true;
    }
}

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
    teardownVisualViewportWatch();
});

/**
 * حركة الانزلاق: translateX(-100% + X) يُظهر أقصى اليمين من الصف.
 * flex-row-reverse يضع العمود الجوزي يمين الورق فيُظهر عند الإغلاق طرف العمود وليس الورقة.
 */
const slideClass = computed(() => {
    if (panelOpen.value) {
        return 'notebook-slide--open';
    }
    if (peekExpanded.value) {
        return 'notebook-slide--peek';
    }
    return 'notebook-slide--collapsed';
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
        <!-- اتجاه LTR ثابت حتى لا يعكس flex تحت dir=rtl للصفحة -->
        <div class="team-notebook-root pointer-events-none fixed inset-x-0 bottom-0 top-0 z-[70] md:inset-x-auto md:left-0 md:right-auto" dir="ltr">
            <Transition
                enter-active-class="transition-opacity duration-300 ease-out"
                leave-active-class="transition-opacity duration-200 ease-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="panelOpen"
                    class="pointer-events-auto fixed inset-0 z-[60] bg-slate-950/40 backdrop-blur-[3px]"
                    aria-hidden="true"
                    @click="panelOpen = false"
                />
            </Transition>

            <!-- جوال مغلق: أسفل اليسار فوق الشريط — جوال مفتوح: وسط المساحة المرئية (visualViewport) فوق الكيبورد -->
            <div
                class="pointer-events-none z-[80]"
                :class="[
                    dockAnchorTransitionClass,
                    panelOpen
                        ? 'md:absolute md:left-0 md:top-1/2 md:-translate-y-1/2 md:bottom-auto max-md:fixed max-md:inset-x-0 max-md:bottom-auto max-md:flex max-md:items-center max-md:justify-start max-md:pl-2 max-md:pr-2'
                        : 'absolute max-md:left-1 md:left-0 max-md:bottom-[calc(4.85rem+env(safe-area-inset-bottom,0px))] md:top-1/2 md:-translate-y-1/2 md:bottom-auto',
                ]"
                :style="dockAnchorStyle"
            >
                <div
                    class="notebook-slide pointer-events-auto flex w-max max-w-[calc(100vw-0.75rem)] flex-row-reverse items-stretch will-change-transform md:max-w-none md:shadow-2xl md:shadow-black/25"
                    :class="[
                        slideClass,
                        panelOpen ? 'shadow-2xl shadow-black/25 max-md:shadow-2xl max-md:shadow-black/30' : 'shadow-lg shadow-black/20',
                    ]"
                    @pointerdown.capture="onDockPointerDownCapture"
                >
                    <!-- عمود الدفتر — بجانب حافة الشاشة عند الإغلاق (يمين الصف بسبب row-reverse) -->
                    <button
                        type="button"
                        class="notebook-spine relative z-[2] flex shrink-0 cursor-pointer flex-col items-center justify-between rounded-l-none border border-amber-950/40 bg-gradient-to-b from-[#4a3220] via-[#6b472e] to-[#2e1f14] shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] ring-amber-100/30 md:w-[3.75rem] md:rounded-r-2xl md:py-5 md:ring-2"
                        :class="
                            panelOpen
                                ? 'w-[3.75rem] rounded-r-xl py-5 ring-2'
                                : 'w-11 rounded-r-lg py-2.5 ring-1 md:w-[3.75rem] md:rounded-r-2xl md:py-5 md:ring-2'
                        "
                        aria-label="فتح دفتر الفريق"
                        :aria-expanded="panelOpen"
                        @mouseenter="onSpineEnter"
                        @mouseleave="onSpineLeave"
                    >
                        <!-- شريط مرجعي (bookmark) -->
                        <span
                            class="pointer-events-none absolute -left-1 top-[18%] rounded-sm bg-gradient-to-b from-rose-400 to-rose-700 shadow-md ring-1 ring-rose-950/20"
                            :class="panelOpen ? 'h-10 w-3' : 'h-7 w-2.5 md:h-10 md:w-3'"
                            aria-hidden="true"
                        />
                        <span
                            class="pointer-events-none absolute h-px bg-gradient-to-r from-transparent via-amber-100/40 to-transparent"
                            :class="panelOpen ? 'inset-x-2 top-3' : 'inset-x-1.5 top-2 md:inset-x-2 md:top-3'"
                        />
                        <div class="flex flex-col items-center" :class="panelOpen ? 'gap-1.5 pt-1' : 'gap-0.5 pt-0.5 md:gap-1.5 md:pt-1'">
                            <svg
                                class="text-amber-50 drop-shadow"
                                :class="panelOpen ? 'h-9 w-9' : 'h-6 w-6 md:h-9 md:w-9'"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.65"
                                aria-hidden="true"
                            >
                                <path d="M6 4h9l3 3v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                                <path d="M14 4v4h4M8 11h8M8 15h6" />
                            </svg>
                            <span
                                class="font-black uppercase text-amber-100/80"
                                :class="panelOpen ? 'text-[9px] tracking-[0.28em]' : 'text-[7px] tracking-[0.2em] md:text-[9px] md:tracking-[0.28em]'"
                            >
                                Notes
                            </span>
                        </div>
                        <span
                            class="rotate-180 px-0.5 text-center font-bold leading-tight text-amber-50/95 [writing-mode:vertical-rl]"
                            :class="panelOpen ? 'mb-2 px-1 text-[11px]' : 'mb-1 text-[9px] md:mb-2 md:px-1 md:text-[11px]'"
                        >
                            دفتر الفريق
                        </span>
                        <span
                            class="pointer-events-none absolute h-px bg-gradient-to-r from-transparent via-amber-100/25 to-transparent"
                            :class="panelOpen ? 'inset-x-2 bottom-3' : 'inset-x-1.5 bottom-2 md:inset-x-2 md:bottom-3'"
                        />
                    </button>

                    <!-- صفحات الورق -->
                    <div
                        class="notebook-paper relative z-[1] flex flex-col rounded-r-none border border-r-0 border-amber-900/20 bg-[linear-gradient(105deg,#fffef9_0%,#f7f0e4_45%,#fdfbf6_100%)] md:min-h-[min(26rem,62vh)] md:w-[22rem] md:rounded-l-xl"
                        :class="
                            panelOpen
                                ? 'max-md:max-h-[min(28rem,calc(100dvh-2rem))] max-md:overflow-y-auto min-h-[min(22rem,min(52vh,calc(100dvh-10rem)))] w-[min(22rem,calc(100vw-1rem))] rounded-l-xl md:!w-[min(34rem,calc(100vw-2rem))]'
                                : 'min-h-[min(14rem,36vh)] w-[min(13.5rem,calc(100vw-3.25rem))] rounded-l-lg md:min-h-[min(26rem,62vh)] md:w-[22rem] md:rounded-l-xl'
                        "
                        role="dialog"
                        :aria-modal="panelOpen"
                        :aria-hidden="!panelOpen"
                        @mouseenter="onPaperPeekEnter"
                        @mouseleave="onPaperPeekLeave"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 opacity-[0.055] [background-image:linear-gradient(#78716c_1px,transparent_1px)] [background-size:100%_1.35rem]"
                        />
                        <div
                            class="pointer-events-none absolute inset-y-5 right-5 w-px bg-rose-400/40"
                            aria-hidden="true"
                        />

                        <div
                            class="relative flex min-h-0 flex-1 flex-col md:px-5 md:pb-4 md:pt-5"
                            :class="panelOpen ? 'px-3.5 pb-3 pt-3.5' : 'px-2.5 pb-2 pt-2.5'"
                        >
                            <div
                                class="flex items-start justify-between border-b border-amber-900/12"
                                :class="panelOpen ? 'gap-2 pb-2.5' : 'gap-1.5 pb-2 md:gap-2 md:pb-2.5'"
                            >
                                <p class="font-black text-slate-900" :class="panelOpen ? 'text-sm' : 'text-xs md:text-sm'">دفتر الملاحظات</p>
                                <button
                                    type="button"
                                    class="bg-white/90 font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 hover:bg-white md:rounded-xl md:px-2.5 md:py-1 md:text-[11px]"
                                    :class="panelOpen ? 'rounded-xl px-2.5 py-1 text-[11px]' : 'rounded-lg px-2 py-0.5 text-[10px]'"
                                    @click.stop="panelOpen = false"
                                >
                                    إغلاق
                                </button>
                            </div>

                            <div
                                class="grid grid-cols-2 bg-slate-900/[0.05] ring-1 ring-slate-900/[0.06]"
                                :class="
                                    panelOpen
                                        ? 'mt-2.5 gap-1 rounded-xl p-1 md:mt-2.5'
                                        : 'mt-2 gap-0.5 rounded-lg p-0.5 md:mt-2.5 md:gap-1 md:rounded-xl md:p-1'
                                "
                            >
                                <button
                                    type="button"
                                    class="font-bold transition-colors duration-150 md:rounded-lg md:px-2 md:py-2 md:text-[11px]"
                                    :class="[
                                        panelOpen ? 'rounded-lg px-2 py-2 text-[11px]' : 'rounded-md px-1.5 py-1.5 text-[10px]',
                                        activeSheet === 'personal'
                                            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10'
                                            : 'text-slate-600 hover:bg-white/80',
                                    ]"
                                    @click.stop="activeSheet = 'personal'"
                                >
                                    خاصّتي
                                </button>
                                <button
                                    type="button"
                                    class="font-bold transition-colors duration-150 md:rounded-lg md:px-2 md:py-2 md:text-[11px]"
                                    :class="[
                                        panelOpen ? 'rounded-lg px-2 py-2 text-[11px]' : 'rounded-md px-1.5 py-1.5 text-[10px]',
                                        activeSheet === 'shared'
                                            ? 'bg-emerald-50 text-emerald-950 shadow-sm ring-1 ring-emerald-800/15'
                                            : 'text-slate-600 hover:bg-white/80',
                                    ]"
                                    @click.stop="activeSheet = 'shared'"
                                >
                                    عامّة للفريق
                                </button>
                            </div>

                            <div class="flex min-h-0 flex-1 flex-col" :class="panelOpen ? 'mt-2.5' : 'mt-2 md:mt-2.5'">
                                <template v-if="activeSheet === 'personal'">
                                    <p class="text-slate-500" :class="panelOpen ? 'mb-1 text-[10px]' : 'mb-0.5 text-[9px] md:mb-1 md:text-[10px]'">
                                        لا تظهر إلا لك.
                                    </p>
                                    <textarea
                                        v-model="personalBody"
                                        rows="8"
                                        class="w-full flex-1 resize-none border border-slate-200/95 bg-white/90 leading-relaxed text-slate-900 shadow-inner outline-none placeholder:text-slate-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-200/80 md:min-h-[10rem] md:rounded-xl md:px-3 md:py-2 md:text-[13px]"
                                        :class="
                                            panelOpen
                                                ? 'min-h-[10rem] rounded-xl px-3 py-2 text-[13px] max-md:min-h-[min(12rem,28svh)]'
                                                : 'min-h-[6.5rem] rounded-lg px-2 py-1.5 text-[12px]'
                                        "
                                        placeholder="اكتب ملاحظاتك…"
                                        :disabled="!panelOpen"
                                        @input="schedulePersonalSave"
                                        @blur="flushPersonalSave"
                                    />
                                    <p class="mt-1 text-[10px] text-slate-400 tabular-nums">
                                        {{ savingPersonal ? 'جاري الحفظ…' : 'يُحفظ تلقائيًا' }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="text-slate-500" :class="panelOpen ? 'mb-1 text-[10px]' : 'mb-0.5 text-[9px] md:mb-1 md:text-[10px]'">
                                        يراها كل الفريق.
                                    </p>
                                    <textarea
                                        v-model="sharedBody"
                                        rows="8"
                                        class="w-full flex-1 resize-none border border-emerald-900/20 bg-emerald-50/50 leading-relaxed text-slate-900 shadow-inner outline-none placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200/80 md:min-h-[10rem] md:rounded-xl md:px-3 md:py-2 md:text-[13px]"
                                        :class="
                                            panelOpen
                                                ? 'min-h-[10rem] rounded-xl px-3 py-2 text-[13px] max-md:min-h-[min(12rem,28svh)]'
                                                : 'min-h-[6.5rem] rounded-lg px-2 py-1.5 text-[12px]'
                                        "
                                        placeholder="ملاحظات عامة للفريق…"
                                        :disabled="!panelOpen"
                                        @input="scheduleSharedSave"
                                        @blur="flushSharedSave"
                                    />
                                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-[10px] text-slate-500">
                                        <span>{{ savingShared ? 'جاري الحفظ…' : 'يُحفظ تلقائيًا' }}</span>
                                        <span v-if="sharedHint" class="max-w-full truncate text-slate-400">{{ sharedHint }}</span>
                                    </div>
                                </template>
                            </div>

                            <p v-if="!panelOpen" class="mt-1.5 text-center text-[9px] font-semibold text-slate-500 md:mt-2 md:text-[10px]">
                                اضغط العمود أو الورقة عند إظهارها لفتح الدفتر بالكامل
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.notebook-slide {
    transition: transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
}

/* مطوي: الشريط الظاهر = عرض العمود (جوال w-11 = 2.75rem، md = 3.75rem) */
.notebook-slide--collapsed {
    transform: translateX(calc(-100% + 2.75rem));
}

@media (min-width: 768px) {
    .notebook-slide--collapsed {
        transform: translateX(calc(-100% + 3.75rem));
    }
}

/* مرّ الماوس: يخرج جزءًا من الورق — أقل على الجوال لتقليل الحجب */
.notebook-slide--peek {
    transform: translateX(calc(-100% + 7.25rem));
}

@media (min-width: 768px) {
    .notebook-slide--peek {
        transform: translateX(calc(-100% + 12rem));
    }
}

/* مفتوح بالكامل */
.notebook-slide--open {
    transform: translateX(0);
}

@media (prefers-reduced-motion: reduce) {
    .notebook-slide {
        transition-duration: 0.22s;
    }
}
</style>
