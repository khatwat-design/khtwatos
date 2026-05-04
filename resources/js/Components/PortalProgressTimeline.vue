<script setup>
const props = defineProps({
    timeline: {
        type: Object,
        default: () => ({}),
    },
});

function dotClass(step) {
    if (step.status === 'completed') {
        return 'border-emerald-500 bg-emerald-500 text-white shadow-sm shadow-emerald-900/10';
    }
    if (step.status === 'current') {
        /* نشاط واضح دون إيحاء خطأ (بدل تعبئة حمراء كاملة) */
        return 'border-sky-500 bg-white text-sky-600 shadow-md ring-[3px] ring-sky-100';
    }
    return 'border-slate-200 bg-slate-50 text-slate-400 shadow-inner';
}

function statusBadgeClass(step) {
    if (step.status === 'completed') {
        return 'border-emerald-200/80 bg-emerald-50 text-emerald-800';
    }
    if (step.status === 'current') {
        return props.timeline?.is_paused
            ? 'border-amber-200/90 bg-amber-50 text-amber-900'
            : 'border-sky-200/90 bg-sky-50 text-sky-900';
    }
    return 'border-slate-200/80 bg-white text-slate-500';
}

function formatTs(iso) {
    if (!iso) {
        return null;
    }
    try {
        return new Date(iso).toLocaleString('ar-SA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch {
        return null;
    }
}

function stepTimeCaption(step) {
    if (step.status === 'completed') {
        const c = formatTs(step.completed_at);
        return c ? `اكتملت في ${c}` : 'مكتملة';
    }
    if (step.status === 'current') {
        const ent = formatTs(step.entered_at);
        if (props.timeline?.is_paused) {
            return ent ? `متوقف مؤقتًا · بدء المرحلة ${ent}` : 'متوقف مؤقتًا ضمن هذه المرحلة';
        }
        return ent ? `قيد التنفيذ · بدء المرحلة ${ent}` : 'قيد التنفيذ الآن';
    }
    return 'لم تبدأ بعد';
}

function stepStatusLabel(step) {
    if (step.status === 'completed') {
        return 'مكتملة';
    }
    if (step.status === 'current') {
        return props.timeline?.is_paused ? 'متوقف مؤقتًا' : 'قيد التنفيذ';
    }
    return 'قادمة';
}
</script>

<template>
    <div v-if="timeline?.steps?.length" class="ui-card overflow-hidden p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-bold tracking-tight text-gray-900 sm:text-xl">مسار العمل معنا</h2>
                <p class="mt-2 max-w-prose text-sm leading-relaxed text-gray-600">
                    تعرّف على المراحل القادمة والمكتملة دون الحاجة للاستفسار من الفريق.
                </p>
            </div>
            <p
                v-if="timeline.refreshed_at"
                class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full border border-slate-200/90 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-600"
            >
                <span class="text-[10px] text-slate-400">آخر تحديث</span>
                <span class="tabular-nums text-slate-700">{{ formatTs(timeline.refreshed_at) }}</span>
            </p>
        </div>

        <div v-if="timeline.is_paused" class="mt-4 rounded-2xl border border-amber-200/90 bg-gradient-to-l from-amber-50/80 to-amber-50 px-4 py-3 text-sm font-medium text-amber-950 shadow-sm ring-1 ring-amber-100/60">
            حسابك في حالة «متوقف» حاليًا ضمن مسار العمل. سيُبلغ الفريق عند أي تغيير.
        </div>

        <!-- Desktop: horizontal (scroll on md if needed) -->
        <div class="mt-8 hidden md:block">
            <div
                class="flex items-stretch justify-between gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:gap-5"
            >
                <template v-for="(step, idx) in timeline.steps" :key="`tl-h-${step.key}`">
                    <div class="relative flex min-w-[140px] max-w-[168px] shrink-0 flex-1 flex-col items-center text-center">
                        <div
                            class="relative z-[1] flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold transition-all duration-200"
                            :class="dotClass(step)"
                        >
                            <span v-if="step.status === 'completed'" class="text-sm" aria-hidden="true">✓</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                        <span
                            class="mt-2 inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold leading-none"
                            :class="statusBadgeClass(step)"
                        >
                            {{ stepStatusLabel(step) }}
                        </span>
                        <p class="mt-2 text-xs font-bold leading-snug text-gray-900">{{ step.title_ar }}</p>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-gray-600">{{ step.description_ar }}</p>
                        <p class="mt-2 text-[10px] leading-snug text-gray-500">{{ stepTimeCaption(step) }}</p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Mobile: vertical timeline -->
        <ol class="relative mt-8 space-y-4 md:hidden">
            <li
                v-for="(step, idx) in timeline.steps"
                :key="`tl-v-${step.key}`"
                class="relative grid grid-cols-[2.75rem_minmax(0,1fr)] gap-x-3"
            >
                <div class="relative flex justify-center pt-1">
                    <div
                        v-if="idx < timeline.steps.length - 1"
                        class="pointer-events-none absolute top-11 bottom-[-1.15rem] w-[3px] rounded-full bg-gradient-to-b from-slate-300/85 via-slate-200/90 to-slate-100/70"
                        aria-hidden="true"
                    />
                    <span
                        class="relative z-[1] flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold transition-all duration-200"
                        :class="dotClass(step)"
                    >
                        <span v-if="step.status === 'completed'" class="text-sm leading-none">✓</span>
                        <span v-else>{{ idx + 1 }}</span>
                    </span>
                </div>
                <div
                    class="min-w-0 rounded-2xl border border-slate-100/90 bg-gradient-to-l from-white to-slate-50/90 p-3.5 shadow-sm ring-1 ring-slate-100/40"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-semibold leading-tight"
                            :class="statusBadgeClass(step)"
                        >
                            {{ stepStatusLabel(step) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm font-bold leading-snug text-gray-900">
                        {{ step.title_ar }}
                    </p>
                    <p class="mt-1.5 text-xs leading-relaxed text-gray-600">
                        {{ step.description_ar }}
                    </p>
                    <p class="mt-2 border-t border-slate-100/80 pt-2 text-[11px] leading-relaxed text-slate-500">
                        {{ stepTimeCaption(step) }}
                    </p>
                </div>
            </li>
        </ol>
    </div>
</template>
