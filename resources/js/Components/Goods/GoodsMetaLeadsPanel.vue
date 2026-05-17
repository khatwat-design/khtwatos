<script setup>
import GoodsLeadPhoneActions from '@/Components/Goods/GoodsLeadPhoneActions.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const editForm = useForm({
    workflow_status: 'new',
    owner_user_id: null,
    team_notes: '',
    probability_label: '',
    reason_label: '',
    outcome_label: '',
    next_contact_date: '',
    next_call_at: '',
    note: '',
});

function metaQuery(extra = {}) {
    return {
        tab: 'meta_leads',
        meta_status: props.meta_filters?.status || undefined,
        meta_campaign: props.meta_filters?.campaign || undefined,
        meta_owner: props.meta_filters?.owner || undefined,
        meta_view: props.meta_filters?.view || undefined,
        ...extra,
    };
}

function filterByAssignee(assignee, view = null) {
    router.get(
        route('goods.index'),
        metaQuery({
            meta_owner: assignee.id,
            meta_view: view || undefined,
        }),
        { preserveState: true, replace: true },
    );
}

function clearAssigneeFilter() {
    router.get(route('goods.index'), metaQuery({ meta_owner: undefined, meta_view: undefined }), {
        preserveState: true,
        replace: true,
    });
}

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
        new: 'bg-emerald-100 text-emerald-800',
        following: 'bg-sky-100 text-sky-800',
        potential: 'bg-amber-100 text-amber-800',
        unlikely: 'bg-rose-100 text-rose-800',
        qualified: 'bg-indigo-100 text-indigo-800',
        won: 'bg-emerald-100 text-emerald-700',
        lost: 'bg-slate-100 text-slate-700',
        rejected: 'bg-rose-100 text-rose-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700';
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

