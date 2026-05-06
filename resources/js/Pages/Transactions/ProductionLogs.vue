<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ logs: Object, menus: Array, canCreate: Boolean, canCancel: Boolean });
const form = useForm({ menu_item_id: null, quantity: 1, production_date: new Date().toISOString().slice(0,16), notes: '' });
const selectedMenu = computed(() => props.menus.find(m => m.id === form.menu_item_id));
const requiredItems = computed(() => selectedMenu.value?.recipe_items?.map((item) => ({
    id: item.id,
    name: item.ingredient?.name,
    unit: item.ingredient?.unit?.symbol,
    required: Number(item.quantity_per_serving) * Number(form.quantity || 0),
    stock: Number(item.ingredient?.current_stock || 0),
})) || []);
const submit = () => form.post(route('production-logs.store'), { onSuccess: () => form.reset('notes') });
const cancelLog = (id) => confirm('Batalkan production log dan kembalikan stok?') && router.post(route('production-logs.cancel', id));
</script>

<template>
    <AuthenticatedLayout title="Production Log">
        <h1 class="mb-4 text-2xl font-bold">Production Log</h1>
        <form v-if="canCreate" class="mb-6 rounded-lg border border-orange-100 bg-white p-4 shadow-sm" @submit.prevent="submit">
            <div class="grid gap-3 md:grid-cols-4">
                <select v-model="form.menu_item_id" class="rounded-md border-slate-300"><option :value="null">Menu</option><option v-for="m in menus" :key="m.id" :value="m.id">{{ m.name }}</option></select>
                <input v-model="form.quantity" type="number" step="0.001" min="0.001" class="rounded-md border-slate-300" />
                <input v-model="form.production_date" type="datetime-local" class="rounded-md border-slate-300" />
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Complete</button>
            </div>
            <div class="mt-1 grid gap-2 text-xs text-red-600 md:grid-cols-4">
                <div>{{ form.errors.menu_item_id }}</div>
                <div>{{ form.errors.quantity }}</div>
                <div>{{ form.errors.production_date }}</div>
                <div>{{ form.errors.stock }}</div>
            </div>
            <div v-if="selectedMenu" class="mt-4 rounded-md bg-stone-50 p-3 text-sm">
                <div class="mb-2 font-semibold">Kebutuhan bahan</div>
                <div v-for="r in requiredItems" :key="r.id" :class="r.required > r.stock ? 'text-red-600' : 'text-slate-700'">{{ r.name }}: {{ r.required.toFixed(3) }} {{ r.unit }} <span class="text-xs text-slate-500">(stok {{ r.stock.toFixed(3) }})</span></div>
            </div>
        </form>
        <div class="overflow-x-auto rounded-lg border border-orange-100 bg-white"><table class="min-w-full text-sm"><thead><tr class="border-b bg-stone-50 text-left"><th class="p-3">Kode</th><th>Menu</th><th>User</th><th>Qty</th><th>Status</th><th v-if="canCancel">Aksi</th></tr></thead><tbody><tr v-for="l in logs.data" :key="l.id" class="border-b"><td class="p-3 font-semibold">{{ l.production_code }}</td><td>{{ l.menu_item?.name }}</td><td>{{ l.user?.name }}</td><td>{{ l.quantity }}</td><td>{{ l.status }}</td><td v-if="canCancel"><button v-if="l.status === 'completed'" class="text-red-600" @click="cancelLog(l.id)">Cancel</button></td></tr></tbody></table></div>
    </AuthenticatedLayout>
</template>
