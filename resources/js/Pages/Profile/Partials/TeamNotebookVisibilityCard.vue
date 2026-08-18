<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const processing = ref(false);

const checked = computed(() => Boolean(page.props.auth?.user?.show_team_notebook ?? true));

function onToggle(next) {
    if (processing.value) {
        return;
    }
    processing.value = true;
    router.patch(
        route('profile.team-notebook.update'),
        { show_team_notebook: next },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">دفتر الملاحظات</h2>
            <p class="mt-1 text-sm text-gray-600">
                يمكنك إخفاء الشريط الجانبي للملاحظات دون حذف ما كتبته؛ المحتوى الشخصي والمشترك يبقى محفوظاً حتى تعيد الإظهار.
            </p>
        </header>

        <div class="mt-6 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50/80 p-4">
            <Checkbox
                id="show_team_notebook"
                :checked="checked"
                :disabled="processing"
                class="mt-0.5 shrink-0"
                @update:checked="onToggle"
            />
            <div class="min-w-0 flex-1">
                <InputLabel
                    for="show_team_notebook"
                    value="إظهار دفتر الملاحظات في الواجهة"
                    class="!inline cursor-pointer font-medium text-gray-900"
                />
                <p class="mt-1 text-xs text-gray-500">
                    عند الإيقاف يختفي الشريط فقط؛ لا يُمسح النص من الخادم.
                </p>
                <InputError class="mt-2" :message="page.props.errors?.show_team_notebook" />
            </div>
        </div>
    </section>
</template>
