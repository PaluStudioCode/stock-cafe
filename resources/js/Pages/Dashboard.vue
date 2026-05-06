<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LoadingState from '@/Components/LoadingState.vue';
import { RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    metrics: Array,
    lowStock: Array,
    recentActivities: Array,
    recentPurchaseOrders: Array,
    recentStockUsages: Array,
    draftAdjustments: Array,
    productionLogs: Array,
    activeMenus: Number,
    role: String,
    refreshedAt: String,
});

const loading = ref(false);
const summary = ref({
    metrics: props.metrics || [],
    lowStock: props.lowStock || [],
    recentActivities: props.recentActivities || [],
    recentPurchaseOrders: props.recentPurchaseOrders || [],
    recentStockUsages: props.recentStockUsages || [],
    draftAdjustments: props.draftAdjustments || [],
    productionLogs: props.productionLogs || [],
    activeMenus: props.activeMenus || 0,
    role: props.role,
    refreshedAt: props.refreshedAt,
});

const roleLabel = computed(() => ({
    owner: 'Owner',
    inventory_staff: 'Inventory Staff',
    barista: 'Barista',
}[summary.value.role] || 'User'));

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
const format = (metric) => metric.format === 'currency' ? money(metric.value) : metric.format === 'decimal' ? Number(metric.value || 0).toLocaleString('id-ID') : metric.value;
const dateTime = (value) => value ? new Date(value).toLocaleString('id-ID') : '-';

const refresh = async () => {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('dashboard.summary'));
        summary.value = data;
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout title="Dashboard">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Dashboard {{ roleLabel }}</h1>
                <p class="text-sm text-slate-500">Ringkasan realtime MVP dari transaksi stok terakhir.</p>
            </div>
            <button
                class="inline-flex h-10 items-center gap-2 rounded-md border border-orange-200 bg-white px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50 disabled:opacity-60"
                :disabled="loading"
                @click="refresh"
            >
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" /> Refresh
            </button>
        </div>

        <LoadingState v-if="loading" />
        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="metric in summary.metrics" :key="metric.label" class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm">
                <div class="text-sm text-slate-500">{{ metric.label }}</div>
                <div class="mt-2 text-2xl font-black text-slate-900">{{ format(metric) }}</div>
            </div>
        </div>

        <div class="mt-3 text-xs text-slate-500">
            Terakhir refresh: {{ dateTime(summary.refreshedAt) }}
        </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-[1.1fr_.9fr]">
            <section class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm">
                <h2 class="font-bold">Bahan Perlu Diperhatikan</h2>
                <EmptyState v-if="!summary.lowStock?.length" class="mt-4" title="Tidak ada stok menipis" message="Semua bahan masih berada di atas batas minimum." />
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-slate-500"><th class="py-2">Bahan</th><th>Stok</th><th>Minimum</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in summary.lowStock" :key="item.id" class="border-b last:border-0">
                                <td class="py-2 font-medium">{{ item.name }}</td>
                                <td>{{ item.current_stock }} {{ item.unit?.symbol }}</td>
                                <td>{{ item.minimum_stock }}</td>
                                <td><Badge :value="Number(item.current_stock) === 0 ? 'Habis' : 'Menipis'" :tone="Number(item.current_stock) === 0 ? 'red' : 'orange'" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="summary.role === 'owner'" class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm">
                <h2 class="font-bold">Aktivitas Terbaru</h2>
                <EmptyState v-if="!summary.recentActivities?.length" class="mt-4" title="Belum ada aktivitas" />
                <div v-else class="mt-3 space-y-3">
                    <div v-for="activity in summary.recentActivities" :key="activity.id" class="rounded-md bg-stone-50 p-3">
                        <div class="text-sm font-semibold">{{ activity.description }}</div>
                        <div class="text-xs text-slate-500">{{ activity.user?.name || 'System' }} / {{ activity.action }}</div>
                    </div>
                </div>
            </section>

            <section v-else-if="summary.role === 'inventory_staff'" class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm">
                <h2 class="font-bold">Operasional Inventory</h2>
                <div class="mt-3 space-y-5">
                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-700">Purchase Order Terbaru</div>
                        <EmptyState v-if="!summary.recentPurchaseOrders?.length" title="Belum ada purchase order" />
                        <div v-else class="space-y-2">
                            <div v-for="po in summary.recentPurchaseOrders" :key="po.id" class="flex items-center justify-between rounded-md bg-stone-50 p-3 text-sm">
                                <span class="font-semibold">{{ po.purchase_code }}</span>
                                <Badge :value="po.status" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-700">Stock Usage Terbaru</div>
                        <EmptyState v-if="!summary.recentStockUsages?.length" title="Belum ada stock usage" />
                        <div v-else class="space-y-2">
                            <div v-for="usage in summary.recentStockUsages" :key="usage.id" class="flex items-center justify-between rounded-md bg-stone-50 p-3 text-sm">
                                <span class="font-semibold">{{ usage.usage_code }}</span>
                                <Badge :value="usage.status" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-700">Draft Adjustment</div>
                        <EmptyState v-if="!summary.draftAdjustments?.length" title="Tidak ada draft adjustment" />
                        <div v-else class="space-y-2">
                            <div v-for="adj in summary.draftAdjustments" :key="adj.id" class="rounded-md bg-stone-50 p-3 text-sm font-semibold">{{ adj.adjustment_code }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-lg border border-orange-100 bg-white p-4 shadow-sm">
                <h2 class="font-bold">Production Log Saya</h2>
                <EmptyState v-if="!summary.productionLogs?.length" class="mt-4" title="Belum ada production log" message="Production log yang kamu buat akan tampil di sini." />
                <div v-else class="mt-3 space-y-2">
                    <div v-for="log in summary.productionLogs" :key="log.id" class="flex items-center justify-between rounded-md bg-stone-50 p-3 text-sm">
                        <div>
                            <div class="font-semibold">{{ log.production_code }}</div>
                            <div class="text-xs text-slate-500">{{ log.menu_item?.name }} / {{ log.quantity }} porsi</div>
                        </div>
                        <Badge :value="log.status" />
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
