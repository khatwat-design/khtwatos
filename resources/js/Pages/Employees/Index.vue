<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    employees: Array,
    teams: Array,
    canAssignAdmin: Boolean,
    arab_country_dial_options: {
        type: Array,
        default: () => [],
    },
    analytics: {
        type: Object,
        default: () => ({}),
    },
    analytics_range_days: {
        type: Number,
        default: 30,
    },
    team_summary: {
        type: Object,
        default: () => ({}),
    },
    teams_breakdown: {
        type: Array,
        default: () => [],
    },
});

const rangeOptions = [
    { value: 7, label: '7 أيام' },
    { value: 14, label: '14 يوم' },
    { value: 30, label: '30 يوم' },
    { value: 60, label: '60 يوم' },
    { value: 90, label: '90 يوم' },
];
const selectedRange = ref(props.analytics_range_days);

function changeRange(days) {
    selectedRange.value = days;
    router.get(route('employees.index'), { range: days }, { preserveScroll: true, preserveState: true });
}

function fmtSeconds(s) {
    const total = Math.max(0, Number(s || 0));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    if (h <= 0 && m <= 0) return '٠ د';
    if (h <= 0) return `${m} د`;
    return `${h}س ${m}د`;
}

function fmtDateShort(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('ar-IQ', { day: 'numeric', month: 'short' });
    } catch {
        return iso;
    }
}

function analyticsFor(empId) {
    return props.analytics?.[empId] || null;
}

function attendanceBarHeight(seconds) {
    const max = 9 * 3600;
    const ratio = Math.min(1, (Number(seconds) || 0) / max);
    return Math.max(6, Math.round(ratio * 56));
}

function attendanceBarColor(status) {
    if (status === 'present') return 'bg-emerald-500';
    if (status === 'remote') return 'bg-sky-500';
    if (status === 'leave') return 'bg-amber-400';
    if (status === 'sick') return 'bg-rose-400';
    return 'bg-slate-300';
}

const teamOverview = computed(() => {
    const empArr = props.employees || [];
    const totals = empArr.reduce(
        (acc, emp) => {
            const a = analyticsFor(emp.id);
            if (a) {
                acc.activeSeconds += Number(a.attendance?.today_active_seconds || 0);
                acc.tasksCompleted += Number(a.tasks?.completed || 0);
                acc.tasksOverdue += Number(a.tasks?.completed_overdue || 0);
                acc.openTasks += Number(a.tasks?.open || 0);
                acc.ticketsResolved += Number(a.tickets?.resolved || 0);
                if (a.attendance?.today_checked_in) acc.checkedIn += 1;
            }
            return acc;
        },
        { activeSeconds: 0, tasksCompleted: 0, tasksOverdue: 0, openTasks: 0, ticketsResolved: 0, checkedIn: 0 },
    );
    return {
        totalEmployees: empArr.length,
        ...totals,
    };
});

const summary = computed(() => props.team_summary || {});

const sortOptions = [
    { value: 'score', label: 'الأداء الأعلى' },
    { value: 'completed', label: 'المهام المنجزة' },
    { value: 'overdue', label: 'الأعلى تأخراً' },
    { value: 'active', label: 'الأنشط اليوم' },
    { value: 'on_time', label: 'الأكثر التزاماً' },
    { value: 'name', label: 'الاسم (أ-ي)' },
];
const filterOptions = [
    { value: 'all', label: 'الكل' },
    { value: 'top', label: 'أفضل أداء' },
    { value: 'attention', label: 'يحتاج انتباه' },
    { value: 'overdue', label: 'لديهم تأخر' },
    { value: 'inactive', label: 'غير نشطين' },
    { value: 'overloaded', label: 'حِمل مرتفع' },
    { value: 'checked_in', label: 'حاضرون اليوم' },
];
const sortBy = ref('score');
const filterBy = ref('all');
const employeeSearch = ref('');

function onTimeRatioOf(empId) {
    const r = analyticsFor(empId)?.tasks?.on_time_ratio;
    return r == null ? null : Number(r);
}

const visibleEmployees = computed(() => {
    const term = (employeeSearch.value || '').trim().toLowerCase();
    let arr = (props.employees || []).slice();

    if (term) {
        arr = arr.filter((e) => `${e.name} ${e.username || ''}`.toLowerCase().includes(term));
    }

    if (filterBy.value !== 'all') {
        arr = arr.filter((e) => {
            const a = analyticsFor(e.id);
            if (!a) return false;
            switch (filterBy.value) {
                case 'top':
                    return (a.productivity?.score || 0) >= 70;
                case 'attention':
                    return (a.productivity?.score || 0) < 55;
                case 'overdue':
                    return (a.tasks?.overdue_open || 0) > 0 || (a.tasks?.completed_overdue || 0) > 0;
                case 'inactive':
                    return !!a.is_inactive;
                case 'overloaded':
                    return !!a.is_overloaded;
                case 'checked_in':
                    return !!a.attendance?.today_checked_in;
                default:
                    return true;
            }
        });
    }

    arr.sort((a, b) => {
        const aa = analyticsFor(a.id);
        const bb = analyticsFor(b.id);
        const an = (v) => (v == null ? -1 : v);
        switch (sortBy.value) {
            case 'completed':
                return (bb?.tasks?.completed || 0) - (aa?.tasks?.completed || 0);
            case 'overdue':
                return (bb?.tasks?.overdue_open || 0) - (aa?.tasks?.overdue_open || 0);
            case 'active':
                return (bb?.attendance?.today_active_seconds || 0) - (aa?.attendance?.today_active_seconds || 0);
            case 'on_time':
                return an(bb?.tasks?.on_time_ratio) - an(aa?.tasks?.on_time_ratio);
            case 'name':
                return String(a.name).localeCompare(String(b.name), 'ar');
            case 'score':
            default:
                return (bb?.productivity?.score || 0) - (aa?.productivity?.score || 0);
        }
    });

    return arr;
});

function gradeClass(grade) {
    switch (grade) {
        case 'A':
            return 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-300';
        case 'B':
            return 'bg-sky-100 text-sky-800 ring-1 ring-sky-300';
        case 'C':
            return 'bg-amber-100 text-amber-800 ring-1 ring-amber-300';
        case 'D':
            return 'bg-orange-100 text-orange-800 ring-1 ring-orange-300';
        default:
            return 'bg-rose-100 text-rose-800 ring-1 ring-rose-300';
    }
}

function scoreBarColor(score) {
    if (score >= 85) return 'bg-emerald-500';
    if (score >= 70) return 'bg-sky-500';
    if (score >= 55) return 'bg-amber-500';
    if (score >= 40) return 'bg-orange-500';
    return 'bg-rose-500';
}

function signalToneClass(tone) {
    switch (tone) {
        case 'amber':
            return 'bg-amber-50 text-amber-800 ring-1 ring-amber-200';
        case 'emerald':
            return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200';
        case 'rose':
            return 'bg-rose-50 text-rose-800 ring-1 ring-rose-200';
        case 'orange':
            return 'bg-orange-50 text-orange-800 ring-1 ring-orange-200';
        case 'sky':
            return 'bg-sky-50 text-sky-800 ring-1 ring-sky-200';
        default:
            return 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
    }
}

function fmtDelta(value) {
    if (value == null || Number.isNaN(Number(value))) return null;
    const v = Number(value);
    const sign = v > 0 ? '+' : '';
    return `${sign}${v.toFixed(0)}%`;
}

function deltaToneClass(value, { lowerIsBetter = false } = {}) {
    if (value == null) return 'text-slate-400';
    const v = Number(value);
    if (v === 0) return 'text-slate-500';
    const isImproving = lowerIsBetter ? v < 0 : v > 0;
    return isImproving ? 'text-emerald-700' : 'text-rose-700';
}

function workloadBarWidth(idx) {
    return Math.max(4, Math.min(180, Number(idx || 0)));
}

function workloadBarColor(idx) {
    const n = Number(idx || 0);
    if (n >= 160) return 'bg-rose-500';
    if (n >= 120) return 'bg-orange-500';
    if (n >= 80) return 'bg-emerald-500';
    if (n >= 40) return 'bg-sky-500';
    return 'bg-slate-300';
}

