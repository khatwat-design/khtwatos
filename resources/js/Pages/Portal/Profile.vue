<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="ui-card p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">حالة الاشتراك</h2>
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="subscriptionBadgeClass">
                        {{ subscription?.is_active ? 'مفعل' : 'غير مفعل' }}
                    </span>
                </div>
                <div class="mt-3 grid gap-2 text-sm text-gray-700 md:grid-cols-2">
                    <p>بداية الاشتراك: <strong>{{ formatDt(subscription?.started_at) }}</strong></p>
                    <p>انتهاء الاشتراك: <strong>{{ formatDt(subscription?.ends_at) }}</strong></p>
                </div>
            </div>

            <div class="ui-card p-6">
                <h2 class="text-lg font-semibold text-gray-900">بيانات العميل</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="saveInfo">
                    <div>
                        <InputLabel for="name" value="الاسم" />
                        <TextInput id="name" v-model="infoForm.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="infoForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="company" value="الشركة" />
                        <TextInput id="company" v-model="infoForm.company" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="infoForm.errors.company" />
                    </div>
                    <div>
                        <InputLabel for="email" value="البريد" />
                        <TextInput id="email" v-model="infoForm.email" type="email" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="infoForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="الهاتف" />
                        <TextInput id="phone" v-model="infoForm.phone" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="infoForm.errors.phone" />
                    </div>
                    <div class="md:col-span-2">
                        <PrimaryButton :disabled="infoForm.processing">حفظ البيانات</PrimaryButton>
                    </div>
                </form>
            </div>

            <div class="ui-card p-6">
                <h2 class="text-lg font-semibold text-gray-900">ربط Meta (One-click setup)</h2>
                <p class="mt-1 text-sm text-gray-600">
                    من هنا تربط حساب Meta مرة واحدة، والنظام يسوي الفحص والإعداد التلقائي للحساب الإعلاني والصفحة والصلاحيات.
                </p>

                <div class="mt-4 rounded-xl border border-gray-200 bg-white/80 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm">
                            <p class="font-semibold text-gray-900">
                                الحالة:
                                <span v-if="meta_setup?.connected" class="text-emerald-700">متصل</span>
                                <span v-else class="text-amber-700">غير متصل</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-600">
                                {{ meta_setup?.meta_user_name ? `Meta User: ${meta_setup.meta_user_name}` : 'لم يتم ربط Meta بعد' }}
                            </p>
                        </div>
                        <a
                            :href="route('portal.meta.oauth.redirect')"
                            class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            ربط Meta الآن
                        </a>
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

            <div class="ui-card p-6">
                <h2 class="text-lg font-semibold text-gray-900">تسجيل الدخول للبوابة</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="saveAccount">
                    <div class="md:col-span-2">
                        <InputLabel for="portal_username" value="اسم المستخدم" />
                        <TextInput id="portal_username" v-model="accountForm.portal_username" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="accountForm.errors.portal_username" />
                    </div>
                    <div>
                        <InputLabel for="portal_password" value="كلمة السر الجديدة" />
                        <TextInput id="portal_password" v-model="accountForm.portal_password" type="password" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="accountForm.errors.portal_password" />
                    </div>
                    <div>
                        <InputLabel for="portal_password_confirmation" value="تأكيد كلمة السر" />
                        <TextInput id="portal_password_confirmation" v-model="accountForm.portal_password_confirmation" type="password" class="mt-1 block w-full" />
                    </div>
                    <div class="md:col-span-2">
                        <PrimaryButton :disabled="accountForm.processing">حفظ بيانات الدخول</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
</template>
