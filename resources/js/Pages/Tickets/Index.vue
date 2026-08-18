<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tickets: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    canTriage: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    meta: { type: Object, default: () => ({ statuses: [], priorities: [], categories: [] }) },
    team_users: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
});

const page = usePage();

const statusLabels = {
    open: 'مفتوح',
    in_progress: 'قيد المعالجة',
    waiting: 'بانتظار رد',
    resolved: 'تم الحل',
    closed: 'مغلق',
};

const priorityLabels = {
    low: 'منخفض',
    normal: 'عادي',
    high: 'مرتفع',
    critical: 'حرج',
};

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
    switch (priority) {
        case 'critical':
            return 'bg-rose-600 text-white';
        case 'high':
            return 'bg-orange-500 text-white';
        case 'normal':
            return 'bg-slate-200 text-slate-800';
        case 'low':
            return 'bg-slate-100 text-slate-600';
        default:
            return 'bg-slate-100 text-slate-600';
    }
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

const showCreateModal = ref(false);
const createForm = useForm({
    title: '',
    body: '',
    category: 'general',
    priority: 'normal',
    client_id: null,
    assignee_id: null,
});

function openCreate() {
    showCreateModal.value = true;
    createForm.reset();
    createForm.category = 'general';
    createForm.priority = 'normal';
    createForm.client_id = null;
    createForm.assignee_id = null;
    createForm.clearErrors();
}

function submitCreate() {
    createForm.post(route('tickets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
        },
    });
}

function applyFilters(payload) {
    const merged = { ...props.filters, ...payload };
    const clean = {};
    Object.keys(merged).forEach((k) => {
        const v = merged[k];
        if (v === null || v === '' || v === false || v === undefined) return;
        clean[k] = v;
    });
    router.get(route('tickets.index'), clean, { preserveScroll: true, preserveState: true });
}

const filteredCount = computed(() => props.tickets.length);

const showFilterModal = ref(false);
const filterDraft = ref({
    status: props.filters.status || null,
    priority: props.filters.priority || null,
    category: props.filters.category || null,
    assignee_id: props.filters.assignee_id != null ? String(props.filters.assignee_id) : null,
    client_id: props.filters.client_id != null ? String(props.filters.client_id) : null,
    mine: !!props.filters.mine,
});
const searchInput = ref(props.filters.q || '');

const activeFilters = computed(() => {
    const list = [];
    if (props.filters.status) list.push({ key: 'status', label: 'الحالة', value: statusLabels[props.filters.status] || props.filters.status });
    if (props.filters.priority) list.push({ key: 'priority', label: 'الأولوية', value: priorityLabels[props.filters.priority] || props.filters.priority });
    if (props.filters.category) list.push({ key: 'category', label: 'التصنيف', value: categoryLabels[props.filters.category] || props.filters.category });
    if (props.filters.assignee_id) {
        let name = 'غير محدّد';
        if (props.filters.assignee_id === 'unassigned') name = 'غير مُسندة';
        else {
            const u = props.team_users.find((x) => String(x.id) === String(props.filters.assignee_id));
            if (u) name = u.name;
        }
        list.push({ key: 'assignee_id', label: 'المسؤول', value: name });
    }
    if (props.filters.client_id) {
        const c = props.clients.find((x) => String(x.id) === String(props.filters.client_id));
        list.push({ key: 'client_id', label: 'العميل', value: c?.name || `#${props.filters.client_id}` });
    }
    if (props.filters.mine) list.push({ key: 'mine', label: 'النطاق', value: 'مُسندة إليّ' });
    if (props.filters.q) list.push({ key: 'q', label: 'بحث', value: props.filters.q });
    return list;
});

const activeFiltersCount = computed(() => activeFilters.value.length);

function openFilterModal() {
    filterDraft.value = {
        status: props.filters.status || null,
        priority: props.filters.priority || null,
        category: props.filters.category || null,
        assignee_id: props.filters.assignee_id != null ? String(props.filters.assignee_id) : null,
        client_id: props.filters.client_id != null ? String(props.filters.client_id) : null,
        mine: !!props.filters.mine,
    };
    showFilterModal.value = true;
}

