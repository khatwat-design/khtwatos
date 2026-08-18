<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    canTriage: { type: Boolean, default: false },
    team_users: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ statuses: [], priorities: [], categories: [] }) },
});

const page = usePage();

const statusLabels = {
    open: 'مفتوح',
    in_progress: 'قيد المعالجة',
    waiting: 'بانتظار رد',
    resolved: 'تم الحل',
    closed: 'مغلق',
};
const priorityLabels = { low: 'منخفض', normal: 'عادي', high: 'مرتفع', critical: 'حرج' };
const categoryLabels = {
    bug: 'مشكلة برمجية',
    feature: 'طلب ميزة',
    access: 'صلاحيات/وصول',
    billing: 'فواتير/اشتراكات',
    performance: 'بطء/أداء',
    ui_ux: 'تصميم/واجهة',
    integration: 'تكامل/API',
    data: 'بيانات/تقارير',
    security: 'أمان/خصوصية',
    notifications: 'إشعارات',
    backup: 'نسخ احتياطي',
    mobile: 'تطبيق الجوال',
    training: 'تدريب/مساعدة',
    task_management: 'إدارة مهام',
    general: 'عام',
};

function statusClass(status) {
    switch (status) {
        case 'open':
            return 'bg-rose-100 text-rose-700 ring-1 ring-rose-200';
        case 'in_progress':
            return 'bg-amber-100 text-amber-800 ring-1 ring-amber-200';
        case 'waiting':
            return 'bg-sky-100 text-sky-800 ring-1 ring-sky-200';
        case 'resolved':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200';
        case 'closed':
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function priorityClass(priority) {
    if (priority === 'critical') return 'bg-rose-600 text-white';
    if (priority === 'high') return 'bg-orange-500 text-white';
    if (priority === 'normal') return 'bg-slate-200 text-slate-800';
    return 'bg-slate-100 text-slate-600';
}

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ar-IQ', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function fmtDuration(s) {
    const total = Math.max(0, Number(s || 0));
    if (!total) return '—';
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    if (h > 0) return `${h}س ${m}د`;
    return `${m}د`;
}

const replyForm = useForm({ body: '', is_internal: false });

function submitReply() {
    replyForm.post(route('tickets.messages.store', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => replyForm.reset('body'),
    });
}

const updateForm = useForm({
    status: props.ticket.status,
    priority: props.ticket.priority,
    category: props.ticket.category,
    assignee_id: props.ticket.assignee?.id || null,
    client_id: props.ticket.client?.id || null,
});

function submitUpdate() {
    updateForm.patch(route('tickets.update', props.ticket.id), { preserveScroll: true });
}

function quickStatus(value) {
    updateForm.status = value;
    submitUpdate();
}

const reassignOpen = ref(false);
const reassignForm = useForm({ assignee_id: props.ticket.assignee?.id || null });

function openReassign() {
    reassignForm.assignee_id = props.ticket.assignee?.id || null;
    reassignOpen.value = true;
}

function submitReassign() {
    reassignForm.patch(route('tickets.update', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            reassignOpen.value = false;
        },
    });
}

function destroyTicket() {
    if (!confirm('هل أنت متأكد من حذف هذه التذكرة نهائياً؟')) return;
    useForm({}).delete(route('tickets.destroy', props.ticket.id));
}

const isAdmin = computed(() => Boolean(page.props.auth?.user?.role === 'admin'));
</script>

