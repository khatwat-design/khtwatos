<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const allDays = [
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
    { value: 6, label: 'السبت' },
];

const defaultDays = [0, 1, 2, 3, 4];

const sanitizeTime = (value, fallback) => {
    if (!value || typeof value !== 'string') {
        return fallback;
    }
    return value.slice(0, 5);
};

const form = useForm({
    name: user.name,
    email: user.email,
    is_bookable: Boolean(user.is_bookable),
    availability_days: Array.isArray(user.availability_days) && user.availability_days.length
        ? user.availability_days
        : defaultDays,
    availability_start_time: sanitizeTime(user.availability_start_time, '09:00'),
    availability_end_time: sanitizeTime(user.availability_end_time, '17:00'),
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">بيانات الملف</h2>

            <p class="mt-1 text-sm text-gray-600">
                حدّث اسمك وبريدك وحدد أوقات توفرك التي تظهر للعملاء في صفحة الحجز.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="الاسم" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="البريد الإلكتروني" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox
                    id="is_bookable"
                    v-model:checked="form.is_bookable"
                    name="is_bookable"
                />
                <InputLabel
                    for="is_bookable"
                    value="إظهاري في صفحة الحجز"
                />
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-900">أيام وساعات التوفر</h3>
                <p class="mt-1 text-xs text-gray-500">
                    لا يمكن للعميل الحجز إلا ضمن هذه الأوقات.
                </p>

                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="day in allDays"
                        :key="day.value"
                        class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm"
                    >
                        <Checkbox
                            :id="`day-${day.value}`"
                            :checked="form.availability_days.includes(day.value)"
                            @change="
                                (event) => {
                                    if (event.target.checked) {
                                        form.availability_days = [...form.availability_days, day.value]
                                            .map((v) => Number(v))
                                            .filter((v, i, a) => a.indexOf(v) === i);
                                    } else {
                                        form.availability_days = form.availability_days.filter((v) => Number(v) !== day.value);
                                    }
                                }
                            "
                        />
                        <span>{{ day.label }}</span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.availability_days" />

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="availability_start_time" value="من الساعة" />
                        <TextInput
                            id="availability_start_time"
                            type="time"
                            class="mt-1 block w-full"
                            v-model="form.availability_start_time"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.availability_start_time" />
                    </div>

                    <div>
                        <InputLabel for="availability_end_time" value="إلى الساعة" />
                        <TextInput
                            id="availability_end_time"
                            type="time"
                            class="mt-1 block w-full"
                            v-model="form.availability_end_time"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.availability_end_time" />
                    </div>
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    لم يتم تأكيد بريدك بعد.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                    >
                        إعادة إرسال رابط التحقق
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    تم إرسال رابط تحقق جديد إلى بريدك.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">حفظ</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        تم الحفظ.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