function ratioPercent(r) {
    if (r == null) return null;
    return Math.round(Number(r) * 100);
}

const moodMeta = {
    great: { emoji: '🤩', label: 'ممتاز', cls: 'bg-emerald-50 text-emerald-800 ring-emerald-200', bar: 'bg-emerald-500' },
    good: { emoji: '🙂', label: 'جيد', cls: 'bg-sky-50 text-sky-800 ring-sky-200', bar: 'bg-sky-500' },
    neutral: { emoji: '😐', label: 'عادي', cls: 'bg-slate-100 text-slate-700 ring-slate-200', bar: 'bg-slate-400' },
    tired: { emoji: '😴', label: 'متعب', cls: 'bg-amber-50 text-amber-800 ring-amber-200', bar: 'bg-amber-500' },
    low: { emoji: '🥺', label: 'مشغول', cls: 'bg-rose-50 text-rose-800 ring-rose-200', bar: 'bg-rose-500' },
};

function moodInfo(code) {
    return moodMeta[code] || null;
}

function fmtFullDateShort(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString('ar-IQ', { day: 'numeric', month: 'short' });
    } catch {
        return iso;
    }
}

function fmtTimeShort(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleTimeString('ar-IQ', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return '—';
    }
}

function clearEmployeeFilters() {
    employeeSearch.value = '';
    filterBy.value = 'all';
    sortBy.value = 'score';
}

const page = usePage();

/** إن تعطّلت props في بيئة الإنتاج لا يبقى select بدون خيارات فيُرسل مفتاح فارغ */
const dialOptions = computed(() =>
    props.arab_country_dial_options?.length
        ? props.arab_country_dial_options
        : [{ value: '964', label: 'العراق (+964)' }],
);

const roleLabels = {
    admin: 'مدير النظام',
    lead: 'قائد فريق',
    member: 'موظف',
};

const dayOptions = [
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
    { value: 6, label: 'السبت' },
];

function buildDefaultSchedule(source = null) {
    const map = source && typeof source === 'object' ? source : {};

    return dayOptions.map((day) => {
        const raw = map[String(day.value)] || map[day.value] || {};
        return {
            day: day.value,
            enabled: typeof raw.enabled === 'boolean'
                ? raw.enabled
                : [0, 1, 2, 3, 4].includes(day.value),
            start: (raw.start || '09:00').slice(0, 5),
            end: (raw.end || '17:00').slice(0, 5),
        };
    });
}

function scheduleSummary(schedule) {
    const enabled = (schedule || []).filter((row) => row.enabled);
    if (!enabled.length) {
        return 'لا يوجد توفر';
    }

    return enabled
        .map((row) => {
            const dayLabel = dayOptions.find((d) => d.value === Number(row.day))?.label || row.day;
            return `${dayLabel}: ${row.start} - ${row.end}`;
        })
        .join(' | ');
}

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const editingEmployeeId = ref(null);

const createForm = useForm({
    name: '',
    username: '',
    password: '',
    phone_country_code: '964',
    phone_local: '',
    role: 'member',
    is_bookable: true,
    availability_schedule: buildDefaultSchedule(),
    teams: [],
});

const editForm = useForm({
    name: '',
    username: '',
    password: '',
    phone_country_code: '964',
    phone_local: '',
    role: 'member',
    is_bookable: true,
    availability_schedule: buildDefaultSchedule(),
    teams: [],
});

const sendingLoginId = ref(null);

/** نص تشخيصي يظهر حتى لو لم تُعرَض أخطاء الحقول (شبكة، 419، استثناء، إلخ). */
const createSubmitDiagnostics = ref('');
const editSubmitDiagnostics = ref('');

/**
 * @param {unknown} errors
 * @returns {string[]}
 */
function collectAllFormErrors(errors) {
    if (!errors || typeof errors !== 'object') {
        return [];
    }
    const out = [];
    for (const [key, val] of Object.entries(errors)) {
        if (Array.isArray(val)) {
            val.forEach((item) => out.push(`${key}: ${item}`));
        } else if (val != null && String(val).trim() !== '') {
            out.push(`${key}: ${String(val)}`);
        }
    }
    return out;
}

/**
 * @param {unknown} inertiaErrors
 * @param {string} headline
 */
function buildSubmitDiagnostics(inertiaErrors, headline) {
    const lines = collectAllFormErrors(inertiaErrors);
    if (!lines.length) {
        return headline;
    }
    return `${headline}\n${lines.join('\n')}`;
}

watch(
    () => page.props.errors,
    (errs) => {
        const bag = errs && typeof errs === 'object' ? errs : {};
        if (!Object.keys(bag).length) {
            return;
        }
        const text = collectAllFormErrors(bag).join('\n') || JSON.stringify(bag);
        if (createModalOpen.value) {
            createSubmitDiagnostics.value = `أخطاء عامة من الخادم:\n${text}`;
        }
        if (editModalOpen.value) {
            editSubmitDiagnostics.value = `أخطاء عامة من الخادم:\n${text}`;
        }
    },
    { deep: true },
);

/** يُرسل مع الطلب: أرقام فقط لمفتاح الدولة، و allocation_percent الفارغ → null (لا يفشل تحقق integer على السيرفر). */
function normalizeAllocationPercent(val) {
    if (val === '' || val === undefined || val === null) {
        return null;
    }
    if (typeof val === 'number' && !Number.isNaN(val)) {
        return val;
    }
    const s = String(val).trim();
    if (s === '') {
        return null;
    }
    const n = parseInt(s, 10);
    return Number.isNaN(n) ? null : n;
}

function employeePayloadTransform(data) {
    let phone_country_code = data.phone_country_code;
    if (phone_country_code != null && String(phone_country_code).trim() !== '') {
        phone_country_code = String(phone_country_code).replace(/\D/g, '');
    } else {
        phone_country_code = '';
    }

    const teams = Array.isArray(data.teams)
        ? data.teams.map((t) => ({
            ...t,
            allocation_percent: normalizeAllocationPercent(t.allocation_percent),
        }))
        : data.teams;

    return {
        ...data,
        phone_country_code,
        teams,
    };
}

createForm.transform((data) => employeePayloadTransform(data));
editForm.transform((data) => employeePayloadTransform(data));

