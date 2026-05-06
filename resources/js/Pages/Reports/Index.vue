<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps({ report: String, title: String, rows: Object, filters: Object });
const page = usePage();
const filter = ref({ date_from: props.filters?.date_from || '2026-05-01', date_to: props.filters?.date_to || '2026-05-06', status: props.filters?.status || '', type: props.filters?.type || '' });
const apply = () => router.get(route('reports.index', props.report), filter.value, { preserveState: true });
const exportUrl = (format) => route('reports.export', [props.report, format]) + '?' + new URLSearchParams(filter.value).toString();
</script>
<template>
    <AuthenticatedLayout :title="title">
        <h1 class="mb-4 text-2xl font-bold">{{ title }}</h1>
        <div class="mb-4 grid gap-3 rounded-lg border border-orange-100 bg-white p-4 md:grid-cols-5"><input v-model="filter.date_from" type="date" class="rounded-md border-slate-300" /><input v-model="filter.date_to" type="date" class="rounded-md border-slate-300" /><input v-model="filter.status" class="rounded-md border-slate-300" placeholder="Status" /><input v-model="filter.type" class="rounded-md border-slate-300" placeholder="Type" /><button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" @click="apply">Filter</button></div>
        <div v-if="page.props.auth.user.role==='owner'" class="mb-4 flex gap-2"><a :href="exportUrl('xlsx')" class="rounded-md border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700">Export Excel</a><a :href="exportUrl('pdf')" class="rounded-md border border-orange-200 px-3 py-2 text-sm font-semibold text-orange-700">Export PDF</a></div>
        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white"><table class="min-w-full text-sm"><tbody><tr v-for="row in rows.data" :key="row.id" class="border-b"><td class="p-3 font-semibold">{{ row.name || row.code || row.purchase_code || row.production_code || row.usage_code || row.type }}</td><td>{{ row.status || row.type }}</td><td>{{ row.current_stock || row.total_amount || row.estimated_total_cost || row.stock_after }}</td></tr></tbody></table></div>
        <div class="mt-4 flex gap-2"><Link v-for="link in rows.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-1 text-sm" :class="{ 'bg-orange-600 text-white': link.active }" v-html="link.label" /></div>
    </AuthenticatedLayout>
</template>
