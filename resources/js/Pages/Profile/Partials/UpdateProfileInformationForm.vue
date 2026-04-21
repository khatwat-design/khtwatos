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

function sanitizeTime(value, fallback) {
    if (!value || typeof value !== 'string') {
        return fallback;
    }
    return value.slice(0, 5);
}

function buildAvailabilitySchedule(rawSchedule, fallbackDays = [0, 1, 2, 3, 4]) {
    const map = rawSchedule && typeof rawSchedule === 'object' ? rawSchedule : {};

    return allDays.map((day) => {
        const raw = map[String(day.value)] || map[day.value] || {};
        const enabled = typeof raw.enabled === 'boolean'
            ? raw.enabled
            : fallbackDays.includes(day.value);

        return {
            day: day.value,
            enabled,
            start: sanitizeTime(raw.start, '09:00'),
            end: sanitizeTime(raw.end, '17:00'),
        };
    });
}

const form = useForm({
    name: user.name,
    email: user.email,
    is_bookable: Boolean(user.is_bookable),
    availability_schedule: buildAvailabilitySchedule(
        user.availability_schedule,
        Array.isArray(user.availability_days) && user.availability_days.length
            ? user.availability_days
            : [0, 1, 2, 3, 4],
    ),
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
                <h3 class="text-sm font-semibold text-gray-900">التوفر اليومي (ساعات مختلفة لكل يوم)</h3>
                <p class="mt-1 text-xs text-gray-500">
                    لا يمكن للعميل الحجز إلا ضمن هذه الأوقات.
                </p>

                <div class="mt-3 space-y-3">
                    <div
                        v-for="(row, idx) in form.availability_schedule"
                        :key="`availability-row-${row.day}`"
                        class="rounded-md border border-gray-200 p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                <Checkbox
                                    :id="`day-${row.day}`"
                                    v-model:checked="row.enabled"
                                />
                                {{ allDays.find((d) => d.value === row.day)?.label }}
                            </label>
                            <span class="text-xs text-gray-500">
                                {{ row.enabled ? 'متاح' : 'غير متاح' }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel :for="`availability-start-${row.day}`" value="من الساعة" />
                                <TextInput
                                    :id="`availability-start-${row.day}`"
                                    type="time"
                                    class="mt-1 block w-full"
                                    v-model="row.start"
                                    :disabled="!row.enabled"
                                />
                            </div>
                            <div>
                                <InputLabel :for="`availability-end-${row.day}`" value="إلى الساعة" />
                                <TextInput
                                    :id="`availability-end-${row.day}`"
                                    type="time"
                                    class="mt-1 block w-full"
                                    v-model="row.end"
                                    :disabled="!row.enabled"
                                />
                            </div>
                        </div>

                        <InputError class="mt-2" :message="form.errors[`availability_schedule.${idx}.start`]" />
                        <InputError class="mt-2" :message="form.errors[`availability_schedule.${idx}.end`]" />
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.availability_schedule" />
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
