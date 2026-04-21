<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { getSessionSummaries } from '@/data/salesTrainingCurriculum';

const sessions = getSessionSummaries();
</script>

<template>
    <Head title="الأكاديمية" />

    <AuthenticatedLayout>
        <template #title>الأكاديمية</template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-2xl border border-orange-500/30 bg-black p-6 text-white shadow-xl">
                <h1 class="text-2xl font-bold">أكاديمية خارج المخزون</h1>
                <p class="mt-2 text-sm text-gray-300">
                    مسارات التدريب الداخلية للوكالة، مع محتوى المحاضرات والتمارين.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <Link
                    :href="route('academy.sales-training')"
                    class="group rounded-2xl border border-orange-500/30 bg-gradient-to-br from-black to-neutral-900 p-6 text-white shadow-lg transition hover:border-orange-400 hover:shadow-orange-900/20"
                >
                    <p class="text-xs text-orange-300">المسار 01</p>
                    <h2 class="mt-1 text-xl font-bold">تدريب المبيعات</h2>
                    <p class="mt-2 text-sm text-gray-300">
                        نفس محتوى برنامج Khtwat Hub: الجلسات، المحاضرات، والتمارين.
                    </p>
                    <p class="mt-4 text-sm font-semibold text-orange-300">
                        {{ sessions.length }} جلسات تدريبية
                    </p>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
