<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();

const dismissed = ref(new Set());

const rawAlerts = computed(() => {
    const list = page.props.operational_alerts;
    return Array.isArray(list) ? list : [];
});

const visibleAlerts = computed(() => rawAlerts.value.filter((a) => a?.id && !dismissed.value.has(String(a.id))));

const storageKey = 'kharij-ops-alerts-dismissed';

onMounted(() => {
    try {
        const raw = sessionStorage.getItem(storageKey);
        if (!raw) {
            return;
        }
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            dismissed.value = new Set(parsed.map(String));
        }
    } catch {
        /* ignore */
    }
});

function persistDismissed() {
    try {
        sessionStorage.setItem(storageKey, JSON.stringify([...dismissed.value]));
    } catch {
        /* ignore */
    }
}

function dismiss(id) {
    if (!id) {
        return;
    }
    dismissed.value = new Set([...dismissed.value, String(id)]);
    persistDismissed();
}

function severityRing(sev) {
    if (sev === 'critical') {
        return 'border-s-4 border-s-rose-600';
    }
    if (sev === 'warning') {
        return 'border-s-4 border-s-amber-600';
    }
    return 'border-s-4 border-s-slate-400';
}
</script>

<template>
    <div v-if="visibleAlerts.length" class="relative z-10 border-b border-slate-200 bg-slate-50/95 px-3 py-2 md:px-5" role="region" aria-label="تنبيهات تشغيلية">
        <div class="mx-auto flex max-w-6xl flex-col gap-2">
            <div
                v-for="alert in visibleAlerts"
                :key="alert.id"
                class="flex flex-wrap items-start gap-2 rounded-lg bg-white px-3 py-2 text-[12px] leading-snug text-slate-800 shadow-sm ring-1 ring-slate-200/80 md:items-center md:justify-between md:text-[13px]"
                :class="severityRing(alert.severity)"
                dir="rtl"
            >
                <div class="min-w-0 flex-1 space-y-0.5">
                    <p class="font-semibold text-slate-900">{{ alert.title_ar }}</p>
                    <p class="text-slate-600">{{ alert.body_ar }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <Link
                        v-if="alert.href"
                        :href="alert.href"
                        class="rounded-md bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-slate-800"
                    >
                        {{ alert.action_ar || 'انتقال' }}
                    </Link>
                    <button
                        type="button"
                        class="text-[11px] font-semibold text-slate-500 underline decoration-slate-400 underline-offset-2 hover:text-slate-800"
                        @click="dismiss(alert.id)"
                    >
                        إخفاء
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
