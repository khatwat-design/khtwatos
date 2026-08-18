<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    meetings: Array, hosts: Array, clients: Array, teams: Array,
    filters: Object, stats: Object, metaLeads: Array,
});

const now = ref(new Date());
setInterval(() => { now.value = new Date(); }, 60000);

const search = ref('');

const statusLabels = {
    scheduled: 'مجدول', canceled: 'ملغى', completed: 'مكتمل', postponed: 'مؤجل',
};
const statusColors = {
    scheduled: 'bg-amber-100 text-amber-800', canceled: 'bg-rose-100 text-rose-800',
    completed: 'bg-emerald-100 text-emerald-800', postponed: 'bg-indigo-100 text-indigo-800',
};
const workflowLabels = {
    kull: 'الكل', sakhin: 'ساخن', dafae: 'دافئ', barid: 'بارد',
    moutafaq: 'تم الاتفاق', lam_yattasel: 'لم يتواصل', moshtarek: 'مشترك',
    istihqaq: 'استحقاق', istihdaf: 'استهداف', matabaa: 'متابعة',
    mohtamal: 'محتمل', khutwat: 'خطوات', khat: 'خط',
};
const workflowColors = {
    sakhin: 'bg-orange-100 text-orange-800', dafae: 'bg-amber-100 text-amber-800',
    barid: 'bg-sky-100 text-sky-800', moutafaq: 'bg-emerald-100 text-emerald-800',
    lam_yattasel: 'bg-gray-100 text-gray-700', matabaa: 'bg-purple-100 text-purple-800',
    mohtamal: 'bg-cyan-100 text-cyan-800', istihdaf: 'bg-indigo-100 text-indigo-800',
};
const employeeNameMap = {
    Mahmoud: 'محمود', Maha: 'مها', Laith: 'ليث',
    'Hussein Salam': 'حسين سلام', 'Hussein Ali': 'حسين علي',
    'Ahmed Bashir': 'أحمد بشير', Admin: 'مدير النظام',
    Abdullah: 'عبدالله', Shatha: 'شذى', Noor: 'نور',
    Nabras: 'نبراس', 'Mohammed Thaer': 'محمد ثائر', 'Mohammed Khalid': 'محمد خالد',
};
function arabicEmployeeName(name) { return employeeNameMap[name] || name; }
function formatDt(iso) { return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }); }
function formatTime(iso) { return new Date(iso).toLocaleString('ar-SA', { timeStyle: 'short' }); }
function formatDate(iso) { return new Date(iso).toLocaleDateString('ar-SA', { weekday: 'long', month: 'long', day: 'numeric' }); }
function countdown(iso) {
    const diff = new Date(iso) - now.value;
    if (diff <= 0) return 'الآن';
    const hrs = Math.floor(diff / 3600000), mins = Math.floor((diff % 3600000) / 60000);
    return hrs > 0 ? `بعد ${hrs}س ${mins}د` : `بعد ${mins}د`;
}
function countdownUrgency(iso) {
    const diff = new Date(iso) - now.value;
    if (diff <= 0) return 'text-emerald-600';
    if (diff < 3600000) return 'text-red-600 font-bold';
    if (diff < 7200000) return 'text-amber-600';
    return 'text-gray-400';
}
function isToday(iso) {
    const d = new Date(iso), t = now.value;
    return d.getDate() === t.getDate() && d.getMonth() === t.getMonth() && d.getFullYear() === t.getFullYear();
}
function isTomorrow(iso) {
    const d = new Date(iso), t = new Date(now.value);
    t.setDate(t.getDate() + 1);
    return d.getDate() === t.getDate() && d.getMonth() === t.getMonth() && d.getFullYear() === t.getFullYear();
}
function isThisWeek(iso) {
    const d = new Date(iso), t = now.value;
    const start = new Date(t); start.setDate(t.getDate() - ((t.getDay() + 6) % 7));
    const end = new Date(start); end.setDate(start.getDate() + 7);
    return d >= start && d < end;
}
function openWhatsApp(phone) {
    window.open(`https://wa.me/${String(phone).replace(/[^0-9]/g, '')}`, '_blank');
}

