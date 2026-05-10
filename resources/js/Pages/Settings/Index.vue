<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const initialFields = {};
for (const item of props.items) {
    if (!item.locked) {
        initialFields[item.key] = Boolean(item.value);
    }
}

const form = useForm(initialFields);

function submit() {
    form.patch(route('settings.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="إعدادات النظام" />

    <AuthenticatedLayout>
        <template #title>إعدادات النظام</template>

        <div class="mx-auto max-w-3xl space-y-6 text-black">
            <p class="text-sm text-slate-600">
                هذه الصفحة لمدير النظام فقط. التعديلات تُخزَّن في قاعدة البيانات وتُطبَّق فوراً مع جدولة Laravel والواجهة؛ الإعدادات المعطّلة من ملف البيئة (.env)
                لا يمكن تفعيلها من هنا حتى تُحدَّث البيئة يدوياً.
            </p>

            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    v-for="item in items"
                    :key="item.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold text-slate-900">{{ item.label }}</h3>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ item.help }}</p>
                            <p v-if="item.locked && item.lock_hint" class="mt-2 text-xs font-medium text-amber-800">
                                {{ item.lock_hint }}
                            </p>
                            <p v-if="!item.locked" class="mt-2 text-[11px] text-slate-500">
                                الحالة الفعلية حالياً:
                                <span :class="item.effective ? 'font-semibold text-emerald-700' : 'font-semibold text-slate-500'">
                                    {{ item.effective ? 'مفعّل' : 'معطّل' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <Checkbox
                                v-if="!item.locked"
                                :checked="form[item.key]"
                                :disabled="form.processing"
                                @update:checked="(v) => (form[item.key] = v)"
                            />
                            <Checkbox v-else :checked="item.effective" disabled />
                            <span class="text-[10px] uppercase tracking-wide text-slate-400">{{
                                item.locked ? 'مقفل بالبيئة' : ''
                            }}</span>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors[item.key]" />
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <PrimaryButton type="submit" :disabled="form.processing">حفظ الإعدادات</PrimaryButton>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">تم التحديث.</span>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
