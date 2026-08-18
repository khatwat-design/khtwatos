<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    hosts: Array,
    clients: Array,
    metaLeads: Array,
    teams: Array,
    defaults: Object,
});

const employeeNameMap = {
    Mahmoud: 'محمود', Maha: 'مها', Laith: 'ليث',
    'Hussein Salam': 'حسين سلام', 'Hussein Ali': 'حسين علي',
    'Ahmed Bashir': 'أحمد بشير', Admin: 'مدير النظام',
    Abdullah: 'عبدالله', Shatha: 'شذى', Noor: 'نور',
    Nabras: 'نبراس', 'Mohammed Thaer': 'محمد ثائر', 'Mohammed Khalid': 'محمد خالد',
};
function arabicEmployeeName(name) { return employeeNameMap[name] || name; }

const searchLead = ref('');
const showLeadPicker = ref(false);
const filteredLeads = computed(() => {
    if (!props.metaLeads) return [];
    if (!searchLead.value) return props.metaLeads.slice(0, 20);
    const q = searchLead.value.toLowerCase();
    return props.metaLeads.filter(ml =>
        ml.full_name?.toLowerCase().includes(q) ||
        ml.phone?.includes(q) ||
        ml.campaign_name?.toLowerCase().includes(q)
    ).slice(0, 20);
});
const selectedLead = computed(() => {
    if (!form.goods_meta_lead_id || !props.metaLeads) return null;
    return props.metaLeads.find(ml => ml.id === form.goods_meta_lead_id);
});

const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
tomorrow.setHours(10, 0, 0, 0);
const tomorrowStr = tomorrow.toISOString().slice(0, 16);

const form = useForm({
    user_id: props.defaults.user_id,
    client_id: props.defaults.client_id,
    goods_meta_lead_id: null,
    title: '',
    start_at: tomorrowStr,
    end_at: '',
    reason: '',
    invitee_name: '',
    invitee_email: '',
    team_ids: props.defaults.team_ids || [],
    participant_ids: props.defaults.participant_ids || [],
});

function submit() { form.post(route('meetings.store')); }
function selectLead(lead) {
    form.goods_meta_lead_id = lead.id;
    form.invitee_name = lead.full_name;
    form.invitee_email = '';
    if (!form.title) form.title = `اجتماع مع ${lead.full_name}`;
    showLeadPicker.value = false;
    searchLead.value = '';
}
function clearLead() { form.goods_meta_lead_id = null; form.invitee_name = ''; }
</script>

