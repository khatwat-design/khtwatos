<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    card: { type: Object, required: true },
    isMine: { type: Boolean, default: false },
});

const subtitle = computed(() => {
    const parts = [];
    if (props.card?.client_name) {
        parts.push(props.card.client_name);
    }
    if (props.card?.column_name) {
        parts.push(props.card.column_name);
    }
    return parts.join(' · ');
});

function taskHref(card) {
    if (!card?.id) {
        return '#';
    }
    return route('tasks.index', {
        team: card.team_slug || undefined,
        task: card.id,
        column: card.board_column_id || undefined,
    });
}
</script>

<template>
    <Link
        :href="taskHref(card)"
        class="mt-2 block rounded-xl border px-3 py-2.5 text-start transition active:scale-[0.99]"
        :class="
            isMine
                ? 'border-white/30 bg-white/15 hover:bg-white/20'
                : 'border-emerald-200/90 bg-emerald-50/90 hover:bg-emerald-50'
        "
        @click.stop
    >
        <p
            class="text-[10px] font-bold uppercase tracking-wide"
            :class="isMine ? 'text-white/80' : 'text-emerald-800'"
        >
            مهمة
        </p>
        <p class="mt-0.5 text-sm font-bold leading-snug" :class="isMine ? 'text-white' : 'text-slate-900'">
            {{ card.title }}
        </p>
        <p v-if="subtitle" class="mt-1 text-[11px]" :class="isMine ? 'text-white/85' : 'text-emerald-900/80'">
            {{ subtitle }}
        </p>
        <p class="mt-1.5 text-[10px] font-semibold" :class="isMine ? 'text-white/75' : 'text-brand-700'">
            فتح لوحة المهام ←
        </p>
    </Link>
</template>
