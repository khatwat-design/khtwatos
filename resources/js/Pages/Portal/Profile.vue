<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: Object,
    profile: Object,
});

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
