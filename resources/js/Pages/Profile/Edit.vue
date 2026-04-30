<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    metaOAuth: {
        type: Object,
        default: () => ({}),
    },
});

const disconnectMetaForm = useForm({});

function disconnectMetaOAuth() {
    disconnectMetaForm.post(route('profile.meta.oauth.disconnect'));
}
</script>

<template>
    <Head title="الملف الشخصي" />

    <AuthenticatedLayout>
        <template #title>الملف الشخصي</template>

        <div class="profile-page mx-auto max-w-7xl space-y-6">
            <div class="profile-light-card bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="profile-light-card bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div v-if="props.metaOAuth?.is_media_buyer" class="profile-light-card bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <div class="max-w-xl space-y-3">
                    <h3 class="text-lg font-semibold">مصادقة Meta (ميديا باير / مدير النظام)</h3>
                    <p class="text-sm text-gray-600">
                        هذا الربط مطلوب حتى يتمكن النظام من منحك صلاحية الوصول لحسابات العملاء الإعلانية بعد موافقة العميل.
                    </p>
                    <p class="text-xs text-gray-500">
                        الحالة:
                        <span class="font-semibold text-gray-800">{{ props.metaOAuth?.connected ? 'متصل' : 'غير متصل' }}</span>
                        <span v-if="props.metaOAuth?.meta_user_name"> · {{ props.metaOAuth.meta_user_name }}</span>
                    </p>

                    <div class="flex items-center gap-2">
                        <a
                            :href="route('profile.meta.oauth.redirect')"
                            class="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700"
                        >
                            {{ props.metaOAuth?.connected ? 'إعادة مصادقة Meta' : 'ربط Meta الآن' }}
                        </a>
                        <PrimaryButton
                            v-if="props.metaOAuth?.connected"
                            type="button"
                            :disabled="disconnectMetaForm.processing"
                            @click="disconnectMetaOAuth"
                        >
                            فصل الربط
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="profile-light-card bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.profile-light-card,
.profile-light-card :deep(*) {
    color: #111111;
}

.profile-light-card :deep(input),
.profile-light-card :deep(select),
.profile-light-card :deep(textarea) {
    color: #111111 !important;
}

.profile-light-card :deep(input::placeholder),
.profile-light-card :deep(textarea::placeholder) {
    color: #555555 !important;
}
</style>
