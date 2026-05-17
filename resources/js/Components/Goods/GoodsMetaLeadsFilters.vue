<script setup>
import Modal from '@/Components/Modal.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    meta_lead_status_options: { type: Array, default: () => [] },
    meta_filters: { type: Object, default: () => ({}) },
    meta_campaign_options: { type: Array, default: () => [] },
    meta_assignee_stats: { type: Array, default: () => [] },
});

const showModal = ref(false);
const draftStatus = ref('');
const draftCampaign = ref('');

watch(showModal, (open) => {
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
        chips.push({ key: 'owner', label: rep?.name || 'موظف', tone: 'brand' });
    }
    if (props.meta_filters?.status) {
        chips.push({ key: 'status', label: statusLabel(props.meta_filters.status), tone: 'emerald' });
    }
    if (props.meta_filters?.campaign) {
        chips.push({ key: 'campaign', label: props.meta_filters.campaign, tone: 'slate' });
    }
    if (props.meta_filters?.view === 'upcoming_calls') {
        chips.push({ key: 'view', label: 'مكالمات قادمة', tone: 'sky' });
    }
    return chips;
});

const activeFilterCount = computed(() => activeChips.value.length);

const assigneeInitial = (name) => {
    const t = String(name || '?').trim();
    return t.charAt(0) || '؟';
};

function navigate(extra = {}) {
    router.get(
        route('goods.index'),
        {
            tab: 'meta_leads',
            meta_status: props.meta_filters?.status || undefined,
            meta_campaign: props.meta_filters?.campaign || undefined,
            meta_owner: props.meta_filters?.owner || undefined,
            meta_view: props.meta_filters?.view || undefined,
            ...extra,
        },
        {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                showModal.value = false;
            },
        },
    );
}

function selectAssignee(rep, view = null) {
    navigate({
        meta_owner: rep.id,
        meta_view: view || undefined,
    });
}

function clearAssigneeFilter() {
    router.get(
        route('goods.index'),
        {
            tab: 'meta_leads',
            meta_clear: 1,
            meta_campaign: props.meta_filters?.campaign || undefined,
        },
        { preserveState: true, replace: true, onSuccess: () => { showModal.value = false; } },
    );
}

function applyDraftFilters() {
    navigate({
        meta_status: draftStatus.value || undefined,
        meta_campaign: draftCampaign.value || undefined,
    });
}

function clearAllFilters() {
    router.get(
        route('goods.index'),
        { tab: 'meta_leads', meta_clear: 1 },
        { preserveState: true, replace: true, onSuccess: () => { showModal.value = false; } },
    );
}

