<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    packs: { type: Array, default: () => [] },
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'close']);

const activePackId = ref(null);

const activePack = computed(() => {
    const packs = props.packs || [];
    if (!packs.length) {
        return null;
    }
    const id = activePackId.value || packs[0]?.id;
    return packs.find((p) => p.id === id) || packs[0];
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.packs?.length) {
            activePackId.value = props.packs[0].id;
        }
    },
);

function pick(sticker) {
    emit('select', sticker);
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[115] flex flex-col justify-end bg-black/40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:items-center sm:justify-center"
                dir="rtl"
                @click.self="emit('close')"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl" @click.stop>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-slate-900">الملصقات</p>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            @click="emit('close')"
                        >
                            إغلاق
                        </button>
                    </div>
                    <div v-if="packs.length > 1" class="mb-2 flex flex-wrap gap-1">
                        <button
                            v-for="pack in packs"
                            :key="pack.id"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold transition"
                            :class="
                                activePack?.id === pack.id
                                    ? 'bg-brand-600 text-white'
                                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                            "
                            @click="activePackId = pack.id"
                        >
                            {{ pack.label }}
                        </button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                        <button
                            v-for="sticker in activePack?.stickers || []"
                            :key="sticker.key"
                            type="button"
                            class="flex h-12 items-center justify-center rounded-xl text-2xl transition hover:bg-slate-100 active:scale-95"
                            :title="sticker.key"
                            @click="pick(sticker)"
                        >
                            {{ sticker.emoji }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
