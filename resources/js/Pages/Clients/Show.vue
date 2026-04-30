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
const maxAttachmentSizeBytes = 10 * 1024 * 1024;
const clientLogoPreview = ref(props.client.logo_url || '');

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
        },
    });
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
                        <InputLabel for="client_attachment_file" value="رفع ملف مرجعي" />
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

                        <div class="my-1 h-px bg-slate-200" />

                        <InputLabel for="reference_link_title" value="عنوان الرابط (اختياري)" />
                        <TextInput
                            id="reference_link_title"
                            v-model="referenceLinkForm.title"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="مثال: صفحة المنتج"
                        />
                        <InputLabel for="reference_link_url" value="رابط مرجعي" />
                        <TextInput
                            id="reference_link_url"
                            v-model="referenceLinkForm.url"
                            type="url"
                            class="mt-1 block w-full"
                            placeholder="https://example.com"
                            dir="ltr"
                        />
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
                    <h2 class="text-lg font-semibold text-gray-900">البيانات</h2>
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
                                placeholder="مثال: 123456789 أو رابط الصفحة"
                            />
                        </div>
                        <div>
                            <InputLabel for="instagram_page" value="صفحة الانستغرام" />
                            <TextInput
                                id="instagram_page"
                                v-model="clientForm.instagram_page"
                                class="mt-1 block w-full"
                                placeholder="مثال: 1784... أو @username"
                            />
                        </div>
                        <div>
                            <InputLabel for="ad_account_id" value="رقم الحساب الإعلاني المرتبط" />
                            <TextInput
                                id="ad_account_id"
                                :model-value="props.meta_profile?.ad_account_id ? (String(props.meta_profile.ad_account_id).startsWith('act_') ? props.meta_profile.ad_account_id : `act_${props.meta_profile.ad_account_id}`) : 'غير مرتبط'"
                                class="mt-1 block w-full bg-gray-50"
                                readonly
                            />
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
            v-if="showProductModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showProductModal = false"
        >
            <div class="glass-modal w-full max-w-lg p-5">
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
