<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: { type: Array, default: () => [] } });
</script>

<template>
    <nav v-if="links.length" class="mt-4 flex flex-wrap gap-2" aria-label="Pagination">
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url || '#'"
            class="rounded-md border px-3 py-1 text-sm"
            :class="{ 'bg-orange-600 text-white': link.active, 'pointer-events-none text-slate-400': !link.url }"
            v-html="link.label"
        />
    </nav>
</template>
