<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    employees: Array,
    teams: Array,
    canAssignAdmin: Boolean,
});

const roleLabels = {
    admin: 'مدير النظام',
    lead: 'قائد فريق',
    member: 'موظف',
};

const dayOptions = [
    { value: 0, label: 'الأحد' },
    { value: 1, label: 'الإثنين' },
    { value: 2, label: 'الثلاثاء' },
    { value: 3, label: 'الأربعاء' },
    { value: 4, label: 'الخميس' },
    { value: 5, label: 'الجمعة' },
    { value: 6, label: 'السبت' },
];

function buildDefaultSchedule(source = null) {
    const map = source && typeof source === 'object' ? source : {};

    return dayOptions.map((day) => {
        const raw = map[String(day.value)] || map[day.value] || {};
        return {
            day: day.value,
            enabled: typeof raw.enabled === 'boolean'
                ? raw.enabled
                : [0, 1, 2, 3, 4].includes(day.value),
            start: (raw.start || '09:00').slice(0, 5),
            end: (raw.end || '17:00').slice(0, 5),
        };
    });
}

function scheduleSummary(schedule) {
    const enabled = (schedule || []).filter((row) => row.enabled);
    if (!enabled.length) {
        return 'لا يوجد توفر';
    }

    return enabled
        .map((row) => {
            const dayLabel = dayOptions.find((d) => d.value === Number(row.day))?.label || row.day;
            return `${dayLabel}: ${row.start} - ${row.end}`;
        })
        .join(' | ');
}

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const editingEmployeeId = ref(null);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'member',
    is_bookable: true,
    availability_schedule: buildDefaultSchedule(),
    teams: [],
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'member',
    is_bookable: true,
    availability_schedule: buildDefaultSchedule(),
    teams: [],
});

function openCreateModal() {
    createForm.reset();
    createForm.clearErrors();
    createForm.role = 'member';
    createForm.is_bookable = true;
    createForm.availability_schedule = buildDefaultSchedule();
    createForm.teams = [];
    createModalOpen.value = true;
}

function closeCreateModal() {
    createModalOpen.value = false;
}

function openEditModal(employee) {
    editingEmployeeId.value = employee.id;
    editForm.clearErrors();
    editForm.name = employee.name;
    editForm.email = employee.email;
    editForm.password = '';
    editForm.role = employee.role;
    editForm.is_bookable = Boolean(employee.is_bookable);
    editForm.availability_schedule = buildDefaultSchedule(employee.availability_schedule);
    editForm.teams = (employee.teams || []).map((t) => ({
        id: t.id,
        allocation_percent: t.allocation_percent ?? '',
        is_lead: Boolean(t.is_lead),
    }));
    editModalOpen.value = true;
}

function closeEditModal() {
    editModalOpen.value = false;
    editingEmployeeId.value = null;
}

function toggleTeamSelection(form, teamId, checked) {
    const idx = form.teams.findIndex((t) => Number(t.id) === Number(teamId));

    if (checked && idx < 0) {
        form.teams.push({
            id: Number(teamId),
            allocation_percent: '',
            is_lead: false,
        });
        return;
    }

    if (!checked && idx >= 0) {
        form.teams.splice(idx, 1);
    }
}

function hasTeam(form, teamId) {
    return form.teams.some((t) => Number(t.id) === Number(teamId));
}

function teamItem(form, teamId) {
    return form.teams.find((t) => Number(t.id) === Number(teamId)) || null;
}

function submitCreate() {
    createForm.post(route('employees.store'), {
        preserveScroll: true,
        onSuccess: () => closeCreateModal(),
    });
}

