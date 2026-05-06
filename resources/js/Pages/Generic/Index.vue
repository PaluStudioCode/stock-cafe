<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StockMovementDetailModal from '@/Components/StockMovementDetailModal.vue';
import { labelForValue } from '@/utilities/formatters';
import { Eye, Pencil, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ resource: String, title: String, rows: Object, filters: Object, lookups: Object, readonly: Boolean });
const showForm = ref(false);
const editingRow = ref(null);
const rowToDelete = ref(null);
const selectedMovement = ref(null);
const form = useForm({});
const search = ref(props.filters?.search || '');
const ingredientFilters = ref({
    category_id: props.filters?.category_id || '',
    supplier_id: props.filters?.supplier_id || '',
    is_active: props.filters?.is_active ?? '',
    stock_status: props.filters?.stock_status || '',
});
const stockResources = ['ingredients'];
const stockMovementFilters = ref({
    ingredient_id: props.filters?.ingredient_id || '',
    category_id: props.filters?.category_id || '',
    type: props.filters?.type || '',
    reference_type: props.filters?.reference_type || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
});
const rowList = computed(() => props.rows?.data || []);
const hiddenTableColumns = ['id','created_at','updated_at','email_verified_at','password','remember_token','ingredient_category_id','unit_id','primary_supplier_id','ingredient_id','user_id','reference_id'];
const resourceColumns = {
    'ingredient-categories': ['name','description'],
    units: ['name','symbol'],
    suppliers: ['name','phone','address','notes','is_active'],
    ingredients: ['code','name','category','unit','supplier','last_unit_cost','current_stock','minimum_stock','reorder_level','is_active'],
    'menu-items': ['code','name','category','selling_price','recipe_items_count','is_active'],
    'recipe-items': ['menu_item','ingredient','quantity_per_serving','notes'],
    'stock-movements': ['ingredient','user','type','reference_type','quantity_in','quantity_out','stock_before','stock_after','unit_cost_snapshot','notes'],
};
const keys = computed(() => resourceColumns[props.resource] || (rowList.value[0] ? Object.keys(rowList.value[0]).filter(k => !hiddenTableColumns.includes(k)) : []));
const fields = computed(() => ({
    'ingredient-categories': ['name','description'], units: ['name','symbol'], suppliers: ['name','phone','address','notes','is_active'],
    ingredients: ['code','name','ingredient_category_id','unit_id','primary_supplier_id','last_unit_cost','current_stock','minimum_stock','reorder_level','is_active'],
    'menu-items': ['name','category','selling_price','is_active'], 'recipe-items': ['menu_item_id','ingredient_id','quantity_per_serving','notes'],
    users: ['name','email','password','role','is_active'], settings: ['key','value','description'],
}[props.resource] || []));
const labels = {
    name: 'Nama',
    description: 'Deskripsi',
    symbol: 'Simbol',
    phone: 'Telepon',
    address: 'Alamat',
    notes: 'Catatan',
    is_active: 'Status',
    code: 'Kode',
    category: 'Kategori',
    ingredient_category_id: 'Kategori',
    unit: 'Satuan',
    unit_id: 'Satuan',
    supplier: 'Supplier',
    primary_supplier_id: 'Supplier utama',
    last_unit_cost: 'Harga modal terakhir',
    current_stock: 'Stok saat ini',
    minimum_stock: 'Stok minimum',
    reorder_level: 'Reorder level',
    selling_price: 'Harga jual',
    recipe_items_count: 'Jumlah bahan resep',
    menu_item: 'Menu',
    menu_item_id: 'Menu',
    ingredient: 'Bahan',
    ingredient_id: 'Bahan',
    quantity_per_serving: 'Jumlah per porsi',
    user: 'Pengguna',
    user_id: 'Pengguna',
    type: 'Tipe',
    reference_type: 'Referensi',
    quantity_in: 'Masuk',
    quantity_out: 'Keluar',
    stock_before: 'Stok sebelum',
    stock_after: 'Stok sesudah',
    unit_cost_snapshot: 'Harga snapshot',
};

const routeName = (action) => props.resource === 'users' || props.resource === 'settings'
    ? `admin.${action}`
    : ['menu-items','recipe-items'].includes(props.resource)
        ? `menu.${action}`
        : `data.${action}`;
