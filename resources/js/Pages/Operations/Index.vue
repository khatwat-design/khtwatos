<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    userRole: String,
    clients: Array,
    submissions: Array,
    allSubmitted: Boolean,
    analytics: Array,
    showAnalyticsDefault: Boolean,
    currentDate: String,
});

const today = new Date().toISOString().split('T')[0];

const subMap = computed(() => {
    const map = {};
    for (const s of props.submissions || []) {
        map[s.client_id] = s;
    }
    return map;
});

const selectedClient = ref(null);
const modalOpen = ref(false);

const form = useForm({
    client_id: null,
    spend: null,
    ctr: null,
    client_feedback: '',
    personal_feedback: '',
    orders_count: null,
    revenue: null,
});

const isMediaBuyer = computed(() => props.userRole === 'media_buyer' || props.userRole === 'admin');
const isAccountManager = computed(() => props.userRole === 'account_manager' || props.userRole === 'admin');
const isAdmin = computed(() => props.userRole === 'admin');

function openModal(client) {
    selectedClient.value = client;
    const existing = subMap.value[client.id];
    form.reset();
    form.client_id = client.id;
    if (existing) {
        form.spend = existing.spend || null;
        form.ctr = existing.ctr || null;
        form.client_feedback = existing.client_feedback || '';
        form.personal_feedback = existing.personal_feedback || '';
        form.orders_count = existing.orders_count || null;
        form.revenue = existing.revenue || null;
    }
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    selectedClient.value = null;
}

function submitForm() {
    form.post(route('operations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            router.reload({ only: ['submissions', 'allSubmitted', 'analytics', 'showAnalyticsDefault'] });
        },
    });
}

function isClientDone(clientId) {
    const s = subMap.value[clientId];
    return s && s.is_submitted;
}

function formatNum(val) {
    if (val == null || val === '') return '—';
    return Number(val).toLocaleString('ar-SA');
}

function formatCurrency(val) {
    if (val == null || val === '') return '—';
    return `${Number(val).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} $`;
}

const pageTitle = computed(() => 'تقارير العملاء');

const doneCount = computed(() => props.clients.filter((c) => isClientDone(c.id)).length);
const totalCount = computed(() => props.clients.length);

const pageSubtitle = computed(() => {
    if (totalCount.value === 0 && !props.analytics?.length) return 'لا يوجد عملاء لك';
    return `تم ${doneCount.value} من ${totalCount.value}`;
});

function goToDate(date) {
    router.get(route('operations.index', { date }), {}, { preserveState: false, preserveScroll: true });
}

function prevDay() {
    const d = new Date(props.currentDate);
    d.setDate(d.getDate() - 1);
    goToDate(d.toISOString().split('T')[0]);
}

function nextDay() {
    const d = new Date(props.currentDate);
    d.setDate(d.getDate() + 1);
    goToDate(d.toISOString().split('T')[0]);
}

function onDateChange(e) {
    goToDate(e.target.value);
}