function submitEdit() {
    if (!editingEmployeeId.value) {
        return;
    }

    editForm.patch(route('employees.update', editingEmployeeId.value), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
}

function deleteEmployee(employee) {
    if (!confirm(`حذف الموظف ${employee.name}؟`)) {
        return;
    }
    router.delete(route('employees.destroy', employee.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="الموظفين" />

    <AuthenticatedLayout>
        <template #title>الموظفين</template>

        <div class="employees-page mx-auto max-w-6xl space-y-4">
            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">
                    إدارة كاملة لبيانات الموظفين، فرقهم، الصلاحيات، وتوفرهم للحجز.
                </p>
                <PrimaryButton type="button" @click="openCreateModal">إضافة موظف</PrimaryButton>
            </div>

            <div class="space-y-3 md:hidden">
                <div
                    v-for="emp in employees"
                    :key="`mobile-${emp.id}`"
                    class="ui-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">{{ emp.name }}</div>
                            <div class="text-xs text-gray-500">{{ emp.email }}</div>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            {{ roleLabels[emp.role] || emp.role }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="text-xs text-gray-500">الفرق</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="team in emp.teams"
                                :key="`mobile-${emp.id}-${team.slug}`"
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                            >
                                {{ team.name }}
                                <span v-if="team.is_lead"> (قائد)</span>
                                <span v-if="team.allocation_percent !== null && team.allocation_percent !== ''">
                                    · {{ team.allocation_percent }}%
                                </span>
                            </span>
                            <span v-if="!(emp.teams || []).length" class="text-xs text-gray-400">—</span>
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-700">
                        <span class="rounded-full px-2 py-0.5" :class="emp.is_bookable ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                            {{ emp.is_bookable ? 'متاح للحجز' : 'غير متاح' }}
                        </span>
                        <div class="mt-1">{{ scheduleSummary(emp.availability_schedule) }}</div>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"
                            @click="openEditModal(emp)"
                        >
                            تعديل
                        </button>
                        <DangerButton
                            type="button"
                            class="!px-2 !py-1 !text-xs"
                            @click="deleteEmployee(emp)"
                        >
                            حذف
                        </DangerButton>
                    </div>
                </div>
                <div v-if="!employees.length" class="ui-card p-6 text-center text-sm text-gray-500">
                    لا يوجد موظفون.
                </div>
            </div>

            <div class="ui-card hidden overflow-hidden md:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">الاسم</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">اسم المستخدم</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">الدور</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">الفرق</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">التوفر</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-600">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="emp in employees"
                            :key="emp.id"
                        >
                            <td class="px-4 py-2 font-medium text-gray-900">{{ emp.name }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ emp.email }}</td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ roleLabels[emp.role] || emp.role }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="team in emp.teams"
                                        :key="`${emp.id}-${team.slug}`"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                    >
                                        {{ team.name }}
                                        <span v-if="team.is_lead"> (قائد)</span>
                                        <span v-if="team.allocation_percent !== null && team.allocation_percent !== ''">
                                            · {{ team.allocation_percent }}%
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="text-xs text-gray-700">
                                    <span class="rounded-full px-2 py-0.5" :class="emp.is_bookable ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                                        {{ emp.is_bookable ? 'متاح للحجز' : 'غير متاح' }}
                                    </span>
                                    <div class="mt-1">
                                        {{ scheduleSummary(emp.availability_schedule) }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50"
                                        @click="openEditModal(emp)"
                                    >
                                        تعديل
                                    </button>
                                    <DangerButton
                                        type="button"
                                        class="!px-2 !py-1 !text-xs"
                                        @click="deleteEmployee(emp)"
                                    >
                                        حذف
                                    </DangerButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!employees.length">
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                لا يوجد موظفون.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="createModalOpen" @close="closeCreateModal">
            <div class="glass-modal employee-light-modal p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">إضافة موظف جديد</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="c_name" value="الاسم" />
                            <TextInput id="c_name" v-model="createForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="createForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="c_email" value="اسم المستخدم" />
                            <TextInput id="c_email" v-model="createForm.email" type="text" class="mt-1 block w-full" autocomplete="username" required />
                            <InputError class="mt-1" :message="createForm.errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="c_role" value="الدور" />
                            <select id="c_role" v-model="createForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="member">موظف</option>
                                <option value="lead">قائد فريق</option>
                                <option v-if="canAssignAdmin" value="admin">مدير النظام</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="c_password" value="كلمة المرور" />
                            <TextInput id="c_password" v-model="createForm.password" type="password" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="createForm.errors.password" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="createForm.is_bookable" type="checkbox" class="rounded border-gray-300 text-brand-600" />
                        متاح للحجز
                    </label>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">التوفر اليومي (بساعات مختلفة لكل يوم)</p>
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(row, idx) in createForm.availability_schedule"
                                :key="`c-day-${row.day}`"
                                class="rounded border border-gray-100 p-2"
                            >
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="row.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                        />
                                        {{ dayOptions.find((d) => d.value === row.day)?.label }}
                                    </label>
                                    <span class="text-xs text-gray-500">{{ row.enabled ? 'متاح' : 'غير متاح' }}</span>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`c_av_start_${row.day}`" value="من الساعة" />
                                        <TextInput :id="`c_av_start_${row.day}`" v-model="row.start" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`c_av_end_${row.day}`" value="إلى الساعة" />
                                        <TextInput :id="`c_av_end_${row.day}`" v-model="row.end" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="createForm.errors[`availability_schedule.${idx}.start`]" />
                                <InputError class="mt-1" :message="createForm.errors[`availability_schedule.${idx}.end`]" />
                            </div>
                        </div>
                        <InputError class="mt-1" :message="createForm.errors.availability_schedule" />
                    </div>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">الفرق</p>
                        <p v-if="!teams.length" class="mt-2 text-xs text-amber-700">
                            لا توجد فرق حالياً، سيتم إنشاء الفرق الافتراضية تلقائياً عند إعادة تحميل الصفحة.
                        </p>
                        <div class="mt-2 space-y-2">
                            <div v-for="team in teams" :key="`c-team-${team.id}`" class="rounded border border-gray-100 p-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="hasTeam(createForm, team.id)"
                                        class="rounded border-gray-300 text-brand-600"
                                        @change="(e) => toggleTeamSelection(createForm, team.id, e.target.checked)"
                                    />
                                    {{ team.name }}
                                </label>
                                <div v-if="hasTeam(createForm, team.id)" class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`c-allocation-${team.id}`" value="نسبة التوزيع %" />
                                        <TextInput
                                            :id="`c-allocation-${team.id}`"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full"
                                            :model-value="teamItem(createForm, team.id)?.allocation_percent"
                                            @update:model-value="(v) => (teamItem(createForm, team.id).allocation_percent = v)"
                                        />
                                    </div>
                                    <label class="mt-6 flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                            :checked="teamItem(createForm, team.id)?.is_lead"
                                            @change="(e) => (teamItem(createForm, team.id).is_lead = e.target.checked)"
                                        />
                                        قائد الفريق
                                    </label>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="createForm.errors.teams" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="w-full rounded border border-gray-300 px-4 py-2 text-sm sm:w-auto"
                            @click="closeCreateModal"
                        >
                            إلغاء
                        </button>
                        <PrimaryButton :disabled="createForm.processing" type="submit" class="w-full justify-center sm:w-auto">إضافة</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="editModalOpen" @close="closeEditModal">
            <div class="glass-modal employee-light-modal p-4 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">تعديل الموظف</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="e_name" value="الاسم" />
                            <TextInput id="e_name" v-model="editForm.name" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="editForm.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="e_email" value="اسم المستخدم" />
                            <TextInput id="e_email" v-model="editForm.email" type="text" class="mt-1 block w-full" autocomplete="username" required />
                            <InputError class="mt-1" :message="editForm.errors.email" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="e_role" value="الدور" />
                            <select id="e_role" v-model="editForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="member">موظف</option>
                                <option value="lead">قائد فريق</option>
                                <option v-if="canAssignAdmin || editForm.role === 'admin'" value="admin">مدير النظام</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="e_password" value="كلمة مرور جديدة (اختياري)" />
                            <TextInput id="e_password" v-model="editForm.password" type="password" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="editForm.errors.password" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_bookable" type="checkbox" class="rounded border-gray-300 text-brand-600" />
                        متاح للحجز
                    </label>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">التوفر اليومي (بساعات مختلفة لكل يوم)</p>
                        <div class="mt-2 space-y-2">
                            <div
                                v-for="(row, idx) in editForm.availability_schedule"
                                :key="`e-day-${row.day}`"
                                class="rounded border border-gray-100 p-2"
                            >
                                <div class="flex items-center justify-between">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="row.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                        />
                                        {{ dayOptions.find((d) => d.value === row.day)?.label }}
                                    </label>
                                    <span class="text-xs text-gray-500">{{ row.enabled ? 'متاح' : 'غير متاح' }}</span>
                                </div>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`e_av_start_${row.day}`" value="من الساعة" />
                                        <TextInput :id="`e_av_start_${row.day}`" v-model="row.start" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                    <div>
                                        <InputLabel :for="`e_av_end_${row.day}`" value="إلى الساعة" />
                                        <TextInput :id="`e_av_end_${row.day}`" v-model="row.end" type="time" class="mt-1 block w-full" :disabled="!row.enabled" />
                                    </div>
                                </div>
                                <InputError class="mt-1" :message="editForm.errors[`availability_schedule.${idx}.start`]" />
                                <InputError class="mt-1" :message="editForm.errors[`availability_schedule.${idx}.end`]" />
                            </div>
                        </div>
                        <InputError class="mt-1" :message="editForm.errors.availability_schedule" />
                    </div>

                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-900">الفرق</p>
                        <p v-if="!teams.length" class="mt-2 text-xs text-amber-700">
                            لا توجد فرق حالياً، سيتم إنشاء الفرق الافتراضية تلقائياً عند إعادة تحميل الصفحة.
                        </p>
                        <div class="mt-2 space-y-2">
                            <div v-for="team in teams" :key="`e-team-${team.id}`" class="rounded border border-gray-100 p-2">
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="hasTeam(editForm, team.id)"
                                        class="rounded border-gray-300 text-brand-600"
                                        @change="(e) => toggleTeamSelection(editForm, team.id, e.target.checked)"
                                    />
                                    {{ team.name }}
                                </label>
                                <div v-if="hasTeam(editForm, team.id)" class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <InputLabel :for="`e-allocation-${team.id}`" value="نسبة التوزيع %" />
                                        <TextInput
                                            :id="`e-allocation-${team.id}`"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full"
                                            :model-value="teamItem(editForm, team.id)?.allocation_percent"
                                            @update:model-value="(v) => (teamItem(editForm, team.id).allocation_percent = v)"
                                        />
                                    </div>
                                    <label class="mt-6 flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-brand-600"
                                            :checked="teamItem(editForm, team.id)?.is_lead"
                                            @change="(e) => (teamItem(editForm, team.id).is_lead = e.target.checked)"
                                        />
                                        قائد الفريق
                                    </label>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="editForm.errors.teams" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="w-full rounded border border-gray-300 px-4 py-2 text-sm sm:w-auto"
                            @click="closeEditModal"
                        >
                            إلغاء
                        </button>
                        <PrimaryButton :disabled="editForm.processing" type="submit" class="w-full justify-center sm:w-auto">حفظ التغييرات</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.glass-modal {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    animation: modal-in 220ms ease-out;
}

.employee-light-modal {
    color: #111111;
}

.employee-light-modal :deep(input),
.employee-light-modal :deep(select),
.employee-light-modal :deep(textarea),
.employee-light-modal :deep(label),
.employee-light-modal :deep(p),
.employee-light-modal :deep(span),
.employee-light-modal :deep(.text-gray-900),
.employee-light-modal :deep(.text-gray-800),
.employee-light-modal :deep(.text-gray-700),
.employee-light-modal :deep(.text-gray-600),
.employee-light-modal :deep(.text-gray-500) {
    color: #111111 !important;
}

.employees-page :deep(.bg-white),
.employees-page :deep(.bg-white\/70),
.employees-page :deep(.bg-white\/75),
.employees-page :deep(.bg-white\/80),
.employees-page :deep(.bg-gray-50),
.employees-page :deep(.bg-gray-100) {
    color: #111111 !important;
}

.employees-page :deep(.bg-white a),
.employees-page :deep(.bg-white\/70 a),
.employees-page :deep(.bg-white\/75 a),
.employees-page :deep(.bg-white\/80 a),
.employees-page :deep(.bg-gray-50 a),
.employees-page :deep(.bg-gray-100 a) {
    color: #111111 !important;
}

.employees-page :deep(select),
.employees-page :deep(input),
.employees-page :deep(textarea) {
    color: #111111;
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
