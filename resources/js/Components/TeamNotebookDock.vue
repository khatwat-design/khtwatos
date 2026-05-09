<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

watch(panelOpen, (open) => {
    if (!open) {
        peekExpanded.value = false;
    }
});

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
});

/**
 * حركة الانزلاق: النسبة من عرض الحاوية (عمود + ورق).
 * مرتاح: يظهر العمود (~56px) دائمًا؛ المرّ يُخرج جزءًا من الورق.
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

            <!-- موضع: أسفل اليسار على الجوال فوق شريط التنقل، وسط الجانب الأيسر على الشاشات الكبيرة -->
            <div
                class="pointer-events-none absolute left-3 z-[80] max-md:bottom-[calc(4.85rem+env(safe-area-inset-bottom,0px))] md:left-0 md:top-1/2 md:-translate-y-1/2"
            >
                <div
                    class="notebook-slide pointer-events-auto flex w-max max-w-[calc(100vw-1.25rem)] flex-row items-stretch shadow-2xl shadow-black/25 will-change-transform md:max-w-none"
                    :class="slideClass"
                    @pointerdown.capture="onDockPointerDownCapture"
                >
                    <!-- عمود الدفتر — دائمًا ظاهر بوضوح -->
                    <button
                        type="button"
                        class="notebook-spine relative z-[2] flex w-14 shrink-0 cursor-pointer flex-col items-center justify-between rounded-r-xl border border-amber-950/40 bg-gradient-to-b from-[#4a3220] via-[#6b472e] to-[#2e1f14] py-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)] ring-2 ring-amber-100/30 md:w-[3.75rem] md:rounded-r-2xl md:py-5"
                        aria-label="فتح دفتر الفريق"
                        :aria-expanded="panelOpen"
                        @mouseenter="onSpineEnter"
                        @mouseleave="onSpineLeave"
                    >
                        <!-- شريط مرجعي (bookmark) -->
                        <span
                            class="pointer-events-none absolute -right-1 top-[18%] h-10 w-3 rounded-sm bg-gradient-to-b from-rose-400 to-rose-700 shadow-md ring-1 ring-rose-950/20"
                            aria-hidden="true"
                        />
                        <span
                            class="pointer-events-none absolute inset-x-2 top-3 h-px bg-gradient-to-r from-transparent via-amber-100/40 to-transparent"
                        />
                        <div class="flex flex-col items-center gap-1.5 pt-1">
                            <svg
                                class="h-8 w-8 text-amber-50 drop-shadow md:h-9 md:w-9"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.65"
                                aria-hidden="true"
                            >
                                <path d="M6 4h9l3 3v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                                <path d="M14 4v4h4M8 11h8M8 15h6" />
                            </svg>
                            <span class="text-[9px] font-black uppercase tracking-[0.28em] text-amber-100/80">Notes</span>
                        </div>
                        <span
                            class="mb-2 rotate-180 px-1 text-center text-[11px] font-bold leading-tight text-amber-50/95 [writing-mode:vertical-rl]"
                        >
                            دفتر الفريق
                        </span>
                        <span
                            class="pointer-events-none absolute inset-x-2 bottom-3 h-px bg-gradient-to-r from-transparent via-amber-100/25 to-transparent"
                        />
                    </button>

                    <!-- صفحات الورق -->
                    <div
                        class="notebook-paper relative z-[1] flex min-h-[min(22rem,52vh)] w-[min(17.5rem,calc(100vw-5.5rem))] flex-col rounded-l-xl border border-r-0 border-amber-900/20 bg-[linear-gradient(105deg,#fffef9_0%,#f7f0e4_45%,#fdfbf6_100%)] md:min-h-[min(26rem,62vh)] md:w-[22rem]"
                        :class="panelOpen ? 'md:!w-[min(34rem,calc(100vw-2rem))]' : ''"
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
                            class="pointer-events-none absolute inset-y-5 left-5 w-px bg-rose-400/40"
                            aria-hidden="true"
                        />

                        <div class="relative flex min-h-0 flex-1 flex-col px-3.5 pb-3 pt-3.5 md:px-5 md:pb-4 md:pt-5">
                            <div class="flex items-start justify-between gap-2 border-b border-amber-900/12 pb-2.5">
                                <div>
                                    <p class="text-sm font-black text-slate-900">دفتر الملاحظات</p>
                                    <p class="mt-0.5 text-[11px] leading-snug text-slate-600">خاصّة لك + ورقة عامة للفريق</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-xl bg-white/90 px-2.5 py-1 text-[11px] font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 hover:bg-white"
                                    @click.stop="panelOpen = false"
                                >
                                    إغلاق
                                </button>
                            </div>

                            <div class="mt-2.5 grid grid-cols-2 gap-1 rounded-xl bg-slate-900/[0.05] p-1 ring-1 ring-slate-900/[0.06]">
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-2 text-[11px] font-bold transition-colors duration-150"
                                    :class="
                                        activeSheet === 'personal'
                                            ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-900/10'
                                            : 'text-slate-600 hover:bg-white/80'
                                    "
                                    @click.stop="activeSheet = 'personal'"
                                >
                                    خاصّتي
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-2 text-[11px] font-bold transition-colors duration-150"
                                    :class="
                                        activeSheet === 'shared'
                                            ? 'bg-emerald-50 text-emerald-950 shadow-sm ring-1 ring-emerald-800/15'
                                            : 'text-slate-600 hover:bg-white/80'
                                    "
                                    @click.stop="activeSheet = 'shared'"
                                >
                                    عامّة للفريق
                                </button>
                            </div>

                            <div class="mt-2.5 flex min-h-0 flex-1 flex-col">
                                <template v-if="activeSheet === 'personal'">
                                    <p class="mb-1 text-[10px] text-slate-500">لا تظهر إلا لك.</p>
                                    <textarea
                                        v-model="personalBody"
                                        rows="8"
                                        class="min-h-[10rem] w-full flex-1 resize-none rounded-xl border border-slate-200/95 bg-white/90 px-3 py-2 text-[13px] leading-relaxed text-slate-900 shadow-inner outline-none placeholder:text-slate-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-200/80"
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
                                    <p class="mb-1 text-[10px] text-slate-500">يراها كل الفريق.</p>
                                    <textarea
                                        v-model="sharedBody"
                                        rows="8"
                                        class="min-h-[10rem] w-full flex-1 resize-none rounded-xl border border-emerald-900/20 bg-emerald-50/50 px-3 py-2 text-[13px] leading-relaxed text-slate-900 shadow-inner outline-none placeholder:text-slate-500 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200/80"
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

                            <p v-if="!panelOpen" class="mt-2 text-center text-[10px] font-semibold text-slate-500">
                                اضغط الورقة أو العمود لفتح الدفتر بالكامل
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

/* مطوي: يظهر تقريبًا عمود الكتاب فقط (~56–60px) */
.notebook-slide--collapsed {
    transform: translateX(calc(-100% + 3.75rem));
}

@media (min-width: 768px) {
    .notebook-slide--collapsed {
        transform: translateX(calc(-100% + 4.25rem));
    }
}

/* مرّ الماوس: يخرج جزء واضح من الورق */
.notebook-slide--peek {
    transform: translateX(calc(-100% + 10.5rem));
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