const filteredMeetings = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.meetings;
    return props.meetings.filter(m =>
        m.title?.toLowerCase().includes(q) ||
        m.host?.name?.toLowerCase().includes(q) ||
        m.meta_lead?.name?.toLowerCase().includes(q) ||
        m.meta_lead?.phone?.includes(q) ||
        m.client?.name?.toLowerCase().includes(q) ||
        m.invitee_name?.toLowerCase().includes(q)
    );
});

const groupedMeetings = computed(() => {
    const groups = { today: [], tomorrow: [], week: [], later: [] };
    for (const m of filteredMeetings.value) {
        if (isToday(m.start_at)) groups.today.push(m);
        else if (isTomorrow(m.start_at)) groups.tomorrow.push(m);
        else if (isThisWeek(m.start_at)) groups.week.push(m);
        else groups.later.push(m);
    }
    return groups;
});

function goIndex(params) {
    isMeetingsLoading.value = true;
    router.get(route('meetings.index'), params, {
        preserveState: true, replace: true,
        onFinish: () => { isMeetingsLoading.value = false; },
    });
}
function baseFilters() {
    return {
        user_id: props.filters.user_id || undefined,
        client_id: props.filters.client_id || undefined,
        status: props.filters.status || undefined,
        scope: props.filters.scope || undefined,
        include_archived: props.filters.include_archived ? 1 : undefined,
        meta_lead_id: props.filters.meta_lead_id || undefined,
    };
}
function setHost(e) { goIndex({ ...baseFilters(), user_id: e.target.value || undefined }); }
function setClient(e) { goIndex({ ...baseFilters(), client_id: e.target.value || undefined }); }
function setMetaLead(e) { goIndex({ ...baseFilters(), meta_lead_id: e.target.value || undefined }); }
function setStatus(e) { goIndex({ ...baseFilters(), status: e.target.value || undefined }); }
function setScope(e) { goIndex({ ...baseFilters(), scope: e.target.value || undefined }); }
function toggleArchivedFilter(e) { goIndex({ ...baseFilters(), include_archived: e.target.checked ? 1 : undefined }); }
function clearAllFilters() { goIndex({ include_archived: undefined }); }
function deleteMeeting(id) {
    if (!confirm('حذف هذا الاجتماع؟')) return;
    isMeetingsLoading.value = true;
    router.delete(route('meetings.destroy', id), { preserveScroll: true, onFinish: () => { isMeetingsLoading.value = false; } });
}
function archiveMeeting(id) { router.post(route('meetings.archive', id), {}, { preserveScroll: true }); }
function restoreMeeting(id) { router.post(route('meetings.restore', id), {}, { preserveScroll: true }); }
function postponeMeeting(id) { router.post(route('meetings.postpone', id), {}, { preserveScroll: true }); }

const activeFilterCount = computed(() => {
    let count = 0;
    if (props.filters.user_id) count++;
    if (props.filters.client_id) count++;
    if (props.filters.status) count++;
    if (props.filters.scope) count++;
    if (props.filters.meta_lead_id) count++;
    if (props.filters.include_archived) count++;
    return count;
});

const isMeetingsLoading = ref(false);
const showFilters = ref(false);
const filterRef = ref(null);

const completeModalOpen = ref(false);
const completingMeetingId = ref(null);
const completeForm = useForm({ summary: '' });

function openCompleteModal(id) {
    completingMeetingId.value = id; completeForm.summary = '';
    completeForm.clearErrors(); completeModalOpen.value = true;
}
function closeCompleteModal() { completeModalOpen.value = false; completingMeetingId.value = null; }
function submitComplete() {
    if (!completingMeetingId.value) return;
    completeForm.post(route('meetings.complete', completingMeetingId.value), {
        preserveScroll: true, onSuccess: closeCompleteModal,
    });
}

