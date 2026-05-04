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
        <div class="mx-auto w-full min-w-0 max-w-5xl space-y-4 sm:space-y-6">
            <div
                class="flex flex-col gap-3 rounded-2xl border border-slate-100/90 bg-white/90 p-4 shadow-md ring-1 ring-slate-100/50 sm:flex-row sm:items-center sm:justify-between sm:p-5"
            >
                <div class="min-w-0">
                    <h2 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">إدارة المنتجات</h2>
                    <p class="mt-1 text-sm text-slate-600">تحديث الأسعار والمخزون يظهر مباشرة في تقاريرك.</p>
                </div>
                <PrimaryButton
                    type="button"
                    class="w-full shrink-0 justify-center rounded-xl px-5 py-3 text-sm font-bold shadow-sm sm:w-auto sm:py-2.5"
                    @click="showAddModal = true"
                >
                    إضافة منتج
                </PrimaryButton>
            </div>

            <!-- موبايل: بطاقات -->
            <div class="space-y-3 md:hidden">
                <article
                    v-for="product in products"
                    :key="`p-card-${product.id}`"
                    class="rounded-2xl border border-slate-100/90 bg-white/95 p-4 shadow-sm ring-1 ring-slate-100/40"
                >
                    <div class="space-y-3">
                        <div>
                            <InputLabel :for="`m-name-${product.id}`" value="الاسم" class="text-xs text-slate-500" />
                            <TextInput :id="`m-name-${product.id}`" v-model="product.name" class="mt-1 block w-full rounded-xl border-slate-200 text-sm" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel :for="`m-price-${product.id}`" value="السعر" class="text-xs text-slate-500" />
                                <TextInput
                                    :id="`m-price-${product.id}`"
                                    v-model="product.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm"
                                />
                            </div>
                            <div>
                                <InputLabel :for="`m-qty-${product.id}`" value="الكمية" class="text-xs text-slate-500" />
                                <TextInput
                                    :id="`m-qty-${product.id}`"
                                    v-model="product.stock_quantity"
                                    type="number"
                                    min="0"
                                    class="mt-1 block w-full rounded-xl border-slate-200 text-sm"
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel :for="`m-det-${product.id}`" value="التفاصيل" class="text-xs text-slate-500" />
                            <textarea
                                :id="`m-det-${product.id}`"
                                v-model="product.details"
                                rows="2"
                                class="mt-1 block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="product.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                            منتج نشط
                        </label>
                        <div class="flex gap-2 pt-1">
                            <button
                                type="button"
                                class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-transparent bg-emerald-600 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800"
                                @click="saveProduct(product)"
                            >
                                حفظ
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-sm font-semibold text-rose-800 hover:bg-rose-100"
                                @click="removeProduct(product)"
                            >
                                حذف
                            </button>
                        </div>
                    </div>
                </article>
                <p v-if="!products?.length" class="rounded-2xl border border-dashed border-slate-200 bg-white/60 py-12 text-center text-sm text-slate-500">
                    لا توجد منتجات بعد.
                </p>
            </div>

            <!-- سطح المكتب: جدول -->
            <div class="hidden overflow-hidden rounded-2xl border border-slate-100/90 bg-white/95 shadow-md ring-1 ring-slate-100/50 md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/90 text-right text-xs font-semibold text-slate-500">
                                <th class="px-4 py-3">الاسم</th>
                                <th class="px-4 py-3">السعر</th>
                                <th class="px-4 py-3">الكمية</th>
                                <th class="px-4 py-3">التفاصيل</th>
                                <th class="px-4 py-3">نشط</th>
                                <th class="px-4 py-3">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="product in products" :key="product.id" class="border-b border-slate-100 align-top transition hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <TextInput v-model="product.name" class="block min-w-[8rem] rounded-xl border-slate-200 text-sm" />
                                </td>
                                <td class="px-4 py-3">
                                    <TextInput v-model="product.unit_price" type="number" min="0" step="0.01" class="block w-28 rounded-xl border-slate-200 text-sm" />
                                </td>
                                <td class="px-4 py-3">
                                    <TextInput v-model="product.stock_quantity" type="number" min="0" class="block w-24 rounded-xl border-slate-200 text-sm" />
                                </td>
                                <td class="px-4 py-3">
                                    <textarea v-model="product.details" rows="2" class="block w-56 rounded-xl border-slate-200 text-sm shadow-sm" />
                                </td>
                                <td class="px-4 py-3">
                                    <input v-model="product.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-xl border border-transparent bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 active:bg-emerald-800"
                                            @click="saveProduct(product)"
                                        >
                                            حفظ
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-100"
                                            @click="removeProduct(product)"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!products?.length">
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">لا توجد منتجات بعد.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            v-if="showAddModal"
            class="fixed inset-0 z-50 flex flex-col justify-end bg-black/50 p-0 sm:items-center sm:justify-center sm:p-4"
            @click.self="showAddModal = false"
        >
            <div
                class="max-h-[min(92dvh,40rem)] w-full overflow-y-auto overscroll-contain rounded-t-3xl border border-slate-200/90 bg-white p-5 shadow-2xl sm:mx-auto sm:max-h-[90vh] sm:max-w-md sm:rounded-3xl sm:p-6"
            >
                <div class="sticky top-0 z-10 -mx-5 mb-4 flex items-center justify-between border-b border-slate-100 bg-white px-5 pb-3 sm:-mx-6 sm:mb-5 sm:px-6 sm:pb-4">
                    <h3 class="text-lg font-bold text-slate-900">إضافة منتج</h3>
                    <button
                        type="button"
                        class="min-h-10 rounded-xl px-3 text-sm font-medium text-slate-500 hover:bg-slate-100"
                        @click="showAddModal = false"
                    >
                        إغلاق
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="submitAdd">
                    <div>
                        <InputLabel for="add_name" value="اسم المنتج" />
                        <TextInput id="add_name" v-model="addForm.name" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" required />
                        <InputError class="mt-1" :message="addForm.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="add_unit_price" value="السعر" />
                        <TextInput id="add_unit_price" v-model="addForm.unit_price" type="number" min="0" step="0.01" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" required />
                        <InputError class="mt-1" :message="addForm.errors.unit_price" />
                    </div>
                    <div>
                        <InputLabel for="add_stock_quantity" value="الكمية" />
                        <TextInput id="add_stock_quantity" v-model="addForm.stock_quantity" type="number" min="0" class="mt-2 block min-h-11 w-full rounded-xl border-slate-200" />
                        <InputError class="mt-1" :message="addForm.errors.stock_quantity" />
                    </div>
                    <div>
                        <InputLabel for="add_details" value="تفاصيل المنتج" />
                        <textarea id="add_details" v-model="addForm.details" rows="3" class="mt-2 block w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                        <InputError class="mt-1" :message="addForm.errors.details" />
                    </div>
                    <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto"
                            @click="showAddModal = false"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-transparent bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            :disabled="addForm.processing"
                        >
                            حفظ المنتج
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PortalLayout>
</template>
