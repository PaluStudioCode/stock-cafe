<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { labelForValue } from '@/utilities/formatters';
import { router, useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({ logs: Object, menus: Array, canCreate: Boolean, canCancel: Boolean });
const showForm = ref(false);
const form = useForm({ menu_item_id: null, quantity: 1, production_date: new Date().toISOString().slice(0, 16), notes: '' });
const selectedMenu = computed(() => props.menus.find((menu) => menu.id === form.menu_item_id));
const requiredItems = computed(() => selectedMenu.value?.recipe_items?.map((item) => ({
    id: item.id,
    name: item.ingredient?.name,
    unit: item.ingredient?.unit?.symbol,
    required: Number(item.quantity_per_serving) * Number(form.quantity || 0),
    stock: Number(item.ingredient?.current_stock || 0),
})) || []);

const resetForm = () => {
    form.reset();
    form.menu_item_id = null;
    form.quantity = 1;
    form.production_date = new Date().toISOString().slice(0, 16);
    form.notes = '';
    form.clearErrors();
};
const openCreate = () => {
    resetForm();
    showForm.value = true;
};
const closeForm = () => {
    showForm.value = false;
    resetForm();
};
const submit = () => form.post(route('production-logs.store'), { preserveScroll: true, onSuccess: closeForm });
const cancelLog = (id) => confirm('Batalkan catatan produksi dan kembalikan stok?') && router.post(route('production-logs.cancel', id));
</script>

<template>
    <AuthenticatedLayout title="Catatan Produksi">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Catatan Produksi</h1>
            <button v-if="canCreate" type="button" class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-700" @click="openCreate">
                <Plus class="h-4 w-4" /> Tambah
            </button>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <form class="max-h-[90vh] overflow-y-auto p-5" @submit.prevent="submit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-slate-800">Tambah Catatan Produksi</h2>
                    <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup form" title="Tutup form" @click="closeForm">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <select v-model="form.menu_item_id" class="rounded-md border-slate-300">
                        <option :value="null">Pilih menu</option>
                        <option v-for="menu in menus" :key="menu.id" :value="menu.id">{{ menu.name }}</option>
                    </select>
                    <input v-model="form.quantity" type="number" step="0.001" min="0.001" class="rounded-md border-slate-300" placeholder="Jumlah" />
                    <input v-model="form.production_date" type="datetime-local" class="rounded-md border-slate-300" />
                    <input v-model="form.notes" class="rounded-md border-slate-300" placeholder="Catatan" />
                </div>
                <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-4">
                    <div>{{ form.errors.menu_item_id }}</div>
                    <div>{{ form.errors.quantity }}</div>
                    <div>{{ form.errors.production_date }}</div>
                    <div>{{ form.errors.notes || form.errors.stock }}</div>
                </div>

                <div v-if="selectedMenu" class="mt-4 rounded-md bg-stone-50 p-3 text-sm">
                    <div class="mb-2 font-semibold">Kebutuhan bahan</div>
                    <div v-for="item in requiredItems" :key="item.id" :class="item.required > item.stock ? 'text-red-600' : 'text-slate-700'">
                        {{ item.name }}: {{ item.required.toFixed(3) }} {{ item.unit }}
                        <span class="text-xs text-slate-500">(stok {{ item.stock.toFixed(3) }})</span>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="closeForm">Batal</button>
                    <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Selesaikan' }}
                    </button>
                </div>
            </form>
        </Modal>

        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-stone-50 text-left">
                        <th class="p-3">Kode</th>
                        <th>Menu</th>
                        <th>Pengguna</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th v-if="canCancel">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!logs.data.length">
                        <td :colspan="canCancel ? 6 : 5" class="p-6 text-center text-sm text-slate-500">Belum ada catatan produksi.</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id" class="border-b">
                        <td class="p-3 font-semibold">{{ log.production_code }}</td>
                        <td>{{ log.menu_item?.name || '-' }}</td>
                        <td>{{ log.user?.name || '-' }}</td>
                        <td>{{ log.quantity }}</td>
                        <td>{{ labelForValue(log.status) }}</td>
                        <td v-if="canCancel">
                            <button v-if="log.status === 'completed'" class="text-red-600" @click="cancelLog(log.id)">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
