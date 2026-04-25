<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
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
