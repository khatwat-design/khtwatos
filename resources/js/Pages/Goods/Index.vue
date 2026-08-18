<script setup>
import GoodsMetaLeadsPanel from '@/Components/Goods/GoodsMetaLeadsPanel.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const props = defineProps({
    meta_leads: { type: Array, default: () => [] },
    meta_lead_status_options: { type: Array, default: () => [] },
    meta_filters: { type: Object, default: () => ({}) },
    meta_campaign_options: { type: Array, default: () => [] },
    meta_analytics: { type: Object, default: () => ({}) },
    meta_assignee_stats: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
    meta_leads_webhook_configured: { type: Boolean, default: false },
});

const page = usePage();
</script>

<template>
    <Head title="إدارة العملاء" />

    <AuthenticatedLayout>
        <template #title>إدارة العملاء</template>

        <div class="mx-auto w-full min-w-0 max-w-7xl space-y-4 px-3 pb-8 sm:px-4">
            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {{ page.props.flash.error }}
            </div>

            <GoodsMetaLeadsPanel
                :meta_leads="meta_leads"
                :meta_lead_status_options="meta_lead_status_options"
                :meta_filters="meta_filters"
                :meta_campaign_options="meta_campaign_options"
                :meta_analytics="meta_analytics"
                :meta_assignee_stats="meta_assignee_stats"
                :owners="owners"
                :meta_leads_webhook_configured="meta_leads_webhook_configured"
            />
        </div>
    </AuthenticatedLayout>
</template>
