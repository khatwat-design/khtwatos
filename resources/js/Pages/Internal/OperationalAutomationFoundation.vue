<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    catalog: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="الأتمتة — مركز التشغيل" />

    <AuthenticatedLayout>
        <template #title>الأتمتة</template>
        <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <header class="rounded-2xl border border-amber-200/80 bg-amber-50/90 p-4 text-sm text-amber-950">
                <h1 class="text-lg font-bold">أساس الأتمتة التشغيلية (داخلي)</h1>
                <p class="mt-2 leading-relaxed">
                    صفحة تحضيرية لمركز تحكم الأتمتة لاحقاً — غير مكتملة ولا تظهر في القائمة العامة.
                    الهدف: تجميع مصادر التذكير والأتمتة دون تغيير سلوك الإنتاج الحالي.
                </p>
            </header>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">فهرس الأتمتة المكتشفة</h2>
                <ul class="mt-3 divide-y divide-slate-100 text-xs text-slate-800">
                    <li v-for="row in catalog" :key="row.id" class="py-3">
                        <p class="font-semibold text-slate-900">{{ row.name_ar }}</p>
                        <p v-if="row.domain" class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500">
                            {{ row.domain }}
                        </p>
                        <p class="mt-0.5 font-mono text-[11px] text-slate-600">{{ row.trigger }}</p>
                        <ul v-if="row.code_paths?.length" class="mt-1 list-inside list-disc text-[11px] text-slate-500">
                            <li v-for="p in row.code_paths" :key="p">{{ p }}</li>
                        </ul>
                        <p v-if="row.notes" class="mt-1 text-[11px] leading-relaxed text-slate-600">
                            {{ row.notes }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
