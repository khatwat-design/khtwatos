<script setup>
import GoodsLeadPhoneActions from '@/Components/Goods/GoodsLeadPhoneActions.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    meta_leads: { type: Array, default: () => [] },
    meta_lead_status_options: { type: Array, default: () => [] },
    meta_filters: { type: Object, default: () => ({}) },
    meta_campaign_options: { type: Array, default: () => [] },
    meta_analytics: { type: Object, default: () => ({}) },
    meta_assignee_stats: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
    meta_leads_webhook_configured: { type: Boolean, default: false },
});

const search = ref('');
const editingId = ref(null);
const showFilterPanel = ref(false);
const filterPanelRef = ref(null);

const editForm = useForm({
    workflow_status: 'new',
    owner_user_id: null,
    team_notes: '',
    probability_label: '',
    reason_label: '',
    outcome_label: '',
    next_contact_date: '',
    next_call_at: '',
    has_whatsapp: true,
    note: '',
});

function metaQuery(extra = {}) {
    return {
        tab: 'meta_leads',
        meta_status: props.meta_filters?.status || undefined,
        meta_campaign: props.meta_filters?.campaign || undefined,
        meta_owner: props.meta_filters?.owner || undefined,
        meta_view: props.meta_filters?.view || undefined,
        meta_date_from: props.meta_filters?.date_from || undefined,
        meta_date_to: props.meta_filters?.date_to || undefined,
        ...extra,
    };
}

function onMetaStatusFilter(event) {
    router.get(route('goods.index'), metaQuery({ meta_status: event.target.value || undefined }), {
        preserveState: true,
        replace: true,
    });
}

function onMetaCampaignFilter(event) {
    router.get(route('goods.index'), metaQuery({ meta_campaign: event.target.value || undefined }), {
        preserveState: true,
        replace: true,
    });
}

function onMetaDateFromFilter(event) {
    router.get(route('goods.index'), metaQuery({ meta_date_from: event.target.value || undefined }), {
        preserveState: true,
        replace: true,
    });
}