function handleClickOutside(e) {
    if (filterRef.value && !filterRef.value.contains(e.target)) {
        showFilters.value = false;
    }
}
onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <Head title="الاجتماعات" />
    <AuthenticatedLayout>
        <template #title>
            <div class="flex items-center gap-2">
                <span>الاجتماعات</span>
                <span class="hidden text-sm font-normal text-gray-400 sm:inline">— نظام إدارة العملاء</span>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-4">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="group relative overflow-hidden rounded-2xl border border-gray-100 bg-gradient-to-br from-white to-gray-50 p-4 shadow-sm transition hover:shadow-md">
                    <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-gray-100/60" />
                    <div class="relative">
                        <p class="text-[11px] font-semibold text-gray-500">اجتماعات اليوم</p>
                        <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-gray-900">{{ stats?.today || 0 }}</p>
                    </div>
                </div>
                <div class="group relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-4 shadow-sm transition hover:shadow-md">
                    <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-amber-100/60" />
                    <div class="relative">
                        <p class="text-[11px] font-semibold text-amber-600">المجدولة</p>
                        <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-amber-700">{{ stats?.scheduled || 0 }}</p>
                    </div>
                </div>
                <div class="group relative overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-white to-emerald-50 p-4 shadow-sm transition hover:shadow-md">
                    <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-emerald-100/60" />
                    <div class="relative">
                        <p class="text-[11px] font-semibold text-emerald-600">المكتملة</p>
                        <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-emerald-700">{{ stats?.completed || 0 }}</p>
                    </div>
                </div>
                <div class="group relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-4 shadow-sm transition hover:shadow-md">
                    <div class="absolute -left-3 -top-3 h-16 w-16 rounded-full bg-indigo-100/60" />
                    <div class="relative">
                        <p class="text-[11px] font-semibold text-indigo-600">المؤرشفة</p>
                        <p class="mt-1 text-3xl font-black tabular-nums tracking-tight text-indigo-700">{{ stats?.archived || 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input v-model="search" type="text"
                        class="min-h-11 w-full rounded-xl border border-gray-200 bg-white pr-10 pl-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-brand-400 focus:ring-1 focus:ring-brand-400"
                        placeholder="ابحث بالعنوان، العميل، المضيف..."
                    />
                </div>
                <div ref="filterRef" class="relative">
                    <button type="button"
                        class="relative flex min-h-11 min-w-11 items-center justify-center rounded-xl border bg-white shadow-sm transition"
                        :class="activeFilterCount > 0 ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click.stop="showFilters = !showFilters">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                        <span v-if="activeFilterCount > 0"
                            class="absolute -left-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <Transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                        <div v-if="showFilters"
                            class="absolute left-0 top-full z-50 mt-2 w-72 rounded-2xl border border-gray-200 bg-white p-4 shadow-xl"
                            dir="rtl">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-900">الفلاتر</h3>
                                <button v-if="activeFilterCount > 0" type="button"
                                    class="text-xs font-medium text-brand-600 hover:text-brand-700"
                                    @click="clearAllFilters">مسح الكل</button>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[11px] font-semibold text-gray-500">المضيف:</label>
                                    <select class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="filters.user_id ?? ''" @change="setHost">
                                        <option value="">الكل</option>
                                        <option v-for="h in hosts" :key="h.id" :value="h.id">{{ arabicEmployeeName(h.name) }}</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[11px] font-semibold text-gray-500">الحالة:</label>
                                    <select class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="filters.status ?? ''" @change="setStatus">
                                        <option value="">الكل</option>
                                        <option value="scheduled">مجدول</option>
                                        <option value="postponed">مؤجل</option>
                                        <option value="completed">منتهي</option>
                                        <option value="canceled">ملغى</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[11px] font-semibold text-gray-500">النوع:</label>
                                    <select class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="filters.scope ?? ''" @change="setScope">
                                        <option value="">الكل</option>
                                        <option value="internal">داخلية</option>
                                        <option value="client">اجتماعات العملاء</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[11px] font-semibold text-gray-500">العميل:</label>
                                    <select class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="filters.client_id ?? ''" @change="setClient">
                                        <option value="">الكل</option>
                                        <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                                <div v-if="metaLeads?.length" class="space-y-1">
                                    <label class="block text-[11px] font-semibold text-gray-500">عميل متجر:</label>
                                    <select class="min-h-10 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 text-sm text-gray-900"
                                        :value="filters.meta_lead_id ?? ''" @change="setMetaLead">
                                        <option value="">الكل</option>
                                        <option v-for="ml in metaLeads" :key="ml.id" :value="ml.id">{{ ml.full_name }}</option>
                                    </select>
                                </div>
                                <label class="flex items-center gap-2 text-[11px] font-semibold text-gray-500">
                                    <input type="checkbox" class="rounded border-gray-300 text-brand-600"
                                        :checked="Boolean(filters.include_archived)" @change="toggleArchivedFilter" />
                                    عرض المؤرشفة
                                </label>
                            </div>
                        </div>
                    </Transition>
                </div>
                <Link :href="route('meetings.create')"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    جديد
                </Link>
            </div>

            <div v-if="isMeetingsLoading" class="space-y-3">
                <div v-for="n in 4" :key="`skel-${n}`" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="skeleton h-4 w-1/3 rounded-lg" />
                    <div class="skeleton mt-3 h-3 w-2/3 rounded-lg" />
                    <div class="skeleton mt-4 h-14 rounded-xl" />
                </div>
            </div>

            <div v-else-if="!meetings.length"
                class="rounded-2xl border border-dashed border-gray-200 bg-white/80 px-4 py-10 text-center text-sm text-gray-500">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                    <svg class="h-7 w-7 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 6v12M6 12h12" />
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-900">لا توجد اجتماعات</p>
                <p class="mt-1">أنشئ اجتماعاً جديداً لتبدأ.</p>
                <Link :href="route('meetings.create')"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    اجتماع جديد
                </Link>
            </div>

            <template v-else>
                <div v-if="groupedMeetings.today.length">
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-base font-bold text-gray-900 sm:text-lg">اليوم</h3>
                        <span class="text-xs text-gray-400">{{ groupedMeetings.today.length }} اجتماعات</span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="m in groupedMeetings.today" :key="m.id"
                            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex gap-3">
                                <div class="flex shrink-0 flex-col items-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-sm">
                                        {{ formatTime(m.start_at) }}
                                    </div>
                                    <span v-if="m.status === 'scheduled'"
                                        class="mt-1 text-xs leading-tight" :class="countdownUrgency(m.start_at)">
                                        {{ countdown(m.start_at) }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-2">
                                        <h4 class="min-w-0 flex-1 truncate text-[15px] font-bold text-gray-900">{{ m.title }}</h4>
                                        <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight" :class="statusColors[m.status]">
                                            {{ statusLabels[m.status] }}
                                        </span>
                                    </div>
                                    <div v-if="m.meta_lead" class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-brand-700">{{ m.meta_lead.name }}</span>
                                        <span v-if="m.meta_lead.status" class="rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight" :class="workflowColors[m.meta_lead.status]">
                                            {{ workflowLabels[m.meta_lead.status] }}
                                        </span>
                                        <span v-if="m.meta_lead.campaign" class="text-xs text-gray-500">{{ m.meta_lead.campaign }}</span>
                                        <a v-if="m.meta_lead.phone" :href="`tel:${m.meta_lead.phone}`" class="text-xs text-gray-600 hover:text-brand-600" dir="ltr">{{ m.meta_lead.phone }}</a>
                                    </div>
                                    <div v-else-if="m.client" class="mt-1 text-sm text-gray-600">
                                        <Link :href="route('clients.show', m.client.id)" class="text-brand-600 hover:underline">{{ m.client.name }}</Link>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600">
                                        <span class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
                                            </svg>
                                            {{ m.host ? arabicEmployeeName(m.host.name) : '—' }}
                                        </span>
                                        <span v-if="m.participants?.length" class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                            </svg>
                                            {{ m.participants.length }}
                                        </span>
                                        <span v-if="m.invitee_name" class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="2" y="4" width="20" height="16" rx="2" /><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                                            </svg>
                                            {{ m.invitee_name }}
                                        </span>
                                    </div>
                                    <div v-if="m.reason" class="mt-2 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-700">{{ m.reason }}</div>
                                    <div v-if="m.summary" class="mt-1.5 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                        <span class="font-semibold">ملخص:</span> {{ m.summary }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="m.source === 'internal'"
                                class="mt-3 flex gap-1.5 overflow-x-auto border-t border-gray-100 pt-3">
                                <Link :href="route('meetings.edit', m.id)"
                                    class="whitespace-nowrap rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">تعديل</Link>
                                <button v-if="m.status !== 'completed'" type="button"
                                    class="whitespace-nowrap rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50"
                                    @click="openCompleteModal(m.id)">تم الاجتماع</button>
                                <button v-if="m.status !== 'completed'" type="button"
                                    class="whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50"
                                    @click="postponeMeeting(m.id)">مؤجل</button>
                                <button v-if="m.meta_lead?.phone" type="button"
                                    class="whitespace-nowrap rounded-xl border border-green-200 bg-white px-3 py-1.5 text-xs font-semibold text-green-700 shadow-sm transition hover:bg-green-50"
                                    @click="openWhatsApp(m.meta_lead.phone)">واتساب</button>
                                <button type="button"
                                    class="whitespace-nowrap rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm transition hover:bg-rose-50"
                                    @click="deleteMeeting(m.id)">حذف</button>
                                <button v-if="!m.archived_at && (m.status === 'completed' || m.status === 'canceled')" type="button"
                                    class="whitespace-nowrap rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                                    @click="archiveMeeting(m.id)">أرشفة</button>
                                <button v-if="m.archived_at" type="button"
                                    class="whitespace-nowrap rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50"
                                    @click="restoreMeeting(m.id)">استرجاع</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedMeetings.tomorrow.length" class="mt-8">
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-base font-bold text-gray-900 sm:text-lg">غداً</h3>
                        <span class="text-xs text-gray-400">{{ groupedMeetings.tomorrow.length }} اجتماعات</span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="m in groupedMeetings.tomorrow" :key="m.id"
                            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex gap-3">
                                <div class="flex shrink-0 flex-col items-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-gray-400 to-gray-500 text-sm font-bold text-white shadow-sm">
                                        {{ formatTime(m.start_at) }}
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="min-w-0 flex-1 truncate text-[15px] font-bold text-gray-900">{{ m.title }}</h4>
                                        <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight" :class="statusColors[m.status]">{{ statusLabels[m.status] }}</span>
                                    </div>
                                    <div v-if="m.meta_lead" class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-brand-700">{{ m.meta_lead.name }}</span>
                                        <span v-if="m.meta_lead.status" class="rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight"
                                            :class="workflowColors[m.meta_lead.status]">{{ workflowLabels[m.meta_lead.status] }}</span>
                                    </div>
                                    <div v-else-if="m.client" class="mt-1 text-sm text-brand-600">{{ m.client.name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ arabicEmployeeName(m.host?.name) || '—' }}</div>
                                    <div v-if="m.source === 'internal'" class="mt-3 flex gap-1.5">
                                        <Link :href="route('meetings.edit', m.id)"
                                            class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50">تعديل</Link>
                                        <button v-if="m.status !== 'completed'" type="button"
                                            class="rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50"
                                            @click="openCompleteModal(m.id)">تم الاجتماع</button>
                                        <button v-if="m.status !== 'completed'" type="button"
                                            class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50"
                                            @click="postponeMeeting(m.id)">مؤجل</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedMeetings.week.length" class="mt-8">
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-base font-bold text-gray-900 sm:text-lg">هذا الأسبوع</h3>
                        <span class="text-xs text-gray-400">{{ groupedMeetings.week.length }} اجتماعات</span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="m in groupedMeetings.week" :key="m.id"
                            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="text-xs font-medium text-gray-500">{{ formatDate(m.start_at) }} • {{ formatTime(m.start_at) }}</span>
                                        <span class="rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight" :class="statusColors[m.status]">{{ statusLabels[m.status] }}</span>
                                    </div>
                                    <h4 class="mt-0.5 truncate text-sm font-semibold text-gray-900">{{ m.title }}</h4>
                                    <div v-if="m.meta_lead" class="mt-1 flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold text-brand-600">{{ m.meta_lead.name }}</span>
                                        <span v-if="m.meta_lead.status" class="rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight"
                                            :class="workflowColors[m.meta_lead.status]">{{ workflowLabels[m.meta_lead.status] }}</span>
                                    </div>
                                    <div v-else-if="m.client" class="text-xs text-brand-600">{{ m.client.name }}</div>
                                </div>
                                <div v-if="m.source === 'internal'" class="flex shrink-0 gap-1">
                                    <Link :href="route('meetings.edit', m.id)" class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50">تعديل</Link>
                                    <button v-if="m.status !== 'completed'" type="button" class="rounded-xl border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50" @click="openCompleteModal(m.id)">تم</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="groupedMeetings.later.length" class="mt-8">
                    <div class="mb-3 flex items-baseline gap-2">
                        <h3 class="text-base font-bold text-gray-900 sm:text-lg">لاحقاً</h3>
                        <span class="text-xs text-gray-400">{{ groupedMeetings.later.length }} اجتماعات</span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="m in groupedMeetings.later" :key="m.id"
                            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="text-xs font-medium text-gray-500">{{ formatDate(m.start_at) }} • {{ formatTime(m.start_at) }}</span>
                                        <span class="rounded-md px-2 py-0.5 text-[10px] font-bold leading-tight" :class="statusColors[m.status]">{{ statusLabels[m.status] }}</span>
                                    </div>
                                    <h4 class="mt-0.5 truncate text-sm font-semibold text-gray-900">{{ m.title }}</h4>
                                    <div v-if="m.meta_lead" class="mt-1 text-xs font-semibold text-brand-600">{{ m.meta_lead.name }}</div>
                                    <div v-else-if="m.client" class="text-xs text-brand-600">{{ m.client.name }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <Modal :show="completeModalOpen" @close="closeCompleteModal">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">توثيق ملخص الاجتماع</h2>
                <p class="mt-1 text-sm text-gray-500">سيتم تحديث حالة عميل المتجر إلى "متابعة" بعد الإكمال.</p>
                <form class="mt-4 space-y-3" @submit.prevent="submitComplete">
                    <div>
                        <textarea v-model="completeForm.summary" rows="4"
                            class="block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm"
                            placeholder="اكتب ملخص الاجتماع والخطوات القادمة" required />
                        <InputError class="mt-1" :message="completeForm.errors.summary" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                            @click="closeCompleteModal">إلغاء</button>
                        <PrimaryButton :disabled="completeForm.processing" type="submit">حفظ كمكتمل</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.skeleton {
    position: relative;
    overflow: hidden;
    background: rgba(241, 245, 249, 0.8);
}
.skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.78), transparent);
    animation: shimmer 1.1s ease-in-out infinite;
}
@keyframes shimmer { to { transform: translateX(100%); } }

.filter-fade-enter-active,
.filter-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.filter-fade-enter-from,
.filter-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px) scale(0.96);
}
</style>
