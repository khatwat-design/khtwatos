<script setup>
import { splitMessageBody } from '@/utils/chatMentions.js';
import { computed } from 'vue';

const props = defineProps({
    body: { type: String, default: '' },
    mentions: { type: Array, default: () => [] },
    isMine: { type: Boolean, default: false },
});

const parts = computed(() => splitMessageBody(props.body, props.mentions));
</script>

<template>
    <p v-if="body" class="whitespace-pre-wrap break-words">
        <template v-for="(part, index) in parts" :key="`part-${index}`">
            <span v-if="part.type === 'text'">{{ part.text }}</span>
            <span
                v-else
                class="rounded px-0.5 font-semibold"
                :class="
                    isMine
                        ? 'bg-white/20 text-white'
                        : part.user
                          ? 'bg-brand-50 text-brand-800'
                          : 'text-brand-700'
                "
            >
                {{ part.text }}
            </span>
        </template>
    </p>
</template>
