<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    meeting: Object,
    hosts: Array,
    clients: Array,
});

const employeeNameMap = {
    Mahmoud: 'محمود',
    Maha: 'مها',
    Laith: 'ليث',
    'Hussein Salam': 'حسين سلام',
    'Hussein Ali': 'حسين علي',
    'Ahmed Bashir': 'أحمد بشير',
    Admin: 'مدير النظام',
    Abdullah: 'عبدالله',
    Shatha: 'شذى',
    Noor: 'نور',
    Nabras: 'نبراس',
    'Mohammed Thaer': 'محمد ثائر',
    'Mohammed Khalid': 'محمد خالد',
};

function arabicEmployeeName(name) {
    return employeeNameMap[name] || name;
}

const form = useForm({
    user_id: props.meeting.user_id,
    client_id: props.meeting.client_id,
    title: props.meeting.title,
    start_at: props.meeting.start_at,
    end_at: props.meeting.end_at || '',
    reason: props.meeting.reason || '',
    summary: props.meeting.summary || '',
    invitee_name: props.meeting.invitee_name || '',
    invitee_email: props.meeting.invitee_email || '',
    status: props.meeting.status,
});

function submit() {
    form.patch(route('meetings.update', props.meeting.id));
}

function destroyMeeting() {
    if (!confirm('حذف هذا الاجتماع؟ لا يمكن التراجع.')) {
        return;
    }
    router.delete(route('meetings.destroy', props.meeting.id));
}
</script>

<template>
    <Head title="تعديل اجتماع" />

    <AuthenticatedLayout>
        <template #title>تعديل اجتماع داخلي</template>

        <div class="mx-auto max-w-xl space-y-4">
            <Link
                :href="route('meetings.index', { client_id: meeting.client_id || undefined })"
                class="text-sm text-brand-600 hover:underline"
            >
                ← العودة للاجتماعات
            </Link>

            <div class="rounded-lg bg-white p-6 shadow ring-1 ring-gray-200">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="user_id" value="المضيف (موظف)" />
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            required
                        >
                            <option
                                v-for="h in hosts"
                                :key="h.id"
                                :value="h.id"
                            >
                                {{ arabicEmployeeName(h.name) }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.user_id" />
                    </div>

                    <div>
                        <InputLabel for="client_id" value="العميل (اختياري)" />
                        <select
                            id="client_id"
                            v-model="form.client_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option :value="null">—</option>
                            <option
                                v-for="c in clients"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.client_id" />
                    </div>

                    <div>
                        <InputLabel for="title" value="عنوان الاجتماع" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="start_at" value="البداية" />
                        <TextInput
                            id="start_at"
                            v-model="form.start_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.start_at" />
                    </div>

                    <div>
                        <InputLabel for="end_at" value="النهاية" />
                        <TextInput
                            id="end_at"
                            v-model="form.end_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.end_at" />
                    </div>

                    <div>
                        <InputLabel for="status" value="الحالة" />
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            required
                        >
                            <option value="scheduled">مجدول</option>
                            <option value="canceled">ملغى</option>
                            <option value="completed">مكتمل</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.status" />
                    </div>

                    <div>
                        <InputLabel for="invitee_name" value="اسم الضيف" />
                        <TextInput
                            id="invitee_name"
                            v-model="form.invitee_name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.invitee_name" />
                    </div>

                    <div>
                        <InputLabel for="invitee_email" value="بريد الضيف" />
                        <TextInput
                            id="invitee_email"
                            v-model="form.invitee_email"
                            type="email"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.invitee_email" />
                    </div>

                    <div>
                        <InputLabel for="reason" value="السبب / ملاحظات" />
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        />
                        <InputError class="mt-1" :message="form.errors.reason" />
                    </div>

                    <div>
                        <InputLabel for="summary" value="ملخص الاجتماع والحل" />
                        <textarea
                            id="summary"
                            v-model="form.summary"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="يظهر عند توثيق الاجتماع كمكتمل"
                        />
                        <InputError class="mt-1" :message="form.errors.summary" />
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton :disabled="form.processing">حفظ التغييرات</PrimaryButton>
                        <Link
                            :href="route('meetings.index')"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                        >
                            إلغاء
                        </Link>
                    </div>
                </form>

                <div class="mt-8 border-t border-gray-100 pt-6">
                    <p class="text-sm text-gray-600">حذف الاجتماع من السجل الداخلي.</p>
                    <DangerButton
                        type="button"
                        class="mt-3"
                        @click="destroyMeeting"
                    >
                        حذف الاجتماع
                    </DangerButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
