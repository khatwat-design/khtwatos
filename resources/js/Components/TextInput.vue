<script setup>
import { onMounted, ref } from 'vue';

/**
 * لا نفرض String فقط: حقول مثل نسبة التوزيع % تمرّر أعدادًا من الـ API أو من input[type=number]،
 * وفرض String كان يسبب أخطاء Vue في وحدة التحكم وقد يمنع إرسال النموذج.
 */
const model = defineModel({
    required: false,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
        v-model="model"
        ref="input"
    />
</template>
