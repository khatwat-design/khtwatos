<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const props = defineProps({
    client: Object,
    profile: Object,
    meta_setup: Object,
    subscription: {
        type: Object,
        default: () => ({}),
    },
});

const subscriptionBadgeClass = computed(() => (
    props.subscription?.is_active
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-amber-100 text-amber-700'
));

const metaOauthError = computed(() => {
    const e = page.props.errors?.meta_oauth;
    return Array.isArray(e) ? e[0] : e;
});

const metaStatusBadgeClass = computed(() => {
    const s = props.meta_setup?.connection_status;
    if (s === 'connected') return 'bg-emerald-100 text-emerald-800';
    if (s === 'connecting') return 'bg-sky-100 text-sky-800';
    if (s === 'partially_connected') return 'bg-amber-100 text-amber-900';
    if (s === 'needs_reconnect' || s === 'error') return 'bg-rose-100 text-rose-800';
    return 'bg-gray-100 text-gray-700';
});

const metaStatusLabel = computed(() => {
    const map = {
        not_connected: 'غير متصل',
        connecting: 'جاري الربط',
        connected: 'متصل',
        partially_connected: 'متصل جزئياً',
        error: 'خطأ',
        needs_reconnect: 'يحتاج إعادة ربط',
    };
    const s = props.meta_setup?.connection_status;
    return map[s] || 'غير متصل';
});