function openCreateModal() {
    createSubmitDiagnostics.value = '';
    createForm.reset();
    createForm.clearErrors();
    createForm.role = 'member';
    createForm.is_bookable = true;
    createForm.phone_country_code = '964';
    createForm.phone_local = '';
    createForm.availability_schedule = buildDefaultSchedule();
    createForm.teams = [];
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

function openEditModal(employee) {
    editSubmitDiagnostics.value = '';
    editingEmployeeId.value = employee.id;
    editForm.clearErrors();
    editForm.name = employee.name;
    editForm.username = employee.username || '';
    editForm.password = '';
    const rawCc = employee.phone_country_code;
    editForm.phone_country_code =
        rawCc != null && String(rawCc).trim() !== '' ? String(rawCc).replace(/\D/g, '') : '964';
    editForm.phone_local = employee.phone_local != null ? String(employee.phone_local) : '';
    editForm.role = employee.role;
    editForm.is_bookable = Boolean(employee.is_bookable);
    editForm.availability_schedule = buildDefaultSchedule(employee.availability_schedule);
    editForm.teams = (employee.teams || []).map((t) => ({
        id: t.id,
        allocation_percent: t.allocation_percent ?? '',
        is_lead: Boolean(t.is_lead),
    }));
    editModalOpen.value = true;
}

function closeEditModal() {
    editModalOpen.value = false;
    editingEmployeeId.value = null;
}

function toggleTeamSelection(form, teamId, checked) {
    const idx = form.teams.findIndex((t) => Number(t.id) === Number(teamId));

    if (checked && idx < 0) {
        form.teams.push({
            id: Number(teamId),
            allocation_percent: '',
            is_lead: false,
        });
        return;
    }

    if (!checked && idx >= 0) {
        form.teams.splice(idx, 1);
    }
}

function hasTeam(form, teamId) {
    return form.teams.some((t) => Number(t.id) === Number(teamId));
}

function teamItem(form, teamId) {
    return form.teams.find((t) => Number(t.id) === Number(teamId)) || null;
}

function submitCreate() {
    createSubmitDiagnostics.value = '';
    createForm.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createSubmitDiagnostics.value = '';
            closeCreateModal();
        },
        onError: (errors) => {
            createSubmitDiagnostics.value = buildSubmitDiagnostics(
                errors,
                'رفض الخادم الطلب (تحقق Laravel):',
            );
            nextTick(() => {
                document.getElementById('employee-create-diagnostics')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        },
        onFinish: () => {
            const fieldErrors = Object.keys(createForm.errors || {}).length;
            if (
                createModalOpen.value &&
                !createForm.recentlySuccessful &&
                !fieldErrors &&
                !createSubmitDiagnostics.value
            ) {
                createSubmitDiagnostics.value =
                    'انتهى الطلب دون رسائل تحقق. افتح Network في DevTools وراقب طلب POST إلى /employees (حالة 419/500/302)، أو حدّث الصفحة وأعد المحاولة.';
            }
            nextTick(() => {
                document.getElementById('employee-create-diagnostics')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        },
    });
}

function submitEdit() {
    if (!editingEmployeeId.value) {
        editSubmitDiagnostics.value = 'تعذّر الحفظ: لم يُحدَّد رقم الموظف.';
        return;
    }

    editSubmitDiagnostics.value = '';
    editForm.patch(route('employees.update', editingEmployeeId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editSubmitDiagnostics.value = '';
            closeEditModal();
        },
        onError: (errors) => {
            editSubmitDiagnostics.value = buildSubmitDiagnostics(
                errors,
                'رفض الخادم الطلب (تحقق Laravel):',
            );
            nextTick(() => {
                document.getElementById('employee-edit-diagnostics')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        },
        onFinish: () => {
            const fieldErrors = Object.keys(editForm.errors || {}).length;
            if (
                editModalOpen.value &&
                !editForm.recentlySuccessful &&
                !fieldErrors &&
                !editSubmitDiagnostics.value
            ) {
                editSubmitDiagnostics.value =
                    'انتهى الطلب دون رسائل تحقق. افتح Network في DevTools وراقب طلب PATCH (حالة 419/500/302)، أو حدّث الصفحة وأعد المحاولة.';
            }
            nextTick(() => {
                document.getElementById('employee-edit-diagnostics')?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        },
    });
}

function deleteEmployee(employee) {
    if (!confirm(`حذف الموظف ${employee.name}؟`)) {
        return;
    }
    router.delete(route('employees.destroy', employee.id), {
        preserveScroll: true,
    });
}

function sendLoginWhatsApp(employee) {
    if (!employee.phone) {
        return;
    }
    if (
        !confirm(
            'سيتم إنشاء كلمة مرور جديدة للموظف وإرسال رابط الدخول واسم المستخدم وكلمة المرور عبر واتساب. هل تريد المتابعة؟',
        )
    ) {
        return;
    }
    sendingLoginId.value = employee.id;
    router.post(route('employees.login-whatsapp', employee.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            sendingLoginId.value = null;
        },
    });
}
</script>

<template>
    <Head title="الموظفين" />

    <AuthenticatedLayout>
        <template #title>الموظفين</template>

        <div class="employees-page mx-auto max-w-6xl space-y-4">
            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">
                    إدارة كاملة لبيانات الموظفين، فرقهم، الصلاحيات، وتوفرهم للحجز، مع تتبّع حضور وتحليلات أداء.
                </p>
                <PrimaryButton type="button" @click="openCreateModal">إضافة موظف</PrimaryButton>
            </div>

            <!-- Analytics summary -->
            <div class="ui-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-200/70 bg-gradient-to-l from-brand-50 via-white to-indigo-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">لوحة أداء الفريق</div>
                        <div class="text-[11px] text-slate-500">تحليلات الموظفين خلال آخر {{ selectedRange }} يوماً.</div>
                    </div>
                    <div class="flex items-center gap-1.5 self-start sm:self-auto">
                        <button
                            v-for="opt in rangeOptions"
                            :key="opt.value"
                            type="button"
                            :class="[
                                'rounded-full border px-2.5 py-1 text-[11px] font-semibold transition-all',
                                selectedRange === opt.value
                                    ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            ]"
                            @click="changeRange(opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">الموظفون</div>
                        <div class="text-xl font-bold text-slate-900">{{ teamOverview.totalEmployees }}</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">حاضرون اليوم</div>
                        <div class="text-xl font-bold text-emerald-700">{{ teamOverview.checkedIn }}</div>
                        <div v-if="summary.punctuality_today != null" class="text-[10px] text-emerald-700">
                            التزام: {{ ratioPercent(summary.punctuality_today) }}%
                        </div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">إجمالي وقت اليوم</div>
                        <div class="text-xl font-bold text-slate-900">{{ fmtSeconds(teamOverview.activeSeconds) }}</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">المهام المكتملة</div>
                        <div class="text-xl font-bold text-brand-700">{{ teamOverview.tasksCompleted }}</div>
                        <div class="text-[10px] text-amber-700">منها {{ teamOverview.tasksOverdue }} متأخرة</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">مهام مفتوحة</div>
                        <div class="text-xl font-bold text-slate-900">{{ teamOverview.openTasks }}</div>
                        <div v-if="summary.tasks_overdue_open" class="text-[10px] text-rose-700">
                            {{ summary.tasks_overdue_open }} متأخرة الآن
                        </div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">تذاكر تم حلها</div>
                        <div class="text-xl font-bold text-indigo-700">{{ teamOverview.ticketsResolved }}</div>
                    </div>
                </div>

                <!-- Second KPI row: Quality + SLA -->
                <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">معدل الالتزام بالمواعيد</div>
                        <div class="text-xl font-bold" :class="(summary.on_time_completion_rate ?? 0) >= 0.8 ? 'text-emerald-700' : (summary.on_time_completion_rate ?? 0) >= 0.6 ? 'text-amber-700' : 'text-rose-700'">
                            {{ summary.on_time_completion_rate != null ? `${ratioPercent(summary.on_time_completion_rate)}%` : '—' }}
                        </div>
                        <div class="text-[10px] text-slate-500">المهام المنجزة قبل الموعد</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">متوسط زمن الإنجاز</div>
                        <div class="text-xl font-bold text-slate-900">{{ fmtSeconds(summary.sla?.avg_completion_seconds || 0) }}</div>
                        <div class="text-[10px] text-slate-500">للمهمة الواحدة</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">متوسط أول استجابة</div>
                        <div class="text-xl font-bold text-slate-900">{{ fmtSeconds(summary.sla?.avg_first_response_seconds || 0) }}</div>
                        <div class="text-[10px] text-slate-500">على التذاكر</div>
                    </div>
                    <div class="bg-white p-3">
                        <div class="text-[11px] text-slate-500">متوسط نقاط الأداء</div>
                        <div class="text-xl font-bold" :class="(summary.avg_productivity_score ?? 0) >= 70 ? 'text-emerald-700' : (summary.avg_productivity_score ?? 0) >= 50 ? 'text-amber-700' : 'text-rose-700'">
                            {{ summary.avg_productivity_score ?? 0 }}<span class="text-xs text-slate-400">/100</span>
                        </div>
                        <div class="text-[10px] text-slate-500">من إجمالي الفريق</div>
                    </div>
                </div>
            </div>

            <!-- Insights: top performers + needs attention -->
            <div class="grid gap-3 md:grid-cols-2">
                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">🏆</span>
                            <span class="text-sm font-bold text-slate-900">أفضل أداء</span>
                        </div>
                        <span class="text-[10px] text-slate-500">آخر {{ selectedRange }} يوماً</span>
                    </div>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(p, i) in summary.top_performers || []"
                            :key="`top-${p.id}`"
                            class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-50 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200">{{ i + 1 }}</span>
                                <img v-if="p.avatar_url" :src="p.avatar_url" class="h-6 w-6 rounded-full border border-slate-200 object-cover" />
                                <span class="truncate text-xs font-semibold text-slate-800">{{ p.name }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold" :class="gradeClass(p.grade)">{{ p.grade }}</span>
                                <span class="text-xs font-bold text-slate-900">{{ p.score }}</span>
                            </div>
                        </li>
                        <li v-if="!(summary.top_performers || []).length" class="text-[11px] text-slate-400">لا توجد بيانات كافية بعد.</li>
                    </ul>
                </div>

                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-700">⚠</span>
                            <span class="text-sm font-bold text-slate-900">يحتاج انتباه</span>
                        </div>
                        <span class="text-[10px] text-slate-500">الأعلى تأخراً / حِملاً</span>
                    </div>
                    <ul class="space-y-1.5">
                        <li
                            v-for="b in summary.bottlenecks || []"
                            :key="`bn-${b.id}`"
                            class="flex items-center justify-between gap-2 rounded-lg border border-rose-100 bg-rose-50/40 px-2.5 py-1.5"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <img v-if="b.avatar_url" :src="b.avatar_url" class="h-6 w-6 rounded-full border border-slate-200 object-cover" />
                                <span class="truncate text-xs font-semibold text-slate-800">{{ b.name }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <span class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ b.overdue_open }} متأخرة</span>
                                <span class="text-[10px] text-slate-500">{{ b.open }} مفتوحة</span>
                            </div>
                        </li>
                        <li
                            v-for="o in summary.overloaded_employees || []"
                            :key="`ol-${o.id}`"
                            class="flex items-center justify-between gap-2 rounded-lg border border-orange-100 bg-orange-50/40 px-2.5 py-1.5"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <img v-if="o.avatar_url" :src="o.avatar_url" class="h-6 w-6 rounded-full border border-slate-200 object-cover" />
                                <span class="truncate text-xs font-semibold text-slate-800">{{ o.name }}</span>
                            </div>
                            <span class="rounded-full bg-orange-600 px-2 py-0.5 text-[10px] font-bold text-white">حِمل مرتفع</span>
                        </li>
                        <li
                            v-for="iEmp in summary.inactive_employees || []"
                            :key="`in-${iEmp.id}`"
                            class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <img v-if="iEmp.avatar_url" :src="iEmp.avatar_url" class="h-6 w-6 rounded-full border border-slate-200 object-cover" />
                                <span class="truncate text-xs font-semibold text-slate-700">{{ iEmp.name }}</span>
                            </div>
                            <span class="rounded-full bg-slate-600 px-2 py-0.5 text-[10px] font-bold text-white">غير نشط</span>
                        </li>
                        <li
                            v-if="!(summary.bottlenecks || []).length && !(summary.overloaded_employees || []).length && !(summary.inactive_employees || []).length"
                            class="rounded-lg border border-emerald-100 bg-emerald-50 px-2.5 py-2 text-[11px] text-emerald-800"
                        >
                            ممتاز! لا توجد نقاط اختناق ظاهرة في هذه الفترة.
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Mood pulse + today's notes -->
            <div class="grid gap-3 md:grid-cols-2">
                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">🌡️</span>
                            <span class="text-sm font-bold text-slate-900">نبض الفريق اليوم</span>
                        </div>
                        <span class="text-[10px] text-slate-500">{{ summary.mood_total_today || 0 }} من سجّلوا حضور</span>
                    </div>
                    <div v-if="(summary.mood_total_today || 0) > 0" class="space-y-1.5">
                        <div
                            v-for="(info, key) in moodMeta"
                            :key="`mood-${key}`"
                            class="flex items-center gap-2"
                        >
                            <span class="inline-flex w-20 items-center gap-1 text-xs font-semibold text-slate-700">
                                <span>{{ info.emoji }}</span>
                                <span>{{ info.label }}</span>
                            </span>
                            <div class="relative h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="info.bar"
                                    :style="{ width: ((summary.mood_distribution_today?.[key]?.ratio || 0) * 100) + '%' }"
                                />
                            </div>
                            <span class="w-10 text-end text-[11px] font-bold text-slate-700">
                                {{ summary.mood_distribution_today?.[key]?.count || 0 }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-center text-[11px] text-slate-500">
                        لم يسجّل أي موظف حضوره اليوم بعد.
                    </div>
                </div>

                <div class="ui-card p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-base">💬</span>
                            <span class="text-sm font-bold text-slate-900">ملاحظات الفريق اليوم</span>
                        </div>
                        <span class="text-[10px] text-slate-500">{{ (summary.today_notes || []).length }} ملاحظة</span>
                    </div>
                    <ul v-if="(summary.today_notes || []).length" class="space-y-1.5">
                        <li
                            v-for="n in summary.today_notes"
                            :key="`note-${n.employee_id}`"
                            class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2"
                        >
                            <img v-if="n.avatar_url" :src="n.avatar_url" class="h-7 w-7 shrink-0 rounded-full border border-slate-200 object-cover" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-xs font-semibold text-slate-800">{{ n.name }}</span>
                                    <span
                                        v-if="moodInfo(n.mood)"
                                        class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-semibold ring-1"
                                        :class="moodInfo(n.mood).cls"
                                    >
                                        <span>{{ moodInfo(n.mood).emoji }}</span>
                                        <span>{{ moodInfo(n.mood).label }}</span>
                                    </span>
                                </div>
                                <div class="mt-0.5 whitespace-pre-line text-[11px] leading-relaxed text-slate-700">
                                    {{ n.note }}
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-3 py-3 text-center text-[11px] text-slate-500">
                        لم يكتب أي موظف ملاحظة اليوم.
                    </div>
                </div>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="ui-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="ui-card border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
                {{ page.props.flash.error }}
            </div>

            <!-- Teams performance breakdown -->
            <div v-if="(teams_breakdown || []).length" class="ui-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200/70 bg-slate-50/60 px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-base">👥</span>
                        <span class="text-sm font-bold text-slate-900">مؤشرات الأداء حسب الفريق</span>
                    </div>
                    <span class="text-[10px] text-slate-500">آخر {{ selectedRange }} يوماً</span>
                </div>
                <div class="grid gap-px bg-slate-100 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="team in teams_breakdown"
                        :key="`team-${team.id}`"
                        class="bg-white p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-bold text-slate-900">{{ team.name }}</span>
                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600">{{ team.member_count }} أعضاء</span>
                                </div>
                                <div v-if="team.lead" class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-500">
                                    <img v-if="team.lead.avatar_url" :src="team.lead.avatar_url" class="h-3.5 w-3.5 rounded-full border border-slate-200 object-cover" />
                                    قائد الفريق: <span class="font-semibold text-slate-700">{{ team.lead.name }}</span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-0.5">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                                    :class="team.avg_productivity_score >= 70 ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : team.avg_productivity_score >= 50 ? 'bg-amber-50 text-amber-800 ring-amber-200' : 'bg-rose-50 text-rose-800 ring-rose-200'"
                                >
                                    أداء: {{ team.avg_productivity_score }}/100
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full transition-all" :class="scoreBarColor(team.avg_productivity_score)" :style="{ width: team.avg_productivity_score + '%' }" />
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-1 text-center text-[10px]">
                            <div class="rounded-md bg-emerald-50 px-1.5 py-1">
                                <div class="text-emerald-700">حاضرون اليوم</div>
                                <div class="text-sm font-bold text-emerald-900">{{ team.today_checked_in }}<span class="text-[9px] text-slate-400">/{{ team.member_count }}</span></div>
                            </div>
                            <div class="rounded-md bg-sky-50 px-1.5 py-1">
                                <div class="text-sky-700">منجزة</div>
                                <div class="text-sm font-bold text-sky-900">{{ team.tasks_completed }}</div>
                            </div>
                            <div class="rounded-md bg-rose-50 px-1.5 py-1">
                                <div class="text-rose-700">متأخرة</div>
                                <div class="text-sm font-bold text-rose-900">{{ team.tasks_overdue_open }}</div>
                            </div>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-500">
                            <span>الالتزام: <strong :class="(team.on_time_ratio ?? 0) >= 0.8 ? 'text-emerald-700' : 'text-slate-700'">{{ team.on_time_ratio != null ? `${ratioPercent(team.on_time_ratio)}%` : '—' }}</strong></span>
                            <span>وقت اليوم: <strong class="text-slate-700">{{ fmtSeconds(team.today_active_seconds) }}</strong></span>
                            <span>تذاكر حُلّت: <strong class="text-slate-700">{{ team.tickets_resolved }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & sort toolbar for employees -->
            <div class="ui-card flex flex-col gap-2 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M21 21l-4.3-4.3" />
                        </svg>
                        <input
                            v-model="employeeSearch"
                            type="search"
                            placeholder="ابحث باسم الموظف أو اسم المستخدم..."
                            class="w-full rounded-xl border border-slate-200 bg-white py-2 pe-9 ps-3 text-sm placeholder:text-slate-400 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                        />
                    </div>
                    <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                        ترتيب:
                        <select
                            v-model="sortBy"
                            class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:border-brand-400 focus:outline-none focus:ring-1 focus:ring-brand-300"
                        >
                            <option v-for="o in sortOptions" :key="`s-${o.value}`" :value="o.value">{{ o.label }}</option>
                        </select>
                    </label>
                    <button
                        v-if="employeeSearch || filterBy !== 'all' || sortBy !== 'score'"
                        type="button"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-100"
                        @click="clearEmployeeFilters"
                    >
                        إعادة تعيين
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="f in filterOptions"
                        :key="`f-${f.value}`"
                        type="button"
                        :class="['rounded-full border px-2.5 py-1 text-[11px] font-semibold transition-all', filterBy === f.value ? 'border-brand-500 bg-brand-50 text-brand-800 ring-1 ring-brand-300' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50']"
                        @click="filterBy = f.value"
                    >
                        {{ f.label }}
                    </button>
                    <span class="ms-auto text-[11px] text-slate-500">{{ visibleEmployees.length }} / {{ (employees || []).length }} موظف</span>
                </div>
            </div>

            <!-- Unified employee cards: profile + analytics + actions -->
            <div class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="emp in visibleEmployees"
                    :key="`emp-${emp.id}`"
                    class="ui-card flex flex-col gap-3 overflow-hidden p-0"
                >
                    <!-- Header: avatar + identity + presence badge -->
                    <div class="flex flex-col gap-2 border-b border-slate-200/70 bg-gradient-to-l from-slate-50 via-white to-brand-50/40 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3">
                            <img
                                :src="emp.avatar_url || '/images/mobile-logo.png'"
                                alt="avatar"
                                class="h-12 w-12 shrink-0 rounded-full border border-slate-200 object-cover shadow-sm"
                            />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="text-sm font-bold text-slate-900">{{ emp.name }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                        {{ roleLabels[emp.role] || emp.role }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500" dir="ltr">@{{ emp.username }}</div>
                                <div v-if="emp.phone" class="mt-0.5 text-[11px] text-slate-600" dir="ltr">📞 +{{ emp.phone }}</div>
                                <div v-else class="mt-0.5 text-[11px] text-slate-400">— لا يوجد هاتف</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span
                                v-if="analyticsFor(emp.id)?.attendance?.today_checked_in"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" /> حاضر اليوم
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500"
                            >
                                لم يسجّل حضور اليوم
                            </span>
                            <span
                                v-if="moodInfo(analyticsFor(emp.id)?.attendance?.today_mood)"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1"
                                :class="moodInfo(analyticsFor(emp.id).attendance.today_mood).cls"
                                title="مزاج اليوم"
                            >
                                <span>{{ moodInfo(analyticsFor(emp.id).attendance.today_mood).emoji }}</span>
                                <span>{{ moodInfo(analyticsFor(emp.id).attendance.today_mood).label }}</span>
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="emp.is_bookable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ emp.is_bookable ? 'متاح للحجز' : 'غير متاح للحجز' }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-3 px-4 pb-4">
                        <!-- Today's note from employee (if any) -->
                        <div
                            v-if="analyticsFor(emp.id)?.attendance?.today_note"
                            class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-2 text-[11px] leading-relaxed text-amber-900"
                        >
                            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="currentColor"><path d="M7 7h3v3l-3 3V7zm7 0h3v3l-3 3V7z" /></svg>
                            <div class="min-w-0 flex-1">
                                <div class="text-[9px] font-bold uppercase tracking-wider text-amber-700">ملاحظة اليوم</div>
                                <div class="mt-0.5 whitespace-pre-line">{{ analyticsFor(emp.id).attendance.today_note }}</div>
                            </div>
                        </div>

                        <!-- Today's plan (selected tasks during check-in) -->
                        <details
                            v-if="analyticsFor(emp.id)?.attendance?.today_plan"
                            class="rounded-xl border border-sky-100 bg-sky-50/50 open:bg-sky-50"
                        >
                            <summary class="cursor-pointer list-none px-3 py-1.5 text-[11px] font-semibold text-sky-800">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>
                                    خطة اليوم المُلتزَم بها
                                </span>
                            </summary>
                            <div class="px-3 pb-2 text-[11px] leading-relaxed text-sky-900 whitespace-pre-line">
                                {{ analyticsFor(emp.id).attendance.today_plan }}
                            </div>
                        </details>
                        <!-- Teams -->
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">الفرق</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="team in emp.teams"
                                    :key="`team-${emp.id}-${team.slug}`"
                                    class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-100"
                                >
                                    {{ team.name }}
                                    <span v-if="team.is_lead" class="text-amber-700"> · قائد</span>
                                    <span v-if="team.allocation_percent !== null && team.allocation_percent !== ''" class="text-slate-500">
                                        · {{ team.allocation_percent }}%
                                    </span>
                                </span>
                                <span v-if="!(emp.teams || []).length" class="text-[11px] text-slate-400">— غير معيّن</span>
                            </div>
                        </div>

                        <!-- Productivity score block -->
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-base font-bold"
                                        :class="gradeClass(analyticsFor(emp.id)?.productivity?.grade)"
                                    >
                                        {{ analyticsFor(emp.id)?.productivity?.grade || 'E' }}
                                    </span>
                                    <div>
                                        <div class="text-[10px] text-slate-500">نقاط الأداء</div>
                                        <div class="text-lg font-bold text-slate-900">
                                            {{ analyticsFor(emp.id)?.productivity?.score ?? 0 }}<span class="text-xs text-slate-400">/100</span>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="(analyticsFor(emp.id)?.attendance?.streak_days || 0) >= 2"
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800 ring-1 ring-amber-200"
                                >
                                    🔥 {{ analyticsFor(emp.id).attendance.streak_days }} أيام
                                </div>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="scoreBarColor(analyticsFor(emp.id)?.productivity?.score || 0)"
                                    :style="{ width: (analyticsFor(emp.id)?.productivity?.score || 0) + '%' }"
                                />
                            </div>
                            <div class="mt-1.5 grid grid-cols-4 gap-1 text-center text-[9px]">
                                <div>
                                    <div class="text-slate-500">التزام</div>
                                    <div class="font-semibold text-slate-700">{{ analyticsFor(emp.id)?.productivity?.breakdown?.on_time ?? 0 }}%</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">حضور</div>
                                    <div class="font-semibold text-slate-700">{{ analyticsFor(emp.id)?.productivity?.breakdown?.attendance ?? 0 }}%</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">نشاط</div>
                                    <div class="font-semibold text-slate-700">{{ analyticsFor(emp.id)?.productivity?.breakdown?.activity ?? 0 }}%</div>
                                </div>
                                <div>
                                    <div class="text-slate-500">إنجاز</div>
                                    <div class="font-semibold text-slate-700">{{ analyticsFor(emp.id)?.productivity?.breakdown?.completion ?? 0 }}%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Quality signals (chips) -->
                        <div
                            v-if="(analyticsFor(emp.id)?.signals || []).length"
                            class="flex flex-wrap gap-1"
                        >
                            <span
                                v-for="sig in analyticsFor(emp.id).signals"
                                :key="`sig-${emp.id}-${sig.code}`"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="signalToneClass(sig.tone)"
                            >
                                {{ sig.label }}
                            </span>
                        </div>

                        <!-- Today / averages strip with trend deltas -->
                        <div class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-white px-2 py-2">
                                <div class="text-[10px] text-slate-500">وقت اليوم</div>
                                <div class="text-sm font-bold text-slate-900">{{ fmtSeconds(analyticsFor(emp.id)?.attendance?.today_active_seconds || 0) }}</div>
                                <div
                                    v-if="fmtDelta(analyticsFor(emp.id)?.trends?.active_seconds_delta)"
                                    class="text-[9px] font-semibold"
                                    :class="deltaToneClass(analyticsFor(emp.id)?.trends?.active_seconds_delta)"
                                >
                                    {{ fmtDelta(analyticsFor(emp.id).trends.active_seconds_delta) }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-2 py-2">
                                <div class="text-[10px] text-slate-500">معدل يومي</div>
                                <div class="text-sm font-bold text-slate-900">{{ fmtSeconds(analyticsFor(emp.id)?.attendance?.avg_active_seconds || 0) }}</div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-2 py-2">
                                <div class="text-[10px] text-slate-500">أيام حضور</div>
                                <div class="text-sm font-bold text-slate-900">{{ analyticsFor(emp.id)?.attendance?.days_present || 0 }}</div>
                                <div class="text-[9px] text-slate-500">
                                    {{ ratioPercent(analyticsFor(emp.id)?.attendance?.attendance_ratio) ?? 0 }}% من أيام العمل
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-2 py-2">
                                <div class="text-[10px] text-slate-500">آخر حضور</div>
                                <div class="text-sm font-bold text-slate-900">{{ fmtDateShort(analyticsFor(emp.id)?.attendance?.last_check_in_at) }}</div>
                            </div>
                        </div>

                        <!-- Today's real activity sessions (transparency) -->
                        <details
                            v-if="(analyticsFor(emp.id)?.attendance?.today_sessions || []).length"
                            class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/40"
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    سجل جلسات اليوم الفعلية
                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">
                                        {{ analyticsFor(emp.id).attendance.today_sessions.length }}
                                    </span>
                                </span>
                                <span class="text-[9px] text-slate-400">من فترات نشاط حقيقية (لا يحسب الخمول)</span>
                            </summary>
                            <ul class="space-y-1 px-3 pb-3 text-[10px]">
                                <li
                                    v-for="(s, i) in analyticsFor(emp.id).attendance.today_sessions"
                                    :key="`sess-${emp.id}-${i}`"
                                    class="flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-2 py-1.5"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="inline-flex h-1.5 w-1.5 rounded-full"
                                            :class="s.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'"
                                        />
                                        <span dir="ltr" class="font-mono text-slate-700">{{ fmtTimeShort(s.started_at) }}</span>
                                        <span class="text-slate-400">←</span>
                                        <span dir="ltr" class="font-mono text-slate-700">{{ s.is_open ? 'نشط' : fmtTimeShort(s.ended_at || s.last_heartbeat_at) }}</span>
                                    </div>
                                    <span class="font-bold text-slate-800">{{ fmtSeconds(s.active_seconds) }}</span>
                                </li>
                            </ul>
                        </details>

                        <!-- 7-day attendance bar chart -->
                        <div class="rounded-xl border border-slate-200 bg-white p-2">
                            <div class="mb-1 flex items-center justify-between text-[10px] text-slate-500">
                                <span>آخر 7 أيام</span>
                                <span>المعدل: {{ fmtSeconds(analyticsFor(emp.id)?.attendance?.avg_active_seconds || 0) }}</span>
                            </div>
                            <div class="flex h-14 items-end gap-1.5">
                                <div
                                    v-for="(d, i) in analyticsFor(emp.id)?.attendance?.last_seven_days || []"
                                    :key="`bar-${emp.id}-${i}`"
                                    class="flex flex-1 flex-col items-center justify-end gap-1"
                                    :title="`${d.date} — ${fmtSeconds(d.active_seconds)}`"
                                >
                                    <div
                                        class="w-full rounded-t"
                                        :class="attendanceBarColor(d.status)"
                                        :style="{ height: attendanceBarHeight(d.active_seconds) + 'px' }"
                                    />
                                    <span class="text-[8px] text-slate-400">{{ fmtDateShort(d.date) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Task + ticket KPI grid with deltas -->
                        <div class="grid grid-cols-2 gap-2 text-center text-[11px] sm:grid-cols-3 lg:grid-cols-6">
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5">
                                <div class="text-[10px] text-emerald-700">مهام منجزة</div>
                                <div class="text-sm font-bold text-emerald-900">{{ analyticsFor(emp.id)?.tasks?.completed || 0 }}</div>
                                <div
                                    v-if="fmtDelta(analyticsFor(emp.id)?.trends?.tasks_completed_delta)"
                                    class="text-[9px] font-semibold"
                                    :class="deltaToneClass(analyticsFor(emp.id)?.trends?.tasks_completed_delta)"
                                >
                                    {{ fmtDelta(analyticsFor(emp.id).trends.tasks_completed_delta) }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-sky-200 bg-sky-50 px-2 py-1.5">
                                <div class="text-[10px] text-sky-700">الالتزام بالموعد</div>
                                <div class="text-sm font-bold text-sky-900">
                                    {{ analyticsFor(emp.id)?.tasks?.on_time_ratio != null ? `${ratioPercent(analyticsFor(emp.id).tasks.on_time_ratio)}%` : '—' }}
                                </div>
                                <div class="text-[9px] text-slate-500">
                                    {{ analyticsFor(emp.id)?.tasks?.completed_on_time || 0 }} / {{ analyticsFor(emp.id)?.tasks?.completed || 0 }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5">
                                <div class="text-[10px] text-amber-700">مهام مفتوحة</div>
                                <div class="text-sm font-bold text-amber-900">{{ analyticsFor(emp.id)?.tasks?.open || 0 }}</div>
                                <div class="text-[9px] text-rose-700">{{ analyticsFor(emp.id)?.tasks?.overdue_open || 0 }} متأخرة</div>
                            </div>
                            <div class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5">
                                <div class="text-[10px] text-rose-700">متوسط إنجاز</div>
                                <div class="text-sm font-bold text-rose-900">{{ fmtSeconds(analyticsFor(emp.id)?.tasks?.avg_completion_seconds || 0) }}</div>
                                <div
                                    v-if="fmtDelta(analyticsFor(emp.id)?.trends?.avg_completion_seconds_delta)"
                                    class="text-[9px] font-semibold"
                                    :class="deltaToneClass(analyticsFor(emp.id)?.trends?.avg_completion_seconds_delta, { lowerIsBetter: true })"
                                >
                                    {{ fmtDelta(analyticsFor(emp.id).trends.avg_completion_seconds_delta) }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-2 py-1.5">
                                <div class="text-[10px] text-indigo-700">تذاكر حُلَّت</div>
                                <div class="text-sm font-bold text-indigo-900">{{ analyticsFor(emp.id)?.tickets?.resolved || 0 }}</div>
                                <div
                                    v-if="fmtDelta(analyticsFor(emp.id)?.trends?.tickets_resolved_delta)"
                                    class="text-[9px] font-semibold"
                                    :class="deltaToneClass(analyticsFor(emp.id)?.trends?.tickets_resolved_delta)"
                                >
                                    {{ fmtDelta(analyticsFor(emp.id).trends.tickets_resolved_delta) }}
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5">
                                <div class="text-[10px] text-slate-700">أول استجابة</div>
                                <div class="text-sm font-bold text-slate-900">{{ fmtSeconds(analyticsFor(emp.id)?.tickets?.first_response_avg_seconds || 0) }}</div>
                                <div class="text-[9px] text-slate-500">{{ analyticsFor(emp.id)?.tickets?.first_response_count || 0 }} تذكرة</div>
                            </div>
                        </div>

                        <!-- Workload balance bar -->
                        <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span>ميزان الحِمل مقارنة بمتوسط الفريق</span>
                                <span class="font-bold text-slate-700">
                                    {{ analyticsFor(emp.id)?.tasks?.workload_index || 0 }}<span class="text-slate-400">%</span>
                                </span>
                            </div>
                            <div class="relative mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <!-- 100% marker line -->
                                <div class="absolute inset-y-0 left-1/2 w-px bg-slate-300" style="transform: translateX(-0.5px)"></div>
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="workloadBarColor(analyticsFor(emp.id)?.tasks?.workload_index || 0)"
                                    :style="{ width: (workloadBarWidth(analyticsFor(emp.id)?.tasks?.workload_index || 0) / 2) + '%' }"
                                />
                            </div>
                            <div class="mt-0.5 flex items-center justify-between text-[9px] text-slate-400">
                                <span>أقل</span>
                                <span>متوسط الفريق</span>
                                <span>أعلى</span>
                            </div>
                        </div>

                        <!-- Recent attendance notes (collapsible) -->
                        <details
                            v-if="(analyticsFor(emp.id)?.attendance?.recent_notes || []).length"
                            class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/50"
                        >
                            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z" /></svg>
                                    أحدث الملاحظات والمزاج
                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-600">
                                        {{ analyticsFor(emp.id).attendance.recent_notes.length }}
                                    </span>
                                </span>
                            </summary>
                            <ul class="space-y-1.5 px-3 pb-3">
                                <li
                                    v-for="(rn, i) in analyticsFor(emp.id).attendance.recent_notes"
                                    :key="`rn-${emp.id}-${i}`"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-2"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] font-semibold text-slate-500">{{ fmtFullDateShort(rn.date) }}</span>
                                        <span
                                            v-if="moodInfo(rn.mood)"
                                            class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-semibold ring-1"
                                            :class="moodInfo(rn.mood).cls"
                                        >
                                            <span>{{ moodInfo(rn.mood).emoji }}</span>
                                            <span>{{ moodInfo(rn.mood).label }}</span>
                                        </span>
                                    </div>
                                    <div class="mt-1 whitespace-pre-line text-[11px] leading-relaxed text-slate-700">{{ rn.note }}</div>
                                </li>
                            </ul>
                        </details>

                        <!-- Availability summary (collapsible) -->
                        <details class="rounded-xl border border-slate-200 bg-white open:bg-slate-50/50">
                            <summary class="cursor-pointer list-none px-3 py-2 text-[11px] font-semibold text-slate-700">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                                    جدول التوفّر الأسبوعي
                                </span>
                            </summary>
                            <div class="px-3 pb-3 text-[11px] leading-relaxed text-slate-600">
                                {{ scheduleSummary(emp.availability_schedule) }}
                            </div>
                        </details>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200/70 pt-3">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="openEditModal(emp)"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                تعديل
                            </button>
                            <SecondaryButton
                                type="button"
                                class="!inline-flex !items-center !gap-1 !rounded-lg !border-emerald-300 !bg-emerald-50 !px-3 !py-1.5 !text-xs !font-semibold !text-emerald-900 hover:!bg-emerald-100 disabled:!opacity-50"
                                :disabled="!emp.phone || sendingLoginId === emp.id"
                                @click="sendLoginWhatsApp(emp)"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.4 11.4 0 0 0 12 0a11.5 11.5 0 0 0-9.9 17.3L0 24l6.9-2A11.5 11.5 0 0 0 12 23a11.5 11.5 0 0 0 8.5-19.5zM12 21.1a9.5 9.5 0 0 1-4.8-1.3l-.3-.2-4.1 1.2 1.1-4-.2-.3A9.5 9.5 0 1 1 21.5 12 9.5 9.5 0 0 1 12 21.1z"/></svg>
                                واتساب
                            </SecondaryButton>
                            <DangerButton
                                type="button"
                                class="!inline-flex !items-center !gap-1 !rounded-lg !px-3 !py-1.5 !text-xs !font-semibold"
                                @click="deleteEmployee(emp)"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m5 0V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2"/></svg>
                                حذف
                            </DangerButton>
                        </div>
                    </div>
                </div>
                <div v-if="!employees.length" class="ui-card col-span-full p-8 text-center text-sm text-slate-500">
                    لا يوجد موظفون بعد. اضغط "إضافة موظف" لإنشاء أول حساب.
                </div>
            </div>

        </div>

        <Modal :show="createModalOpen" @close="closeCreateModal">
            <div class="glass-modal employee-light-modal p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">إضافة موظف جديد</h2>
                <div
                    v-if="createSubmitDiagnostics"
                    id="employee-create-diagnostics"
                    class="mt-3 rounded-lg border-2 border-rose-600 bg-rose-100 px-3 py-3 text-sm text-rose-950"
                    dir="auto"
                >
                    <p class="font-bold">تشخيص الحفظ</p>
                    <pre class="mt-2 max-h-52 overflow-y-auto whitespace-pre-wrap break-words font-sans text-xs leading-relaxed">{{ createSubmitDiagnostics }}</pre>
                </div>
                <div
                    v-if="Object.keys(createForm.errors).length"
                    class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900"
                >
                    <p class="font-semibold">تعذّر الحفظ — راجع الحقول أو الرسائل أدناه:</p>
                    <ul class="mt-2 list-disc space-y-0.5 ps-5">
                        <li v-for="(msg, key) in createForm.errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="c_name" value="الاسم" />
                            <TextInput id="c_name" v-model="createForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="createForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="c_username" value="اسم المستخدم" />
                            <TextInput id="c_username" v-model="createForm.username" type="text" class="mt-1 block w-full font-mono text-sm" dir="ltr" placeholder="مثال: aj2642" autocomplete="username" required />
                            <p class="mt-0.5 text-[11px] text-gray-500">أحرف لاتينية صغيرة وأرقام فقط (بدون بريد إلكتروني).</p>
                            <InputError class="mt-1" :message="createForm.errors.username" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="c_phone_cc" value="مفتاح الدولة" />
                            <select
                                id="c_phone_cc"
                                v-model="createForm.phone_country_code"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                autocomplete="off"
                            >
                                <option
                                    v-for="opt in dialOptions"
                                    :key="`cc-${opt.value}`"
                                    :value="String(opt.value)"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="createForm.errors.phone_country_code" />
                        </div>
                        <div>
                            <InputLabel for="c_phone_local" value="رقم الهاتف (بدون المفتاح)" />
                            <TextInput
                                id="c_phone_local"
                                v-model="createForm.phone_local"
                                type="text"
                                class="mt-1 block w-full font-mono text-sm"
                                dir="ltr"
                                placeholder="مثال: 7812345678"
                                autocomplete="tel-national"
                            />
                            <p class="mt-0.5 text-[11px] text-gray-500">اترك فارغًا إن لم يكن للموظف رقم واتساب.</p>
                            <InputError class="mt-1" :message="createForm.errors.phone_local" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="c_role" value="الدور" />
                            <select id="c_role" v-model="createForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="member">موظف</option>
                                <option value="lead">قائد فريق</option>
                                <option v-if="canAssignAdmin" value="admin">مدير النظام</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="c_password" value="كلمة المرور" />
                            <TextInput id="c_password" v-model="createForm.password" type="password" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="createForm.errors.password" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="createForm.is_bookable" type="checkbox" class="rounded border-gray-300 text-brand-600" />
                        متاح للحجز
                    </label>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">التوفر اليومي (بساعات مختلفة لكل يوم)</p>
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(row, idx) in createForm.availability_schedule"
                                :key="`c-day-${row.day}`"
                                class="rounded border border-gray-100 p-2"
                            >
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="row.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                        />
                                        {{ dayOptions.find((d) => d.value === row.day)?.label }}
                                    </label>
                                    <span class="text-xs text-gray-500">{{ row.enabled ? 'متاح' : 'غير متاح' }}</span>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`c_av_start_${row.day}`" value="من الساعة" />
                                        <TextInput :id="`c_av_start_${row.day}`" v-model="row.start" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`c_av_end_${row.day}`" value="إلى الساعة" />
                                        <TextInput :id="`c_av_end_${row.day}`" v-model="row.end" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="createForm.errors[`availability_schedule.${idx}.start`]" />
                                <InputError class="mt-1" :message="createForm.errors[`availability_schedule.${idx}.end`]" />
                            </div>
                        </div>
                        <InputError class="mt-1" :message="createForm.errors.availability_schedule" />
                    </div>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">الفرق</p>
                        <p v-if="!teams.length" class="mt-2 text-xs text-amber-700">
                            لا توجد فرق حالياً، سيتم إنشاء الفرق الافتراضية تلقائياً عند إعادة تحميل الصفحة.
                        </p>
                        <div class="mt-2 space-y-2">
                            <div v-for="team in teams" :key="`c-team-${team.id}`" class="rounded border border-gray-100 p-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="hasTeam(createForm, team.id)"
                                        class="rounded border-gray-300 text-brand-600"
                                        @change="(e) => toggleTeamSelection(createForm, team.id, e.target.checked)"
                                    />
                                    {{ team.name }}
                                </label>
                                <div v-if="hasTeam(createForm, team.id)" class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`c-allocation-${team.id}`" value="نسبة التوزيع %" />
                                        <TextInput
                                            :id="`c-allocation-${team.id}`"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full"
                                            :model-value="teamItem(createForm, team.id)?.allocation_percent"
                                            @update:model-value="(v) => (teamItem(createForm, team.id).allocation_percent = v)"
                                        />
                                    </div>
                                    <label class="mt-6 flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                            :checked="teamItem(createForm, team.id)?.is_lead"
                                            @change="(e) => (teamItem(createForm, team.id).is_lead = e.target.checked)"
                                        />
                                        قائد الفريق
                                    </label>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="createForm.errors.teams" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="w-full rounded border border-gray-300 px-4 py-2 text-sm sm:w-auto"
                            @click="closeCreateModal"
                        >
                            إلغاء
                        </button>
                        <PrimaryButton :disabled="createForm.processing" type="submit" class="w-full justify-center sm:w-auto">إضافة</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="editModalOpen" @close="closeEditModal">
            <div class="glass-modal employee-light-modal p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">تعديل الموظف</h2>
                <div
                    v-if="editSubmitDiagnostics"
                    id="employee-edit-diagnostics"
                    class="mt-3 rounded-lg border-2 border-rose-600 bg-rose-100 px-3 py-3 text-sm text-rose-950"
                    dir="auto"
                >
                    <p class="font-bold">تشخيص الحفظ</p>
                    <pre class="mt-2 max-h-52 overflow-y-auto whitespace-pre-wrap break-words font-sans text-xs leading-relaxed">{{ editSubmitDiagnostics }}</pre>
                </div>
                <div
                    v-if="Object.keys(editForm.errors).length"
                    class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900"
                >
                    <p class="font-semibold">تعذّر الحفظ — راجع الحقول أو الرسائل أدناه:</p>
                    <ul class="mt-2 list-disc space-y-0.5 ps-5">
                        <li v-for="(msg, key) in editForm.errors" :key="key">{{ msg }}</li>
                    </ul>
                </div>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="e_name" value="الاسم" />
                            <TextInput id="e_name" v-model="editForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="editForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="e_username" value="اسم المستخدم" />
                            <TextInput id="e_username" v-model="editForm.username" type="text" class="mt-1 block w-full font-mono text-sm" dir="ltr" autocomplete="username" required />
                            <InputError class="mt-1" :message="editForm.errors.username" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="e_phone_cc" value="مفتاح الدولة" />
                            <select
                                id="e_phone_cc"
                                v-model="editForm.phone_country_code"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                autocomplete="off"
                            >
                                <option
                                    v-for="opt in dialOptions"
                                    :key="`ecc-${opt.value}`"
                                    :value="String(opt.value)"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="editForm.errors.phone_country_code" />
                        </div>
                        <div>
                            <InputLabel for="e_phone_local" value="رقم الهاتف (بدون المفتاح)" />
                            <TextInput
                                id="e_phone_local"
                                v-model="editForm.phone_local"
                                type="text"
                                class="mt-1 block w-full font-mono text-sm"
                                dir="ltr"
                                placeholder="مثال: 7812345678"
                                autocomplete="tel-national"
                            />
                            <p class="mt-0.5 text-[11px] text-gray-500">اترك الحقلين فارغين لإزالة رقم الهاتف.</p>
                            <InputError class="mt-1" :message="editForm.errors.phone_local" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="e_role" value="الدور" />
                            <select id="e_role" v-model="editForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="member">موظف</option>
                                <option value="lead">قائد فريق</option>
                                <option v-if="canAssignAdmin || editForm.role === 'admin'" value="admin">مدير النظام</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="e_password" value="كلمة مرور جديدة (اختياري)" />
                            <TextInput id="e_password" v-model="editForm.password" type="password" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="editForm.errors.password" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_bookable" type="checkbox" class="rounded border-gray-300 text-brand-600" />
                        متاح للحجز
                    </label>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">التوفر اليومي (بساعات مختلفة لكل يوم)</p>
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(row, idx) in editForm.availability_schedule"
                                :key="`e-day-${row.day}`"
                                class="rounded border border-gray-100 p-2"
                            >
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="row.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                        />
                                        {{ dayOptions.find((d) => d.value === row.day)?.label }}
                                    </label>
                                    <span class="text-xs text-gray-500">{{ row.enabled ? 'متاح' : 'غير متاح' }}</span>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`e_av_start_${row.day}`" value="من الساعة" />
                                        <TextInput :id="`e_av_start_${row.day}`" v-model="row.start" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`e_av_end_${row.day}`" value="إلى الساعة" />
                                        <TextInput :id="`e_av_end_${row.day}`" v-model="row.end" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="editForm.errors[`availability_schedule.${idx}.start`]" />
                                <InputError class="mt-1" :message="editForm.errors[`availability_schedule.${idx}.end`]" />
                            </div>
                        </div>
                        <InputError class="mt-1" :message="editForm.errors.availability_schedule" />
                    </div>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">الفرق</p>
                        <p v-if="!teams.length" class="mt-2 text-xs text-amber-700">
                            لا توجد فرق حالياً، سيتم إنشاء الفرق الافتراضية تلقائياً عند إعادة تحميل الصفحة.
                        </p>
                        <div class="mt-2 space-y-2">
                            <div v-for="team in teams" :key="`e-team-${team.id}`" class="rounded border border-gray-100 p-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="hasTeam(editForm, team.id)"
                                        class="rounded border-gray-300 text-brand-600"
                                        @change="(e) => toggleTeamSelection(editForm, team.id, e.target.checked)"
                                    />
                                    {{ team.name }}
                                </label>
                                <div v-if="hasTeam(editForm, team.id)" class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`e-allocation-${team.id}`" value="نسبة التوزيع %" />
                                        <TextInput
                                            :id="`e-allocation-${team.id}`"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full"
                                            :model-value="teamItem(editForm, team.id)?.allocation_percent"
                                            @update:model-value="(v) => (teamItem(editForm, team.id).allocation_percent = v)"
                                        />
                                    </div>
                                    <label class="mt-6 flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                            :checked="teamItem(editForm, team.id)?.is_lead"
                                            @change="(e) => (teamItem(editForm, team.id).is_lead = e.target.checked)"
                                        />
                                        قائد الفريق
                                    </label>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="editForm.errors.teams" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="w-full rounded border border-gray-300 px-4 py-2 text-sm sm:w-auto"
                            @click="closeEditModal"
                        >
                            إلغاء
                        </button>
                        <PrimaryButton :disabled="editForm.processing" type="submit" class="w-full justify-center sm:w-auto">حفظ التغييرات</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.glass-modal {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    animation: modal-in 220ms ease-out;
}

.employee-light-modal {
    color: #111111;
}

.employee-light-modal :deep(input),
.employee-light-modal :deep(select),
.employee-light-modal :deep(textarea),
.employee-light-modal :deep(label),
.employee-light-modal :deep(p),
.employee-light-modal :deep(span),
.employee-light-modal :deep(.text-gray-900),
.employee-light-modal :deep(.text-gray-800),
.employee-light-modal :deep(.text-gray-700),
.employee-light-modal :deep(.text-gray-600),
.employee-light-modal :deep(.text-gray-500) {
    color: #111111 !important;
}

.employees-page :deep(.bg-white),
.employees-page :deep(.bg-white\/70),
.employees-page :deep(.bg-white\/75),
.employees-page :deep(.bg-white\/80),
.employees-page :deep(.bg-gray-50),
.employees-page :deep(.bg-gray-100) {
    color: #111111 !important;
}

.employees-page :deep(.bg-white a),
.employees-page :deep(.bg-white\/70 a),
.employees-page :deep(.bg-white\/75 a),
.employees-page :deep(.bg-white\/80 a),
.employees-page :deep(.bg-gray-50 a),
.employees-page :deep(.bg-gray-100 a) {
    color: #111111 !important;
}

.employees-page :deep(select),
.employees-page :deep(input),
.employees-page :deep(textarea) {
    color: #111111;
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
