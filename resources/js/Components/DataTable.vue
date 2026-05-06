<script setup>
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    emptyTitle: { type: String, default: 'Belum ada data' },
});
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-orange-100 bg-white shadow-sm">
        <EmptyState v-if="!rows.length" :title="emptyTitle" />
        <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-stone-50 text-left text-xs uppercase text-slate-500">
                        <th v-for="column in columns" :key="column.key" class="px-3 py-3">{{ column.label }}</th>
                        <th v-if="$slots.actions" class="px-3 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-b last:border-0">
                        <td v-for="column in columns" :key="column.key" class="max-w-xs truncate px-3 py-3">
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="px-3 py-3">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
