<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    meta_lead_status_options: { type: Array, default: () => [] },
    meta_filters: { type: Object, default: () => ({}) },
    meta_campaign_options: { type: Array, default: () => [] },
    meta_assignee_stats: { type: Array, default: () => [] },
});

const showSheet = ref(false);
const draftStatus = ref('');
const draftCampaign = ref('');

watch(showSheet, (open) => {
    if (open) {
        draftStatus.value = props.meta_filters?.status || '';
        draftCampaign.value = props.meta_filters?.campaign || '';
    }
});

function statusLabel(status) {
    return props.meta_lead_status_options?.find((s) => s.value === status)?.label || status;
}

const activeChips = computed(() => {
    const chips = [];
    const ownerId = Number(props.meta_filters?.owner || 0);
    if (ownerId > 0) {
        const rep = props.meta_assignee_stats?.find((r) => Number(r.id) === ownerId);
        chips.push({ key: 'owner', label: rep?.name || 'موظف' });
    }
    if (props.meta_filters?.status) {
        chips.push({ key: 'status', label: statusLabel(props.meta_filters.status) });
    }
    if (props.meta_filters?.campaign) {
        chips.push({ key: 'campaign', label: props.meta_filters.campaign });
    }
    if (props.meta_filters?.view === 'upcoming_calls') {
        chips.push({ key: 'view', label: 'مكالمات قادمة' });
    }
    return chips;
});

const activeFilterCount = computed(() => activeChips.value.length);

const assigneeInitial = (name) => {
    const t = String(name || '?').trim();
    return t.charAt(0) || '؟';
};

const assigneeAccent = (name) => {
    const n = String(name || '');
    if (n.includes('نبراس')) {
        return { ring: 'ring-violet-200', bg: 'from-violet-500 to-violet-700', light: 'from-violet-50 to-white border-violet-200' };
    }
    if (n.includes('حسين')) {
        return { ring: 'ring-sky-200', bg: 'from-sky-500 to-sky-700', light: 'from-sky-50 to-white border-sky-200' };
    }
    return { ring: 'ring-brand-200', bg: 'from-brand-500 to-brand-700', light: 'from-brand-50 to-white border-brand-200' };
};

function filterQuery(overrides = {}) {
    const params = {
        tab: 'meta_leads',
        meta_owner: props.meta_filters?.owner ?? undefined,
        meta_view: props.meta_filters?.view ?? undefined,
        meta_status: props.meta_filters?.status ?? undefined,
        meta_campaign: props.meta_filters?.campaign ?? undefined,
        ...overrides,
    };

    return Object.fromEntries(
        Object.entries(params).filter(([key, value]) => {
            if (value === undefined || value === null) {
                return false;
            }
            if (key === 'meta_clear') {
                return true;
            }
            if (value === '' && Object.prototype.hasOwnProperty.call(overrides, key)) {
                return true;
            }

            return value !== '';
        }),
    );
}

function navigate(overrides = {}) {
    router.get(route('goods.index'), filterQuery(overrides), {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            showSheet.value = false;
        },
    });
}

function selectAssignee(rep, view = null) {
    navigate({
        meta_owner: rep.id,
        meta_view: view || undefined,
    });
}

function clearAssigneeFilter() {
    const params = { tab: 'meta_leads', meta_clear: 1 };
    if (props.meta_filters?.campaign) {
        params.meta_campaign = props.meta_filters.campaign;
    }
    router.get(route('goods.index'), params, { preserveState: true, replace: true });
}

function applyDraftFilters() {
    navigate({
        meta_status: draftStatus.value,
        meta_campaign: draftCampaign.value,
    });
}

function clearAllFilters() {
    router.get(
        route('goods.index'),
        { tab: 'meta_leads', meta_clear: 1 },
        {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                showSheet.value = false;
            },
        },
    );
}

function chipClass(active) {
    return active
        ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300'
        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50';
}
</script>

