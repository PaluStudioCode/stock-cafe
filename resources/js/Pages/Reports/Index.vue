<script setup>
import Toast from '@/Components/Toast.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelForValue } from '@/utilities/formatters';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { FileSpreadsheet, FileText, Filter, LoaderCircle } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    report: String,
    title: String,
    activeReportTitle: String,
    reports: Array,
    rows: Object,
    filters: Object,
    lookups: Object,
    columns: Array,
    requiresDateFilter: Boolean,
});

const page = usePage();
const exporting = ref('');
const exportError = ref('');
const filter = ref({
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
    status: props.filters?.status || '',
    type: props.filters?.type || '',
    usage_type: props.filters?.usage_type || '',
    supplier_id: props.filters?.supplier_id || '',
    menu_item_id: props.filters?.menu_item_id || '',
    user_id: props.filters?.user_id || '',
    ingredient_id: props.filters?.ingredient_id || '',
    category_id: props.filters?.category_id || '',
});

const ownerCanExport = computed(() => page.props.auth.user.role === 'owner');
const showCategory = computed(() => ['stock', 'low-stock', 'stock-movement', 'inventory-value'].includes(props.report));
const showSupplier = computed(() => ['stock', 'purchases', 'inventory-value'].includes(props.report));
const showStatus = computed(() => ['purchases', 'production', 'waste'].includes(props.report));
const showMenu = computed(() => props.report === 'production');
const showUser = computed(() => props.report === 'production');
const showUsageType = computed(() => props.report === 'waste');
const showIngredient = computed(() => props.report === 'stock-movement');
const showMovementType = computed(() => props.report === 'stock-movement');
const statusOptions = computed(() => props.lookups?.statuses?.[props.report] || []);

const compactFilters = () => Object.fromEntries(
    Object.entries(filter.value).filter(([, value]) => value !== '' && value !== null && value !== undefined),
);

const apply = () => router.get(route('reports.index'), { report: props.report, ...compactFilters() }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
});

const changeReport = (report) => {
    if (report === props.report) {
        return;
    }

    router.get(route('reports.index'), { report }, {
        preserveScroll: true,
    });
};

const valueAt = (row, key) => key.split('.').reduce((value, segment) => value?.[segment], row);

const displayValue = (row, key) => {
    const value = valueAt(row, key);

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (['status', 'type', 'usage_type', 'reference_type'].includes(key)) {
        return labelForValue(value);
    }

    if (key.includes('date') || key === 'created_at') {
        const date = new Date(value);
        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: key === 'purchase_date' ? undefined : 'short' });
    }

    return value;
};

const filenameFrom = (disposition, fallback) => {
    const utf = disposition?.match(/filename\*=UTF-8''([^;]+)/i);
    const quoted = disposition?.match(/filename="?([^";]+)"?/i);
    const filename = utf?.[1] || quoted?.[1];

    return filename ? decodeURIComponent(filename) : fallback;
};

const showExportError = async (message) => {
    exportError.value = '';
    await nextTick();
    exportError.value = message;
};

const exportReport = async (format) => {
    exporting.value = format;
    exportError.value = '';

    try {
        const response = await axios.get(route('reports.export', [props.report, format]), {
            params: compactFilters(),
            responseType: 'blob',
        });
        const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/octet-stream' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filenameFrom(response.headers['content-disposition'], `laporan-${props.report}.${format}`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        await showExportError(error?.response?.status === 403
            ? 'Ekspor hanya tersedia untuk Owner.'
            : 'Ekspor gagal diproses. Coba ulangi.');
    } finally {
        exporting.value = '';
    }
};
</script>

