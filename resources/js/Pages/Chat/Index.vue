<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    teams: Array,
    selectedTeam: Object,
    messages: Array,
});

const form = useForm({
    team_id: props.selectedTeam?.id ?? null,
    body: '',
});

function submitMessage() {
    if (!form.team_id || !form.body.trim()) {
        return;
    }

    form.post(route('chat.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.body = '';
        },
    });
}

function teamHref(slug) {
    return route('chat.index', { team: slug });
}

function formatDt(iso) {
    return new Date(iso).toLocaleString('ar-SA', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="دردشة الفريق" />

    <AuthenticatedLayout>
        <template #title>دردشة الفريق</template>

        <div class="mx-auto max-w-5xl space-y-4">
            <div class="flex gap-2 overflow-x-auto whitespace-nowrap rounded-lg bg-white p-3 shadow ring-1 ring-gray-200">
                <Link
                    v-for="team in teams"
                    :key="team.id"
                    :href="teamHref(team.slug)"
                    :class="[
                        'rounded-full px-3 py-1 text-sm font-medium',
                        selectedTeam?.id === team.id
                            ? 'bg-brand-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                    ]"
                >
                    {{ team.name }}
                </Link>
            </div>

            <div class="rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
                <div class="mb-3 text-sm text-gray-600">
                    محادثة فريق: <span class="font-semibold text-gray-900">{{ selectedTeam?.name }}</span>
                </div>

                <div class="max-h-[55vh] space-y-2 overflow-y-auto rounded-md bg-gray-50 p-3">
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="rounded-md bg-white p-2 text-sm shadow-sm ring-1 ring-gray-200"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-gray-900">{{ msg.user?.name || 'عضو' }}</span>
                            <span class="text-xs text-gray-500">{{ formatDt(msg.created_at) }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ msg.body }}</p>
                    </div>
                    <p v-if="!messages?.length" class="text-center text-sm text-gray-500">
                        لا توجد رسائل بعد. ابدأ الدردشة مع فريقك.
                    </p>
                </div>

                <form class="mt-3 space-y-2" @submit.prevent="submitMessage">
                    <textarea
                        v-model="form.body"
                        rows="3"
                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm"
                        placeholder="اكتب رسالة للفريق..."
                    />
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">إرسال</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