const fieldValue = (row, field) => field === 'password' ? '' : row?.[field] ?? (field === 'is_active' ? true : '');
const openForm = (row = null) => {
    editingRow.value = row;
    fields.value.forEach((field) => {
        form[field] = fieldValue(row, field);
    });
    form.clearErrors();
    showForm.value = true;
};
const closeForm = () => {
    showForm.value = false;
    editingRow.value = null;
    form.clearErrors();
};
const submit = () => {
    const options = {
        forceFormData: true,
        onSuccess: closeForm,
        onFinish: () => form.transform((data) => data),
    };
    if (editingRow.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route(routeName('update'), [props.resource, editingRow.value.id]), options);
        return;
    }

    form.post(route(routeName('store'), props.resource), options);
};
const destroyRow = (id) => {
    const name = routeName('destroy');
    router.delete(route(name, [props.resource, id]), {
        onFinish: () => rowToDelete.value = null,
    });
};
const compact = (params) => Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
const doSearch = () => {
    const params = stockResources.includes(props.resource)
        ? { search: search.value, ...ingredientFilters.value }
        : props.resource === 'stock-movements'
            ? { search: search.value, ...stockMovementFilters.value }
        : { search: search.value };

    router.get(window.location.pathname, compact(params), { preserveState: true });
};
const labelFor = (key) => labels[key] || key.replaceAll('_', ' ');
const display = (row, key) => {
    const value = row[key];

    if (key === 'is_active') {
        return value ? 'Aktif' : 'Nonaktif';
    }
    if (typeof value === 'boolean') {
        return value ? 'Ya' : 'Tidak';
    }
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    if (typeof value === 'object') {
        return value.name || value.email || value.code || value.symbol || '-';
    }
    if (['status', 'type', 'reference_type', 'role', 'action', 'module'].includes(key)) {
        return labelForValue(value);
    }

    return value;
};
const optionLabel = (opt, field) => {
    if (field === 'ingredient_id') {
        return `${opt.name} (${opt.unit?.symbol || '-'})`;
    }

    return opt.name;
};
</script>