<template>
    <AuthenticatedLayout :title="title">
        <Toast :message="exportError" type="error" />

        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ title }}</h1>
                <div class="mt-1 text-sm font-semibold text-orange-700">{{ activeReportTitle }}</div>
            </div>
            <div v-if="ownerCanExport" class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-orange-200 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!!exporting"
                    @click="exportReport('xlsx')"
                >
                    <LoaderCircle v-if="exporting === 'xlsx'" class="h-4 w-4 animate-spin" />
                    <FileSpreadsheet v-else class="h-4 w-4" />
                    {{ exporting === 'xlsx' ? 'Menyiapkan...' : 'Ekspor Excel' }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-orange-200 px-3 text-sm font-semibold text-orange-700 hover:bg-orange-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!!exporting"
                    @click="exportReport('pdf')"
                >
                    <LoaderCircle v-if="exporting === 'pdf'" class="h-4 w-4 animate-spin" />
                    <FileText v-else class="h-4 w-4" />
                    {{ exporting === 'pdf' ? 'Menyiapkan...' : 'Ekspor PDF' }}
                </button>
            </div>
        </div>

        <div class="mb-4 overflow-x-auto border-b border-orange-100">
            <div class="flex min-w-max gap-2" role="tablist" aria-label="Jenis laporan">
                <button
                    v-for="item in reports"
                    :key="item.key"
                    type="button"
                    class="h-10 rounded-t-md border border-b-0 px-3 text-sm font-semibold"
                    :class="item.key === report ? 'border-orange-200 bg-white text-orange-700' : 'border-transparent text-slate-600 hover:bg-orange-50 hover:text-orange-700'"
                    :aria-selected="item.key === report"
                    role="tab"
                    @click="changeReport(item.key)"
                >
                    {{ item.label }}
                </button>
            </div>
        </div>

        <div class="mb-4 grid gap-3 rounded-lg border border-orange-100 bg-white p-4 md:grid-cols-4 xl:grid-cols-6">
            <label v-if="requiresDateFilter" class="grid gap-1 text-xs font-semibold text-slate-600">
                Dari
                <input v-model="filter.date_from" type="date" class="h-10 rounded-md border-slate-300 text-sm" />
            </label>
            <label v-if="requiresDateFilter" class="grid gap-1 text-xs font-semibold text-slate-600">
                Sampai
                <input v-model="filter.date_to" type="date" class="h-10 rounded-md border-slate-300 text-sm" />
            </label>
            <label v-if="showCategory" class="grid gap-1 text-xs font-semibold text-slate-600">
                Kategori
                <select v-model="filter.category_id" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="category in lookups.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
            </label>
            <label v-if="showSupplier" class="grid gap-1 text-xs font-semibold text-slate-600">
                Supplier
                <select v-model="filter.supplier_id" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="supplier in lookups.suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                </select>
            </label>
            <label v-if="showMenu" class="grid gap-1 text-xs font-semibold text-slate-600">
                Menu
                <select v-model="filter.menu_item_id" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="menu in lookups.menuItems" :key="menu.id" :value="menu.id">{{ menu.name }}</option>
                </select>
            </label>
            <label v-if="showUser" class="grid gap-1 text-xs font-semibold text-slate-600">
                Pengguna
                <select v-model="filter.user_id" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="user in lookups.users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
            </label>
            <label v-if="showIngredient" class="grid gap-1 text-xs font-semibold text-slate-600">
                Bahan
                <select v-model="filter.ingredient_id" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="ingredient in lookups.ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
                </select>
            </label>
            <label v-if="showUsageType" class="grid gap-1 text-xs font-semibold text-slate-600">
                Jenis penggunaan
                <select v-model="filter.usage_type" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="type in lookups.usageTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </label>
            <label v-if="showMovementType" class="grid gap-1 text-xs font-semibold text-slate-600">
                Tipe pergerakan
                <select v-model="filter.type" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="type in lookups.movementTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
            </label>
            <label v-if="showStatus" class="grid gap-1 text-xs font-semibold text-slate-600">
                Status
                <select v-model="filter.status" class="h-10 rounded-md border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
            </label>
            <div class="flex items-end">
                <button
                    type="button"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-orange-600 px-4 text-sm font-semibold text-white hover:bg-orange-700"
                    @click="apply"
                >
                    <Filter class="h-4 w-4" />
                    Terapkan
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-orange-50 text-xs uppercase text-slate-600">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="whitespace-nowrap px-3 py-3 font-bold">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!rows.data.length">
                        <td :colspan="columns.length" class="px-3 py-10 text-center text-sm text-slate-500">Belum ada data laporan.</td>
                    </tr>
                    <tr v-for="row in rows.data" :key="row.id" class="border-t border-orange-50">
                        <td v-for="column in columns" :key="`${row.id}-${column.key}`" class="whitespace-nowrap px-3 py-3">
                            {{ displayValue(row, column.key) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <Link
                v-for="link in rows.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-md border px-3 py-1 text-sm"
                :class="{ 'bg-orange-600 text-white': link.active, 'pointer-events-none opacity-50': !link.url }"
                preserve-scroll
                v-html="link.label"
            />
        </div>
    </AuthenticatedLayout>
</template>