function formatDt(iso) {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

const infoForm = useForm({
    section: 'info',
    name: props.profile?.name || '',
    company: props.profile?.company || '',
    email: props.profile?.email || '',
    phone: props.profile?.phone || '',
});

const accountForm = useForm({
    section: 'account',
    portal_username: props.profile?.portal_username || '',
    portal_password: '',
    portal_password_confirmation: '',
});

function saveInfo() {
    infoForm.patch(route('portal.profile.update'), {
        preserveScroll: true,
    });
}

function saveAccount() {
    accountForm.patch(route('portal.profile.update'), {
        preserveScroll: true,
        onSuccess: () => accountForm.reset('portal_password', 'portal_password_confirmation'),
    });
}
</script>

<template>
    <PortalLayout title="الملف الشخصي" :client="client">
        <div class="mx-auto w-full min-w-0 max-w-4xl space-y-4 sm:space-y-6">
            <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-5 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-bold tracking-tight text-slate-900">حالة الاشتراك</h2>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold shadow-sm ring-1 ring-black/5" :class="subscriptionBadgeClass">
                        {{ subscription?.is_active ? 'مفعل' : 'غير مفعل' }}
                    </span>
                </div>
                <div class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                    <p class="rounded-xl bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100">
                        بداية الاشتراك: <strong class="tabular-nums text-slate-900">{{ formatDt(subscription?.started_at) }}</strong>
                    </p>
                    <p class="rounded-xl bg-slate-50/80 px-3 py-2 ring-1 ring-slate-100">
                        انتهاء الاشتراك: <strong class="tabular-nums text-slate-900">{{ formatDt(subscription?.ends_at) }}</strong>
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-5 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">بيانات العميل</h2>
                <p class="mt-1 text-sm text-slate-600">حدّث معلومات التواصل المعروضة لفريق العمل.</p>
                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="saveInfo">
                    <div>
                        <InputLabel for="name" value="الاسم" />
                        <TextInput id="name" v-model="infoForm.name" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" required />
                        <InputError class="mt-1" :message="infoForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="company" value="الشركة" />
                        <TextInput id="company" v-model="infoForm.company" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                        <InputError class="mt-1" :message="infoForm.errors.company" />
                    </div>
                    <div>
                        <InputLabel for="email" value="البريد" />
                        <TextInput id="email" v-model="infoForm.email" type="email" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                        <InputError class="mt-1" :message="infoForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="الهاتف" />
                        <TextInput id="phone" v-model="infoForm.phone" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                        <InputError class="mt-1" :message="infoForm.errors.phone" />
                    </div>
                    <div class="md:col-span-2 pt-1">
                        <PrimaryButton class="w-full justify-center rounded-xl sm:w-auto" :disabled="infoForm.processing">حفظ البيانات</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-5 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">ربط Meta</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                    من هنا تربط حساب Meta مرة واحدة، والنظام يسوي الفحص والإعداد التلقائي للحساب الإعلاني والصفحة والصلاحيات.
                </p>

                <div class="mt-5 rounded-2xl border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white p-4 shadow-inner sm:p-5">
                    <div v-if="metaOauthError" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">
                        {{ metaOauthError }}
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm">
                            <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                <span>الحالة:</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-bold" :class="metaStatusBadgeClass">
                                    {{ metaStatusLabel }}
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-gray-600">
                                {{ meta_setup?.user_message || (meta_setup?.meta_user_name ? `Meta User: ${meta_setup.meta_user_name}` : 'لم يتم ربط Meta بعد') }}
                            </p>
                            <p v-if="meta_setup?.meta_user_name && meta_setup?.user_message" class="mt-0.5 text-[11px] text-gray-500">
                                Meta User: {{ meta_setup.meta_user_name }}
                            </p>
                            <p v-if="meta_setup?.token_expires_at" class="mt-1 text-[11px] text-gray-500">
                                انتهاء الرمز (تقريبي): {{ formatDt(meta_setup.token_expires_at) }}
                            </p>
                            <p v-if="meta_setup?.last_connected_at" class="mt-0.5 text-[11px] text-gray-500">
                                آخر ربط ناجح: {{ formatDt(meta_setup.last_connected_at) }}
                            </p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                            <a
                                v-if="meta_setup?.connection_status === 'needs_reconnect' || meta_setup?.connection_status === 'error' || meta_setup?.connection_status === 'partially_connected'"
                                :href="route('portal.meta.oauth.redirect', { reconnect: 1 })"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-rose-700"
                            >
                                إعادة ربط Meta
                            </a>
                            <a
                                :href="route('portal.meta.oauth.redirect')"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-blue-700"
                            >
                                {{ meta_setup?.connected ? 'تحديث الربط' : 'ربط Meta الآن' }}
                            </a>
                        </div>
                    </div>
                    <div v-if="meta_setup?.missing_permissions?.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50/90 px-3 py-2 text-[11px] text-amber-950">
                        <p class="font-semibold">صلاحيات ناقصة:</p>
                        <p class="mt-1 font-mono text-[10px] leading-relaxed">{{ meta_setup.missing_permissions.join(', ') }}</p>
                    </div>

                    <div class="mt-4 grid gap-2 text-xs text-gray-700 md:grid-cols-2">
                        <p>Business: <strong>{{ meta_setup?.meta_business_id || '—' }}</strong></p>
                        <p>Ad Account: <strong>{{ meta_setup?.ad_account_id || '—' }}</strong></p>
                        <p>Page: <strong>{{ meta_setup?.meta_page_id || '—' }}</strong></p>
                        <p>Instagram: <strong>{{ meta_setup?.meta_instagram_account_id || '—' }}</strong></p>
                    </div>
                </div>

                <div v-if="meta_setup?.issues?.length" class="mt-4 space-y-2">
                    <div
                        v-for="(issue, idx) in meta_setup.issues"
                        :key="`meta-issue-${idx}`"
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3"
                    >
                        <p class="text-sm font-semibold text-amber-900">{{ issue.title }}</p>
                        <p class="mt-1 text-xs text-amber-800">{{ issue.message }}</p>
                        <a
                            v-if="issue.fix_url"
                            :href="issue.fix_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700"
                        >
                            {{ issue.fix_label || 'حل المشكلة' }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-100/90 bg-white/95 p-5 shadow-md ring-1 ring-slate-100/50 sm:p-6">
                <h2 class="text-lg font-bold tracking-tight text-slate-900">تسجيل الدخول للبوابة</h2>
                <p class="mt-1 text-sm text-slate-600">غيّر اسم المستخدم أو كلمة السر عند الحاجة.</p>
                <form class="mt-5 grid gap-4 md:grid-cols-2" @submit.prevent="saveAccount">
                    <div class="md:col-span-2">
                        <InputLabel for="portal_username" value="اسم المستخدم" />
                        <TextInput id="portal_username" v-model="accountForm.portal_username" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" required />
                        <InputError class="mt-1" :message="accountForm.errors.portal_username" />
                    </div>
                    <div>
                        <InputLabel for="portal_password" value="كلمة السر الجديدة" />
                        <TextInput id="portal_password" v-model="accountForm.portal_password" type="password" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                        <InputError class="mt-1" :message="accountForm.errors.portal_password" />
                    </div>
                    <div>
                        <InputLabel for="portal_password_confirmation" value="تأكيد كلمة السر" />
                        <TextInput id="portal_password_confirmation" v-model="accountForm.portal_password_confirmation" type="password" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                    </div>
                    <div class="md:col-span-2 pt-1">
                        <PrimaryButton class="w-full justify-center rounded-xl sm:w-auto" :disabled="accountForm.processing">حفظ بيانات الدخول</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
</template>
