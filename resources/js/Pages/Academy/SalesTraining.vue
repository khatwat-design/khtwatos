<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    getSessionSummaries,
    getSlidesForSession,
    SESSION_COUNT,
} from '@/data/salesTrainingCurriculum';

const sessions = getSessionSummaries();
const currentSession = ref(1);
const currentSlideIndex = ref(0);

const slides = computed(() => getSlidesForSession(currentSession.value));
const currentSlide = computed(() => slides.value[currentSlideIndex.value] || null);
const progress = computed(() =>
    slides.value.length
        ? ((currentSlideIndex.value + 1) / slides.value.length) * 100
        : 0,
);

watch(currentSession, () => {
    currentSlideIndex.value = 0;
});

function nextSlide() {
    if (currentSlideIndex.value < slides.value.length - 1) {
        currentSlideIndex.value += 1;
    }
}

function prevSlide() {
    if (currentSlideIndex.value > 0) {
        currentSlideIndex.value -= 1;
    }
}
</script>

<template>
    <Head title="تدريب المبيعات" />

    <AuthenticatedLayout>
        <template #title>تدريب المبيعات</template>

        <div class="mx-auto max-w-6xl space-y-5">
            <div class="rounded-2xl border border-orange-500/30 bg-black p-5 text-white shadow-xl">
                <h1 class="text-2xl font-bold">معسكر تدريب المبيعات</h1>
                <p class="mt-1 text-sm text-gray-300">
                    نفس المحاضرات ومحتوياتها من Khtwat Hub.
                </p>
                <p class="mt-2 text-xs text-orange-300">
                    الجلسة {{ currentSession }} من {{ SESSION_COUNT }} · الشريحة
                    {{ currentSlideIndex + 1 }} من {{ slides.length }}
                </p>
            </div>

            <div class="grid gap-2 rounded-xl bg-white p-3 ring-1 ring-gray-200 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="s in sessions"
                    :key="s.id"
                    type="button"
                    :class="[
                        'rounded-lg border px-3 py-2 text-right transition',
                        currentSession === s.id
                            ? 'border-orange-500 bg-orange-50 text-orange-800'
                            : 'border-gray-200 bg-white hover:bg-gray-50',
                    ]"
                    @click="currentSession = s.id"
                >
                    <p class="text-sm font-semibold">{{ s.label }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ s.subtitle }}</p>
                </button>
            </div>

            <div
                v-if="currentSlide"
                class="rounded-2xl border border-orange-500/20 bg-gradient-to-b from-neutral-950 to-black p-6 text-white shadow-2xl"
            >
                <template v-if="currentSlide.variant === 'cover'">
                    <div class="text-center">
                        <p class="text-sm text-orange-300">{{ currentSlide.sessionTitle }}</p>
                        <h2 class="mt-3 text-3xl font-bold">{{ currentSlide.sessionSubtitle }}</h2>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'objectives'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li
                            v-for="(item, i) in currentSlide.items"
                            :key="i"
                            class="rounded-lg border border-orange-500/20 bg-orange-500/5 px-3 py-2"
                        >
                            {{ item }}
                        </li>
                    </ul>
                </template>

                <template v-else-if="currentSlide.variant === 'split'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-red-400/30 bg-red-500/5 p-4">
                            <h3 class="font-semibold text-red-300">{{ currentSlide.negativeTitle }}</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(item, i) in currentSlide.negativeItems" :key="i">• {{ item }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-emerald-400/30 bg-emerald-500/5 p-4">
                            <h3 class="font-semibold text-emerald-300">{{ currentSlide.positiveTitle }}</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(item, i) in currentSlide.positiveItems" :key="i">• {{ item }}</li>
                            </ul>
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'table'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 overflow-hidden rounded-xl border border-orange-500/20">
                        <table class="min-w-full text-sm">
                            <thead class="bg-orange-500/10">
                                <tr>
                                    <th class="px-3 py-2 text-start">{{ currentSlide.columns[0] }}</th>
                                    <th class="px-3 py-2 text-start">{{ currentSlide.columns[1] }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, i) in currentSlide.rows"
                                    :key="i"
                                    class="border-t border-orange-500/10"
                                >
                                    <td class="px-3 py-2">{{ row[0] }}</td>
                                    <td class="px-3 py-2 text-gray-200">{{ row[1] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'cards'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="(card, i) in currentSlide.cards"
                            :key="i"
                            class="rounded-xl border border-orange-500/25 bg-orange-500/5 p-4"
                        >
                            <h3 class="font-semibold text-orange-300">{{ card.title }}</h3>
                            <p class="mt-1 text-sm text-gray-200">{{ card.body }}</p>
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'list'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li
                            v-for="(item, i) in currentSlide.items"
                            :key="i"
                            class="rounded-lg border border-white/10 bg-white/5 px-3 py-2"
                        >
                            • {{ item }}
                        </li>
                    </ul>
                </template>

                <template v-else-if="currentSlide.variant === 'stages'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div
                            v-for="(stage, i) in currentSlide.stages"
                            :key="i"
                            class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-4"
                        >
                            <p class="text-xs text-orange-300">{{ stage.key }}</p>
                            <h3 class="mt-1 font-semibold">{{ stage.title }}</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(b, j) in stage.bullets" :key="j">• {{ b }}</li>
                            </ul>
                            <p v-if="stage.example" class="mt-2 text-xs text-gray-300">{{ stage.example }}</p>
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'objections'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <p class="mt-3 rounded-lg border border-orange-500/30 bg-orange-500/10 px-3 py-2 text-sm">
                        {{ currentSlide.objection }}
                    </p>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <div
                            v-for="(step, i) in currentSlide.steps"
                            :key="i"
                            class="rounded-xl border border-white/10 bg-white/5 p-3"
                        >
                            <p class="text-xs text-orange-300">{{ step.label }}</p>
                            <p class="mt-1 text-sm">{{ step.text }}</p>
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'funnel'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="(step, i) in currentSlide.steps"
                            :key="i"
                            class="rounded-xl border border-orange-500/25 bg-orange-500/5 px-3 py-3 text-sm"
                            :style="{ width: `${100 - i * 8}%`, marginInlineStart: `${i * 4}%` }"
                        >
                            {{ step }}
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'metrics'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="(item, i) in currentSlide.items"
                            :key="i"
                            class="rounded-xl border border-orange-500/25 bg-orange-500/5 p-3 text-sm"
                        >
                            {{ item }}
                        </div>
                    </div>
                </template>

                <template v-else-if="currentSlide.variant === 'exercise'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li
                            v-for="(item, i) in currentSlide.items"
                            :key="i"
                            class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3 py-2"
                        >
                            {{ item }}
                        </li>
                    </ul>
                </template>

                <template v-else-if="currentSlide.variant === 'comparison'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-4">
                            <h3 class="font-semibold text-orange-300">{{ currentSlide.marketingTitle }}</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(item, i) in currentSlide.marketingItems" :key="i">• {{ item }}</li>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-orange-500/20 bg-orange-500/5 p-4">
                            <h3 class="font-semibold text-orange-300">{{ currentSlide.salesTitle }}</h3>
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(item, i) in currentSlide.salesItems" :key="i">• {{ item }}</li>
                            </ul>
                        </div>
                    </div>
                    <p class="mt-4 rounded-lg bg-orange-500/10 px-3 py-2 text-center text-sm text-orange-100">
                        {{ currentSlide.sharedGoal }}
                    </p>
                </template>

                <template v-else-if="currentSlide.variant === 'ecosystem'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <p class="mt-4 rounded-xl border border-orange-500/20 bg-orange-500/5 p-4 text-sm">
                        {{ currentSlide.description }}
                    </p>
                </template>

                <template v-else-if="currentSlide.variant === 'simulation'">
                    <h2 class="text-2xl font-bold">{{ currentSlide.heading }}</h2>
                    <p class="mt-4 rounded-xl border border-orange-500/20 bg-orange-500/5 p-4 text-sm">
                        {{ currentSlide.description }}
                    </p>
                </template>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full bg-orange-500 transition-all"
                        :style="{ width: `${progress}%` }"
                    />
                </div>
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50 disabled:opacity-40"
                        :disabled="currentSlideIndex === 0"
                        @click="prevSlide"
                    >
                        السابق
                    </button>
                    <p class="text-xs text-gray-500">
                        الجلسة {{ currentSession }} · {{ currentSlideIndex + 1 }} / {{ slides.length }}
                    </p>
                    <button
                        type="button"
                        class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-40"
                        :disabled="currentSlideIndex >= slides.length - 1"
                        @click="nextSlide"
                    >
                        التالي
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