<template>
    <Head title="اجتماع جديد" />
    <AuthenticatedLayout>
        <template #title>
            <div class="flex items-center gap-2">
                <span>اجتماع جديد</span>
                <span class="hidden text-sm font-normal text-gray-400 sm:inline">— نظام إدارة العملاء</span>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-4">
            <Link :href="route('meetings.index')"
                class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7" />
                </svg>
                العودة للاجتماعات
            </Link>

            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-5 shadow-sm sm:p-6">
                <form class="space-y-4 sm:space-y-5" @submit.prevent="submit">
                    <!-- Lead Selection -->
                    <div v-if="metaLeads?.length">
                        <InputLabel value="عميل متجر" />
                        <div v-if="!selectedLead" class="relative mt-1">
                            <div
                                class="flex cursor-pointer items-center justify-between rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-500 transition hover:border-brand-300 sm:px-4 sm:py-3"
                                @click="showLeadPicker = !showLeadPicker">
                                <span>اختر عميل متجر...</span>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m7 10 5 5 5-5" />
                                </svg>
                            </div>
                            <div v-if="showLeadPicker"
                                class="absolute inset-x-0 top-full z-50 mt-1 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
                                <div class="border-b border-gray-100 p-2">
                                    <input v-model="searchLead" type="text"
                                        placeholder="ابحث بالاسم أو الهاتف..."
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 shadow-sm" autofocus />
                                </div>
                                <div class="max-h-48 overflow-y-auto sm:max-h-60">
                                    <button v-for="ml in filteredLeads" :key="ml.id" type="button"
                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-right text-sm transition hover:bg-brand-50 sm:px-4"
                                        @click="selectLead(ml)">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-xs font-bold text-white shadow-sm">
                                            {{ ml.full_name?.charAt(0) || '?' }}
                                        </div>
                                        <div class="min-w-0 flex-1 text-right">
                                            <div class="font-medium text-gray-900">{{ ml.full_name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ ml.phone || '—' }}{{ ml.campaign_name ? ' • ' + ml.campaign_name : '' }}
                                            </div>
                                        </div>
                                    </button>
                                    <div v-if="!filteredLeads.length" class="px-4 py-6 text-center text-sm text-gray-400">لا توجد نتائج</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-1 flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2.5 sm:px-4 sm:py-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white shadow-sm">
                                {{ selectedLead.full_name?.charAt(0) || '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-brand-900">{{ selectedLead.full_name }}</div>
                                <div class="text-xs text-brand-600">
                                    {{ selectedLead.phone || '' }}{{ selectedLead.campaign_name ? ' — ' + selectedLead.campaign_name : '' }}
                                </div>
                            </div>
                            <button type="button"
                                class="shrink-0 rounded-lg border border-brand-300 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100"
                                @click="clearLead">تغيير</button>
                        </div>
                        <InputError class="mt-1" :message="form.errors.goods_meta_lead_id" />
                    </div>

                    <!-- Host -->
                    <div>
                        <InputLabel for="user_id" value="المضيف" />
                        <select id="user_id" v-model="form.user_id"
                            class="mt-1 block w-full min-h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200"
                            required>
                            <option v-for="h in hosts" :key="h.id" :value="h.id">
                                {{ arabicEmployeeName(h.name) }}{{ h.role === 'lead' ? ' • مدير قسم' : '' }}
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.user_id" />
                    </div>

                    <!-- Title -->
                    <div>
                        <InputLabel for="title" value="عنوان الاجتماع" />
                        <TextInput id="title" v-model="form.title" type="text" class="mt-1 block w-full"
                            placeholder="مثال: اجتماع متابعة مع ..." required />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <!-- Date/Time -->
                    <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                        <div>
                            <InputLabel for="start_at" value="تاريخ ووقت البداية" />
                            <TextInput id="start_at" v-model="form.start_at" type="datetime-local" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.start_at" />
                        </div>
                        <div>
                            <InputLabel for="end_at" value="النهاية (اختياري)" />
                            <TextInput id="end_at" v-model="form.end_at" type="datetime-local" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.end_at" />
                        </div>
                    </div>

                    <!-- Teams & Participants -->
                    <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                        <div>
                            <InputLabel for="team_ids" value="مع قسم كامل (اختياري)" />
                            <select id="team_ids" v-model="form.team_ids" multiple size="3"
                                class="mt-1 block w-full min-h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm">
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.team_ids" />
                        </div>
                        <div>
                            <InputLabel for="participant_ids" value="موظفين محددين (اختياري)" />
                            <select id="participant_ids" v-model="form.participant_ids" multiple size="3"
                                class="mt-1 block w-full min-h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm">
                                <option v-for="h in hosts" :key="`p-${h.id}`" :value="h.id">{{ arabicEmployeeName(h.name) }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.participant_ids" />
                        </div>
                    </div>

                    <!-- Client (legacy) -->
                    <div>
                        <InputLabel for="client_id" value="عميل تقليدي (اختياري)" />
                        <select id="client_id" v-model="form.client_id"
                            class="mt-1 block w-full min-h-11 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-sm">
                            <option :value="null">—</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.client_id" />
                    </div>

                    <!-- Invitee -->
                    <div class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                        <div>
                            <InputLabel for="invitee_name" value="اسم الضيف" />
                            <TextInput id="invitee_name" v-model="form.invitee_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.invitee_name" />
                        </div>
                        <div>
                            <InputLabel for="invitee_email" value="بريد الضيف" />
                            <TextInput id="invitee_email" v-model="form.invitee_email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors.invitee_email" />
                        </div>
                    </div>

                    <!-- Reason -->
                    <div>
                        <InputLabel for="reason" value="السبب / ملاحظات" />
                        <textarea id="reason" v-model="form.reason" rows="3"
                            class="mt-1 block w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm"
                            placeholder="سبب الاجتماع، النقاط الرئيسية للمناقشة..." />
                        <InputError class="mt-1" :message="form.errors.reason" />
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4 sm:gap-3 sm:pt-5">
                        <PrimaryButton :disabled="form.processing" class="w-full min-h-11 sm:w-auto sm:px-6">
                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ الاجتماع' }}
                        </PrimaryButton>
                        <Link :href="route('meetings.index')"
                            class="flex w-full min-h-11 items-center justify-center rounded-xl border border-gray-200 bg-white px-5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 sm:w-auto">
                            إلغاء
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
