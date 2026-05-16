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
                <div
                    class="flex max-h-[min(72dvh,28rem)] w-full max-w-lg flex-col rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    @click.stop
                >
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 px-3 py-2.5">
                        <div>
                            <p class="text-sm font-bold text-slate-900">الملصقات</p>
                            <p v-if="activePack?.subtitle" class="text-[11px] text-slate-500">{{ activePack.subtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            @click="emit('close')"
                        >
                            إغلاق
                        </button>
                    </div>
                    <div v-if="packs.length > 1" class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-100 px-2 py-2">
                        <button
                            v-for="pack in packs"
                            :key="pack.id"
                            type="button"
                            class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold transition"
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
                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                            <button
                                v-for="sticker in activePack?.stickers || []"
                                :key="sticker.key"
                                type="button"
                                class="rounded-xl p-1.5 transition hover:bg-slate-50 active:scale-95"
                                :title="sticker.label || 'ملصق'"
                                @click="pick(sticker)"
                            >
                                <img
                                    :src="sticker.url"
                                    alt=""
                                    class="mx-auto h-16 w-16 object-contain"
                                    loading="lazy"
                                    draggable="false"
                                />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
