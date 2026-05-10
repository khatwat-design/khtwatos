<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    toggle_sections: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    app_meta: {
        type: Object,
        default: () => ({}),
    },
    schedule_hints: {
        type: Array,
        default: () => [],
    },
    nav_sections: {
        type: Array,
        default: () => [],
    },
    teams_navigation: {
        type: Array,
        default: () => [],
    },
    database_backup: {
        type: Object,
        default: () => ({
            files: [],
            connection: '',
            driver: '',
            mode: 'full',
            keep_max_files: 30,
            compress: true,
            encrypt: false,
            encryption_configured: true,
            openssl_iterations: 600000,
            include_public_storage: true,
            include_private_storage: true,
            schedule_enabled: false,
            schedule_at: '03:30',
            storage_hint: '',
        }),
    },
});

const page = usePage();

const tabIds = ['overview', 'automation', 'navigation', 'backups', 'system'];

const tabMatch = String(page.url).match(/[?&]tab=([^&]+)/);
const initialTabId = tabMatch?.[1];
const activeTab = ref(initialTabId && tabIds.includes(initialTabId) ? initialTabId : 'overview');

watch(
    () => page.url,
    (url) => {
        const m = String(url).match(/[?&]tab=([^&]+)/);
        const t = m?.[1];
        if (t && tabIds.includes(t)) {
            activeTab.value = t;
        }
    },
);

function setTab(tid) {
    activeTab.value = tid;
    try {
        const url = new URL(window.location.href);
        if (tid === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tid);
        }
        const q = url.searchParams.toString();
        window.history.replaceState({}, '', q ? `${url.pathname}?${q}` : url.pathname);
    } catch {
        /* ignore */
    }
}

const categoryAccent = {
    notifications: 'border-emerald-400 bg-emerald-50/40 ring-emerald-100',
    whatsapp: 'border-green-500 bg-green-50/40 ring-green-100',
    portal: 'border-sky-400 bg-sky-50/40 ring-sky-100',
    reports: 'border-violet-400 bg-violet-50/40 ring-violet-100',
    other: 'border-slate-300 bg-slate-50/60 ring-slate-100',
};

function sectionAccent(sectionId) {
    return categoryAccent[sectionId] || categoryAccent.other;
}

const initialFields = {};
for (const section of props.toggle_sections) {
    for (const item of section.items || []) {
        if (!item.locked) {
            initialFields[item.key] = Boolean(item.value);
        }
    }
}

const form = useForm(initialFields);

function submitGeneral() {
    form.patch(route('settings.update'), { preserveScroll: true });
}

function deepCloneTeams(raw) {
    return raw.map((t) => ({
        id: t.id,
        name: t.name,
        slug: t.slug,
        routes: Object.fromEntries(
            Object.entries(t.routes || {}).map(([routeName, flags]) => [
                routeName,
                {
                    allow_members: !!flags.allow_members,
                    allow_leads: !!flags.allow_leads,
                },
            ]),
        ),
    }));
}

const teamsNavLocal = ref(deepCloneTeams(props.teams_navigation));

watch(
    () => props.teams_navigation,
    (v) => {
        teamsNavLocal.value = deepCloneTeams(v);
    },
    { deep: true },
);

const teamSearch = ref('');
const filteredTeamsNav = computed(() => {
    const q = teamSearch.value.trim().toLowerCase();
    if (!q) {
        return teamsNavLocal.value;
    }
    return teamsNavLocal.value.filter(
        (t) =>
            String(t.name).toLowerCase().includes(q) ||
            String(t.slug).toLowerCase().includes(q),
    );
});

const expandedTeamIds = ref(
    new Set(teamsNavLocal.value.length <= 4 ? teamsNavLocal.value.map((t) => t.id) : teamsNavLocal.value.slice(0, 2).map((t) => t.id)),
);

function teamExpanded(id) {
    return expandedTeamIds.value.has(id);
}

