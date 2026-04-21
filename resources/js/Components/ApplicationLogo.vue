<script setup>
defineProps({
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <img
        src="/images/logo.png"
        alt="خارج المخزون"
        class="h-10 w-auto max-w-[140px] object-contain"
        :class="class"
    />
</template>
