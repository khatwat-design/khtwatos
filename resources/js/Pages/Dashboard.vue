<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="لوحة التحكم" />

    <AuthenticatedLayout>
        <template #title>لوحة التحكم</template>

        <div class="mx-auto max-w-7xl">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p>تم تسجيل الدخول.</p>
                    <Link
                        :href="route('tasks.index')"
                        class="mt-4 inline-block text-brand-600 hover:underline"
                    >
                        الانتقال إلى المهام
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