const chipToneClass = (tone) => {
    const map = {
        brand: 'bg-brand-50 text-brand-800 ring-brand-200',
        emerald: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        sky: 'bg-sky-50 text-sky-800 ring-sky-200',
        slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    };
    return map[tone] || map.slate;
};
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
        <button
            type="button"
            class="group relative flex min-h-11 w-full items-center gap-3 overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-l from-white via-white to-slate-50/80 px-3 py-2.5 text-start shadow-sm ring-1 ring-slate-100 transition hover:border-brand-200 hover:shadow-md sm:flex-1 sm:max-w-md"
            @click="showModal = true"
        >
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M4 6h16M7 12h10M10 18h4" stroke-linecap="round" />
                </svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-900">فلترة الليدز</span>
                <span v-if="activeFilterCount" class="mt-0.5 block truncate text-[11px] text-slate-500">
                    {{ activeChips.map((c) => c.label).join(' · ') }}
                </span>
                <span v-else class="mt-0.5 block text-[11px] text-slate-400">الموظف، الحالة، الحملة…</span>
            </span>
            <span
                v-if="activeFilterCount"
                class="flex h-6 min-w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white"
            >
                {{ activeFilterCount }}
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:text-brand-600"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <Modal :show="showModal" max-width="lg" @close="showModal = false">
            <div class="relative overflow-hidden rounded-t-2xl sm:rounded-2xl">
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-brand-500/10 via-sky-500/5 to-transparent"
                />

                <div class="relative border-b border-slate-100 px-4 pb-4 pt-5 sm:px-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-600">ليدز ميتا</p>
                            <h2 class="mt-0.5 text-lg font-bold text-slate-900">فلترة وتوزيع الفريق</h2>
                            <p class="mt-1 text-xs text-slate-500">اختر الموظف والحالة ثم طبّق — أو اضغط بطاقة الموظف مباشرة</p>
                        </div>
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200"
                            aria-label="إغلاق"
                            @click="showModal = false"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="activeChips.length" class="mt-3 flex flex-wrap gap-1.5">
                        <span
                            v-for="chip in activeChips"
                            :key="chip.key"
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="chipToneClass(chip.tone)"
                        >
                            {{ chip.label }}
                        </span>
                    </div>
                </div>

                <div class="max-h-[min(70vh,32rem)] space-y-5 overflow-y-auto px-4 py-4 sm:px-6">
                    <section v-if="meta_assignee_stats?.length">
                        <h3 class="text-xs font-bold text-slate-700">توزيع الفريق</h3>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="rep in meta_assignee_stats"
                                :key="`filter-rep-${rep.id}`"
                                type="button"
                                class="group relative overflow-hidden rounded-2xl border p-3 text-start transition"
                                :class="
                                    Number(meta_filters?.owner) === rep.id
                                        ? 'border-brand-400 bg-gradient-to-br from-brand-50 to-white shadow-sm ring-2 ring-brand-200'
                                        : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm'
                                "
                                @click="selectAssignee(rep)"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-base font-black shadow-inner"
                                        :class="
                                            Number(meta_filters?.owner) === rep.id
                                                ? 'bg-brand-600 text-white'
                                                : 'bg-slate-100 text-slate-700 group-hover:bg-brand-100 group-hover:text-brand-800'
                                        "
                                    >
                                        {{ assigneeInitial(rep.name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-bold text-slate-900">{{ rep.name }}</p>
                                        <p class="mt-1 text-[11px] text-slate-600">
                                            ليدز اليوم:
                                            <span class="font-bold tabular-nums text-slate-900">{{ rep.leads_today }}</span>
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-semibold text-sky-700 hover:text-sky-900"
                                            @click.stop="selectAssignee(rep, 'upcoming_calls')"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7H3v12a2 2 0 002 2z" stroke-linecap="round" />
                                            </svg>
                                            مكالمات قادمة: {{ rep.upcoming_calls }}
                                        </button>
                                    </div>
                                </div>
                            </button>
                        </div>
                        <button
                            v-if="meta_filters?.owner || meta_filters?.assignee_defaults_active"
                            type="button"
                            class="mt-2 w-full rounded-xl border border-dashed border-slate-300 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-800"
                            @click="clearAssigneeFilter"
                        >
                            إلغاء فلتر الموظف
                        </button>
                    </section>

                    <section>
                        <h3 class="text-xs font-bold text-slate-700">حالة المتابعة</h3>
                        <select
                            v-model="draftStatus"
                            class="mt-2 min-h-11 w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">كل حالات المتابعة</option>
                            <option v-for="s in meta_lead_status_options" :key="`modal-st-${s.value}`" :value="s.value">
                                {{ s.label }}
                            </option>
                        </select>
                    </section>

                    <section>
                        <h3 class="text-xs font-bold text-slate-700">الحملة</h3>
                        <select
                            v-model="draftCampaign"
                            class="mt-2 min-h-11 w-full rounded-xl border-slate-200 bg-slate-50/50 text-sm shadow-sm focus:border-brand-400 focus:ring-brand-400"
                        >
                            <option value="">كل الحملات</option>
                            <option v-for="c in meta_campaign_options" :key="`modal-c-${c}`" :value="c">{{ c }}</option>
                        </select>
                    </section>

                    <p v-if="meta_filters?.view === 'upcoming_calls'" class="rounded-xl bg-sky-50 px-3 py-2 text-[11px] font-medium text-sky-800 ring-1 ring-sky-100">
                        عرض المكالمات القادمة فقط — مرتبة حسب الموعد
                    </p>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-4 sm:flex-row sm:px-6">
                    <button
                        type="button"
                        class="min-h-11 flex-1 rounded-xl bg-gradient-to-l from-brand-600 to-brand-700 px-4 text-sm font-bold text-white shadow-md transition hover:from-brand-700 hover:to-brand-800"
                        @click="applyDraftFilters"
                    >
                        تطبيق الفلتر
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="clearAllFilters"
                    >
                        مسح الكل
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
