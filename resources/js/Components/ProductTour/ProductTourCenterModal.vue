<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    open: { type: Boolean, default: false },
    startingId: { type: String, default: '' },
});

const emit = defineEmits(['close', 'start', 'reset']);

const page = usePage();

const tours = computed(() => page.props.product_tours?.catalog || []);
const personaLabel = computed(() => page.props.product_tours?.persona_label || '');
const completedCount = computed(() => Number(page.props.product_tours?.completed_count || 0));
const totalCount = computed(() => Number(page.props.product_tours?.total_count || 0));

const progressPercent = computed(() => {
    if (!totalCount.value) {
        return 0;
    }
    return Math.round((completedCount.value / totalCount.value) * 100);
});

function statusLabel(status) {
    if (status === 'completed') {
        return 'مكتملة';
    }
    if (status === 'skipped') {
        return 'تم تخطيها';
    }
    return 'لم تبدأ';
}

function statusClass(status) {
    if (status === 'completed') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    }
    if (status === 'skipped') {
        return 'bg-slate-100 text-slate-600 ring-slate-200';
    }
    return 'bg-sky-50 text-sky-800 ring-sky-200';
}
</script>

<template>
    <Modal :show="open" max-width="2xl" @close="emit('close')">
        <div class="p-5 sm:p-6" dir="rtl">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">مركز التدريب التفاعلي</h2>
                    <p v-if="personaLabel" class="mt-1 text-sm text-slate-600">
                        مسار مخصص لـ <span class="font-semibold text-slate-800">{{ personaLabel }}</span>
                    </p>
                </div>
                <div class="text-end text-sm text-slate-600">
                    <div class="font-semibold text-slate-900">{{ progressPercent }}%</div>
                    <div class="text-xs">{{ completedCount }} / {{ totalCount }} جولة</div>
                </div>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-gradient-to-l from-brand-600 to-sky-500 transition-all duration-500"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>

            <p class="mt-4 text-sm leading-relaxed text-slate-600">
                جولات قصيرة مثل التطبيقات العالمية — كل قسم يُشرح عند زيارته. ابدأ من «متابعة الجولات» أو اختر قسمًا محددًا.
            </p>

            <ul class="mt-4 max-h-[min(50vh,22rem)] space-y-2 overflow-y-auto pe-1">
                <li
                    v-for="tour in tours"
                    :key="tour.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/90 bg-white px-3 py-2.5 shadow-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ tour.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ tour.description }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1"
                            :class="statusClass(tour.status)"
                        >
                            {{ statusLabel(tour.status) }}
                        </span>
                        <button
                            type="button"
                            class="rounded-lg bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-brand-500 disabled:opacity-50"
                            :disabled="startingId === tour.id"
                            @click="emit('start', tour.id)"
                        >
                            {{ tour.status === 'pending' ? 'ابدأ' : 'إعادة' }}
                        </button>
                    </div>
                </li>
            </ul>

            <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                <SecondaryButton type="button" @click="emit('reset')">
                    إعادة كل الجولات
                </SecondaryButton>
                <PrimaryButton type="button" @click="emit('close')">
                    إغلاق
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
