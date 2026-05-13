<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    client: Object,
    history: Array,
    tasks: Array,
    tasks_by_team: Array,
    meetings: Array,
    task_status_history: Array,
    attachments: Array,
    daily_sales: Array,
    campaign_updates: Array,
    products: Array,
    portal_notes: Array,
    portal: Object,
    stages: Array,
    metrics: Object,
    accountManagers: Array,
    campaignManagers: Array,
    meta_profile: Object,
    can_manage_subscription: {
        type: Boolean,
        default: false,
    },
    outside_threads: {
        type: Object,
        default: () => ({}),
    },
    campaign_decision_engine: {
        type: Object,
        default: () => ({}),
    },
});

const history = computed(() =>
    [...(props.history || [])].sort((a, b) => {
        const atDiff = new Date(b?.at || 0).getTime() - new Date(a?.at || 0).getTime();
        if (atDiff !== 0) return atDiff;
        return Number(b?.id || 0) - Number(a?.id || 0);
    }),
);
const page = usePage();
const canDeleteRecords = Boolean(page.props.auth?.can?.deleteRecords);
const canManageProducts = Boolean(canDeleteRecords || page.props.auth?.can?.viewClientPortalLink);
const clientAttachments = ref([...(props.attachments || [])]);
const attachmentInputRef = ref(null);
const clientLogoInputRef = ref(null);
const attachmentUploading = ref(false);
const attachmentProgress = ref(0);
const attachmentError = ref('');
const attachmentFile = ref(null);
const showProductModal = ref(false);
const showPortalAccessCard = ref(true);
const showTaskStatusHistory = ref(true);
const showSubscriptionModal = ref(false);
const showUploadAttachmentModal = ref(false);
const showReferenceLinkModal = ref(false);
const maxAttachmentSizeBytes = 10 * 1024 * 1024;
const clientLogoPreview = ref(props.client.logo_url || '');
const subscriptionForm = useForm({});
const subscriptionDatesForm = useForm({
    subscription_started_at: '',
    subscription_ends_at: '',
});

const clientForm = useForm({
    name: props.client.name,
    company: props.client.company || '',
    email: props.client.email || '',
    phone: props.client.phone || '',
    notes: props.client.notes || '',
    account_manager_id: props.client.account_manager?.id ?? null,
    campaign_manager_id: props.client.campaign_manager?.id ?? null,
    facebook_page: props.meta_profile?.facebook_page || '',
    instagram_page: props.meta_profile?.instagram_page || '',
    logo: null,
    remove_logo: false,
});

const stageForm = useForm({
    current_pipeline_stage_id: props.client.current_stage?.id,
    note: '',
});

const portalForm = useForm({
    portal_username: props.portal?.username || '',
    portal_password: '',
    portal_password_confirmation: '',
});

const productForm = useForm({
    name: '',
    unit_price: '',
    stock_quantity: '',
    details: '',
    is_active: true,
});

const referenceLinkForm = useForm({
    title: '',
    url: '',
});

watch(
    () => props.attachments,
    (nextAttachments) => {
        clientAttachments.value = [...(nextAttachments || [])];
    },
    { deep: true },
);

function saveClient() {
    clientForm
        .transform((data) => ({ ...data, _method: 'patch' }))
        .post(route('clients.update', props.client.id), { forceFormData: true });
}

function onClientLogoChange(event) {
    const file = event.target?.files?.[0] || null;
    clientForm.logo = file;
    clientForm.remove_logo = false;
    clientLogoPreview.value = file ? URL.createObjectURL(file) : (props.client.logo_url || '');
}

function removeClientLogo() {
    clientForm.logo = null;
    clientForm.remove_logo = true;
    clientLogoPreview.value = '';
    if (clientLogoInputRef.value) {
        clientLogoInputRef.value.value = '';
    }
}

function saveStage() {
    stageForm.patch(route('clients.stage', props.client.id), {
        preserveScroll: true,
        onSuccess: () => stageForm.reset('note'),
    });
}

function savePortalCredentials() {
    if (!portalForm.portal_username) {
        return;
    }
    portalForm.patch(route('clients.portal-credentials.update', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            portalForm.reset('portal_password', 'portal_password_confirmation');
        },
    });
}

function activateSubscription() {
    if (!props.can_manage_subscription || subscriptionForm.processing) {
        return;
    }
    subscriptionForm.clearErrors();
    subscriptionForm.post(route('clients.subscription.activate', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['client'],
                preserveScroll: true,
            });
        },
    });
}

const subscriptionStatus = computed(() => props.client?.subscription || {});

function toDateTimeLocal(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    const offsetMs = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
}

function openSubscriptionModal() {
    subscriptionDatesForm.reset();
    subscriptionDatesForm.clearErrors();
    subscriptionDatesForm.subscription_started_at = toDateTimeLocal(subscriptionStatus.value?.started_at || new Date().toISOString());
    subscriptionDatesForm.subscription_ends_at = toDateTimeLocal(subscriptionStatus.value?.ends_at || new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toISOString());
    showSubscriptionModal.value = true;
}

function saveSubscriptionDates() {
    subscriptionDatesForm.clearErrors();
    subscriptionDatesForm.patch(route('clients.subscription.update', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSubscriptionModal.value = false;
            router.reload({
                only: ['client'],
                preserveScroll: true,
            });
        },
    });
}

function saveProduct() {
    if (!canManageProducts) {
        return;
    }
    productForm.post(route('clients.products.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            productForm.reset();
            showProductModal.value = false;
        },
    });
}

function deleteProduct(product) {
    if (!canManageProducts || !product?.id) {
        return;
    }
    if (!confirm('حذف هذا المنتج من العميل؟')) {
        return;
    }
    router.delete(route('clients.products.destroy', product.id), {
        preserveScroll: true,
    });
}

function deleteClient() {
    if (!canDeleteRecords) {
        return;
    }
    if (!confirm('حذف هذا العميل؟ سيتم حذف كل البيانات المرتبطة به.')) {
        return;
    }
    router.delete(route('clients.destroy', props.client.id));
}