<template>
    <div class="space-y-3">
        <!-- بطاقات الفريق — دائماً ظاهرة -->
        <div v-if="meta_assignee_stats?.length" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-600">توزيع الفريق</p>
                <button
                    v-if="meta_filters?.owner || meta_filters?.assignee_defaults_active"
                    type="button"
                    class="text-[11px] font-semibold text-slate-500 underline decoration-slate-300 hover:text-rose-700"
                    @click="clearAssigneeFilter"
                >
                    إلغاء فلتر الموظف
                </button>
            </div>
            <div class="grid gap-2.5 sm:grid-cols-2">
                <button
                    v-for="rep in meta_assignee_stats"
                    :key="`rep-card-${rep.id}`"
                    type="button"
                    class="group relative overflow-hidden rounded-2xl border p-3.5 text-start shadow-sm transition duration-200"
                    :class="[
                        Number(meta_filters?.owner) === rep.id
                            ? `border-2 bg-gradient-to-br ${assigneeAccent(rep.name).light} ring-2 ${assigneeAccent(rep.name).ring}`
                            : 'border-slate-200/90 bg-white hover:border-slate-300 hover:shadow-md',
                    ]"
                    @click="selectAssignee(rep)"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-lg font-black text-white shadow-md"
                            :class="assigneeAccent(rep.name).bg"
                        >
                            {{ assigneeInitial(rep.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900">{{ rep.name }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    class="inline-flex items-center rounded-lg bg-white/90 px-2 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200/80"
                                >
                                    ليدز اليوم
                                    <span class="ms-1 tabular-nums text-slate-900">{{ rep.leads_today }}</span>
                                </span>
                                <span
                                    role="button"
                                    tabindex="0"
                                    class="inline-flex cursor-pointer items-center rounded-lg bg-sky-50 px-2 py-1 text-[11px] font-semibold text-sky-800 ring-1 ring-sky-200/80 transition hover:bg-sky-100"
                                    @click.stop="selectAssignee(rep, 'upcoming_calls')"
                                    @keydown.enter.stop.prevent="selectAssignee(rep, 'upcoming_calls')"
                                >
                                    مكالمات {{ rep.upcoming_calls }}
                                </span>
                            </div>
                        </div>
                    </div>
                </button>
            </div>
            <p v-if="meta_filters?.view === 'upcoming_calls'" class="text-[11px] font-medium text-sky-800">
                عرض المكالمات القادمة فقط
            </p>
        </div>

        <!-- زر فلترة -->
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="inline-flex min-h-10 touch-manipulation items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/50"
                @click="showSheet = true"
            >
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <path d="M4 6h16M7 12h10M10 18h4" stroke-linecap="round" />
                    </svg>
                </span>
                <span>فلترة</span>
                <span
                    v-if="activeFilterCount"
                    class="flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white"
                >
                    {{ activeFilterCount }}
                </span>
            </button>
            <div v-if="activeChips.length" class="flex min-w-0 flex-1 flex-wrap gap-1">
                <span
                    v-for="chip in activeChips"
                    :key="chip.key"
                    class="max-w-full truncate rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700"
                >
                    {{ chip.label }}
                </span>
            </div>
        </div>

        <!-- ورقة فلترة (بدون Modal — النقر يعمل على الجوال) -->
        <Teleport to="body">
            <div
                v-if="showSheet"
                class="mobile-sheet-backdrop z-[80]"
                @click.self="showSheet = false"
            >
                <div class="mobile-sheet-panel mobile-sheet-panel--lg flex max-h-[min(78dvh,32rem)] flex-col p-0" @click.stop>
                    <div class="mobile-sheet-header">
                        <div>
                            <h3 class="mobile-sheet-title">فلترة الليدز</h3>
                            <p class="text-[11px] text-slate-500">الحالة والحملة</p>
                        </div>
                        <button type="button" class="mobile-sheet-close-btn" @click="showSheet = false">إغلاق</button>
                    </div>

                    <div class="mobile-sheet-body min-h-0 flex-1 space-y-5">
                        <section>
                            <h4 class="mb-2 text-xs font-bold text-slate-700">حالة المتابعة</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    type="button"
                                    class="touch-manipulation rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="chipClass(draftStatus === '')"
                                    @click="draftStatus = ''"
                                >
                                    كل الحالات
                                </button>
                                <button
                                    v-for="s in meta_lead_status_options"
                                    :key="`st-pick-${s.value}`"
                                    type="button"
                                    class="touch-manipulation rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="chipClass(draftStatus === s.value)"
                                    @click="draftStatus = s.value"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                        </section>

                        <section>
                            <h4 class="mb-2 text-xs font-bold text-slate-700">الحملة</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    type="button"
                                    class="touch-manipulation rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                    :class="chipClass(draftCampaign === '')"
                                    @click="draftCampaign = ''"
                                >
                                    كل الحملات
                                </button>
                                <button
                                    v-for="c in meta_campaign_options"
                                    :key="`camp-pick-${c}`"
                                    type="button"
                                    class="touch-manipulation max-w-full rounded-full border px-3 py-1.5 text-start text-xs font-semibold transition"
                                    :class="chipClass(draftCampaign === c)"
                                    @click="draftCampaign = c"
                                >
                                    <span class="line-clamp-2">{{ c }}</span>
                                </button>
                            </div>
                            <p v-if="!meta_campaign_options?.length" class="mt-2 text-xs text-slate-400">لا توجد حملات بعد.</p>
                        </section>
                    </div>

                    <div class="mobile-sheet-footer !flex-row">
                        <button
                            type="button"
                            class="min-h-11 flex-1 touch-manipulation rounded-xl bg-brand-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-brand-700"
                            @click="applyDraftFilters"
                        >
                            تطبيق
                        </button>
                        <button
                            type="button"
                            class="min-h-11 touch-manipulation rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="clearAllFilters"
                        >
                            مسح
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
