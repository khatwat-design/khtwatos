<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: Object,
    configured: Boolean,
});

const form = useForm({
    username: '',
    password: '',
});

function submit() {
    form.post(route('portal.login.attempt'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`تسجيل دخول بوابة العميل - ${client?.name || ''}`" />

    <div class="portal-login-screen flex min-h-screen items-center justify-center bg-gray-100 px-4 py-8">
        <div class="portal-login-card w-full max-w-md rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <h1 class="text-xl font-bold text-gray-900">تسجيل دخول بوابة العميل</h1>
            <p class="mt-1 text-sm text-gray-600">
                {{ client?.name }}<span v-if="client?.company"> · {{ client.company }}</span>
            </p>

            <div
                v-if="!configured"
                class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
            >
                لم يتم إعداد اسم المستخدم وكلمة السر لهذه البوابة بعد. يرجى التواصل مع مدير الحساب.
            </div>

            <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="username" value="اسم المستخدم" class="text-black" />
                    <TextInput id="username" v-model="form.username" class="mt-1 block w-full text-black placeholder:text-gray-500" required autofocus />
                    <InputError class="mt-1" :message="form.errors.username" />
                </div>
                <div>
                    <InputLabel for="password" value="كلمة السر" class="text-black" />
                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full text-black placeholder:text-gray-500" required />
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>
                <PrimaryButton :disabled="form.processing">دخول البوابة</PrimaryButton>
            </form>
        </div>
    </div>
</template>

<style scoped>
.portal-login-card,
.portal-login-card :deep(*) {
    color: #111111 !important;
}

.portal-login-card :deep(input),
.portal-login-card :deep(textarea),
.portal-login-card :deep(select) {
    color: #111111 !important;
    background: #ffffff !important;
}

.portal-login-card :deep(input::placeholder),
.portal-login-card :deep(textarea::placeholder) {
    color: #6b7280 !important;
}
</style>
