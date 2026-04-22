<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    teams: Array,
    booked: Boolean,
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
    team_slug: props.teams?.[0]?.slug || '',
    selection_mode: 'members',
    participant_ids: [],
    invitee_name: '',
    invitee_email: '',
    start_at: '',
    project_name: '',
    reason: '',
});

const selectedTeam = computed(
    () => props.teams.find((t) => t.slug === form.team_slug) || null,
);

const hosts = computed(() => selectedTeam.value?.members || []);

const selectedMembers = computed(() => {
    if (!hosts.value.length) {
        return [];
    }

    if (form.selection_mode === 'team') {
        return hosts.value;
    }

    const selectedIds = new Set((form.participant_ids || []).map((id) => Number(id)));
    return hosts.value.filter((h) => selectedIds.has(Number(h.id)));
});

const availableSlots = computed(() => {
    if (!selectedMembers.value.length) {
        return [];
    }

    const slotMaps = selectedMembers.value.map((member) => {
        const map = new Map();
        for (const slot of member.slots || []) {
            map.set(slot.value, slot.label);
        }
        return map;
    });

    if (!slotMaps.length) {
        return [];
    }

    const firstMap = slotMaps[0];
    const result = [];
    for (const [value, label] of firstMap.entries()) {
        const existsForAll = slotMaps.every((map) => map.has(value));
        if (existsForAll) {
            result.push({ value, label });
        }
    }

    return result;
});

watch(
    () => form.team_slug,
    () => {
        form.participant_ids = form.selection_mode === 'team'
            ? hosts.value.map((m) => m.id)
            : (hosts.value[0]?.id ? [hosts.value[0].id] : []);
    },
    { immediate: true },
);

watch(
    () => form.selection_mode,
    () => {
        form.participant_ids = form.selection_mode === 'team'
            ? hosts.value.map((m) => m.id)
            : (hosts.value[0]?.id ? [hosts.value[0].id] : []);
    },
    { immediate: true },
);

watch(
    () => availableSlots.value,
    () => {
        form.start_at = availableSlots.value[0]?.value || '';
    },
    { immediate: true },
);

function submit() {
    form.post(route('book.store'));
}
</script>

<template>
    <Head title="حجز موعد" />

    <div class="min-h-screen bg-gray-100 text-gray-900">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-4 py-4">
                <h1 class="text-lg font-semibold">حجز موعد</h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-10">
            <div
                v-if="booked"
                class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800"
            >
                تم إرسال الحجز بنجاح. سيتواصل معك الموظف في الموعد المحدد.
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold">احجز مباشرة مع الفريق</h2>
                <p class="mt-1 text-sm text-gray-600">
                    اختر قسماً كاملاً أو أكثر من موظف، وحدد موعداً مشتركاً متاحاً للجميع.
                </p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="team_slug" value="القسم" />
                        <select
                            id="team_slug"
                            v-model="form.team_slug"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            required
                        >
                            <option
                                v-for="team in teams"
                                :key="team.slug"
                                :value="team.slug"
                            >
                                {{ team.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="selection_mode" value="طريقة اختيار الحضور" />
                        <select
                            id="selection_mode"
                            v-model="form.selection_mode"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            required
                        >
                            <option value="members">اختيار موظفين محددين</option>
                            <option value="team">اختيار القسم بالكامل</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="participant_ids" :value="form.selection_mode === 'team' ? 'حضور الاجتماع (القسم بالكامل)' : 'الموظفون المشاركون'" />
                        <select
                            id="participant_ids"
                            v-model="form.participant_ids"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            :multiple="form.selection_mode !== 'team'"
                            :size="form.selection_mode !== 'team' ? 6 : 1"
                            :disabled="form.selection_mode === 'team'"
                            required
                        >
                            <option
                                v-for="m in hosts"
                                :key="m.id"
                                :value="m.id"
                            >
                                {{ arabicEmployeeName(m.name) }}{{ m.is_lead || m.role === 'lead' ? ' • مدير قسم' : '' }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ form.selection_mode === 'team' ? 'سيتم حجز الاجتماع مع جميع أفراد القسم.' : 'يمكنك اختيار أكثر من موظف (Ctrl/Cmd + Click).' }}
                        </p>
                        <InputError class="mt-1" :message="form.errors.participant_ids" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="invitee_name" value="اسم العميل / الشركة" />
                            <TextInput
                                id="invitee_name"
                                v-model="form.invitee_name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.invitee_name" />
                        </div>
                        <div>
                            <InputLabel for="invitee_email" value="بريدك الإلكتروني (اختياري)" />
                            <TextInput
                                id="invitee_email"
                                v-model="form.invitee_email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.invitee_email" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="start_at" value="موعد الاجتماع المتاح" />
                            <select
                                id="start_at"
                                v-model="form.start_at"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                                required
                            >
                                <option
                                    v-for="slot in availableSlots"
                                    :key="slot.value"
                                    :value="slot.value"
                                >
                                    {{ slot.label }}
                                </option>
                            </select>
                            <p
                                v-if="!availableSlots.length"
                                class="mt-1 text-xs text-amber-700"
                            >
                                لا توجد أوقات مشتركة متاحة حالياً للموظفين المحددين. غيّر الاختيار أو القسم.
                            </p>
                            <InputError class="mt-1" :message="form.errors.start_at" />
                        </div>
                        <div>
                            <InputLabel for="project_name" value="اسم المشروع" />
                            <TextInput
                                id="project_name"
                                v-model="form.project_name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="form.errors.project_name" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="reason" value="سبب الاجتماع / ملاحظات" />
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        />
                        <InputError class="mt-1" :message="form.errors.reason" />
                    </div>

                    <PrimaryButton :disabled="form.processing || !availableSlots.length" type="submit">
                        تأكيد الحجز
                    </PrimaryButton>
                </form>
            </div>
        </main>
    </div>
</template>