function toggleTeamPanel(id) {
    const next = new Set(expandedTeamIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expandedTeamIds.value = next;
}

function expandAllTeams() {
    expandedTeamIds.value = new Set(teamsNavLocal.value.map((t) => t.id));
}

function collapseAllTeams() {
    expandedTeamIds.value = new Set();
}

const flatRouteNames = computed(() =>
    props.nav_sections.flatMap((s) => s.routes.map((r) => r.route_name)),
);

function setTeamRouteFlag(teamIdx, routeName, field, value) {
    const list = teamsNavLocal.value;
    const team = list[teamIdx];
    if (!team?.routes[routeName]) {
        team.routes[routeName] = { allow_members: false, allow_leads: false };
    }
    team.routes[routeName][field] = value;
}

function teamIndexById(teamId) {
    return teamsNavLocal.value.findIndex((t) => t.id === teamId);
}

function applyBulk(teamId, mode) {
    const ti = teamIndexById(teamId);
    if (ti < 0) {
        return;
    }
    const t = teamsNavLocal.value[ti];
    for (const routeName of flatRouteNames.value) {
        if (!t.routes[routeName]) {
            t.routes[routeName] = { allow_members: false, allow_leads: false };
        }
        if (mode === 'clear') {
            t.routes[routeName].allow_members = false;
            t.routes[routeName].allow_leads = false;
        } else if (mode === 'members') {
            t.routes[routeName].allow_members = true;
            t.routes[routeName].allow_leads = false;
        } else if (mode === 'leads') {
            t.routes[routeName].allow_members = false;
            t.routes[routeName].allow_leads = true;
        } else if (mode === 'both') {
            t.routes[routeName].allow_members = true;
            t.routes[routeName].allow_leads = true;
        }
    }
}

function buildTeamsSubmitPayload() {
    return {
        teams: teamsNavLocal.value.map((t) => ({
            team_id: t.id,
            routes: flatRouteNames.value.map((route_name) => ({
                route_name,
                allow_members: !!t.routes[route_name]?.allow_members,
                allow_leads: !!t.routes[route_name]?.allow_leads,
            })),
        })),
    };
}

const navForm = useForm({ teams: [] });

function submitTeamNavigation() {
    navForm.clearErrors();
    navForm.teams = buildTeamsSubmitPayload().teams;
    navForm.patch(route('settings.team-navigation.update'), {
        preserveScroll: true,
        onSuccess: () => {
            setTab('navigation');
        },
    });
}

function tabLabel(id) {
    const labels = {
        overview: 'نظرة عامة',
        automation: 'التشغيل الآلي',
        navigation: 'القائمة والفرق',
        backups: 'نسخ احتياطي',
        system: 'التطبيق والجدولة',
    };
    return labels[id] || id;
}

function formatBytes(bytes) {
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) {
        return '0 بايت';
    }
    const k = 1024;
    const sizes = ['بايت', 'ك.ب', 'م.ب', 'غ.ب'];
    const i = Math.min(sizes.length - 1, Math.floor(Math.log(n) / Math.log(k)));

    return `${parseFloat((n / k ** i).toFixed(i === 0 ? 0 : 2))} ${sizes[i]}`;
}

const backupBusy = ref(false);

function createBackup() {
    backupBusy.value = true;
    router.post(route('settings.backups.store'), {}, {
        preserveScroll: true,
        onFinish: () => {
            backupBusy.value = false;
        },
    });
}

function deleteBackup(filename) {
    if (!window.confirm(`حذف الملف «${filename}» نهائياً من الخادم؟`)) {
        return;
    }
    backupBusy.value = true;
    router.delete(route('settings.backups.destroy', { filename }), {
        preserveScroll: true,
        onFinish: () => {
            backupBusy.value = false;
        },
    });
}

const envBadgeClass = computed(() => {
    const e = String(props.app_meta?.environment || '').toLowerCase();
    if (e === 'production') {
        return 'bg-emerald-100 text-emerald-900 ring-emerald-200';
    }
    if (e === 'local') {
        return 'bg-amber-100 text-amber-950 ring-amber-200';
    }
    return 'bg-slate-100 text-slate-800 ring-slate-200';
});

const navFormHasErrors = computed(() => Object.keys(navForm.errors || {}).length > 0);
</script>