<template>
    <AuthenticatedLayout :title="title">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold">{{ title }}</h1>
            </div>
            <button v-if="!readonly && fields.length" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700" @click="openForm()">
                <Plus class="h-4 w-4" /> Tambah
            </button>
        </div>

        <div class="mb-4 flex gap-2">
            <input v-model="search" class="w-full rounded-md border-slate-300 focus:border-orange-500 focus:ring-orange-500 sm:max-w-sm" placeholder="Cari data" @keyup.enter="doSearch" />
            <button class="rounded-md border border-orange-200 bg-white p-2 text-orange-700" aria-label="Cari" @click="doSearch"><Search class="h-5 w-5" /></button>
        </div>

        <div v-if="stockResources.includes(resource)" class="mb-4 grid gap-2 md:grid-cols-4">
            <select v-model="ingredientFilters.category_id" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua kategori</option>
                <option v-for="category in lookups.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select v-model="ingredientFilters.supplier_id" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua supplier</option>
                <option v-for="supplier in lookups.suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <select v-model="ingredientFilters.is_active" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>
            <select v-if="resource === 'ingredients'" v-model="ingredientFilters.stock_status" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua stok</option>
                <option value="safe">Aman</option>
                <option value="low">Menipis</option>
                <option value="out">Habis</option>
            </select>
        </div>

        <div v-if="resource === 'stock-movements'" class="mb-4 grid gap-2 md:grid-cols-6">
            <select v-model="stockMovementFilters.ingredient_id" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua bahan</option>
                <option v-for="ingredient in lookups.ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
            </select>
            <select v-model="stockMovementFilters.category_id" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua kategori</option>
                <option v-for="category in lookups.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
            <select v-model="stockMovementFilters.type" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua tipe</option>
                <option v-for="type in lookups.movementTypes" :key="type" :value="type">{{ labelForValue(type) }}</option>
            </select>
            <select v-model="stockMovementFilters.reference_type" class="rounded-md border-slate-300 text-sm" @change="doSearch">
                <option value="">Semua referensi</option>
                <option v-for="type in lookups.referenceTypes" :key="type" :value="type">{{ labelForValue(type) }}</option>
            </select>
            <input v-model="stockMovementFilters.date_from" type="date" class="rounded-md border-slate-300 text-sm" @change="doSearch" />
            <input v-model="stockMovementFilters.date_to" type="date" class="rounded-md border-slate-300 text-sm" @change="doSearch" />
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 p-4" @click.self="closeForm">
            <form class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-5 shadow-xl" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">{{ editingRow ? 'Ubah data' : 'Tambah data' }}</h2>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup form" title="Tutup form" @click="closeForm"><X class="h-4 w-4" /></button>
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <div v-for="field in fields" :key="field">
                        <label class="text-xs font-bold uppercase text-slate-500">{{ labelFor(field) }}</label>
                        <select v-if="field === 'role'" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300">
                            <option v-for="role in lookups.roles" :key="role" :value="role">{{ labelForValue(role) }}</option>
                        </select>
                        <select v-else-if="resource === 'settings' && field === 'key'" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300">
                            <option v-for="key in lookups.settingKeys" :key="key" :value="key">{{ key }}</option>
                        </select>
                        <select v-else-if="resource === 'menu-items' && field === 'category'" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300">
                            <option value="">Pilih kategori</option>
                            <option v-for="category in lookups.menuCategories" :key="category" :value="category">{{ category }}</option>
                        </select>
                        <select v-else-if="field.endsWith('_id')" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300">
                            <option :value="null">Pilih</option>
                            <option v-for="opt in (field.includes('category') ? lookups.categories : field.includes('unit') ? lookups.units : field.includes('supplier') ? lookups.suppliers : field.includes('menu') ? lookups.menus : lookups.ingredients)" :key="opt.id" :value="opt.id">{{ optionLabel(opt, field) }}</option>
                        </select>
                        <select v-else-if="field === 'is_active'" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300"><option :value="true">Aktif</option><option :value="false">Nonaktif</option></select>
                        <textarea v-else-if="['description','address','notes','value'].includes(field)" v-model="form[field]" class="mt-1 w-full rounded-md border-slate-300" rows="2" />
                        <input v-else v-model="form[field]" :type="field === 'password' ? 'password' : 'text'" class="mt-1 w-full rounded-md border-slate-300" />
                        <div v-if="form.errors[field]" class="mt-1 text-xs text-red-600">{{ form.errors[field] }}</div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="closeForm">Batal</button>
                    <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700" :disabled="form.processing">Simpan</button>
                </div>
            </form>
        </div>

        <section class="overflow-hidden rounded-lg border border-orange-100 bg-white shadow-sm">
            <EmptyState v-if="!rowList.length" title="Belum ada data" message="Data akan tampil setelah tersedia atau filter diubah." />
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="border-b bg-stone-50 text-left text-xs uppercase text-slate-500"><th v-for="key in keys" :key="key" class="px-3 py-3">{{ labelFor(key) }}</th><th v-if="!readonly || resource === 'stock-movements'" class="px-3 py-3">Aksi</th></tr></thead>
                    <tbody>
                        <tr v-for="row in rowList" :key="row.id" class="border-b last:border-0">
                            <td v-for="key in keys" :key="key" class="max-w-xs truncate px-3 py-3">{{ display(row, key) }}</td>
                            <td v-if="!readonly || resource === 'stock-movements'" class="px-3 py-3">
                                <button v-if="resource === 'stock-movements'" class="rounded-md p-2 text-orange-700 hover:bg-orange-50" aria-label="Detail" title="Detail" @click="selectedMovement = row"><Eye class="h-4 w-4" /></button>
                                <button v-if="!readonly" class="rounded-md p-2 text-orange-700 hover:bg-orange-50" aria-label="Ubah" title="Ubah" @click="openForm(row)"><Pencil class="h-4 w-4" /></button>
                                <button v-if="!readonly" class="rounded-md p-2 text-red-600 hover:bg-red-50" aria-label="Hapus" title="Hapus" @click="rowToDelete = row"><Trash2 class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Pagination :links="rows.links" />
        <DeleteConfirmationModal
            :show="!!rowToDelete"
            :name="rowToDelete?.name || rowToDelete?.code || rowToDelete?.email || 'data ini'"
            @close="rowToDelete = null"
            @confirm="destroyRow(rowToDelete.id)"
        />
        <StockMovementDetailModal :show="!!selectedMovement" :movement="selectedMovement" @close="selectedMovement = null" />
    </AuthenticatedLayout>
</template>
