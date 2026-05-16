<script setup>
defineProps({
    class: { type: String, default: 'h-5 w-5' },
});
</script>

<template>
    <svg
        :class="$props.class"
        viewBox="0 0 24 24"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <path
            d="M6 3h9l5 5v11a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3Z"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linejoin="round"
        />
        <path
            d="M15 3v5h5"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linejoin="round"
        />
        <circle cx="9" cy="11" r="1.35" fill="currentColor" />
        <circle cx="12.5" cy="11" r="1.35" fill="currentColor" />
        <circle cx="16" cy="11" r="1.35" fill="currentColor" />
        <path
            d="M8.5 15.5c1.2 1 2.2 1.5 3.5 1.5s2.3-.5 3.5-1.5"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
        />
    </svg>
</template>
