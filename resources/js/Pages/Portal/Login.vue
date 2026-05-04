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

    <div
        class="flex min-h-dvh flex-col items-center justify-center bg-gradient-to-br from-slate-100 via-white to-rose-50/40 px-4 py-10 sm:px-6"
    >
        <div class="mb-8 flex flex-col items-center text-center">
            <img src="/images/mobile-logo.png" alt="" class="h-16 w-16 rounded-2xl object-contain shadow-lg ring-4 ring-white" />
            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-slate-500">خارج المخزون</p>
        </div>

        <div
            class="portal-login-card w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/80 bg-white/95 p-6 shadow-2xl shadow-slate-900/10 ring-1 ring-slate-100/60 backdrop-blur-sm sm:p-8"
        >
            <h1 class="text-center text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">بوابة العميل</h1>
            <p class="mt-2 text-center text-sm leading-relaxed text-slate-600">
                {{ client?.name }}<span v-if="client?.company"> · {{ client.company }}</span>
            </p>

            <div
                v-if="!configured"
                class="mt-5 rounded-2xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm font-medium leading-relaxed text-amber-950"
            >
                لم يتم إعداد اسم المستخدم وكلمة السر لهذه البوابة بعد. يرجى التواصل مع مدير الحساب.
            </div>

            <form v-else class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="username" value="اسم المستخدم" class="text-slate-700" />
                    <TextInput
                        id="username"
                        v-model="form.username"
                        class="mt-2 block min-h-11 w-full rounded-xl border-slate-200 text-slate-900 shadow-sm placeholder:text-slate-400"
                        required
                        autofocus
                    />
                    <InputError class="mt-1.5" :message="form.errors.username" />
                </div>
                <div>
                    <InputLabel for="password" value="كلمة السر" class="text-slate-700" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block min-h-11 w-full rounded-xl border-slate-200 text-slate-900 shadow-sm placeholder:text-slate-400"
                        required
                    />
                    <InputError class="mt-1.5" :message="form.errors.password" />
                </div>
                <PrimaryButton class="mt-2 w-full justify-center rounded-xl py-3 text-base font-bold shadow-md sm:py-2.5" :disabled="form.processing">
                    دخول البوابة
                </PrimaryButton>
            </form>
        </div>

        <p class="mt-8 max-w-sm text-center text-[11px] leading-relaxed text-slate-500">
            بياناتك محمية. في حال نسيان كلمة السر تواصل مع فريق الحساب.
        </p>
    </div>
</template>

<style scoped>
.portal-login-card :deep(input),
.portal-login-card :deep(textarea),
.portal-login-card :deep(select) {
    color: #0f172a;
    background: #ffffff;
}

.portal-login-card :deep(input::placeholder),
.portal-login-card :deep(textarea::placeholder) {
    color: #64748b;
}
</style>
