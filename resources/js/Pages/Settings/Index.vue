<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    nav_catalog: {
        type: Array,
        default: () => [],
    },
    teams_navigation: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

const initialFields = {};
for (const item of props.items) {
    if (!item.locked) {
        initialFields[item.key] = Boolean(item.value);
    }
}

const form = useForm(initialFields);

function submit() {
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

const navTeamProcessing = ref(false);

function buildTeamsSubmitPayload() {
    return {
        teams: teamsNavLocal.value.map((t) => ({
            team_id: t.id,
            routes: props.nav_catalog.map((c) => ({
                route_name: c.route_name,
                allow_members: !!t.routes[c.route_name]?.allow_members,
                allow_leads: !!t.routes[c.route_name]?.allow_leads,
            })),
        })),
    };
}

function submitTeamNavigation() {
    navTeamProcessing.value = true;
    router.patch(route('settings.team-navigation.update'), buildTeamsSubmitPayload(), {
        preserveScroll: true,
        onFinish: () => {
            navTeamProcessing.value = false;
        },
    });
}

function setTeamRouteFlag(teamIdx, routeName, field, value) {
    const t = teamsNavLocal.value[teamIdx];
    if (!t.routes[routeName]) {
        t.routes[routeName] = { allow_members: false, allow_leads: false };
    }
    t.routes[routeName][field] = value;
}
</script>

<template>
    <Head title="إعدادات النظام" />

    <AuthenticatedLayout>
        <template #title>إعدادات النظام</template>

        <div class="mx-auto max-w-5xl space-y-10 text-black">
            <p class="text-sm text-slate-600">
                هذه الصفحة لمدير النظام فقط. التعديلات تُخزَّن في قاعدة البيانات؛ الإعدادات المعطّلة من ملف البيئة (.env) لا يمكن تفعيلها من هنا.
            </p>

            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">تبديلات النظام العامة</h2>
                <p class="mt-1 text-xs text-slate-600">إشعارات، تقارير، وجدولة الواتساب.</p>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div
                        v-for="item in items"
                        :key="item.key"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-slate-900">{{ item.label }}</h3>
                                <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ item.help }}</p>
                                <p v-if="item.locked && item.lock_hint" class="mt-2 text-xs font-medium text-amber-800">
                                    {{ item.lock_hint }}
                                </p>
                                <p v-if="!item.locked" class="mt-2 text-[11px] text-slate-500">
                                    الحالة الفعلية حالياً:
                                    <span
                                        :class="
                                            item.effective ? 'font-semibold text-emerald-700' : 'font-semibold text-slate-500'
                                        "
                                    >
                                        {{ item.effective ? 'مفعّل' : 'معطّل' }}
                                    </span>
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <Checkbox
                                    v-if="!item.locked"
                                    :checked="form[item.key]"
                                    :disabled="form.processing"
                                    @update:checked="(v) => (form[item.key] = v)"
                                />
                                <Checkbox v-else :checked="item.effective" disabled />
                                <span class="text-[10px] uppercase tracking-wide text-slate-400">{{
                                    item.locked ? 'مقفل بالبيئة' : ''
                                }}</span>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors[item.key]" />
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <PrimaryButton type="submit" :disabled="form.processing">حفظ التبديلات العامة</PrimaryButton>
                        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">تم التحديث.</span>
                    </div>
                </form>
            </section>

            <section v-if="nav_catalog.length && teamsNavLocal.length" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-slate-900">ظهور الصفحات في القائمة حسب الفريق</h2>
                <p class="mt-2 text-xs leading-relaxed text-slate-600">
                    حدّد لكل قسم (فريق) أي عناصر القائمة يظهر لـ
                    <strong>أعضاء الفريق</strong>
                    ولـ
                    <strong>قادة الفريق</strong>
                    (الموظفون الذين عُلّمت صفحتهم كقائد لهذا الفريق). إذا كان الشخص في أكثر من فريق، تُجمَع الصفحات المسموحة من كل الفرق
                    <strong>المقيّدة</strong>.
                </p>
                <p class="mt-2 text-xs font-medium text-amber-900">
                    إذا كان الموظف ينتمي إلى أي فريق
                    <strong>لا توجد له صفوف هنا</strong>
                    (لم تُضبط له صلاحيات قائمة)، فلا تُطبَّق قيود القائمة من هذه الشاشة، ويُعرَض كل ما تسمح به الصلاحيات العامة (مثل المخزن حسب الفريق).
                    لتقييد فريق معيّن يجب حفظ صفوف له على الأقل صفحة واحدة بتمكين عضو أو قائد.
                </p>
                <p class="mt-1 text-[11px] text-slate-500">
                    الرئيسية تُعرَض دائماً للموظفين المقيّدين حتى لا يُغلق الوصول للوحة بالكامل.
                </p>

                <div class="mt-6 space-y-8">
                    <div
                        v-for="(team, ti) in teamsNavLocal"
                        :key="`team-nav-${team.id}`"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 p-3 sm:p-4"
                    >
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ team.name }}
                            <span class="font-mono text-[11px] font-normal text-slate-500">({{ team.slug }})</span>
                        </h3>

                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-[32rem] w-full border-collapse text-start text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200 text-[11px] text-slate-500">
                                        <th class="py-2 pe-3 font-medium">الصفحة</th>
                                        <th class="py-2 px-2 font-medium">أعضاء الفريق</th>
                                        <th class="py-2 ps-2 font-medium">قادة الفريق</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="cat in nav_catalog"
                                        :key="`tn-${team.id}-${cat.route_name}`"
                                        class="border-b border-slate-100 last:border-0"
                                    >
                                        <td class="py-2 pe-3 font-medium text-slate-800">{{ cat.label }}</td>
                                        <td class="py-2 px-2">
                                            <Checkbox
                                                :checked="!!team.routes[cat.route_name]?.allow_members"
                                                :disabled="navTeamProcessing"
                                                @update:checked="
                                                    (v) => setTeamRouteFlag(ti, cat.route_name, 'allow_members', v)
                                                "
                                            />
                                        </td>
                                        <td class="py-2 ps-2">
                                            <Checkbox
                                                :checked="!!team.routes[cat.route_name]?.allow_leads"
                                                :disabled="navTeamProcessing"
                                                @update:checked="(v) => setTeamRouteFlag(ti, cat.route_name, 'allow_leads', v)"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <PrimaryButton type="button" :disabled="navTeamProcessing" @click="submitTeamNavigation">
                        حفظ صلاحيات القائمة للفرق
                    </PrimaryButton>
                </div>
            </section>

            <p v-else class="text-xs text-slate-500">لا توجد فرق في النظام بعد؛ أنشئ فرقاً من إعدادات الموظفين/المهام أولاً.</p>
        </div>
    </AuthenticatedLayout>
</template>
