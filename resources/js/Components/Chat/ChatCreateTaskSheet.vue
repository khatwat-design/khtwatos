<script setup>
import AppModalShell from '@/Components/AppModalShell.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    error: { type: String, default: '' },
    teams: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    mentionableUsers: { type: Array, default: () => [] },
    defaultTeamSlug: { type: String, default: '' },
    requireTeamPick: { type: Boolean, default: false },
    initialTitle: { type: String, default: '' },
    initialDescription: { type: String, default: '' },
    initialClientId: { type: Number, default: null },
    initialAssigneeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'submit']);

const title = ref('');
const description = ref('');
const teamSlug = ref('');
const clientId = ref('');
const assigneeIds = ref([]);

const clientOptions = computed(() => props.clients || []);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }
        title.value = props.initialTitle || '';
        description.value = props.initialDescription || '';
        teamSlug.value = props.defaultTeamSlug || props.teams?.[0]?.slug || '';
        clientId.value = props.initialClientId ? String(props.initialClientId) : '';
        assigneeIds.value = [...(props.initialAssigneeIds || [])];
    },
);

function toggleAssignee(id) {
    const n = Number(id);
    if (!n) {
        return;
    }
    if (assigneeIds.value.includes(n)) {
        assigneeIds.value = assigneeIds.value.filter((x) => x !== n);
    } else {
        assigneeIds.value = [...assigneeIds.value, n];
    }
}

function onSubmit() {
    emit('submit', {
        title: String(title.value || '').trim(),
        description: String(description.value || '').trim(),
        team_slug: teamSlug.value,
        client_id: clientId.value ? Number(clientId.value) : null,
        assignee_ids: assigneeIds.value,
    });
}
</script>

<template>
    <AppModalShell
        :open="open"
        size="lg"
        :z-index="115"
        aria-label="إنشاء مهمة من الدردشة"
        @close="emit('close')"
    >
        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="onSubmit">
            <header class="app-modal-header">
                <div>
                    <h2 class="app-modal-title">إنشاء مهمة</h2>
                    <p class="app-modal-subtitle">تُضاف إلى لوحة الفريق وتُعلَن في هذه المحادثة</p>
                </div>
                <button type="button" class="app-modal-close" aria-label="إغلاق" @click="emit('close')">
                    ✕
                </button>
            </header>

            <div class="app-modal-body space-y-3">
                <div v-if="requireTeamPick || teams.length > 1">
                    <label class="mb-1 block text-xs font-semibold text-slate-700">لوحة المهام (الفريق)</label>
                    <select
                        v-model="teamSlug"
                        required
                        class="block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                    >
                        <option value="" disabled>اختر الفريق</option>
                        <option v-for="t in teams" :key="t.id" :value="t.slug">{{ t.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">عنوان المهمة</label>
                    <input
                        v-model="title"
                        type="text"
                        required
                        maxlength="255"
                        class="block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                        placeholder="مثال: متابعة عرض السعر"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">العميل (اختياري)</label>
                    <select v-model="clientId" class="block w-full rounded-xl border-slate-200 text-sm shadow-sm">
                        <option value="">بدون عميل</option>
                        <option v-for="c in clientOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>

                <div v-if="mentionableUsers.length">
                    <p class="mb-1.5 text-xs font-semibold text-slate-700">المكلفون</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="u in mentionableUsers"
                            :key="u.id"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-[11px] font-semibold transition"
                            :class="
                                assigneeIds.includes(u.id)
                                    ? 'border-brand-500 bg-brand-50 text-brand-800'
                                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            "
                            @click="toggleAssignee(u.id)"
                        >
                            {{ u.name }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">ملاحظات (اختياري)</label>
                    <textarea
                        v-model="description"
                        rows="3"
                        maxlength="5000"
                        class="block w-full rounded-xl border-slate-200 text-sm shadow-sm"
                        placeholder="تفاصيل إضافية من المحادثة"
                    />
                </div>

                <InputError :message="error" />
            </div>

            <footer class="app-modal-footer">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    :disabled="processing"
                    @click="emit('close')"
                >
                    إلغاء
                </button>
                <PrimaryButton type="submit" class="w-full justify-center sm:w-auto sm:min-w-[9rem]" :disabled="processing">
                    {{ processing ? 'جاري الإنشاء…' : 'إنشاء المهمة' }}
                </PrimaryButton>
            </footer>
        </form>
    </AppModalShell>
</template>
