<script setup>
import { copyText, toLocalIraqiPhone, whatsAppUrl } from '@/utils/iraqiPhone';
import { computed, ref } from 'vue';

const props = defineProps({
    phone: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const copied = ref(false);
let copiedTimer = null;

const localPhone = computed(() => toLocalIraqiPhone(props.phone));
const waLink = computed(() => whatsAppUrl(props.phone));
const canUse = computed(() => localPhone.value.length >= 10);

async function copyForCall() {
    if (!canUse.value) {
        return;
    }
    const ok = await copyText(localPhone.value);
    if (!ok) {
        return;
    }
    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function openWhatsApp() {
    if (!waLink.value) {
        return;
    }
    window.open(waLink.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <div
        v-if="canUse"
        :class="compact ? 'flex flex-wrap items-center gap-1.5' : 'mt-2 flex flex-wrap items-center gap-2'"
        @click.stop
    >
        <p v-if="!compact" class="w-full font-mono text-xs text-gray-600" dir="ltr">{{ localPhone }}</p>
        <button
            type="button"
            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100"
            :class="compact ? 'min-h-8 px-2' : 'flex-1'"
            @click="openWhatsApp"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                />
            </svg>
            <span v-if="!compact">واتساب</span>
        </button>
        <button
            type="button"
            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl border px-2.5 text-xs font-semibold transition"
            :class="[
                compact ? 'min-h-8 px-2' : 'flex-1',
                copied
                    ? 'border-brand-400 bg-brand-50 text-brand-800'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
            ]"
            @click="copyForCall"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <rect x="9" y="9" width="13" height="13" rx="2" />
                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
            </svg>
            <span v-if="!compact">{{ copied ? 'تم النسخ' : 'نسخ للاتصال' }}</span>
            <span v-else class="sr-only">{{ copied ? 'تم النسخ' : 'نسخ' }}</span>
        </button>
        <span v-if="compact" class="font-mono text-xs text-gray-600" dir="ltr">{{ localPhone }}</span>
    </div>
    <p v-else-if="phone" class="mt-1 font-mono text-xs text-gray-400" dir="ltr">{{ phone }}</p>
</template>
