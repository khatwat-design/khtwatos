<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    assignees: { type: Array, default: () => [] },
    assignee_stats: { type: Array, default: () => [] },
    unassigned_count: { type: Number, default: 0 },
    total_leads: { type: Number, default: 0 },
    sales_team_members: { type: Array, default: () => [] },
});

const localAssignees = ref(
    props.assignee_stats.map(a => ({
        user_id: a.id,
        name: a.name,
        weight: a.weight,
        leads_today: a.leads_today,
        upcoming_calls: a.upcoming_calls,
        total_leads: a.total_leads,
    }))
);

const saving = ref(false);
const redistributing = ref(false);
const redistributeResult = ref(null);
const showAddModal = ref(false);
const selectedUserId = ref(null);
const newWeight = ref(50);

const availableMembers = computed(() => {
    const assignedIds = localAssignees.value.map(a => a.user_id);
    return props.sales_team_members.filter(m => !assignedIds.includes(m.id));
});

const totalWeight = computed(() => localAssignees.value.reduce((s, a) => s + a.weight, 0));

const distributionBars = computed(() => {
    const total = totalWeight.value || 1;
    return localAssignees.value.map(a => ({
        ...a,
        percentage: Math.round((a.weight / total) * 100),
    }));
});

function addAssignee() {
    if (!selectedUserId.value) return;
    const member = props.sales_team_members.find(m => m.id === selectedUserId.value);
    if (!member) return;

    localAssignees.value.push({
        user_id: member.id,
        name: member.name,
        weight: newWeight.value,
        leads_today: 0,
        upcoming_calls: 0,
        total_leads: 0,
    });

    selectedUserId.value = null;
    newWeight.value = 50;
    showAddModal.value = false;
}

function removeAssignee(userId) {
    localAssignees.value = localAssignees.value.filter(a => a.user_id !== userId);
}

function adjustWeight(userId, delta) {
    const a = localAssignees.value.find(x => x.user_id === userId);
    if (a) {
        a.weight = Math.max(1, Math.min(100, a.weight + delta));
    }
}

async function saveWeights() {
    saving.value = true;
    try {
        await axios.put(route('sales.lead-assignment.update'), {
            assignees: localAssignees.value.map(a => ({
                user_id: a.user_id,
                weight: a.weight,
            })),
        });
        router.reload({ only: ['assignee_stats'] });
    } catch (e) {
        alert('حدث خطأ أثناء الحفظ');
    } finally {
        saving.value = false;
    }
}

async function redistribute() {
    redistributing.value = true;
    redistributeResult.value = null;
    try {
        const { data } = await axios.post(route('sales.lead-assignment.redistribute'));
        redistributeResult.value = data;
        router.reload({ only: ['assignee_stats', 'unassigned_count'] });
    } catch (e) {
        alert('حدث خطأ أثناء إعادة التوزيع');
    } finally {
        redistributing.value = false;
    }
}
</script>

