<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    stages: Array,
    accountManagers: Array,
});

const form = useForm({
    name: '',
    company: '',
    email: '',
    phone: '',
    notes: '',
    account_manager_id: null,
    current_pipeline_stage_id: props.stages[0]?.id ?? null,
});

function submit() {
    form.post(route('clients.store'));
}
</script>

<template>
    <Head title="عميل جديد" />

    <AuthenticatedLayout>
        <template #title>عميل جديد</template>

        <div class="mx-auto max-w-xl rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="الاسم" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="company" value="الشركة" />
                    <TextInput
                        id="company"
                        v-model="form.company"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.company" />
                </div>
                <div>
                    <InputLabel for="email" value="البريد الإلكتروني" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel for="phone" value="الهاتف" />
                    <TextInput
                        id="phone"
                        v-model="form.phone"
                        type="text"
                        class="mt-1 block w-full"
                    />
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>
                <div>
                    <InputLabel for="account_manager_id" value="مدير الحساب" />
                    <select
                        id="account_manager_id"
                        v-model="form.account_manager_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    >
                        <option :value="null">—</option>
                        <option
                            v-for="u in accountManagers"
                            :key="u.id"
                            :value="u.id"
                        >
                            {{ u.name }}
                        </option>
                    </select>
                    <InputError
                        class="mt-1"
                        :message="form.errors.account_manager_id"
                    />
                </div>
                <div>
                    <InputLabel for="stage" value="المرحلة الابتدائية" />
                    <select
                        id="stage"
                        v-model="form.current_pipeline_stage_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        required
                    >
                        <option v-if="!stages?.length" :value="null" disabled>
                            لا توجد مراحل متاحة حالياً
                        </option>
                        <option
                            v-for="s in stages"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.label }}
                        </option>
                    </select>
                    <InputError
                        class="mt-1"
                        :message="form.errors.current_pipeline_stage_id"
                    />
                </div>
                <div>
                    <InputLabel for="notes" value="ملاحظات" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    />
                    <InputError class="mt-1" :message="form.errors.notes" />
                </div>
                <PrimaryButton :disabled="form.processing">إنشاء</PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
