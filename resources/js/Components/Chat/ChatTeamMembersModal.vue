<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    team: { type: Object, default: null },
    teamDisplayName: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    initialMemberIds: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'save']);

const selectedIds = ref([]);
const loadingMembers = ref(false);
const loadError = ref(false);

function normalizeIds(ids) {
    return [...new Set((ids || []).map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0))];
}

async function loadMembersForOpen() {
    if (!props.team?.id) {
        selectedIds.value = normalizeIds(props.initialMemberIds);
        return;
    }

    loadingMembers.value = true;
    loadError.value = false;
    try {
        const { data } = await window.axios.get(route('chat.teams.members.show', props.team.id), {
            headers: { Accept: 'application/json' },
        });
        selectedIds.value = normalizeIds((data.members || []).map((m) => m.id));
    } catch {
        loadError.value = true;
        selectedIds.value = normalizeIds(props.initialMemberIds);
    } finally {
        loadingMembers.value = false;
    }
}

watch(
    () => props.show,
    (open) => {
        if (open) {
            loadMembersForOpen();
        }
    },
);

function toggleUser(id) {
    const numId = Number(id);
    if (selectedIds.value.includes(numId)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== numId);
        return;
    }
    selectedIds.value = [...selectedIds.value, numId];
}

function submit() {
    if (!selectedIds.value.length) {
        return;
    }
    emit('save', [...selectedIds.value]);
}

const roomTitle = () => props.teamDisplayName || props.team?.name || 'القسم';
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-4 sm:p-5" dir="rtl">
            <h2 class="text-base font-bold text-slate-900">أعضاء غرفة {{ roomTitle() }}</h2>
            <p class="mt-1 text-xs text-slate-600">حدّد الموظفين المسموح لهم بالدخول والمراسلة في هذه الغرفة.</p>
            <p v-if="loadError" class="mt-2 text-xs text-amber-700">تعذّر تحميل القائمة الحالية — يُعرض آخر ما وُجد محليًا.</p>

            <div
                v-if="loadingMembers"
                class="mt-3 rounded-xl border border-slate-200 px-3 py-6 text-center text-sm text-slate-500"
            >
                جاري تحميل الأعضاء...
            </div>

            <div
                v-else
                class="mt-3 max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2"
            >
                <label
                    v-for="u in users"
                    :key="`team-member-${u.id}`"
                    class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-600"
                        :checked="selectedIds.includes(Number(u.id))"
                        :disabled="loadingMembers || processing"
                        @change="toggleUser(u.id)"
                    />
                    <span class="text-sm text-slate-800">{{ u.name }}</span>
                </label>
            </div>

            <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    :disabled="processing"
                    @click="emit('close')"
                >
                    إلغاء
                </button>
                <PrimaryButton
                    type="button"
                    :disabled="processing || loadingMembers || !selectedIds.length"
                    @click="submit"
                >
                    {{ processing ? 'جاري الحفظ...' : 'حفظ الأعضاء' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