function formatDt(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return '—';
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

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[11px] font-semibold text-slate-500">إجمالي الليدز</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ meta_analytics?.total ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[11px] font-semibold text-slate-500">قيد المتابعة</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-sky-700">{{ meta_analytics?.following_count ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[11px] font-semibold text-slate-500">عملاء محتملون</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ meta_analytics?.potential_count ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                <p class="text-[11px] font-semibold text-slate-500">نسبة الإغلاق (بيع)</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">{{ meta_analytics?.conversion_rate ?? 0 }}%</p>
            </div>
        </div>

        <div v-if="meta_assignee_stats?.length" class="flex flex-col gap-2">
            <p class="text-xs font-semibold text-slate-600">توزيع الفريق — اضغط للفلترة</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="rep in meta_assignee_stats"
                    :key="`rep-${rep.id}`"
                    type="button"
                    class="flex min-w-[9rem] flex-col rounded-xl border px-3 py-2 text-start text-xs transition"
                    :class="
                        Number(meta_filters?.owner) === rep.id
                            ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300'
                            : 'border-slate-200 bg-white hover:bg-slate-50'
                    "
                    @click="filterByAssignee(rep)"
                >
                    <span class="font-bold text-slate-900">{{ rep.name }}</span>
                    <span class="mt-1 text-slate-600">ليدز اليوم: {{ rep.leads_today }}</span>
                    <button
                        type="button"
                        class="mt-1 text-start font-semibold text-sky-700 underline decoration-sky-300"
                        @click.stop="filterByAssignee(rep, 'upcoming_calls')"
                    >
                        مكالمات قادمة: {{ rep.upcoming_calls }}
                    </button>
                </button>
                <button
                    v-if="meta_filters?.owner"
                    type="button"
                    class="self-center rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50"
                    @click="clearAssigneeFilter"
                >
                    إلغاء فلتر الموظف
                </button>
            </div>
            <p v-if="meta_filters?.view === 'upcoming_calls'" class="text-[11px] text-sky-800">
                عرض المكالمات القادمة فقط — مرتبة حسب الموعد
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <select
                class="min-h-11 rounded-xl border border-gray-300 bg-white px-3 text-sm"
                :value="meta_filters?.status || ''"
                @change="onMetaStatusFilter"
            >
                <option value="">كل حالات المتابعة</option>
                <option v-for="s in meta_lead_status_options" :key="`mf-${s.value}`" :value="s.value">{{ s.label }}</option>
            </select>
            <select
                class="min-h-11 rounded-xl border border-gray-300 bg-white px-3 text-sm sm:min-w-[12rem]"
                :value="meta_filters?.campaign || ''"
                @change="onMetaCampaignFilter"
            >
                <option value="">كل الحملات</option>
                <option v-for="c in meta_campaign_options" :key="`mc-${c}`" :value="c">{{ c }}</option>
            </select>
            <TextInput v-model="search" class="min-h-11 flex-1 rounded-xl text-sm" placeholder="بحث بالاسم، الهاتف، الحملة…" />
        </div>

        <!-- موبايل: بطاقات (نفس أسلوب عملاء البضاعة) -->
        <div class="space-y-3 md:hidden">
            <template v-for="lead in filteredLeads" :key="`meta-card-${lead.id}`">
                <article class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm ring-1 ring-gray-100/80">
                    <div class="flex gap-3 p-4">
                        <div class="relative shrink-0">
                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-100 bg-gradient-to-br from-sky-50 to-white text-lg font-black text-sky-700 shadow-inner ring-2 ring-white"
                            >
                                {{ String(lead.full_name || '?').trim().charAt(0) || '؟' }}
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold leading-snug text-gray-900">
                                {{ lead.full_name || '—' }}
                            </h3>
                            <GoodsLeadPhoneActions :phone="lead.phone" />
                            <p v-if="lead.platform" class="mt-0.5 text-[10px] font-semibold uppercase text-gray-400">
                                {{ lead.platform }}
                            </p>
                            <span
                                class="mt-2 inline-flex max-w-full items-center rounded-lg px-2.5 py-1 text-[11px] font-semibold leading-tight ring-1 ring-black/5"
                                :class="statusClass(lead.workflow_status)"
                            >
                                {{ statusLabel(lead.workflow_status) }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 border-t border-gray-50 bg-gradient-to-b from-gray-50/40 to-white px-3 pb-2 pt-2">
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">التاريخ</p>
                            <p class="mt-0.5 text-xs font-semibold text-gray-800">{{ formatDt(lead.lead_created_at) }}</p>
                        </div>
                        <div class="rounded-xl bg-white/90 px-2.5 py-2 shadow-sm ring-1 ring-gray-100/80">
                            <p class="text-[10px] font-semibold text-gray-400">المسؤول</p>
                            <p class="mt-0.5 truncate text-xs font-semibold text-gray-800">{{ lead.owner?.name || '—' }}</p>
                        </div>
                    </div>
                    <div v-if="lead.next_call_at" class="border-t border-sky-50 bg-sky-50/40 px-3 py-2">
                        <p class="text-[10px] font-semibold text-sky-700">موعد المكالمة القادمة</p>
                        <p class="mt-0.5 text-xs font-semibold text-sky-900">{{ formatDt(lead.next_call_at) }}</p>
                    </div>
                    <div v-if="lead.campaign_name || lead.ad_name" class="border-t border-gray-50 px-3 py-2">
                        <p class="text-[10px] font-semibold text-gray-400">الحملة</p>
                        <p class="mt-0.5 text-xs font-medium text-gray-800">{{ lead.campaign_name || '—' }}</p>
                        <p v-if="lead.adset_name" class="text-[11px] text-gray-500">{{ lead.adset_name }}</p>
                        <p v-if="lead.ad_name" class="text-[11px] text-gray-400">{{ lead.ad_name }}</p>
                    </div>
                    <div
                        v-if="lead.monthly_orders_answer || lead.goal_answer || lead.reason_label"
                        class="border-t border-gray-50 px-3 py-2 text-xs text-gray-700"
                    >
                        <p v-if="lead.monthly_orders_answer">
                            <span class="font-semibold text-gray-500">طلبات شهرية:</span>
                            {{ lead.monthly_orders_answer }}
                        </p>
                        <p v-if="lead.goal_answer" class="mt-1 line-clamp-3">
                            <span class="font-semibold text-gray-500">الهدف:</span>
                            {{ lead.goal_answer }}
                        </p>
                        <p v-if="lead.reason_label" class="mt-1 text-gray-500">{{ lead.reason_label }}</p>
                    </div>
                    <div
                        v-if="lead.probability_label || lead.outcome_label || lead.team_notes"
                        class="border-t border-gray-50 px-3 py-2 text-[11px] text-gray-600"
                    >
                        <p v-if="lead.probability_label">إحتمالية: {{ lead.probability_label }}</p>
                        <p v-if="lead.outcome_label">النتيجة: {{ lead.outcome_label }}</p>
                        <p v-if="lead.team_notes" class="mt-1 line-clamp-2 text-gray-500">{{ lead.team_notes }}</p>
                    </div>
                    <div class="space-y-3 border-t border-gray-100 px-3 pb-4 pt-3">
                        <button
                            v-if="editingId !== lead.id"
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-brand-300 bg-brand-50 px-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-100"
                            @click="openEdit(lead)"
                        >
                            تعديل المتابعة
                        </button>
                        <form v-else class="space-y-3" @submit.prevent="submitEdit(lead.id)">
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
                            <div>
                                <InputLabel value="إحتمالية العميل" />
                                <TextInput v-model="editForm.probability_label" class="mt-1 w-full text-sm" />
                            </div>
                            <div>
                                <InputLabel value="النتيجة" />
                                <TextInput v-model="editForm.outcome_label" class="mt-1 w-full text-sm" />
                            </div>
                            <div>
                                <InputLabel value="موعد المكالمة القادمة" />
                                <input
                                    v-model="editForm.next_call_at"
                                    type="datetime-local"
                                    class="mt-1 min-h-11 w-full rounded-xl border border-gray-300 px-3 text-sm text-gray-900 shadow-sm"
                                />
                            </div>
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
                لا توجد ليدز ميتا بعد. بعد ربط Apps Script ستظهر هنا تلقائياً.
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
                            <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ formatDt(lead.lead_created_at) }}</td>
                            <td class="px-3 py-2">
                                <p class="font-semibold text-gray-900">{{ lead.full_name || '—' }}</p>
                                <GoodsLeadPhoneActions :phone="lead.phone" compact />
                                <p v-if="lead.platform" class="mt-0.5 text-[10px] uppercase text-gray-400">{{ lead.platform }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700">
                                <p class="font-medium">{{ lead.campaign_name || '—' }}</p>
                                <p class="text-gray-500">{{ lead.adset_name }}</p>
                                <p class="text-gray-400">{{ lead.ad_name }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700 max-w-[14rem]">
                                <p v-if="lead.monthly_orders_answer"><span class="text-gray-500">طلبات:</span> {{ lead.monthly_orders_answer }}</p>
                                <p v-if="lead.goal_answer" class="mt-0.5 line-clamp-2">{{ lead.goal_answer }}</p>
                                <p v-if="lead.reason_label" class="mt-0.5 text-gray-500">{{ lead.reason_label }}</p>
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
                            لا توجد ليدز ميتا بعد. بعد ربط Apps Script ستظهر هنا تلقائياً.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