<template>
    <Head :title="`تذكرة ${ticket.reference}`" />
    <AuthenticatedLayout>
        <template #title>تذكرة {{ ticket.reference }}</template>

        <div class="mx-auto w-full min-w-0 max-w-5xl space-y-4 px-3 pb-8 sm:px-4">
            <div class="flex items-center justify-between">
                <Link
                    :href="route('tickets.index')"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline"
                >
                    ← العودة إلى قائمة التذاكر
                </Link>
                <div v-if="canTriage" class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="openReassign"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="8.5" cy="7" r="3.5" />
                            <path d="M20 8v6M23 11h-6" />
                        </svg>
                        إعادة تعيين
                    </button>
                    <DangerButton
                        v-if="isAdmin"
                        type="button"
                        class="!inline-flex !items-center !gap-1 !rounded-lg !px-3 !py-1.5 !text-xs"
                        @click="destroyTicket"
                    >
                        حذف التذكرة
                    </DangerButton>
                </div>
            </div>

            <!-- Header card -->
            <div class="ui-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-200/80 bg-gradient-to-l from-slate-50 via-white to-brand-50/40 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[10px] text-white">{{ ticket.reference }}</span>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', statusClass(ticket.status)]">{{ statusLabels[ticket.status] || ticket.status }}</span>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', priorityClass(ticket.priority)]">{{ priorityLabels[ticket.priority] || ticket.priority }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">{{ categoryLabels[ticket.category] || ticket.category }}</span>
                            <span
                                v-if="ticket.client"
                                class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-indigo-100"
                            >
                                🏷 {{ ticket.client.name }}
                            </span>
                        </div>
                        <h1 class="text-lg font-bold text-slate-900">{{ ticket.title }}</h1>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600">
                            <span>المُبلِّغ: <strong class="text-slate-800">{{ ticket.reporter?.name || '—' }}</strong></span>
                            <span v-if="ticket.assignee">المسؤول: <strong class="text-slate-800">{{ ticket.assignee.name }}</strong></span>
                            <span v-else class="font-semibold text-rose-600">غير مُسندة</span>
                            <span>أُنشئت: {{ fmtDate(ticket.created_at) }}</span>
                            <span v-if="ticket.first_response_at">أول رد: {{ fmtDate(ticket.first_response_at) }}</span>
                            <span v-if="ticket.resolved_at" class="font-semibold text-emerald-700">
                                ✓ حُلَّت خلال: {{ fmtDuration(ticket.resolution_seconds) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick status actions -->
                <div v-if="canTriage" class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 bg-slate-50/50 px-4 py-2.5">
                    <span class="text-[11px] font-semibold text-slate-500">تغيير سريع:</span>
                    <button
                        v-if="ticket.status !== 'in_progress'"
                        type="button"
                        class="rounded-full bg-amber-500 px-3 py-1 text-[11px] font-semibold text-white hover:bg-amber-600"
                        @click="quickStatus('in_progress')"
                    >
                        ابدأ المعالجة
                    </button>
                    <button
                        v-if="ticket.status !== 'waiting'"
                        type="button"
                        class="rounded-full bg-sky-500 px-3 py-1 text-[11px] font-semibold text-white hover:bg-sky-600"
                        @click="quickStatus('waiting')"
                    >
                        بانتظار رد
                    </button>
                    <button
                        v-if="ticket.status !== 'resolved'"
                        type="button"
                        class="rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700"
                        @click="quickStatus('resolved')"
                    >
                        تم الحل
                    </button>
                    <button
                        v-if="ticket.status === 'resolved'"
                        type="button"
                        class="rounded-full bg-slate-500 px-3 py-1 text-[11px] font-semibold text-white hover:bg-slate-600"
                        @click="quickStatus('open')"
                    >
                        إعادة فتح
                    </button>
                </div>

                <!-- Body -->
                <div class="px-4 py-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-3 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap">
                        {{ ticket.body }}
                    </div>
                </div>
            </div>

            <!-- Triage controls (admin/HR only) -->
            <div v-if="canTriage" class="ui-card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">إدارة التذكرة</div>
                </div>
                <form class="grid gap-2 sm:grid-cols-5" @submit.prevent="submitUpdate">
                    <select
                        v-model="updateForm.status"
                        class="rounded-xl border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                        <option v-for="s in meta.statuses" :key="`u-s-${s}`" :value="s">{{ statusLabels[s] || s }}</option>
                    </select>
                    <select
                        v-model="updateForm.priority"
                        class="rounded-xl border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                        <option v-for="p in meta.priorities" :key="`u-p-${p}`" :value="p">{{ priorityLabels[p] || p }}</option>
                    </select>
                    <select
                        v-model="updateForm.category"
                        class="rounded-xl border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                        <option v-for="c in meta.categories" :key="`u-c-${c}`" :value="c">{{ categoryLabels[c] || c }}</option>
                    </select>
                    <select
                        v-model="updateForm.client_id"
                        class="rounded-xl border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                        <option :value="null">— عميل غير محدد —</option>
                        <option v-for="c in clients" :key="`u-cli-${c.id}`" :value="c.id">{{ c.name }}</option>
                    </select>
                    <select
                        v-model="updateForm.assignee_id"
                        class="rounded-xl border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    >
                        <option :value="null">— غير مُسند —</option>
                        <option v-for="u in team_users" :key="`u-asg-${u.id}`" :value="u.id">{{ u.name }}</option>
                    </select>
                    <div class="sm:col-span-5 flex items-center justify-end">
                        <PrimaryButton type="submit" :disabled="updateForm.processing">حفظ التغييرات</PrimaryButton>
                    </div>
                </form>
            </div>

            <!-- Messages -->
            <div class="ui-card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">المحادثة</div>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                        {{ ticket.messages?.length || 0 }} رسالة
                    </span>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="m in ticket.messages"
                        :key="m.id"
                        :class="[
                            'rounded-2xl border p-3',
                            m.is_internal
                                ? 'border-amber-200 bg-amber-50'
                                : 'border-slate-200 bg-white',
                        ]"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-xs font-semibold text-slate-800">
                                {{ m.user?.name || 'مجهول' }}
                                <span
                                    v-if="m.is_internal"
                                    class="ms-1 rounded bg-amber-200 px-1.5 py-0.5 text-[9px] font-bold text-amber-900"
                                >
                                    ملاحظة داخلية
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500">{{ fmtDate(m.created_at) }}</div>
                        </div>
                        <div class="mt-1 text-sm text-slate-800 whitespace-pre-wrap">{{ m.body }}</div>
                    </div>
                    <div
                        v-if="!ticket.messages?.length"
                        class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-xs text-slate-500"
                    >
                        لا توجد رسائل بعد. ابدأ المحادثة في الأسفل.
                    </div>
                </div>

                <form class="mt-3 space-y-2" @submit.prevent="submitReply">
                    <textarea
                        v-model="replyForm.body"
                        rows="3"
                        maxlength="4000"
                        required
                        placeholder="اكتب ردك..."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                    />
                    <div class="flex items-center justify-between">
                        <label v-if="canTriage" class="flex items-center gap-2 text-[11px] text-slate-600">
                            <input
                                v-model="replyForm.is_internal"
                                type="checkbox"
                                class="rounded border-slate-300 text-amber-600 focus:ring-amber-400"
                            />
                            ملاحظة داخلية (لا تظهر لصاحب التذكرة)
                        </label>
                        <span v-else />
                        <PrimaryButton type="submit" :disabled="replyForm.processing">
                            {{ replyForm.processing ? 'جاري الإرسال...' : 'إرسال الرد' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reassign modal -->
        <Modal :show="reassignOpen" max-width="md" @close="reassignOpen = false">
            <div class="w-full bg-white p-5">
                <div class="mb-3">
                    <h2 class="text-base font-bold text-slate-900">إعادة تعيين التذكرة</h2>
                    <p class="text-[11px] text-slate-500">اختر الموظف الجديد المسؤول عن هذه التذكرة. سيتلقى إشعاراً فورياً.</p>
                </div>

                <form class="space-y-3" @submit.prevent="submitReassign">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">الموظف المسؤول</label>
                        <select
                            v-model="reassignForm.assignee_id"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                        >
                            <option :value="null">— إزالة التعيين —</option>
                            <option v-for="u in team_users" :key="`r-${u.id}`" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                        <SecondaryButton type="button" @click="reassignOpen = false">إلغاء</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="reassignForm.processing">
                            {{ reassignForm.processing ? 'جاري الحفظ...' : 'حفظ التعيين الجديد' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