const notToday = computed(() => props.currentDate !== today);
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #title>{{ pageTitle }}</template>

        <div class="mx-auto max-w-6xl p-4 sm:p-6">
            <!-- Analytics View -->
            <div v-if="props.showAnalyticsDefault && analytics?.length" class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">التحليلات</h1>
                        <p class="mt-1 text-sm text-gray-500">نتائج {{ props.currentDate }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="prevDay"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        <input type="date" :value="props.currentDate" @change="onDateChange"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                        >
                        <button type="button" @click="nextDay" :disabled="!notToday"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in analytics" :key="row.client_id"
                        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-md transition hover:shadow-lg"
                    >
                        <div class="border-b border-gray-100 bg-gradient-to-l from-gray-50 to-white px-5 py-4">
                            <p class="text-base font-bold text-gray-900">{{ row.client }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-0">
                            <div class="border-b border-gray-50 p-4">
                                <p class="text-xs text-gray-500">ROAS</p>
                                <p class="mt-1 text-lg font-bold"
                                    :class="row.roas >= 1.5 ? 'text-emerald-600' : row.roas >= 1 ? 'text-amber-600' : 'text-red-600'"
                                >{{ row.roas != null ? formatNum(row.roas) + 'x' : '—' }}</p>
                            </div>
                            <div class="border-b border-r-0 border-gray-50 p-4 sm:border-r">
                                <p class="text-xs text-gray-500">CAC</p>
                                <p class="mt-1 text-lg font-bold text-gray-800">{{ row.cac != null ? formatCurrency(row.cac) : '—' }}</p>
                            </div>
                            <div class="border-b border-gray-50 p-4">
                                <p class="text-xs text-gray-500">نسبة التحويل</p>
                                <p class="mt-1 text-lg font-bold"
                                    :class="row.conversion_rate >= 10 ? 'text-emerald-600' : row.conversion_rate >= 5 ? 'text-amber-600' : 'text-red-600'"
                                >{{ row.conversion_rate != null ? formatNum(row.conversion_rate) + '%' : '—' }}</p>
                            </div>
                            <div class="border-b border-r-0 border-gray-50 p-4 sm:border-r">
                                <p class="text-xs text-gray-500">CTR</p>
                                <p class="mt-1 text-lg font-bold text-gray-800">{{ row.ctr != null ? formatNum(row.ctr) + '%' : '—' }}</p>
                            </div>
                            <div class="p-4">
                                <p class="text-xs text-gray-500">الإيراد</p>
                                <p class="mt-1 text-lg font-bold text-emerald-700">{{ formatCurrency(row.revenue) }}</p>
                            </div>
                            <div class="border-r-0 p-4 sm:border-r">
                                <p class="text-xs text-gray-500">المصروف</p>
                                <p class="mt-1 text-lg font-bold text-amber-700">{{ formatCurrency(row.spend) }}</p>
                            </div>
                        </div>

                        <div v-if="row.client_feedback !== '—' || row.personal_feedback !== '—'" class="border-t border-gray-100 bg-gray-50/50 px-5 py-3">
                            <p v-if="row.client_feedback !== '—'" class="text-xs text-gray-600">
                                <span class="font-semibold text-gray-700">العميل: </span>{{ row.client_feedback }}
                            </p>
                            <p v-if="row.personal_feedback !== '—'" class="mt-1 text-xs text-gray-600">
                                <span class="font-semibold text-gray-700">ملاحظاتي: </span>{{ row.personal_feedback }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="!analytics?.length" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="mb-4 rounded-full bg-gray-100 p-4">
                        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                    </div>
                    <p class="text-lg font-medium text-gray-600">لا توجد تقارير لهذا اليوم</p>
                </div>
            </div>

            <!-- Report Cards View (only when not showing analytics) -->
            <div v-else>
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ pageTitle }}</h1>
                        <p class="mt-1 text-sm text-gray-500">{{ pageSubtitle }}</p>
                    </div>
                </div>

                <div v-if="totalCount === 0" class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="mb-4 rounded-full bg-gray-100 p-4">
                        <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <p class="text-lg font-medium text-gray-600">لا يوجد عملاء لك اليوم</p>
                    <p class="mt-1 text-sm text-gray-500" v-if="isAdmin">يمكن للمدير رؤية جميع العملاء</p>
                </div>

                <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <button
                        v-for="client in clients"
                        :key="client.id"
                        type="button"
                        class="group relative overflow-hidden rounded-2xl border-2 p-5 text-start shadow-md transition-all duration-200"
                        :class="isClientDone(client.id)
                            ? 'border-emerald-400 bg-gradient-to-br from-emerald-50 to-white shadow-emerald-900/10 hover:shadow-lg hover:shadow-emerald-900/15'
                            : 'border-gray-200 bg-white shadow-gray-900/5 hover:border-gray-300 hover:shadow-lg hover:shadow-gray-900/10'"
                        @click="openModal(client)"
                    >
                        <div v-if="isClientDone(client.id)" class="absolute end-3 top-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                        </div>

                        <div class="mb-3 flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                :class="isClientDone(client.id) ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                            >
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-gray-900">{{ client.name }}</p>
                                <p v-if="client.company" class="mt-0.5 truncate text-xs text-gray-500">{{ client.company }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="isClientDone(client.id)
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full"
                                    :class="isClientDone(client.id) ? 'bg-emerald-500' : 'bg-amber-500'"
                                ></span>
                                {{ isClientDone(client.id) ? 'تم التقرير' : 'انتظار' }}
                            </span>
                        </div>

                        <div class="mt-3 border-t border-gray-100 pt-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400">{{ isClientDone(client.id) ? 'نقر للتعديل' : 'نقر لإضافة البيانات' }}</span>
                                <svg class="h-3.5 w-3.5 text-gray-300 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path v-if="isClientDone(client.id)" stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <Modal :show="modalOpen" @close="closeModal" max-width="lg">
            <div class="p-6">
                <h3 class="mb-5 text-lg font-bold text-gray-900">
                    {{ selectedClient?.name }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <template v-if="isMediaBuyer">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">المصروف اليومي ($)</label>
                            <input v-model="form.spend" type="number" step="0.01" min="0" lang="en"
                                class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                placeholder="مثال: 150.00"
                            >
                            <InputError :message="form.errors.spend" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">CTR (%)</label>
                            <input v-model="form.ctr" type="number" step="0.01" min="0" max="100" lang="en"
                                class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                placeholder="مثال: 2.5"
                            >
                            <InputError :message="form.errors.ctr" />
                        </div>
                    </template>

                    <template v-if="isAccountManager">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">عدد الطلبات</label>
                            <input v-model="form.orders_count" type="number" min="0" lang="en"
                                class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                placeholder="مثال: 10"
                            >
                            <InputError :message="form.errors.orders_count" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">المبلغ ($)</label>
                            <input v-model="form.revenue" type="number" step="0.01" min="0" lang="en"
                                class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                                placeholder="مثال: 500.00"
                            >
                            <InputError :message="form.errors.revenue" />
                        </div>
                    </template>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">رأي العميل</label>
                        <textarea v-model="form.client_feedback" rows="2"
                            class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                            placeholder="انطباع العميل عن الأداء…"
                        ></textarea>
                        <InputError :message="form.errors.client_feedback" />
                    </div>

                    <div v-if="isMediaBuyer">
                        <label class="mb-1 block text-sm font-medium text-gray-700">رأيي الشخصي</label>
                        <textarea v-model="form.personal_feedback" rows="2"
                            class="w-full rounded-xl border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                            placeholder="ملاحظاتي عن الحملة…"
                        ></textarea>
                        <InputError :message="form.errors.personal_feedback" />
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <button type="button" @click="closeModal"
                            class="rounded-xl border border-gray-200 bg-white px-6 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                        >
                            إلغاء
                        </button>
                        <button type="submit" :disabled="form.processing"
                            class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'حفظ…' : 'حفظ' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>