function submitFilterModal() {
    applyFilters({
        status: filterDraft.value.status || null,
        priority: filterDraft.value.priority || null,
        category: filterDraft.value.category || null,
        assignee_id: filterDraft.value.assignee_id || null,
        client_id: filterDraft.value.client_id || null,
        mine: filterDraft.value.mine || null,
    });
    showFilterModal.value = false;
}

function resetFilterDraft() {
    filterDraft.value = {
        status: null,
        priority: null,
        category: null,
        assignee_id: null,
        client_id: null,
        mine: false,
    };
}

function clearAllFilters() {
    searchInput.value = '';
    router.get(route('tickets.index'), {}, { preserveScroll: true, preserveState: true });
}

function removeFilter(key) {
    const payload = { [key]: null };
    if (key === 'q') searchInput.value = '';
    applyFilters(payload);
}

let searchDebounce = null;
function onSearchInput() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters({ q: searchInput.value.trim() || null });
    }, 350);
}
</script>

<template>
    <Head title="المشاكل والدعم" />
    <AuthenticatedLayout>
        <template #title>المشاكل والدعم</template>

        <div class="mx-auto w-full min-w-0 max-w-6xl space-y-4 px-3 pb-8 sm:px-4">
            <div
                v-if="page.props.flash?.success"
                class="ui-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ page.props.flash.success }}
            </div>

            <!-- Header card matching system style -->
            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-base font-bold text-slate-900 sm:text-lg">إدارة المشاكل وتذاكر الدعم</h1>
                    <p class="mt-0.5 text-xs text-slate-600">
                        تتبّع المشاكل والاقتراحات، عيِّن الموظف المسؤول، اربط التذكرة بالعميل، واتابع زمن الحل.
                    </p>
                </div>
                <PrimaryButton @click="openCreate">إنشاء تذكرة جديدة</PrimaryButton>
            </div>

            <!-- KPI strip -->
            <div class="ui-card overflow-hidden">
                <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                    <div class="bg-white px-3 py-3 text-center">
                        <div class="text-[11px] text-slate-500">الإجمالي</div>
                        <div class="mt-0.5 text-xl font-bold text-slate-900">{{ stats.total || 0 }}</div>
                    </div>
                    <div class="bg-white px-3 py-3 text-center">
                        <div class="text-[11px] text-slate-500">مفتوحة</div>
                        <div class="mt-0.5 text-xl font-bold text-rose-700">{{ stats.open || 0 }}</div>
                    </div>
                    <div class="bg-white px-3 py-3 text-center">
                        <div class="text-[11px] text-slate-500">قيد المعالجة</div>
                        <div class="mt-0.5 text-xl font-bold text-amber-700">{{ stats.in_progress || 0 }}</div>
                    </div>
                    <div class="bg-white px-3 py-3 text-center">
                        <div class="text-[11px] text-slate-500">تم حلها</div>
                        <div class="mt-0.5 text-xl font-bold text-emerald-700">{{ stats.resolved || 0 }}</div>
                    </div>
                </div>
                <div
                    v-if="stats.critical || stats.mine_open"
                    class="flex flex-wrap items-center justify-center gap-2 border-t border-slate-100 bg-slate-50/60 px-3 py-2 text-[11px] text-slate-600"
                >
                    <span v-if="stats.mine_open">
                        مُسندة إليك:
                        <strong class="text-slate-800">{{ stats.mine_open }}</strong>
                    </span>
                    <span v-if="stats.critical" class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white">
                        {{ stats.critical }} حرجة
                    </span>
                </div>
            </div>

            <!-- Filter toolbar: search + filter button + active chips -->
            <div class="ui-card flex flex-col gap-2 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        <input
                            v-model="searchInput"
                            type="search"
                            placeholder="ابحث في العنوان أو الوصف أو رقم التذكرة..."
                            class="w-full rounded-xl border border-slate-200 bg-white py-2 pe-9 ps-3 text-sm placeholder:text-slate-400 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            @input="onSearchInput"
                        />
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-all hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
                        @click="openFilterModal"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 5h18M6 12h12M10 19h4" />
                        </svg>
                        فلترة
                        <span
                            v-if="activeFiltersCount"
                            class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-brand-600 px-1.5 text-[10px] font-bold text-white"
                        >
                            {{ activeFiltersCount }}
                        </span>
                    </button>
                    <button
                        v-if="activeFiltersCount"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition-all hover:border-rose-300 hover:bg-rose-100"
                        @click="clearAllFilters"
                    >
                        مسح الكل
                    </button>
                </div>
                <div v-if="activeFiltersCount" class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-semibold text-slate-500">الفلاتر النشطة:</span>
                    <span
                        v-for="f in activeFilters"
                        :key="`chip-${f.key}`"
                        class="inline-flex items-center gap-1 rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800"
                    >
                        <span class="text-brand-500">{{ f.label }}:</span>
                        <span>{{ f.value }}</span>
                        <button
                            type="button"
                            class="ms-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full text-brand-500 hover:bg-brand-200 hover:text-brand-900"
                            :aria-label="`إزالة ${f.label}`"
                            @click="removeFilter(f.key)"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </span>
                </div>
            </div>

            <!-- Ticket list -->
            <div class="space-y-2">
                <Link
                    v-for="t in tickets"
                    :key="t.id"
                    :href="route('tickets.show', t.id)"
                    class="ui-card group flex flex-col gap-2 p-3 transition-all hover:border-brand-200 hover:bg-brand-50/30 hover:shadow-md sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex-1 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[10px] text-white">{{ t.reference }}</span>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', statusClass(t.status)]">
                                {{ statusLabels[t.status] || t.status }}
                            </span>
                            <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', priorityClass(t.priority)]">
                                {{ priorityLabels[t.priority] || t.priority }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                {{ categoryLabels[t.category] || t.category }}
                            </span>
                            <span
                                v-if="t.client"
                                class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-indigo-100"
                            >
                                🏷 {{ t.client.name }}
                            </span>
                        </div>
                        <div class="text-sm font-semibold text-slate-900">{{ t.title }}</div>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
                            <span>المُبلِّغ: <strong class="text-slate-700">{{ t.reporter?.name || '—' }}</strong></span>
                            <span v-if="t.assignee">المسؤول: <strong class="text-slate-700">{{ t.assignee.name }}</strong></span>
                            <span v-else class="font-semibold text-rose-600">غير مُسندة</span>
                            <span>أُنشئت: {{ fmtDate(t.created_at) }}</span>
                            <span v-if="t.resolved_at">حُلَّت خلال: <strong class="text-emerald-700">{{ fmtDuration(t.resolution_seconds) }}</strong></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-start sm:self-auto">
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] text-slate-700">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z" />
                            </svg>
                            {{ t.messages_count || 0 }}
                        </span>
                    </div>
                </Link>
                <div v-if="!filteredCount" class="ui-card flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                    <div class="text-4xl">🎫</div>
                    <div class="text-sm font-semibold text-slate-700">لا توجد تذاكر مطابقة للفلاتر الحالية.</div>
                    <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="openCreate">
                        أنشئ أول تذكرة الآن
                    </button>
                </div>
            </div>
        </div>

        <!-- Create modal -->
        <Modal :show="showCreateModal" max-width="lg" @close="showCreateModal = false">
            <div class="w-full bg-white p-5 sm:p-6">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">إنشاء تذكرة دعم</h2>
                        <p class="text-[11px] text-slate-500">صف المشكلة بدقة وحدّد العميل المعني والموظف المسؤول.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="إغلاق"
                        @click="showCreateModal = false"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <form class="space-y-3" @submit.prevent="submitCreate">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">عنوان المشكلة *</label>
                        <input
                            v-model="createForm.title"
                            type="text"
                            maxlength="160"
                            required
                            placeholder="عنوان مختصر يصف المشكلة"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                        />
                        <p v-if="createForm.errors.title" class="mt-1 text-[11px] text-rose-700">{{ createForm.errors.title }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-700">الوصف الكامل *</label>
                        <textarea
                            v-model="createForm.body"
                            rows="4"
                            maxlength="8000"
                            required
                            placeholder="اشرح الخطوات أو السياق. أرفق أي تفاصيل مفيدة لمعالجة المشكلة بسرعة."
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                        />
                        <p v-if="createForm.errors.body" class="mt-1 text-[11px] text-rose-700">{{ createForm.errors.body }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">العميل المعني (اختياري)</label>
                            <select
                                v-model="createForm.client_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option :value="null">— لا يخص عميلاً محدداً —</option>
                                <option v-for="c in clients" :key="`client-${c.id}`" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">إسناد إلى موظف (اختياري)</label>
                            <select
                                v-model="createForm.assignee_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option :value="null">— يقرّر الأدمن لاحقاً —</option>
                                <option v-for="u in team_users" :key="`user-${u.id}`" :value="u.id">{{ u.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">التصنيف</label>
                            <select
                                v-model="createForm.category"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option v-for="c in meta.categories" :key="`cat-${c}`" :value="c">{{ categoryLabels[c] || c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-700">الأولوية</label>
                            <select
                                v-model="createForm.priority"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option v-for="p in meta.priorities" :key="`prio-${p}`" :value="p">{{ priorityLabels[p] || p }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                        <SecondaryButton type="button" @click="showCreateModal = false">إلغاء</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="createForm.processing">
                            {{ createForm.processing ? 'جاري الإرسال...' : 'إنشاء التذكرة' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Filter modal -->
        <Modal :show="showFilterModal" max-width="lg" @close="showFilterModal = false">
            <div class="w-full bg-white p-5 sm:p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">تصفية التذاكر</h2>
                        <p class="text-[11px] text-slate-500">اختر معايير العرض ثم اضغط "تطبيق" — يمكنك تركها فارغة لإظهار الكل.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="إغلاق"
                        @click="showFilterModal = false"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="mb-1.5 text-xs font-semibold text-slate-700">الحالة</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', !filterDraft.status ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.status = null"
                            >
                                الكل
                            </button>
                            <button
                                v-for="s in meta.statuses"
                                :key="`m-status-${s}`"
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', filterDraft.status === s ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.status = s"
                            >
                                {{ statusLabels[s] || s }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 text-xs font-semibold text-slate-700">الأولوية</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', !filterDraft.priority ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.priority = null"
                            >
                                الكل
                            </button>
                            <button
                                v-for="p in meta.priorities"
                                :key="`m-prio-${p}`"
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', filterDraft.priority === p ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.priority = p"
                            >
                                {{ priorityLabels[p] || p }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 text-xs font-semibold text-slate-700">التصنيف</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', !filterDraft.category ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.category = null"
                            >
                                الكل
                            </button>
                            <button
                                v-for="c in meta.categories"
                                :key="`m-cat-${c}`"
                                type="button"
                                :class="['rounded-full border px-3 py-1 text-xs font-semibold transition-all', filterDraft.category === c ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                                @click="filterDraft.category = c"
                            >
                                {{ categoryLabels[c] || c }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-if="canTriage">
                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">الموظف المسؤول</label>
                            <select
                                v-model="filterDraft.assignee_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option :value="null">— الكل —</option>
                                <option value="unassigned">غير مُسندة</option>
                                <option v-for="u in team_users" :key="`m-user-${u.id}`" :value="String(u.id)">{{ u.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-slate-700">العميل</label>
                            <select
                                v-model="filterDraft.client_id"
                                class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                            >
                                <option :value="null">— الكل —</option>
                                <option v-for="c in clients" :key="`m-client-${c.id}`" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <label
                        v-if="canTriage"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        <input v-model="filterDraft.mine" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-400" />
                        أظهر فقط التذاكر المُسندة إليّ
                    </label>
                </div>

                <div class="mt-5 flex items-center justify-between gap-2 border-t border-slate-200 pt-3">
                    <button
                        type="button"
                        class="text-xs font-semibold text-slate-500 hover:text-rose-700 hover:underline"
                        @click="resetFilterDraft"
                    >
                        إعادة تعيين المعايير
                    </button>
                    <div class="flex items-center gap-2">
                        <SecondaryButton type="button" @click="showFilterModal = false">إلغاء</SecondaryButton>
                        <PrimaryButton type="button" @click="submitFilterModal">تطبيق</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