function formatFileSize(size) {
    const value = Number(size || 0);
    if (value < 1024) return `${value} B`;
    if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ar-IQ', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 0,
    }).format(Number(value || 0));
}

function campaignDecisionHealthPillClass(health) {
    if (health === 'bad') {
        return 'bg-rose-100 text-rose-900 ring-rose-200/90';
    }
    if (health === 'warning') {
        return 'bg-amber-100 text-amber-950 ring-amber-200/90';
    }
    return 'bg-emerald-100 text-emerald-900 ring-emerald-200/90';
}

function campaignDecisionPriorityLabel(priority) {
    const map = { high: 'عاجل', medium: 'متوسط', low: 'متابعة' };
    return map[priority] || priority || '';
}

function formatDeltaPct(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    const n = Number(value);
    return `${n >= 0 ? '+' : ''}${n}%`;
}

function onAttachmentChange(event) {
    const selected = event.target?.files?.[0] || null;
    if (!selected) {
        attachmentFile.value = null;
        return;
    }

    if (Number(selected.size || 0) > maxAttachmentSizeBytes) {
        attachmentFile.value = null;
        attachmentError.value = 'حجم الملف أكبر من 10MB. اختر ملفًا أصغر.';
        if (attachmentInputRef.value) {
            attachmentInputRef.value.value = '';
        }
        return;
    }

    attachmentError.value = '';
    attachmentFile.value = selected;
}

function openUploadAttachmentModal() {
    attachmentError.value = '';
    attachmentProgress.value = 0;
    attachmentFile.value = null;
    if (attachmentInputRef.value) {
        attachmentInputRef.value.value = '';
    }
    showUploadAttachmentModal.value = true;
}

async function uploadClientAttachment() {
    if (!attachmentFile.value || attachmentUploading.value) {
        return;
    }

    attachmentUploading.value = true;
    attachmentProgress.value = 0;
    attachmentError.value = '';

    try {
        const payload = new FormData();
        payload.append('file', attachmentFile.value);

        const response = await window.axios.post(
            route('clients.attachments.store', props.client.id),
            payload,
            {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'multipart/form-data',
                },
                timeout: 120000,
                onUploadProgress: (event) => {
                    const total = Number(event?.total || 0);
                    if (!total) {
                        return;
                    }
                    attachmentProgress.value = Math.min(100, Math.round((Number(event.loaded || 0) / total) * 100));
                },
            },
        );

        if (response?.data?.attachment) {
            clientAttachments.value.unshift(response.data.attachment);
            attachmentFile.value = null;
            if (attachmentInputRef.value) {
                attachmentInputRef.value.value = '';
            }
            attachmentProgress.value = 0;
            showUploadAttachmentModal.value = false;
        }
    } catch (error) {
        attachmentError.value = error?.response?.data?.message || 'تعذر رفع المرفق، حاول مرة أخرى.';
    } finally {
        attachmentUploading.value = false;
    }
}

function saveReferenceLink() {
    if (!referenceLinkForm.url) {
        return;
    }
    referenceLinkForm.post(route('clients.reference-links.store', props.client.id), {
        preserveScroll: true,
        onSuccess: () => {
            referenceLinkForm.reset();
            router.reload({
                only: ['attachments'],
                preserveScroll: true,
            });
            showReferenceLinkModal.value = false;
        },
    });
}

function openReferenceLinkModal() {
    referenceLinkForm.reset();
    referenceLinkForm.clearErrors();
    showReferenceLinkModal.value = true;
}

function canDeleteAttachment(attachment) {
    if (canDeleteRecords) {
        return true;
    }
    const currentUserId = page.props.auth?.user?.id;
    return Number(attachment?.uploaded_by?.id || 0) === Number(currentUserId || 0);
}

function deleteClientAttachment(attachment) {
    if (!attachment?.id || !confirm('حذف هذا المرفق؟')) {
        return;
    }

    router.delete(route('clients.attachments.destroy', attachment.id), {
        preserveScroll: true,
        onSuccess: () => {
            clientAttachments.value = clientAttachments.value.filter((item) => item.id !== attachment.id);
        },
        onError: () => {
            attachmentError.value = 'تعذر حذف المرفق.';
        },
    });
}