function onMetaDateToFilter(event) {
    router.get(route('goods.index'), metaQuery({ meta_date_to: event.target.value || undefined }), {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    router.get(
        route('goods.index'),
        { tab: 'meta_leads', meta_clear: 1 },
        { preserveState: true, replace: true },
    );
    showFilterPanel.value = false;
}

function toggleFilterPanel() {
    showFilterPanel.value = !showFilterPanel.value;
}

function handleClickOutside(e) {
    if (filterPanelRef.value && !filterPanelRef.value.contains(e.target)) {
        showFilterPanel.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const activeFilterCount = computed(() => {
    let count = 0;
    if (props.meta_filters?.status) count++;
    if (props.meta_filters?.campaign) count++;
    if (props.meta_filters?.date_from) count++;
    if (props.meta_filters?.date_to) count++;
    return count;
});

function toDatetimeLocal(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch {
        return '';
    }
}

const filteredLeads = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.meta_leads || [];
    }
    return (props.meta_leads || []).filter((lead) => {
        const hay = [
            lead.full_name,
            lead.phone,
            lead.campaign_name,
            lead.ad_name,
            lead.team_notes,
            lead.probability_label,
            lead.outcome_label,
            lead.city,
            lead.form_answers?.project_type,
            lead.form_answers?.budget,
            lead.form_answers?.brand,
            lead.form_answers?.products,
        ]
            .join(' ')
            .toLowerCase();
        return hay.includes(q);
    });
});

function statusLabel(status) {
    return props.meta_lead_status_options?.find((s) => s.value === status)?.label || status;
}

function statusClass(status) {
    const map = {
        kull:       'bg-slate-100 text-slate-700',
        sakhin:     'bg-orange-100 text-orange-800',
        dafae:        'bg-orange-100 text-orange-800',
        barid:        'bg-sky-100 text-sky-800',
        moutafaq:     'bg-emerald-100 text-emerald-800',
        lam_yattasel: 'bg-rose-100 text-rose-800',
        moshtarek:    'bg-indigo-100 text-indigo-800',
        istihqaq:     'bg-amber-100 text-amber-800',
        istihdaf:     'bg-teal-100 text-teal-800',
        matabaa:      'bg-sky-100 text-sky-800',
        mohtamal:     'bg-amber-100 text-amber-800',
        khutwat:      'bg-emerald-100 text-emerald-800',
        khat:         'bg-slate-100 text-slate-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function formatDt(iso) {
    if (!iso) return null;
    try {
        return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return null;
    }
}

function openEdit(lead) {
    editingId.value = lead.id;
    editForm.workflow_status = lead.workflow_status;
    editForm.owner_user_id = lead.owner?.id ?? null;
    editForm.team_notes = lead.team_notes || '';
    editForm.probability_label = lead.probability_label || '';
    editForm.reason_label = lead.reason_label || '';
    editForm.outcome_label = lead.outcome_label || '';
    editForm.next_contact_date = lead.next_contact_date || '';
    editForm.next_call_at = toDatetimeLocal(lead.next_call_at);
    editForm.has_whatsapp = lead.has_whatsapp !== false;
    editForm.note = '';
}

function submitEdit(leadId) {
    editForm.patch(route('goods.meta-leads.update', leadId), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
}
</script>

<template>
    <div class="space-y-4" data-tour-page-anchor>
        <div
            v-if="!meta_leads_webhook_configured"
            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
        >
            مزامنة Google Sheet غير مفعّلة بعد — أضف <code class="rounded bg-amber-100 px-1">GOODS_META_LEADS_WEBHOOK_SECRET</code> في السيرفر ثم ربط Apps Script.
        </div>

        <!-- بطاقات الإحصائيات المحسّنة -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="group relative overflow-hidden rounded-2xl border border-slate-100 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm transition hover:shadow-md">
                <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-slate-100/60" />
                <div class="relative">
                    <p class="text-[11px] font-semibold text-slate-500">إجمالي العملاء</p>
                    <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-slate-900">{{ meta_analytics?.total ?? 0 }}</p>
                </div>
            </div>
            <div class="group relative overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-br from-white to-sky-50 p-4 shadow-sm transition hover:shadow-md">
                <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-sky-100/60" />
                <div class="relative">
                    <p class="text-[11px] font-semibold text-sky-600">متابعة</p>
                    <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-sky-700">{{ meta_analytics?.following_count ?? 0 }}</p>
                </div>
            </div>
            <div class="group relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-4 shadow-sm transition hover:shadow-md">
                <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-amber-100/60" />
                <div class="relative">
                    <p class="text-[11px] font-semibold text-amber-600">محتمل</p>
                    <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-amber-700">{{ meta_analytics?.potential_count ?? 0 }}</p>
                </div>
            </div>
            <div class="group relative overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-4 shadow-sm transition hover:shadow-md">
                <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-emerald-100/60" />
                <div class="relative">
                    <p class="text-[11px] font-semibold text-emerald-600">نسبة الإغلاق</p>
                    <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-emerald-700">{{ meta_analytics?.conversion_rate ?? 0 }}%</p>
                </div>
            </div>
        </div>

        <!-- شريط البحث + أزرار الفلتر -->
        <div class="flex items-center gap-2">
            <div class="relative flex-1">
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input
                    v-model="search"
                    type="text"
                    class="min-h-11 w-full rounded-xl border border-gray-200 bg-white pr-10 pl-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-brand-400 focus:ring-1 focus:ring-brand-400"
                    placeholder="بحث بالاسم، الهاتف، المدينة، الحملة…"
                />
            </div>

            <!-- زر الفلتر -->
            <div ref="filterPanelRef" class="relative">
                <button
                    type="button"
                    class="relative flex min-h-11 min-w-11 items-center justify-center rounded-xl border bg-white shadow-sm transition"
                    :class="activeFilterCount > 0 ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    @click.stop="toggleFilterPanel"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    <span
                        v-if="activeFilterCount > 0"
                        class="absolute -left-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white"
                    >
                        {{ activeFilterCount }}
                    </span>
                </button>

                <!-- نافذة الفلتر المنبثقة -->
                <Transition
                    enter-active-class="transition ease-out duration-150"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-100"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="showFilterPanel"
                        class="absolute left-0 top-full z-50 mt-2 w-72 rounded-2xl border border-gray-200 bg-white p-4 shadow-xl"
                        dir="rtl"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-gray-900">الفلاتر</h3>
                            <button
                                v-if="activeFilterCount > 0"
                                type="button"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700"
                                @click="clearFilters"
                            >
                                مسح الكل
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">حالة المتابعة</label>
                                <select
                                    class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                    :value="meta_filters?.status || ''"
                                    @change="onMetaStatusFilter"
                                >
                                    <option value="">كل الحالات</option>
                                    <option v-for="s in meta_lead_status_options" :key="`fp-${s.value}`" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-gray-500">الحملة</label>
                                <select
                                    class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                    :value="meta_filters?.campaign || ''"
                                    @change="onMetaCampaignFilter"
                                >
                                    <option value="">كل الحملات</option>
                                    <option v-for="c in meta_campaign_options" :key="`fc-${c}`" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-gray-500">من تاريخ</label>
                                    <input
                                        type="date"
                                        class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="meta_filters?.date_from || ''"
                                        @change="onMetaDateFromFilter"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-gray-500">إلى تاريخ</label>
                                    <input
                                        type="date"
                                        class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="meta_filters?.date_to || ''"
                                        @change="onMetaDateToFilter"
                                    />
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-4 flex min-h-10 w-full items-center justify-center rounded-xl bg-gray-900 text-sm font-semibold text-white transition active:bg-gray-800"
                            @click="showFilterPanel = false"
                        >
                            تطبيق
                        </button>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- موبايل: بطاقات العملاء المحتملين -->
        <div class="space-y-3 md:hidden">
            <template v-for="lead in filteredLeads" :key="`meta-card-${lead.id}`">
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <!-- الصف الرئيسي: الأفاتار + الاسم + الحالة -->
                    <div class="flex items-center gap-3 p-4 pb-3">
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-blue-600 text-sm font-bold text-white shadow-md"
                        >
                            {{ String(lead.full_name || '?').trim().charAt(0) || '؟' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-[15px] font-bold text-gray-900">{{ lead.full_name || '—' }}</h3>
                                <span
                                    class="inline-flex shrink-0 items-center rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight"
                                    :class="statusClass(lead.workflow_status)"
                                >
                                    {{ statusLabel(lead.workflow_status) }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <GoodsLeadPhoneActions
                                    :phone="lead.phone"
                                    :meta-lead-id="lead.id"
                                    :customer-name="lead.full_name"
                                    :has-whatsapp="lead.has_whatsapp"
                                    compact
                                />
                                <span v-if="lead.platform" class="text-[10px] font-semibold uppercase text-gray-400">{{ lead.platform }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- معلومات مختصرة -->
                    <div class="grid grid-cols-2 gap-2 border-t border-gray-100 px-4 py-3">
                        <div v-if="lead.owner?.name" class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span class="truncate text-xs font-medium text-gray-700">{{ lead.owner.name }}</span>
                        </div>
                        <div v-if="lead.city" class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="truncate text-xs text-gray-600">{{ lead.city }}</span>
                        </div>
                        <div v-if="lead.form_answers?.project_type" class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                            <span class="truncate text-xs text-gray-600">{{ lead.form_answers.project_type }}</span>
                        </div>
                        <div v-if="lead.form_answers?.budget" class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <span class="truncate text-xs font-medium text-emerald-700">{{ lead.form_answers.budget }}</span>
                        </div>
                        <div v-if="lead.form_answers?.brand" class="col-span-2 flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <span class="truncate text-xs text-gray-600">{{ lead.form_answers.brand }}</span>
                        </div>
                        <div v-if="lead.next_call_at" class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span class="truncate text-xs font-semibold text-sky-700">{{ formatDt(lead.next_call_at) }}</span>
                        </div>
                        <div v-if="lead.campaign_name" class="col-span-2 flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <span class="truncate text-xs text-gray-600">{{ lead.campaign_name }}</span>
                        </div>
                    </div>

                    <!-- أزرار الإجراءات -->
                    <div class="flex gap-2 border-t border-gray-100 px-4 py-3">
                        <button
                            v-if="editingId !== lead.id"
                            type="button"
                            class="flex min-h-11 flex-1 items-center justify-center rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 active:bg-brand-800"
                            @click="openEdit(lead)"
                        >
                            تعديل المتابعة
                        </button>
                        <button
                            type="button"
                            class="flex min-h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition active:bg-gray-50"
                            @click="editingId = editingId === lead.id ? null : lead.id"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                    </div>

                    <!-- نموذج التعديل (يتوسع عند الضغط) -->
                    <div v-if="editingId === lead.id" class="border-t border-gray-100 bg-gray-50/50 px-4 py-4">
                        <form class="space-y-3" @submit.prevent="submitEdit(lead.id)">
                            <div>
                                <InputLabel value="حالة المتابعة" />
                                <select v-model="editForm.workflow_status" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 text-sm">
                                    <option v-for="s in meta_lead_status_options" :key="`mc-s-${s.value}`" :value="s.value">
                                        {{ s.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="المسؤول" />
                                <select v-model="editForm.owner_user_id" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 text-sm">
                                    <option :value="null">—</option>
                                    <option v-for="o in owners" :key="`mc-o-${o.id}`" :value="o.id">{{ o.name }}</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel value="إحتمالية العميل" />
                                    <TextInput v-model="editForm.probability_label" class="mt-1 w-full text-sm" />
                                </div>
                                <div>
                                    <InputLabel value="النتيجة" />
                                    <TextInput v-model="editForm.outcome_label" class="mt-1 w-full text-sm" />
                                </div>
                            </div>
                            <div>
                                <InputLabel value="موعد المكالمة القادمة" />
                                <input
                                    v-model="editForm.next_call_at"
                                    type="datetime-local"
                                    class="mt-1 min-h-11 w-full rounded-xl border border-gray-300 px-3 text-sm text-gray-900 shadow-sm"
                                />
                            </div>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-800">
                                <input v-model="editForm.has_whatsapp" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>يوجد واتساب على هذا الرقم</span>
                            </label>
                            <div>
                                <InputLabel value="ملاحظات الفريق" />
                                <textarea v-model="editForm.team_notes" rows="2" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                            </div>
                            <div class="flex gap-2">
                                <PrimaryButton class="flex-1 justify-center" :disabled="editForm.processing">حفظ</PrimaryButton>
                                <button type="button" class="min-h-11 rounded-xl px-3 text-sm text-gray-600" @click="editingId = null">
                                    إلغاء
                                </button>
                            </div>
                        </form>
                    </div>
                </article>
            </template>
            <p
                v-if="!filteredLeads.length"
                class="rounded-2xl border border-dashed border-gray-200 bg-white/80 px-4 py-10 text-center text-sm text-gray-500"
            >
                لا يوجد عملاء محتملون بعد. بعد ربط Apps Script ستظهر هنا تلقائياً.
            </p>
        </div>

        <!-- سطح المكتب والتابلت: جدول -->
        <div class="ui-card hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-slate-50/90">
                    <tr>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">التاريخ</th>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">العميل</th>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">الحملة</th>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">الإجابات</th>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">المتابعة</th>
                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500">إجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="lead in filteredLeads" :key="lead.id">
                        <tr class="align-top hover:bg-slate-50/50">
                            <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ formatDt(lead.lead_created_at) || '—' }}</td>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-gray-900">{{ lead.full_name || '—' }}</p>
                                <GoodsLeadPhoneActions
                                    :phone="lead.phone"
                                    :meta-lead-id="lead.id"
                                    :customer-name="lead.full_name"
                                    :has-whatsapp="lead.has_whatsapp"
                                    compact
                                />
                                <p v-if="lead.platform" class="mt-0.5 text-[10px] uppercase text-gray-400">{{ lead.platform }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700">
                                <p class="font-medium">{{ lead.campaign_name || '—' }}</p>
                                <p class="text-gray-500">{{ lead.adset_name }}</p>
                                <p class="text-gray-400">{{ lead.ad_name }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700 max-w-[14rem]">
                                <p v-if="lead.city" class="text-gray-500"><span class="font-medium">المدينة:</span> {{ lead.city }}</p>
                                <p v-if="lead.form_answers?.project_type" class="mt-0.5 text-gray-500"><span class="font-medium">المشروع:</span> {{ lead.form_answers.project_type }}</p>
                                <p v-if="lead.form_answers?.budget" class="mt-0.5 text-gray-500"><span class="font-medium">الميزانية:</span> {{ lead.form_answers.budget }}</p>
                                <p v-if="lead.form_answers?.brand" class="mt-0.5 text-gray-500"><span class="font-medium">البراند:</span> {{ lead.form_answers.brand }}</p>
                                <p v-if="lead.form_answers?.products" class="mt-0.5 line-clamp-1 text-gray-500">{{ lead.form_answers.products }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusClass(lead.workflow_status)">
                                    {{ statusLabel(lead.workflow_status) }}
                                </span>
                                <p v-if="lead.probability_label" class="mt-1 text-[10px] text-gray-500">{{ lead.probability_label }}</p>
                                <p v-if="lead.outcome_label" class="text-[10px] text-gray-500">{{ lead.outcome_label }}</p>
                                <p v-if="lead.owner?.name" class="mt-1 text-[10px] text-gray-600">مسؤول: {{ lead.owner.name }}</p>
                                <p v-if="lead.next_call_at" class="mt-1 text-[10px] font-semibold text-sky-700">
                                    مكالمة: {{ formatDt(lead.next_call_at) }}
                                </p>
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-brand-200 bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-800 hover:bg-brand-100"
                                    @click="openEdit(lead)"
                                >
                                    تعديل
                                </button>
                            </td>
                        </tr>
                        <tr v-if="editingId === lead.id" class="bg-brand-50/30">
                            <td colspan="6" class="px-3 py-3">
                                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitEdit(lead.id)">
                                    <div>
                                        <InputLabel value="حالة المتابعة" />
                                        <select v-model="editForm.workflow_status" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                            <option v-for="s in meta_lead_status_options" :key="`es-${s.value}`" :value="s.value">{{ s.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="المسؤول" />
                                        <select v-model="editForm.owner_user_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                            <option :value="null">—</option>
                                            <option v-for="o in owners" :key="`own-${o.id}`" :value="o.id">{{ o.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="إحتمالية العميل (نص الشيت)" />
                                        <TextInput v-model="editForm.probability_label" class="mt-1 w-full text-sm" />
                                    </div>
                                    <div>
                                        <InputLabel value="النتيجة (نص الشيت)" />
                                        <TextInput v-model="editForm.outcome_label" class="mt-1 w-full text-sm" />
                                    </div>
                                    <div>
                                        <InputLabel value="السبب" />
                                        <TextInput v-model="editForm.reason_label" class="mt-1 w-full text-sm" />
                                    </div>
                                    <div>
                                        <InputLabel value="موعد المكالمة القادمة" />
                                        <input
                                            v-model="editForm.next_call_at"
                                            type="datetime-local"
                                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 md:col-span-2">
                                        <input v-model="editForm.has_whatsapp" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                                        <span>يوجد واتساب على هذا الرقم</span>
                                    </label>
                                    <div class="md:col-span-2">
                                        <InputLabel value="ملاحظات الفريق" />
                                        <textarea v-model="editForm.team_notes" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm" />
                                    </div>
                                    <div class="flex gap-2 md:col-span-2">
                                        <PrimaryButton :disabled="editForm.processing">حفظ</PrimaryButton>
                                        <button type="button" class="text-sm text-gray-600" @click="editingId = null">إلغاء</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!filteredLeads.length">
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">
                            لا يوجد عملاء محتملون بعد. بعد ربط Apps Script ستظهر هنا تلقائياً.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
