<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    client: Object,
    products: Array,
});

const showAddModal = ref(false);
const addForm = useForm({
    name: '',
    unit_price: '',
    stock_quantity: '',
    details: '',
});

function submitAdd() {
    addForm.post(route('portal.products.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddModal.value = false;
        },
    });
}

function saveProduct(product) {
    router.patch(
        route('portal.products.update', product.id),
        {
            name: product.name,
            unit_price: product.unit_price,
            stock_quantity: product.stock_quantity,
            details: product.details,
            is_active: Boolean(product.is_active),
        },
        { preserveScroll: true },
    );
}

function removeProduct(product) {
    if (!confirm('حذف هذا المنتج؟')) return;
    router.delete(route('portal.products.destroy', product.id), { preserveScroll: true });
}
</script>

<template>
    <PortalLayout title="المنتجات" :client="client">
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="ui-card p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">إدارة المنتجات</h2>
                    <PrimaryButton type="button" @click="showAddModal = true">إضافة منتج</PrimaryButton>
                </div>
            </div>

            <div class="ui-card p-5">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-right text-xs text-gray-500">
                                <th class="px-2 py-2">الاسم</th>
                                <th class="px-2 py-2">السعر</th>
                                <th class="px-2 py-2">الكمية</th>
                                <th class="px-2 py-2">التفاصيل</th>
                                <th class="px-2 py-2">نشط</th>
                                <th class="px-2 py-2">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id" class="border-b border-gray-100 align-top">
                                <td class="px-2 py-2"><TextInput v-model="product.name" class="block w-44" /></td>
                                <td class="px-2 py-2"><TextInput v-model="product.unit_price" type="number" min="0" step="0.01" class="block w-32" /></td>
                                <td class="px-2 py-2"><TextInput v-model="product.stock_quantity" type="number" min="0" class="block w-28" /></td>
                                <td class="px-2 py-2">
                                    <textarea v-model="product.details" rows="2" class="block w-56 rounded-md border-gray-300 text-sm shadow-sm" />
                                </td>
                                <td class="px-2 py-2">
                                    <input v-model="product.is_active" type="checkbox" class="rounded border-gray-300" />
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white" @click="saveProduct(product)">
                                            حفظ
                                        </button>
                                        <button type="button" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700" @click="removeProduct(product)">
                                            حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!products?.length">
                                <td colspan="6" class="px-2 py-4 text-center text-gray-500">لا توجد منتجات بعد.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="showAddModal = false"
        >
            <div class="glass-modal portal-light-modal w-full max-w-lg p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">إضافة منتج</h3>
                    <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="showAddModal = false">إغلاق</button>
                </div>
                <form class="mt-4 space-y-3" @submit.prevent="submitAdd">
                    <div>
                        <InputLabel for="name" value="اسم المنتج" />
                        <TextInput id="name" v-model="addForm.name" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="addForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="unit_price" value="السعر" />
                        <TextInput id="unit_price" v-model="addForm.unit_price" type="number" min="0" step="0.01" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="addForm.errors.unit_price" />
                    </div>
                    <div>
                        <InputLabel for="stock_quantity" value="الكمية" />
                        <TextInput id="stock_quantity" v-model="addForm.stock_quantity" type="number" min="0" class="mt-1 block w-full" />
                        <InputError class="mt-1" :message="addForm.errors.stock_quantity" />
                    </div>
                    <div>
                        <InputLabel for="details" value="تفاصيل المنتج" />
                        <textarea id="details" v-model="addForm.details" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        <InputError class="mt-1" :message="addForm.errors.details" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm" @click="showAddModal = false">إلغاء</button>
                        <PrimaryButton :disabled="addForm.processing">حفظ المنتج</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
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

.portal-light-modal,
.portal-light-modal :deep(*) {
    color: #111111;
}

.portal-light-modal :deep(input),
.portal-light-modal :deep(select),
.portal-light-modal :deep(textarea) {
    color: #111111 !important;
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