function formatDt(iso) {
    return new Intl.DateTimeFormat('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

const sourceLabels = {
    internal: 'داخلي',
    calendly: 'Calendly',
};

const teamLabelMap = {
    writing: 'فريق الكتابة',
    'media-buyer': 'مدراء الحملات',
    account: 'مدراء الحسابات',
    sales: 'المبيعات',
    hr: 'الموارد البشرية',
    accounting: 'المحاسبة',
    'media buyer': 'مدراء الحملات',
};

const columnLabelMap = {
    backlog: 'المتراكم',
    todo: 'للعمل',
    'to do': 'للعمل',
    doing: 'قيد التنفيذ',
    'in progress': 'قيد التنفيذ',
    review: 'مراجعة',
    revision: 'تعديل',
    modify: 'تعديل',
    done: 'تم',
    completed: 'تم',
    blocked: 'متوقفة',
};

function localizeTeamName(name) {
    const key = String(name || '')
        .trim()
        .toLowerCase();

    return teamLabelMap[key] || name;
}

function localizeColumnName(name) {
    const key = String(name || '')
        .trim()
        .toLowerCase();

    return columnLabelMap[key] || name;
}

const hasOutsideMessenger = computed(() =>
    Boolean(props.outside_threads?.whatsapp || props.outside_threads?.instagram || props.outside_threads?.messenger),
);

/** فتح «الخارج» على نفس قناة مراسلة العميل (واتساب أو إنستغرام) مع تمرير المحادثة عند توفرها */
function outsideMessengerUrl(channel) {
    const ent = props.outside_threads?.[channel];
    if (!ent && !props.client?.id) {
        return route('outside.index');
    }
    const query = { client: props.client.id, channel };
    if (ent?.conversation_id) {
        query.conversation = ent.conversation_id;
    }
    return route('outside.index', query);
}
</script>

<template>
    <Head :title="client.name" />

    <AuthenticatedLayout>
        <template #title>{{ client.name }}</template>

        <div class="client-page mx-auto max-w-5xl space-y-6">
            <Link
                :href="route('clients.index')"
                class="text-sm text-brand-300 transition-colors duration-200 hover:text-brand-200 hover:underline"
            >
                ← العودة للعملاء
            </Link>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <h2 class="text-lg font-semibold text-gray-900">ملاحظات العميل المؤقتة (24 ساعة)</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="note in portal_notes"
                        :key="`portal-note-${note.id}`"
                        class="rounded-xl border border-amber-300/70 bg-amber-50/90 p-3 shadow-sm transition-all duration-200 ease-out hover:scale-[1.02]"
                    >
                        <p class="text-sm text-amber-900">{{ note.note }}</p>
                        <p class="mt-2 text-[11px] text-amber-700">
                            تنتهي: {{ formatDt(note.expires_at) }}
                        </p>
                    </div>
                    <p v-if="!portal_notes?.length" class="text-sm text-gray-500">
                        لا توجد ملاحظات عميل فعالة حاليًا.
                    </p>
                </div>
            </div>

            <div v-if="portal" class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">إعدادات دخول بوابة العميل</h2>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showPortalAccessCard = !showPortalAccessCard"
                    >
                        {{ showPortalAccessCard ? 'إخفاء' : 'إظهار' }}
                    </button>
                </div>

                <div v-if="showPortalAccessCard">
                    <p class="mt-1 text-sm text-gray-600">
                        إعداد اسم المستخدم وكلمة السر يتم من هنا فقط.
                    </p>

                    <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="savePortalCredentials">
                        <div class="md:col-span-2">
                            <InputLabel for="portal_username" value="اسم المستخدم للبوابة" />
                            <TextInput
                                id="portal_username"
                                v-model="portalForm.portal_username"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-1" :message="portalForm.errors.portal_username" />
                        </div>
                        <div>
                            <InputLabel for="portal_password" value="كلمة السر الجديدة" />
                            <TextInput
                                id="portal_password"
                                v-model="portalForm.portal_password"
                                type="password"
                                class="mt-1 block w-full"
                                :placeholder="portal.has_credentials ? 'اتركها فارغة للإبقاء على الحالية' : ''"
                            />
                            <InputError class="mt-1" :message="portalForm.errors.portal_password" />
                        </div>
                        <div>
                            <InputLabel for="portal_password_confirmation" value="تأكيد كلمة السر" />
                            <TextInput
                                id="portal_password_confirmation"
                                v-model="portalForm.portal_password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <PrimaryButton :disabled="portalForm.processing">
                                حفظ بيانات دخول البوابة
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <h2 class="text-lg font-semibold text-gray-900">مرفقات ومرجع العميل</h2>
                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,280px]">
                    <div class="space-y-2">
                        <div
                            v-for="attachment in clientAttachments"
                            :key="`client-attachment-${attachment.id}`"
                            class="rounded-xl border border-slate-200 bg-slate-50/80 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a
                                        :href="attachment.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="truncate text-sm font-semibold text-brand-600 hover:underline"
                                    >
                                        {{ attachment.is_link ? `🔗 ${attachment.name}` : attachment.name }}
                                    </a>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ attachment.is_link ? 'رابط مرجعي' : (attachment.mime || 'ملف') }}
                                        <span v-if="!attachment.is_link"> • {{ formatFileSize(attachment.size) }}</span>
                                        <span v-if="attachment.uploaded_by"> • بواسطة {{ attachment.uploaded_by.name }}</span>
                                    </p>
                                </div>
                                <button
                                    v-if="canDeleteAttachment(attachment)"
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="deleteClientAttachment(attachment)"
                                >
                                    حذف
                                </button>
                            </div>
                            <img
                                v-if="attachment.is_image && !attachment.is_link"
                                :src="attachment.url"
                                alt="مرفق عميل"
                                class="mt-2 max-h-44 rounded border border-gray-200"
                            />
                        </div>
                        <p v-if="!clientAttachments.length" class="text-sm text-gray-500">
                            لا توجد مرفقات بعد لهذا العميل.
                        </p>
                    </div>

                    <div class="space-y-3 rounded-xl border border-slate-200 bg-white/80 p-3">
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            @click="openUploadAttachmentModal"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 3v12" />
                                <path d="m7 8 5-5 5 5" />
                                <path d="M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4" />
                            </svg>
                            رفع ملف
                        </button>
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            @click="openReferenceLinkModal"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10 13a5 5 0 0 0 7.54.54l2.12-2.12a5 5 0 0 0-7.07-7.07L11.3 5.6" />
                                <path d="M14 11a5 5 0 0 0-7.54-.54L4.34 12.58a5 5 0 0 0 7.07 7.07l1.28-1.28" />
                            </svg>
                            إضافة رابط
                        </button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">منتجات العميل والمخزن</h2>
                </div>
                <div class="mt-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                    <th class="px-2 py-2">المنتج</th>
                                    <th class="px-2 py-2">السعر</th>
                                    <th class="px-2 py-2">المخزون</th>
                                    <th class="px-2 py-2">التفاصيل</th>
                                    <th class="px-2 py-2">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="product in products"
                                    :key="`client-product-${product.id}`"
                                    class="border-b border-gray-100"
                                >
                                    <td class="px-2 py-2 font-medium text-gray-800">{{ product.name }}</td>
                                    <td class="px-2 py-2">{{ formatMoney(product.unit_price) }}</td>
                                    <td class="px-2 py-2">{{ product.stock_quantity ?? 'غير محدد' }}</td>
                                    <td class="px-2 py-2 text-xs text-gray-500">{{ product.details || '—' }}</td>
                                    <td class="px-2 py-2 text-xs">
                                        {{ product.is_active ? 'نشط' : 'غير نشط' }}
                                    </td>
                                </tr>
                                <tr v-if="!products?.length">
                                    <td class="px-2 py-4 text-center text-gray-500" colspan="5">
                                        لا توجد منتجات مرتبطة بهذا العميل بعد.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                v-if="campaign_decision_engine"
                class="rounded-2xl border border-indigo-200/55 bg-gradient-to-br from-indigo-50/95 via-white/85 to-white/90 p-5 shadow-lg shadow-indigo-900/5 ring-1 ring-indigo-100/80 backdrop-blur-xl"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">القرارات المقترحة</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            تحليل قرائي بسيط فوق بيانات الحملات المسجّلة — لا يُنفَّذ تلقائيًا على ميتا.
                        </p>
                    </div>
                    <span
                        v-if="campaign_decision_engine.enabled"
                        class="shrink-0 rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-semibold text-indigo-900"
                    >
                        {{ (campaign_decision_engine.campaigns || []).length }} يوم أداء
                    </span>
                </div>

                <div v-if="campaign_decision_engine.daily_insight" class="mt-4 rounded-xl border border-gray-200/80 bg-white/80 p-3 text-sm text-gray-800">
                    <p class="font-medium text-gray-900">{{ campaign_decision_engine.daily_insight.headline }}</p>
                    <div
                        v-if="campaign_decision_engine.daily_insight.vs_yesterday"
                        class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600"
                    >
                        <span
                            >ROAS عن أمس:
                            <strong class="tabular-nums text-gray-900">{{
                                formatDeltaPct(campaign_decision_engine.daily_insight.vs_yesterday.roas_delta_pct)
                            }}</strong></span
                        >
                        <span
                            >الإنفاق:
                            <strong class="tabular-nums text-gray-900">{{
                                formatDeltaPct(campaign_decision_engine.daily_insight.vs_yesterday.spend_delta_pct)
                            }}</strong></span
                        >
                        <span
                            >إيراد الحملة:
                            <strong class="tabular-nums text-gray-900">{{
                                formatDeltaPct(campaign_decision_engine.daily_insight.vs_yesterday.revenue_delta_pct)
                            }}</strong></span
                        >
                    </div>
                    <p v-if="campaign_decision_engine.daily_insight.key_issue" class="mt-2 text-xs font-medium text-rose-800">
                        تنبيه: {{ campaign_decision_engine.daily_insight.key_issue }}
                    </p>
                    <p v-if="campaign_decision_engine.daily_insight.key_opportunity" class="mt-1 text-xs font-medium text-emerald-800">
                        فرصة: {{ campaign_decision_engine.daily_insight.key_opportunity }}
                    </p>
                </div>

                <div
                    v-if="campaign_decision_engine.profit && (campaign_decision_engine.profit.ad_spend != null || campaign_decision_engine.profit.revenue != null)"
                    class="mt-3 flex flex-wrap gap-3 rounded-xl border border-slate-200/90 bg-slate-50/80 px-3 py-2 text-xs text-gray-700"
                >
                    <span
                        >إيراد (آخر يوم مسجل):
                        <strong class="tabular-nums text-gray-900">{{
                            campaign_decision_engine.profit.revenue != null
                                ? formatMoney(campaign_decision_engine.profit.revenue)
                                : '—'
                        }}</strong></span
                    >
                    <span
                        >إنفاق إعلان:
                        <strong class="tabular-nums text-gray-900">{{ formatMoney(campaign_decision_engine.profit.ad_spend || 0) }}</strong></span
                    >
                    <span v-if="campaign_decision_engine.profit.estimated_profit != null"
                        >ربح تقديري:
                        <strong
                            class="tabular-nums"
                            :class="
                                Number(campaign_decision_engine.profit.estimated_profit) >= 0
                                    ? 'text-emerald-800'
                                    : 'text-rose-800'
                            "
                            >{{ formatMoney(campaign_decision_engine.profit.estimated_profit) }}</strong
                        ></span
                    >
                    <span v-if="campaign_decision_engine.profit.note" class="w-full text-[11px] text-gray-500">{{
                        campaign_decision_engine.profit.note
                    }}</span>
                </div>

                <div v-if="(campaign_decision_engine.recommended_actions || []).length" class="mt-4">
                    <p class="text-xs font-semibold text-gray-600">أهم الإجراءات المقترحة</p>
                    <ul class="mt-2 list-disc space-y-1.5 pe-4 text-sm text-gray-800">
                        <li v-for="(line, idx) in campaign_decision_engine.recommended_actions" :key="`cda-${idx}`" class="leading-snug">
                            {{ line }}
                        </li>
                    </ul>
                </div>

                <div v-if="campaign_decision_engine.enabled && (campaign_decision_engine.campaigns || []).length" class="mt-4 border-t border-gray-200/80 pt-3">
                    <p class="text-xs font-semibold text-gray-600">تفاصيل حسب يوم الأداء (مرتبة حسب الأولوية)</p>
                    <ul class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white/70">
                        <li
                            v-for="row in (campaign_decision_engine.campaigns || []).slice(0, 6)"
                            :key="`cde-${row.id}`"
                            class="px-3 py-2.5 text-sm"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-900">{{ row.label }}</span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold ring-1"
                                        :class="campaignDecisionHealthPillClass(row.health)"
                                    >
                                        {{ row.health === 'good' ? 'جيد' : row.health === 'warning' ? 'تحذير' : 'ضعيف' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-800">
                                        {{ campaignDecisionPriorityLabel(row.priority) }}
                                    </span>
                                </div>
                            </div>
                            <ul class="mt-1.5 list-disc space-y-0.5 pe-4 text-xs text-gray-700">
                                <li v-for="(act, j) in row.actions" :key="`cde-act-${row.id}-${j}`">{{ act }}</li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                    <h2 class="text-lg font-semibold text-gray-900">مبيعات العميل اليومية</h2>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        <li
                            v-for="row in daily_sales"
                            :key="`daily-sale-${row.id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div class="text-gray-700">
                                <span class="font-medium">{{ row.sales_date }}</span>
                                <span class="text-gray-500"> · {{ row.orders_count }} طلب</span>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ formatMoney(row.revenue) }}
                                <span v-if="row.submitted_by"> · {{ row.submitted_by }}</span>
                            </div>
                            <ul v-if="row.items?.length" class="w-full pt-1 text-xs text-gray-500">
                                <li
                                    v-for="item in row.items"
                                    :key="`daily-sale-item-${item.id}`"
                                    class="py-0.5"
                                >
                                    {{ item.product_name }}: {{ item.quantity }} × {{ formatMoney(item.unit_price) }} = {{ formatMoney(item.subtotal) }}
                                </li>
                            </ul>
                        </li>
                        <li v-if="!daily_sales?.length" class="py-4 text-gray-500">
                            لا توجد مبيعات يومية مسجلة بعد.
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                    <h2 class="text-lg font-semibold text-gray-900">وضع الحملات الحالية</h2>
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        <li
                            v-for="row in campaign_updates"
                            :key="`campaign-update-${row.id}`"
                            class="py-2"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-gray-800">{{ row.report_date }}</span>
                                <span class="text-xs text-gray-500">ROAS: {{ row.roas ?? '—' }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">
                                Spend: {{ formatMoney(row.ad_spend) }} · الرسائل: {{ row.messages_count }} · النقرات: {{ row.clicks_count }}
                            </div>
                            <p v-if="row.actions_taken" class="mt-1 text-xs text-gray-500">
                                {{ row.actions_taken }}
                            </p>
                        </li>
                        <li v-if="!campaign_updates?.length" class="py-4 text-gray-500">
                            لم يتم تسجيل وضع حملات بعد.
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-white/20 bg-white/70 p-4 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl transition-all duration-200 ease-out hover:scale-[1.02]">
                    <p class="text-xs font-medium text-gray-500">مهام مفتوحة</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.open_tasks }}
                    </p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/70 p-4 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl transition-all duration-200 ease-out hover:scale-[1.02]">
                    <p class="text-xs font-medium text-gray-500">
                        اجتماعات قادمة
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.upcoming_meetings }}
                    </p>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/70 p-4 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl transition-all duration-200 ease-out hover:scale-[1.02]">
                    <p class="text-xs font-medium text-gray-500">
                        أيام منذ آخر تحديث
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">
                        {{ metrics.days_since_update }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold text-gray-900">البيانات</h2>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="subscriptionStatus?.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ subscriptionStatus?.is_active ? 'اشتراك مفعل' : 'اشتراك غير مفعل' }}
                            </span>
                            <button
                                v-if="can_manage_subscription"
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
                                title="إدارة الاشتراك"
                                @click="openSubscriptionModal"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 7.5V6a2 2 0 0 0-2-2h-1.5" />
                                    <path d="M3 7.5V6a2 2 0 0 1 2-2h1.5" />
                                    <path d="M21 16.5V18a2 2 0 0 1-2 2h-1.5" />
                                    <path d="M3 16.5V18a2 2 0 0 0 2 2h1.5" />
                                    <rect x="7" y="7" width="10" height="10" rx="2" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 grid gap-2 text-xs text-gray-600 sm:grid-cols-2">
                        <p>بداية الاشتراك: <strong>{{ subscriptionStatus?.started_at ? formatDt(subscriptionStatus.started_at) : '—' }}</strong></p>
                        <p>انتهاء الاشتراك: <strong>{{ subscriptionStatus?.ends_at ? formatDt(subscriptionStatus.ends_at) : '—' }}</strong></p>
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="saveClient">
                        <div>
                            <InputLabel for="client_logo" value="شعار العميل" />
                            <div class="mt-2 flex items-center gap-3">
                                <img
                                    :src="clientLogoPreview || '/images/mobile-logo.png'"
                                    alt="شعار العميل"
                                    class="h-14 w-14 rounded-xl border border-gray-200 object-cover"
                                />
                                <div class="space-y-1">
                                    <input
                                        id="client_logo"
                                        ref="clientLogoInputRef"
                                        type="file"
                                        accept="image/*"
                                        class="block text-xs text-gray-600 file:me-2 file:rounded-xl file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-medium"
                                        @change="onClientLogoChange"
                                    />
                                    <button
                                        v-if="clientLogoPreview"
                                        type="button"
                                        class="text-xs text-red-600 hover:underline"
                                        @click="removeClientLogo"
                                    >
                                        إزالة الشعار
                                    </button>
                                </div>
                            </div>
                            <InputError class="mt-1" :message="clientForm.errors.logo" />
                        </div>
                        <div>
                            <InputLabel for="name" value="الاسم" />
                            <TextInput
                                id="name"
                                v-model="clientForm.name"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel for="company" value="الشركة" />
                            <TextInput
                                id="company"
                                v-model="clientForm.company"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="email" value="البريد" />
                            <TextInput
                                id="email"
                                v-model="clientForm.email"
                                type="email"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="phone" value="الهاتف" />
                            <TextInput
                                id="phone"
                                v-model="clientForm.phone"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel for="am" value="مدير الحساب" />
                            <select
                                id="am"
                                v-model="clientForm.account_manager_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
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
                        </div>
                        <div>
                            <InputLabel for="notes" value="ملاحظات" />
                            <textarea
                                id="notes"
                                v-model="clientForm.notes"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <InputLabel for="facebook_page" value="صفحة الفيسبوك" />
                            <TextInput
                                id="facebook_page"
                                v-model="clientForm.facebook_page"
                                class="mt-1 block w-full"
                            />
                            <a
                                v-if="meta_profile?.facebook_page_url"
                                :href="meta_profile.facebook_page_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-block text-xs font-semibold text-blue-600 underline-offset-2 hover:underline"
                            >
                                فتح صفحة الفيسبوك في المتصفح
                            </a>
                        </div>
                        <div>
                            <InputLabel for="instagram_page" value="صفحة الانستغرام" />
                            <TextInput
                                id="instagram_page"
                                v-model="clientForm.instagram_page"
                                class="mt-1 block w-full"
                            />
                            <a
                                v-if="meta_profile?.instagram_page_url"
                                :href="meta_profile.instagram_page_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-block text-xs font-semibold text-pink-700 underline-offset-2 hover:underline"
                            >
                                فتح صفحة الإنستغرام في المتصفح
                            </a>
                        </div>
                        <div>
                            <InputLabel for="ad_account_id" value="رقم الحساب الإعلاني المرتبط" />
                            <TextInput
                                id="ad_account_id"
                                :model-value="props.meta_profile?.ad_account_id ? (String(props.meta_profile.ad_account_id).startsWith('act_') ? props.meta_profile.ad_account_id : `act_${props.meta_profile.ad_account_id}`) : 'غير مرتبط'"
                                class="mt-1 block w-full bg-gray-50"
                                readonly
                            />
                            <div
                                v-if="props.meta_profile?.connection_status"
                                class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-800"
                            >
                                <p class="font-semibold text-slate-900">
                                    حالة ربط Meta (OAuth):
                                    <span class="font-mono text-[10px] text-slate-700">{{ props.meta_profile.connection_status }}</span>
                                </p>
                                <p v-if="props.meta_profile.user_message" class="mt-1 leading-relaxed">
                                    {{ props.meta_profile.user_message }}
                                </p>
                                <p v-if="props.meta_profile.last_error_at" class="mt-1 text-[10px] text-rose-700">
                                    آخر خطأ: {{ props.meta_profile.last_error_message || '—' }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="hasOutsideMessenger"
                            class="rounded-xl border border-slate-200 bg-slate-50/90 px-3 py-3 text-xs text-slate-800"
                        >
                            <p class="font-semibold text-slate-900">المراسلة من «الخارج»</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-600">
                                يفتح المحادثة على نفس القناة التي تواصل منها العميل (واتساب أو إنستغرام أو ماسنجر).
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Link
                                    v-if="outside_threads?.whatsapp"
                                    :href="outsideMessengerUrl('whatsapp')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200/90 bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-900 transition hover:bg-emerald-100/90"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                    </svg>
                                    واتساب في الخارج
                                </Link>
                                <Link
                                    v-if="outside_threads?.instagram"
                                    :href="outsideMessengerUrl('instagram')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-pink-200/90 bg-gradient-to-r from-amber-50/90 to-fuchsia-50/90 px-3 py-1.5 text-[11px] font-bold text-pink-950 transition hover:opacity-95"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="igGradClient" x1="0%" y1="100%" x2="100%" y2="0%">
                                                <stop offset="0%" style="stop-color:#f09433" />
                                                <stop offset="50%" style="stop-color:#e6683c" />
                                                <stop offset="100%" style="stop-color:#bc1888" />
                                            </linearGradient>
                                        </defs>
                                        <path fill="url(#igGradClient)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                    </svg>
                                    إنستغرام في الخارج
                                </Link>
                                <Link
                                    v-if="outside_threads?.messenger"
                                    :href="outsideMessengerUrl('messenger')"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200/90 bg-gradient-to-r from-sky-50/95 to-violet-50/95 px-3 py-1.5 text-[11px] font-bold text-blue-950 transition hover:opacity-95"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0 text-[#0084FF]" viewBox="0 0 24 24" aria-hidden="true">
                                        <path
                                            fill="currentColor"
                                            d="M12 0C5.373 0 0 4.975 0 11.111c0 3.498 2.138 6.561 5.465 8.09-.057 1.515-.354 5.031-.573 5.688-.091.271.067.278.138.203.571-.632 3.765-4.401 5.465-5.865 1.43.396 2.945.609 4.505.609 6.627 0 12-4.974 12-11.111S18.627 0 12 0zm5.539 8.93l-2.98 4.157c-.091.126-.254.168-.392.093l-2.286-1.13-1.107 1.013c-.139.127-.361.067-.416-.116l-.791-2.597-2.981-4.156c-.091-.126-.007-.293.139-.293h8.723c.146 0 .231.167.141.293z"
                                        />
                                    </svg>
                                    ماسنجر في الخارج
                                </Link>
                            </div>
                        </div>
                        <div
                            v-else-if="Number(props.meta_profile?.outside_instagram_threads || 0) > 0 || props.meta_profile?.instagram_page"
                            class="rounded-xl border border-fuchsia-100 bg-fuchsia-50/80 px-3 py-2 text-xs text-fuchsia-950"
                        >
                            <p class="font-semibold">قسم الخارج — إنستغرام</p>
                            <p class="mt-1 text-fuchsia-900/90">
                                محادثات مرتبطة بهذا العميل في «الخارج»:
                                <strong>{{ props.meta_profile?.outside_instagram_threads ?? 0 }}</strong>
                            </p>
                            <p class="mt-1 text-[11px] text-fuchsia-800/80">
                                بعد ربط ميتا من البوابة واشتراك ويب هوك إنستغرام، تظهر رسائل الـ DM هنا مع تمييزها عن واتساب.
                            </p>
                            <Link
                                :href="route('outside.index')"
                                class="mt-2 inline-flex text-[11px] font-semibold text-fuchsia-700 underline decoration-fuchsia-400/60 underline-offset-2 hover:text-fuchsia-900"
                            >
                                فتح قسم الخارج
                            </Link>
                        </div>
                        <div>
                            <InputLabel for="cm" value="مدير الحملة المسؤول" />
                            <select
                                id="cm"
                                v-model="clientForm.campaign_manager_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option :value="null">—</option>
                                <option
                                    v-for="u in campaignManagers"
                                    :key="`campaign-manager-${u.id}`"
                                    :value="u.id"
                                >
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>
                        <PrimaryButton :disabled="clientForm.processing"
                            >حفظ</PrimaryButton
                        >
                    </form>
                </div>

                <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                    <h2 class="text-lg font-semibold text-gray-900">المسار</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        الحالية:
                        <span class="font-medium text-gray-900">{{
                            client.current_stage?.label
                        }}</span>
                    </p>
                    <form class="mt-4 space-y-3" @submit.prevent="saveStage">
                        <div>
                            <InputLabel for="stage" value="نقل إلى مرحلة" />
                            <select
                                id="stage"
                                v-model="stageForm.current_pipeline_stage_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                required
                            >
                                <option
                                    v-for="s in stages"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="snote" value="ملاحظة (اختياري)" />
                            <TextInput
                                id="snote"
                                v-model="stageForm.note"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <PrimaryButton :disabled="stageForm.processing"
                            >تحديث المرحلة</PrimaryButton
                        >
                    </form>

                    <div class="mt-6 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">سجل المسار</h3>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-600">
                            {{ history?.length || 0 }} حدث
                        </span>
                    </div>
                    <ul class="mt-3 max-h-60 space-y-2 overflow-y-auto pe-1 text-sm">
                        <li
                            v-for="h in history"
                            :key="h.id"
                            class="rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 px-3 py-2 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-slate-900">{{ h.stage }}</p>
                                <span class="text-[11px] text-slate-500">{{ formatDt(h.at) }}</span>
                            </div>
                            <div class="mt-1 text-xs text-slate-600">
                                بواسطة: <span class="font-medium">{{ h.user || 'غير محدد' }}</span>
                            </div>
                            <div v-if="h.note" class="mt-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800">
                                {{ h.note }}
                            </div>
                        </li>
                        <li v-if="!history?.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">
                            لا يوجد سجل مسار حتى الآن.
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <h2 class="text-lg font-semibold text-gray-900">المهام حسب الفريق</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div
                        v-for="block in tasks_by_team"
                        :key="block.team"
                        class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-800">
                                {{ localizeTeamName(block.team) }}
                            </h3>
                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600">
                                {{ block.tasks?.length || 0 }} مهمة
                            </span>
                        </div>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="t in block.tasks"
                                :key="t.id"
                                class="rounded-xl border border-slate-200 bg-white p-3"
                            >
                                <p class="text-sm font-semibold text-slate-900">{{ t.title }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-700">
                                        المرحلة: {{ localizeColumnName(t.column) || '—' }}
                                    </span>
                                    <span
                                        v-if="(t.assignees || []).length"
                                        class="rounded-md bg-brand-50 px-2 py-0.5 text-brand-700"
                                    >
                                        الأعضاء: {{ t.assignees.join('، ') }}
                                    </span>
                                    <span
                                        v-else-if="t.assignee"
                                        class="rounded-md bg-brand-50 px-2 py-0.5 text-brand-700"
                                    >
                                        المسؤول: {{ t.assignee }}
                                    </span>
                                </div>
                            </li>
                            <li v-if="!block.tasks.length" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                لا توجد مهام حالياً لهذا الفريق.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">تغيّر حالات المهام</h2>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showTaskStatusHistory = !showTaskStatusHistory"
                    >
                        {{ showTaskStatusHistory ? 'تقليص' : 'توسيع' }}
                    </button>
                </div>
                <ul v-if="showTaskStatusHistory" class="mt-3 divide-y divide-gray-100 text-sm">
                    <li
                        v-for="entry in task_status_history"
                        :key="entry.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-2"
                    >
                        <div class="text-gray-700">
                            <span class="font-medium text-gray-900">{{ entry.task_title }}</span>
                            <span class="mx-1 text-gray-400">{{ localizeColumnName(entry.from) || '—' }}</span>
                            <span class="text-gray-400">←</span>
                            <span class="mx-1">{{ localizeColumnName(entry.to) || '—' }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ entry.by || '—' }} · {{ formatDt(entry.at) }}
                        </div>
                    </li>
                    <li v-if="!task_status_history?.length" class="py-4 text-gray-500">
                        لا يوجد تغيّر حالات مسجل بعد.
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-white/20 bg-white/70 p-6 shadow-xl shadow-slate-900/10 ring-1 ring-white/35 backdrop-blur-xl">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">الاجتماعات</h2>
                    <Link
                        :href="route('meetings.index', { client_id: client.id })"
                        class="text-sm text-brand-600 hover:underline"
                    >
                        فتح القائمة الكاملة
                    </Link>
                </div>
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    <li
                        v-for="m in meetings"
                        :key="m.id"
                        class="flex flex-wrap justify-between gap-2 py-2"
                    >
                        <span class="font-medium text-gray-800">{{
                            m.title || 'اجتماع'
                        }}</span>
                        <span class="text-gray-600">{{ formatDt(m.start_at) }}</span>
                        <span class="text-gray-500">{{ m.invitee_name }}</span>
                        <span class="text-gray-400">{{ m.host }}</span>
                        <span class="text-xs text-gray-400">{{ sourceLabels[m.source] || m.source }}</span>
                    </li>
                    <li v-if="!meetings.length" class="py-4 text-gray-500">
                        لا توجد اجتماعات مرتبطة.
                    </li>
                </ul>
            </div>

            <div v-if="canDeleteRecords" class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-red-50"
                    @click="deleteClient"
                >
                    حذف العميل
                </button>
            </div>
        </div>

        <div
            v-if="showSubscriptionModal"
            class="fixed inset-0 z-50 flex flex-col justify-end bg-black/40 p-0 sm:items-center sm:justify-center sm:bg-black/50 sm:p-4"
            @click.self="showSubscriptionModal = false"
        >
            <div
                class="glass-modal w-full max-w-lg max-h-[min(92dvh,90vh)] overflow-y-auto overscroll-contain !rounded-t-3xl !rounded-b-none p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:!rounded-2xl sm:p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900">إدارة اشتراك العميل</h3>
                    <button
                        type="button"
                        class="rounded-xl px-2 py-1 text-sm text-gray-500 hover:bg-gray-100"
                        @click="showSubscriptionModal = false"
                    >
                        إغلاق
                    </button>
                </div>

                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel for="subscription_started_at" value="تاريخ بدء الاشتراك" />
                        <TextInput
                            id="subscription_started_at"
                            v-model="subscriptionDatesForm.subscription_started_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="subscriptionDatesForm.errors.subscription_started_at" />
                    </div>
                    <div>
                        <InputLabel for="subscription_ends_at" value="تاريخ انتهاء الاشتراك" />
                        <TextInput
                            id="subscription_ends_at"
                            v-model="subscriptionDatesForm.subscription_ends_at"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="subscriptionDatesForm.errors.subscription_ends_at || subscriptionDatesForm.errors.subscription" />
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <InputError class="w-full text-sm" :message="subscriptionForm.errors.subscription || subscriptionDatesForm.errors.subscription" />
                    <button
                        type="button"
                        class="rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        :disabled="subscriptionForm.processing"
                        @click="activateSubscription"
                    >
                        {{ subscriptionForm.processing ? 'جارٍ التفعيل...' : 'تفعيل 30 يوم من الآن' }}
                    </button>
                    <PrimaryButton
                        type="button"
                        :disabled="subscriptionDatesForm.processing"
                        @click="saveSubscriptionDates"
                    >
                        {{ subscriptionDatesForm.processing ? 'جارٍ الحفظ...' : 'حفظ التواريخ' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <div
            v-if="showUploadAttachmentModal"
            class="fixed inset-0 z-50 flex flex-col justify-end bg-black/40 p-0 sm:items-center sm:justify-center sm:bg-black/50 sm:p-4"
            @click.self="showUploadAttachmentModal = false"
        >
            <div
                class="glass-modal w-full max-w-md max-h-[min(92dvh,90vh)] overflow-y-auto overscroll-contain !rounded-t-3xl !rounded-b-none p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:!rounded-2xl sm:p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900">رفع ملف مرجعي</h3>
                    <button
                        type="button"
                        class="rounded-xl px-2 py-1 text-sm text-gray-500 hover:bg-gray-100"
                        @click="showUploadAttachmentModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <div class="mt-4 space-y-3">
                    <input
                        id="client_attachment_file"
                        ref="attachmentInputRef"
                        type="file"
                        class="block w-full text-xs text-gray-600 file:me-2 file:rounded-xl file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-medium"
                        @change="onAttachmentChange"
                    />
                    <p class="text-[11px] text-gray-500">الحد الأقصى: 10MB</p>
                    <div v-if="attachmentUploading" class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div
                            class="h-full rounded-full bg-brand-600 transition-all"
                            :style="{ width: `${attachmentProgress}%` }"
                        />
                    </div>
                    <InputError class="mt-1" :message="attachmentError" />
                    <PrimaryButton
                        type="button"
                        :disabled="attachmentUploading || !attachmentFile"
                        @click="uploadClientAttachment"
                    >
                        {{ attachmentUploading ? 'جاري الرفع...' : 'رفع المرفق' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <div
            v-if="showReferenceLinkModal"
            class="fixed inset-0 z-50 flex flex-col justify-end bg-black/40 p-0 sm:items-center sm:justify-center sm:bg-black/50 sm:p-4"
            @click.self="showReferenceLinkModal = false"
        >
            <div
                class="glass-modal w-full max-w-md max-h-[min(92dvh,90vh)] overflow-y-auto overscroll-contain !rounded-t-3xl !rounded-b-none p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:!rounded-2xl sm:p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900">إضافة رابط مرجعي</h3>
                    <button
                        type="button"
                        class="rounded-xl px-2 py-1 text-sm text-gray-500 hover:bg-gray-100"
                        @click="showReferenceLinkModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <InputLabel for="reference_link_title" value="عنوان الرابط (اختياري)" />
                        <TextInput
                            id="reference_link_title"
                            v-model="referenceLinkForm.title"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="مثال: صفحة المنتج"
                        />
                    </div>
                    <div>
                        <InputLabel for="reference_link_url" value="رابط مرجعي" />
                        <TextInput
                            id="reference_link_url"
                            v-model="referenceLinkForm.url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://example.com"
                            dir="ltr"
                        />
                    </div>
                    <InputError class="mt-1" :message="referenceLinkForm.errors.url || referenceLinkForm.errors.title" />
                    <PrimaryButton
                        type="button"
                        :disabled="referenceLinkForm.processing || !referenceLinkForm.url"
                        @click="saveReferenceLink"
                    >
                        {{ referenceLinkForm.processing ? 'جاري الحفظ...' : 'إضافة رابط' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>

        <div
            v-if="showProductModal"
            class="fixed inset-0 z-50 flex flex-col justify-end bg-black/40 p-0 sm:items-center sm:justify-center sm:bg-black/50 sm:p-4"
            @click.self="showProductModal = false"
        >
            <div
                class="glass-modal w-full max-w-lg max-h-[min(92dvh,90vh)] overflow-y-auto overscroll-contain !rounded-t-3xl !rounded-b-none p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:!rounded-2xl sm:p-5"
            >
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-lg font-semibold text-gray-900">إضافة منتج للعميل</h3>
                    <button
                        type="button"
                        class="rounded-xl px-2 py-1 text-sm text-gray-500 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-gray-100"
                        @click="showProductModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <form class="mt-4 space-y-3" @submit.prevent="saveProduct">
                    <div>
                        <InputLabel for="product_name_modal" value="اسم المنتج" />
                        <TextInput id="product_name_modal" v-model="productForm.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="productForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="product_price_modal" value="سعر المنتج" />
                        <TextInput id="product_price_modal" v-model="productForm.unit_price" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="productForm.errors.unit_price" />
                    </div>
                    <div>
                        <InputLabel for="product_stock_modal" value="المخزون (اختياري)" />
                        <TextInput id="product_stock_modal" v-model="productForm.stock_quantity" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="productForm.errors.stock_quantity" />
                    </div>
                    <div>
                        <InputLabel for="product_details_modal" value="تفاصيل المنتج" />
                        <textarea id="product_details_modal" v-model="productForm.details" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <InputError class="mt-1" :message="productForm.errors.details" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-700 transition-all duration-200 ease-out hover:scale-[1.02] hover:bg-gray-50"
                            @click="showProductModal = false"
                        >
                            إلغاء
                        </button>
                        <PrimaryButton :disabled="productForm.processing">حفظ المنتج</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.client-page :deep(.bg-slate-50),
.client-page :deep(.bg-slate-50\/80),
.client-page :deep(.bg-white\/80),
.client-page :deep(.bg-white\/75),
.client-page :deep(.bg-gray-50) {
    color: #111111 !important;
}

.client-page :deep(.bg-slate-50 a),
.client-page :deep(.bg-slate-50\/80 a),
.client-page :deep(.bg-white\/80 a),
.client-page :deep(.bg-white\/75 a),
.client-page :deep(.bg-gray-50 a) {
    color: #111111 !important;
}

.client-page :deep(input),
.client-page :deep(select),
.client-page :deep(textarea) {
    color: #111111;
}

.glass-modal {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.2);
    animation: modal-in 220ms ease-out;
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