<template>
    <Head title="إدارة توزيع الليدز" />
    <AuthenticatedLayout>
        <template #title>إدارة توزيع الليدز</template>

        <div class="mx-auto w-full min-w-0 max-w-4xl space-y-4 px-3 pb-8 sm:px-4">
            <!-- Stats Overview -->
            <div class="ui-card overflow-hidden">
                <div class="grid grid-cols-3 gap-px bg-slate-100">
                    <div class="bg-white p-4 text-center">
                        <div class="text-2xl font-black text-brand-700">{{ total_leads }}</div>
                        <div class="text-[11px] text-slate-500">إجمالي الليدز</div>
                    </div>
                    <div class="bg-white p-4 text-center">
                        <div class="text-2xl font-black text-emerald-700">{{ total_leads - unassigned_count }}</div>
                        <div class="text-[11px] text-slate-500">مُعيَّن</div>
                    </div>
                    <div class="bg-white p-4 text-center">
                        <div class="text-2xl font-black" :class="unassigned_count > 0 ? 'text-rose-700' : 'text-slate-400'">{{ unassigned_count }}</div>
                        <div class="text-[11px] text-slate-500">غير مُعيَّن</div>
                    </div>
                </div>
            </div>

            <!-- Distribution Visual -->
            <div class="ui-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">التوزيع الحالي</h2>
                    <span class="text-[11px] text-slate-500">الوزن الكلي: {{ totalWeight }}</span>
                </div>
                <div v-if="localAssignees.length" class="space-y-3">
                    <div v-for="a in distributionBars" :key="`bar-${a.user_id}`" class="space-y-1">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-semibold text-slate-700">{{ a.name }}</span>
                            <span class="text-slate-500">{{ a.percentage }}% — {{ a.total_leads }} ليدز</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="a.percentage >= 50 ? 'bg-brand-500' : 'bg-sky-500'"
                                :style="{ width: a.percentage + '%' }"
                            />
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-4 py-6 text-center text-xs text-slate-500">
                    لا يوجد مُعيَّنون بعد.
                </div>
            </div>

            <!-- Assignees Management -->
            <div class="ui-card p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">المُعيَّنون</h2>
                    <button
                        v-if="availableMembers.length"
                        type="button"
                        class="rounded-lg bg-brand-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-brand-700"
                        @click="showAddModal = true"
                    >
                        + إضافة مندوب
                    </button>
                </div>

                <div v-if="localAssignees.length" class="space-y-2">
                    <div
                        v-for="a in localAssignees"
                        :key="`a-${a.user_id}`"
                        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3"
                    >
                        <div class="flex-1">
                            <div class="text-sm font-bold text-slate-900">{{ a.name }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-[10px] text-slate-500">
                                <span>اليوم: <b class="text-slate-700">{{ a.leads_today }}</b></span>
                                <span>قادمة: <b class="text-sky-700">{{ a.upcoming_calls }}</b></span>
                                <span>إجمالي: <b class="text-slate-700">{{ a.total_leads }}</b></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100"
                                @click="adjustWeight(a.user_id, -5)"
                            >
                                −
                            </button>
                            <input
                                v-model.number="a.weight"
                                type="number"
                                min="1"
                                max="100"
                                class="h-7 w-14 rounded-lg border border-slate-200 bg-white text-center text-sm font-bold text-slate-900 focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                            />
                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100"
                                @click="adjustWeight(a.user_id, 5)"
                            >
                                +
                            </button>
                        </div>

                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                            @click="removeAssignee(a.user_id)"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-4 py-6 text-center text-xs text-slate-500">
                    أضِف مندوبين لتوزيع الليدز عليهم.
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :disabled="saving || localAssignees.length === 0"
                    class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700 disabled:opacity-50"
                    @click="saveWeights"
                >
                    {{ saving ? 'جاري الحفظ...' : 'حفظ الأوزان' }}
                </button>
                <button
                    type="button"
                    :disabled="redistributing || unassigned_count === 0"
                    class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    @click="redistribute"
                >
                    {{ redistributing ? 'جاري التوزيع...' : `توزيع (${unassigned_count}) ليدز غير مُعيَّن` }}
                </button>
            </div>

            <!-- Redistribute Result -->
            <div
                v-if="redistributeResult"
                class="ui-card border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
            >
                {{ redistributeResult.message }}
            </div>

            <!-- Add Modal -->
            <Teleport to="body">
                <div
                    v-if="showAddModal"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    @click.self="showAddModal = false"
                >
                    <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">إضافة مندوب للتوزيع</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-slate-600">المندوب</label>
                                <select
                                    v-model="selectedUserId"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                >
                                    <option :value="null" disabled>اختر مندوب...</option>
                                    <option v-for="m in availableMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-slate-600">الوزن (1-100)</label>
                                <input
                                    v-model.number="newWeight"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"
                                />
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                @click="showAddModal = false"
                            >
                                إلغاء
                            </button>
                            <button
                                type="button"
                                :disabled="!selectedUserId"
                                class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                                @click="addAssignee"
                            >
                                إضافة
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>