<template>
    <Head title="إعدادات النظام" />

    <AuthenticatedLayout>
        <template #title>إعدادات النظام</template>

        <div class="settings-shell mx-auto max-w-6xl pb-8 text-slate-900">
            <!-- Hero -->
            <div
                class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50 to-brand-50/50 p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
            >
                <div class="relative z-[1] flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="max-w-2xl space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">لوحة التحكم المركزية</p>
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">إعدادات النظام</h1>
                        <p class="text-sm leading-relaxed text-slate-600">
                            اضبط التشغيل الآلي، تقارير العملاء، وإشعارات الجوال، ثم حدّد صفحات القائمة لكل فريق ولأعضاء /
                            قادة الفريق. أنشئ نسخاً احتياطية للقاعدة من هنا بدل اشتراك النسخ لدى مزوّد الاستضافة. التعديلات تُحفظ في
                            قاعدة البيانات؛ القيم المقفلة من البيئة (.env) لا تُفعَّل من هنا.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 md:flex-col md:items-end">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ring-1"
                            :class="envBadgeClass"
                        >
                            بيئة التشغيل: {{ app_meta.environment }}
                        </span>
                        <Link
                            :href="route('employees.index')"
                            class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:border-brand-300 hover:text-brand-800"
                        >
                            إدارة الموظفين والفرق
                            <svg class="h-3.5 w-3.5 rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Flash -->
            <div
                v-if="page.props.flash?.success"
                class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 shadow-sm"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.error"
                class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-900 shadow-sm"
                role="alert"
            >
                {{ page.props.flash.error }}
            </div>

            <!-- Tabs -->
            <div class="mt-6 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white/90 p-1 shadow-sm scrollbar-thin">
                <button
                    v-for="tid in tabIds"
                    :key="tid"
                    type="button"
                    class="min-w-[7.5rem] shrink-0 rounded-xl px-4 py-2.5 text-xs font-semibold transition sm:text-sm"
                    :class="
                        activeTab === tid
                            ? 'bg-brand-600 text-white shadow-md shadow-brand-600/20'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                    "
                    @click="setTab(tid)"
                >
                    {{ tabLabel(tid) }}
                </button>
            </div>

            <!-- Overview -->
            <div v-show="activeTab === 'overview'" class="mt-6 space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">الفرق</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ stats.teams_total ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ stats.teams_with_nav_rules ?? 0 }} فريق عليه قيود قائمة</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">تبديلات فعّالة</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ stats.toggles_effective_on ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-500">من أصل {{ stats.toggle_definitions_total ?? 0 }} خيارات</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">مقفلة بالبيئة</p>
                        <p class="mt-1 text-2xl font-bold text-amber-700">{{ stats.toggles_locked ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-500">تحتاج تعديل .env على الخادم</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">الجلسة</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ app_meta.session_lifetime_minutes ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">دقيقة قبل انتهاء جلسة المتصفح</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">إرشادات سريعة</h2>
                    <ul class="mt-3 list-inside list-disc space-y-2 text-xs leading-relaxed text-slate-600 sm:text-sm">
                        <li>
                            إذا كان موظف في <strong>أي فريق بدون صفوف صلاحية قائمة</strong>، لا تُطبَّق قيود القائمة من هذه الشاشة.
                        </li>
                        <li>
                            عند تفعيل الصفوف لفريق، يُجمَع السماح بين الفرق المقيّدة؛ صفحة الرئيسية تبقى متاحة لتجنّب إغلاق لوحة
                            التحكم.
                        </li>
                        <li>قيود القائمة لا تمنع الوصول المباشر بالرابط؛ للحماية الكاملة أضف طبقة صلاحيات على المسارات لاحقاً.</li>
                    </ul>
                </div>
            </div>

            <!-- Automation toggles -->
            <div v-show="activeTab === 'automation'" class="mt-6 space-y-8">
                <form class="space-y-8" @submit.prevent="submitGeneral">
                    <div v-for="section in toggle_sections" :key="section.id" class="space-y-4">
                        <div
                            class="flex flex-col gap-1 rounded-2xl border-s-4 px-4 py-3 ring-1 sm:flex-row sm:items-center sm:justify-between"
                            :class="sectionAccent(section.id)"
                        >
                            <div>
                                <h2 class="text-base font-bold text-slate-900">{{ section.title }}</h2>
                                <p class="text-xs text-slate-600">{{ section.description }}</p>
                            </div>
                            <span class="text-[10px] font-medium uppercase tracking-wider text-slate-400">{{ section.id }}</span>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="item in section.items"
                                :key="item.key"
                                class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md"
                            >
                                <div class="min-w-0">
                                    <h3 class="text-sm font-semibold text-slate-900">{{ item.label }}</h3>
                                    <p class="mt-1 text-[11px] leading-relaxed text-slate-600 sm:text-xs">{{ item.help }}</p>
                                    <p v-if="item.locked && item.lock_hint" class="mt-2 text-[11px] font-medium text-amber-800">
                                        {{ item.lock_hint }}
                                    </p>
                                    <div v-if="!item.locked" class="mt-3 flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="
                                                item.effective ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'
                                            "
                                        >
                                            فعلياً: {{ item.effective ? 'يعمل' : 'متوقف' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                    <span class="text-[10px] text-slate-400">{{ item.locked ? 'قراءة فقط' : 'قابل للتعديل' }}</span>
                                    <Checkbox
                                        v-if="!item.locked"
                                        :checked="form[item.key]"
                                        :disabled="form.processing"
                                        @update:checked="(v) => (form[item.key] = v)"
                                    />
                                    <Checkbox v-else :checked="item.effective" disabled />
                                </div>
                                <InputError class="mt-2" :message="form.errors[item.key]" />
                            </div>
                        </div>
                    </div>

                    <div class="sticky bottom-4 z-10 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur supports-[backdrop-filter]:bg-white/80">
                        <PrimaryButton type="submit" :disabled="form.processing">حفظ التبديلات العامة</PrimaryButton>
                        <span v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-700">تم الحفظ بنجاح.</span>
                    </div>
                </form>
            </div>

            <!-- Navigation -->
            <div v-show="activeTab === 'navigation'" class="mt-6 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-xl space-y-2">
                            <h2 class="text-lg font-bold text-slate-900">صلاحيات ظهور القائمة</h2>
                            <p class="text-xs leading-relaxed text-slate-600 sm:text-sm">
                                لكل فريق: حدد الصفحات لـ <strong>أعضاء الفريق</strong> ولـ <strong>قادة الفريق</strong> (علَم القائد في
                                صفحة الموظف). استخدم الأزرار الجماعية لتسريع الإعداد.
                            </p>
                        </div>
                        <div class="flex min-w-[14rem] flex-1 flex-col gap-2 lg:max-w-xs">
                            <label class="text-[11px] font-semibold text-slate-600">بحث عن فريق</label>
                            <TextInput v-model="teamSearch" type="search" class="w-full" placeholder="اسم أو slug..." />
                        </div>
                    </div>

                    <div v-if="nav_sections.length && teamsNavLocal.length" class="mt-6 space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <SecondaryButton type="button" class="text-xs" @click="expandAllTeams">توسيع الكل</SecondaryButton>
                            <SecondaryButton type="button" class="text-xs" @click="collapseAllTeams">طي الكل</SecondaryButton>
                        </div>

                        <div
                            v-if="navFormHasErrors"
                            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium text-red-800"
                            role="alert"
                        >
                            تعذّر التحقق من بعض الحقول. راجع البيانات المرسلة وأعد المحاولة.
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="team in filteredTeamsNav"
                                :key="`panel-${team.id}`"
                                class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/50 shadow-sm"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-start transition hover:bg-white/80 sm:px-5"
                                    @click="toggleTeamPanel(team.id)"
                                >
                                    <div class="min-w-0">
                                        <span class="block truncate text-sm font-bold text-slate-900">{{ team.name }}</span>
                                        <span class="font-mono text-[11px] text-slate-500">{{ team.slug }}</span>
                                    </div>
                                    <svg
                                        class="h-5 w-5 shrink-0 text-slate-500 transition-transform"
                                        :class="{ 'rotate-180': teamExpanded(team.id) }"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="M6 9l6 6 6-6" />
                                    </svg>
                                </button>

                                <div v-show="teamExpanded(team.id)" class="border-t border-slate-200 bg-white px-3 pb-4 pt-2 sm:px-5">
                                    <div class="mb-3 flex flex-wrap gap-2">
                                        <SecondaryButton type="button" class="!px-2 !py-1 text-[11px]" @click="applyBulk(team.id, 'both')">
                                            تفعيل الكل (أعضاء + قادة)
                                        </SecondaryButton>
                                        <SecondaryButton type="button" class="!px-2 !py-1 text-[11px]" @click="applyBulk(team.id, 'members')">
                                            أعضاء فقط
                                        </SecondaryButton>
                                        <SecondaryButton type="button" class="!px-2 !py-1 text-[11px]" @click="applyBulk(team.id, 'leads')">
                                            قادة فقط
                                        </SecondaryButton>
                                        <SecondaryButton type="button" class="!px-2 !py-1 text-[11px]" @click="applyBulk(team.id, 'clear')">
                                            مسح الكل
                                        </SecondaryButton>
                                    </div>

                                    <div class="space-y-6">
                                        <div v-for="sec in nav_sections" :key="`sec-${team.id}-${sec.id}`">
                                            <h4 class="mb-2 border-b border-slate-100 pb-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                                {{ sec.label }}
                                            </h4>
                                            <div class="overflow-x-auto rounded-xl ring-1 ring-slate-100">
                                                <table class="min-w-[28rem] w-full border-collapse text-start text-xs">
                                                    <thead>
                                                        <tr class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                                                            <th class="px-3 py-2 font-semibold">صفحة</th>
                                                            <th class="px-2 py-2 font-semibold">أعضاء</th>
                                                            <th class="px-2 py-2 font-semibold">قادة</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="cat in sec.routes"
                                                            :key="`r-${team.id}-${cat.route_name}`"
                                                            class="border-t border-slate-100 odd:bg-white even:bg-slate-50/40"
                                                        >
                                                            <td class="px-3 py-2 font-medium text-slate-800">{{ cat.label }}</td>
                                                            <td class="px-2 py-2">
                                                                <Checkbox
                                                                    :checked="!!team.routes[cat.route_name]?.allow_members"
                                                                    :disabled="navForm.processing"
                                                                    @update:checked="
                                                                        (v) =>
                                                                            setTeamRouteFlag(
                                                                                teamIndexById(team.id),
                                                                                cat.route_name,
                                                                                'allow_members',
                                                                                v,
                                                                            )
                                                                    "
                                                                />
                                                            </td>
                                                            <td class="px-2 py-2">
                                                                <Checkbox
                                                                    :checked="!!team.routes[cat.route_name]?.allow_leads"
                                                                    :disabled="navForm.processing"
                                                                    @update:checked="
                                                                        (v) =>
                                                                            setTeamRouteFlag(
                                                                                teamIndexById(team.id),
                                                                                cat.route_name,
                                                                                'allow_leads',
                                                                                v,
                                                                            )
                                                                    "
                                                                />
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="teamSearch.trim() && !filteredTeamsNav.length" class="text-center text-sm text-slate-500">
                            لا فرق تطابق البحث.
                        </p>

                        <div class="sticky bottom-4 z-10 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                            <PrimaryButton type="button" :disabled="navForm.processing" @click="submitTeamNavigation">
                                حفظ صلاحيات القائمة للفرق
                            </PrimaryButton>
                            <span v-if="navForm.recentlySuccessful" class="text-sm font-medium text-emerald-700">تم تحديث القائمة.</span>
                        </div>
                    </div>

                    <p v-else class="mt-4 text-sm text-slate-500">لا توجد فرق بعد — أنشئ فرقاً من صفحة الموظفين ثم عد إلى هنا.</p>
                </div>
            </div>

            <!-- Database backups -->
            <div v-show="activeTab === 'backups'" class="mt-6 space-y-6">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs leading-relaxed text-amber-950 shadow-sm sm:text-sm">
                    <strong class="font-semibold">مهم:</strong>
                    الملفات تُخزَّن على<b class="mx-0.5">قرص الخادم فقط</b>
                    (<span class="font-mono">{{ database_backup.storage_hint }}</span>). لو تعطّل الخادم أو الحساب، قد تضيع النسخ مع باقي
                    الملفات. حمّل النسخ دورياً إلى جهازك أو إلى تخزين سحابي (Drive، S3، إلخ) لتكون النسخة خارج الموقع.
                </div>

                <div
                    v-if="database_backup.encrypt && !database_backup.encryption_configured"
                    class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium text-red-900 shadow-sm sm:text-sm"
                    role="alert"
                >
                    التشفير مفعّل في الإعدادات لكن كلمة مرور النسخ غير صالحة (BACKUP_ENCRYPTION_PASSWORD يجب أن يكون 16 حرفاً على الأقل). لن
                    تنجح النسخ التلقائية أو اليدوية حتى تُضبط القيمة على الخادم.
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2">
                            <h2 class="text-lg font-bold text-slate-900">نسخ احتياطي كامل للنظام</h2>
                            <p class="text-xs text-slate-600 sm:text-sm">
                                الوضع الحالي:
                                <strong>{{ database_backup.mode === 'full' ? 'أرشيف كامل (قاعدة + ملفات)' : 'قاعدة البيانات فقط' }}</strong>
                                · الاتصال:
                                <span class="font-mono font-semibold text-slate-800">{{ database_backup.driver }}</span>
                                (<span class="font-mono text-slate-700">{{ database_backup.connection }}</span>)
                            </p>
                            <p class="text-[11px] text-slate-500">
                                في الوضع الكامل يُجمَع: تصدير القاعدة (كل الجداول — عملاء، مهام، إعدادات، إلخ) مع نسخ محليّ لـ
                                <span class="font-mono">storage/app/public</span>
                                و <span class="font-mono">storage/app/private</span> (مرفقات الشاشات، الشعارات، الدردشة… حسب استخدامك)، ثم أرشيف
                                <span class="font-mono">tar.gz</span>
                                مرتب مع ملف <span class="font-mono">manifest.json</span>.
                                {{ database_backup.encrypt ? 'الأرشيف النهائي مُشفّر بـ OpenSSL (AES-256-CBC + PBKDF2).' : 'بدون طبقة تشفير خارجية.' }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                يُحتفظ بآخر <strong>{{ database_backup.keep_max_files }}</strong> ملفاً. ضغط SQL داخل الأرشيف:
                                {{ database_backup.compress ? 'نعم' : 'لا' }}.
                            </p>
                        </div>
                        <PrimaryButton type="button" class="shrink-0" :disabled="backupBusy" @click="createBackup">
                            {{ backupBusy ? 'جاري التنفيذ…' : 'إنشاء نسخة الآن' }}
                        </PrimaryButton>
                    </div>

                    <div
                        class="mt-6 overflow-x-auto rounded-xl border border-slate-100 ring-1 ring-slate-50"
                        v-if="database_backup.files?.length"
                    >
                        <table class="min-w-full border-collapse text-start text-xs sm:text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                                    <th class="px-3 py-2 font-semibold">الملف</th>
                                    <th class="px-3 py-2 font-semibold">النوع</th>
                                    <th class="px-3 py-2 font-semibold">الحجم</th>
                                    <th class="px-3 py-2 font-semibold">التاريخ</th>
                                    <th class="px-3 py-2 font-semibold">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in database_backup.files"
                                    :key="row.filename"
                                    class="border-b border-slate-100 odd:bg-white even:bg-slate-50/50"
                                >
                                    <td class="max-w-[12rem] truncate px-3 py-2 font-mono text-[11px] text-slate-800 sm:max-w-lg sm:text-xs">
                                        {{ row.filename }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <span
                                            class="me-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="row.kind === 'full' ? 'bg-sky-100 text-sky-900' : 'bg-slate-100 text-slate-700'"
                                        >
                                            {{ row.kind === 'full' ? 'كامل' : 'قاعدة فقط' }}
                                        </span>
                                        <span
                                            v-if="row.encrypted"
                                            class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-900"
                                        >
                                            مشفّر
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ formatBytes(row.size_bytes) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ row.created_at }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a
                                                :href="route('settings.backups.download', { filename: row.filename })"
                                                class="text-[11px] font-semibold text-brand-700 underline-offset-2 hover:underline sm:text-xs"
                                            >
                                                تنزيل
                                            </a>
                                            <DangerButton
                                                type="button"
                                                class="!px-2 !py-1 text-[11px]"
                                                :disabled="backupBusy"
                                                @click="deleteBackup(row.filename)"
                                            >
                                                حذف
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-6 text-sm text-slate-500">لا توجد نسخ بعد. اضغط «إنشاء نسخة الآن» لتوليد أول ملف.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">جدولة يومية تلقائية + تشفير على الخادم</h3>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                        تأكد أنّ <span class="font-mono">cron</span> يستدعي
                        <span class="font-mono">php artisan schedule:run</span> كل دقيقة. على الإنتاج يُفضّل ضبط ما يلي في
                        <span class="font-mono">.env</span>:
                    </p>
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100"
                        >BACKUP_MODE=full
BACKUP_ENCRYPT=true
BACKUP_ENCRYPTION_PASSWORD="استبدل_بكلمة_مرور_طويلة_عشوائية_16_حرفاً_أو_أكثر"
BACKUP_SCHEDULE_ENABLED=true
BACKUP_SCHEDULE_AT={{ database_backup.schedule_at }}</pre
                    >
                    <p class="mt-3 text-[11px] text-slate-500">
                        الحالة الحالية للجدولة:
                        <strong>{{ database_backup.schedule_enabled ? 'مفعّلة' : 'معطّلة' }}</strong>
                        — وقت التشغيل المقترح: {{ database_backup.schedule_at }} (
                        {{ app_meta.timezone }} ).
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">استعادة أرشيف كامل (<span class="font-mono">*-full.tar.gz.enc</span>)</h3>
                    <p class="mt-2 text-xs text-slate-600">
                        على جهازك أو على خادم staging آمن: فك التشفير ثم فتح الأرشيف، ثم استيراد القاعدة ونسخ المجلدات إلى مسارات Laravel (بعد
                        إيقاف الموقع مؤقتاً إن لزم).
                    </p>
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100"
                        >export LARAVEL_BACKUP_ENC_PASS='نفس_BACKUP_ENCRYPTION_PASSWORD'
openssl enc -aes-256-cbc -d -salt -pbkdf2 -iter {{ database_backup.openssl_iterations }} \
  -pass env:LARAVEL_BACKUP_ENC_PASS \
  -in backup-xxxx-full.tar.gz.enc -out backup-full.tar.gz
mkdir restore &amp;&amp; tar -xzf backup-full.tar.gz -C restore
cat restore/manifest.json</pre
                    >
                    <p class="mt-2 text-xs text-slate-600">
                        ثم استورد ملف القاعدة تحت <span class="font-mono">restore/01_database/</span> (غالباً
                        <span class="font-mono">database.sql.gz</span> — استخدم <span class="font-mono">gunzip</span> ثم
                        <span class="font-mono">mysql … &lt; database.sql</span>). وللمجلدات:
                    </p>
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-[11px] text-slate-100"
                        >rsync -a restore/02_storage_public/ storage/app/public/
rsync -a restore/03_storage_private/ storage/app/private/</pre
                    >
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">استعادة نسخة «قاعدة فقط» القديمة (<span class="font-mono">.sql.gz</span>)</h3>
                    <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-[11px] text-slate-100"
                        >gunzip -c backup.sql.gz | mysql -h HOST -u USER -p DATABASE_NAME</pre
                    >
                </div>
            </div>

            <!-- System meta -->
            <div v-show="activeTab === 'system'" class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">بيانات التطبيق (قراءة)</h2>
                    <dl class="mt-4 space-y-3 text-xs sm:text-sm">
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">اسم التطبيق</dt>
                            <dd class="font-mono text-slate-900">{{ app_meta.name }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">APP_URL</dt>
                            <dd class="break-all text-end font-mono text-[11px] text-slate-800 sm:text-xs">{{ app_meta.url }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">المنطقة الزمنية</dt>
                            <dd class="font-mono text-slate-900">{{ app_meta.timezone }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">اللغة الافتراضية</dt>
                            <dd class="font-mono text-slate-900">{{ app_meta.locale }}</dd>
                        </div>
                        <div class="flex justify-between gap-2 border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">جلسة المتصفح</dt>
                            <dd class="font-mono text-slate-900">{{ app_meta.session_lifetime_minutes }} دقيقة</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">PHP</dt>
                            <dd class="font-mono text-slate-900">{{ app_meta.php_version }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">جدولة أوامر Laravel (مرجع)</h2>
                    <p class="mt-1 text-[11px] text-slate-500">
                        الأوقات من الإعدادات الحالية؛ تأكد أنّ <span class="font-mono">cron</span> يستدعي
                        <span class="font-mono">schedule:run</span> على الخادم.
                    </p>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="(row, idx) in schedule_hints"
                            :key="`sch-${idx}`"
                            class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-xs"
                        >
                            <p class="font-semibold text-slate-900">{{ row.label }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-brand-700">{{ row.command }}</p>
                            <p class="mt-1 text-[11px] text-slate-600">{{ row.schedule }}</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-thin {
    scrollbar-width: thin;
}
</style>
