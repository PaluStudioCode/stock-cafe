<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Boxes, ChevronDown, ClipboardList, Coffee, Gauge, LogOut, Menu, PackageSearch,
    ReceiptText, ShieldCheck, Users, X
} from 'lucide-vue-next';
import Toast from '@/Components/Toast.vue';

defineProps({ title: { type: String, default: 'CafeStock' } });

const page = usePage();
const mobileOpen = ref(false);
const expanded = ref({});
const user = computed(() => page.props.auth.user);
const role = computed(() => user.value?.role);

const groups = computed(() => {
    const commonMonitoring = [
        { label: 'Stok Bahan', href: route('monitoring.index', 'ingredients') },
        { label: 'Stok Menipis', href: route('monitoring.index', 'low-stock') },
        { label: 'Stok Habis', href: route('monitoring.index', 'out-of-stock') },
    ];
    const ownerReports = [
        ['Laporan Stok', 'stock'], ['Laporan Pembelian', 'purchases'], ['Laporan Produksi', 'production'],
        ['Laporan Waste', 'waste'], ['Laporan Stock Movement', 'stock-movement'], ['Nilai Persediaan', 'inventory-value'],
    ].map(([label, key]) => ({ label, href: route('reports.index', key) }));

    if (role.value === 'barista') {
        return [
            { label: 'Dashboard', icon: Gauge, href: route('dashboard') },
            { label: 'Menu dan Resep', icon: Coffee, children: [
                { label: 'Menu Item', href: route('menu.index', 'menu-items') },
                { label: 'Resep Menu', href: route('menu.index', 'recipe-items') },
            ] },
            { label: 'Produksi', icon: ClipboardList, children: [{ label: 'Production Log', href: route('production-logs.index') }] },
            { label: 'Monitoring Stok', icon: PackageSearch, children: commonMonitoring },
        ];
    }

    const base = [
        { label: 'Dashboard', icon: Gauge, href: route('dashboard') },
        { label: 'Master Data', icon: Boxes, children: [
            { label: 'Kategori Bahan', href: route('data.index', 'ingredient-categories') },
            { label: 'Satuan', href: route('data.index', 'units') },
            { label: 'Supplier', href: route('data.index', 'suppliers') },
            { label: 'Bahan Baku', href: route('data.index', 'ingredients') },
        ] },
        { label: 'Transaksi Stok', icon: ClipboardList, children: [
            { label: 'Purchase Order', href: route('purchase-orders.index') },
            { label: 'Production Log', href: route('production-logs.index') },
            { label: 'Stock Usage', href: route('stock-usages.index') },
            { label: 'Stock Adjustment', href: route('stock-adjustments.index') },
        ] },
        { label: 'Monitoring Stok', icon: PackageSearch, children: [...commonMonitoring, { label: 'Stock Movement', href: route('monitoring.index', 'stock-movements') }] },
        { label: 'Laporan', icon: ReceiptText, children: role.value === 'owner' ? ownerReports : [{ label: 'Laporan Stok', href: route('reports.index', 'stock') }] },
    ];

    if (role.value === 'owner') {
        base.splice(2, 0, { label: 'Menu dan Resep', icon: Coffee, children: [
            { label: 'Menu Item', href: route('menu.index', 'menu-items') },
            { label: 'Resep Menu', href: route('menu.index', 'recipe-items') },
        ] });
        base.push({ label: 'Administrasi', icon: Users, children: [
            { label: 'Pengguna', href: route('admin.index', 'users') },
            { label: 'Settings', href: route('admin.index', 'settings') },
            { label: 'Activity Log', href: route('admin.index', 'activity-logs') },
        ] });
    }

    return base;
});

const currentPath = computed(() => page.url.split('?')[0]);
const pathOf = (href) => new URL(href, window.location.origin).pathname;
const isActive = (href) => currentPath.value === pathOf(href);
const groupIsActive = (group) => group.children?.some((item) => isActive(item.href));
const logout = () => router.post(route('logout'));

watchEffect(() => {
    groups.value.forEach((group) => {
        if (group.children && groupIsActive(group) && expanded.value[group.label] === undefined) {
            expanded.value[group.label] = true;
        }
    });
});

const breadcrumbs = computed(() => {
    if (route().current('dashboard')) {
        return [{ label: 'Dashboard', href: route('dashboard'), active: true }];
    }

    for (const group of groups.value) {
        if (group.href && isActive(group.href)) {
            return [
                { label: 'Dashboard', href: route('dashboard') },
                { label: group.label, active: true },
            ];
        }
        const child = group.children?.find((item) => isActive(item.href));
        if (child) {
            return [
                { label: 'Dashboard', href: route('dashboard') },
                { label: group.label },
                { label: child.label, active: true },
            ];
        }
    }

    return [{ label: 'Dashboard', href: route('dashboard') }, { label: 'Halaman', active: true }];
});
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-stone-50 text-slate-900">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-72 border-r border-orange-100 bg-white transition-transform lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-orange-100 px-5">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-orange-600 text-lg font-black text-white">CS</div>
                <div class="min-w-0">
                    <div class="font-bold">CafeStock</div>
                    <div class="truncate text-xs text-slate-500">Realtime Inventory</div>
                </div>
                <button class="ml-auto rounded-md p-2 lg:hidden" aria-label="Tutup menu" title="Tutup menu" @click="mobileOpen = false">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <nav class="h-[calc(100vh-4rem)] space-y-2 overflow-y-auto p-4">
                <div v-for="group in groups" :key="group.label">
                    <Link
                        v-if="group.href"
                        :href="group.href"
                        class="flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium hover:bg-orange-50"
                        :class="{ 'bg-orange-100 text-orange-800': isActive(group.href) }"
                        @click="mobileOpen = false"
                    >
                        <component :is="group.icon" class="h-4 w-4" />{{ group.label }}
                    </Link>

                    <div v-else>
                        <button
                            type="button"
                            class="flex h-10 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-semibold hover:bg-orange-50"
                            :class="{ 'text-orange-800': groupIsActive(group) }"
                            :aria-expanded="!!expanded[group.label]"
                            @click="expanded[group.label] = !expanded[group.label]"
                        >
                            <component :is="group.icon" class="h-4 w-4" />
                            <span class="min-w-0 flex-1 truncate">{{ group.label }}</span>
                            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expanded[group.label] }" />
                        </button>

                        <div v-show="expanded[group.label]" class="mt-1 space-y-1">
                            <Link
                                v-for="item in group.children"
                                :key="item.href"
                                :href="item.href"
                                class="block h-9 rounded-md px-10 py-2 text-sm hover:bg-orange-50"
                                :class="{ 'bg-orange-100 font-semibold text-orange-800': isActive(item.href) }"
                                @click="mobileOpen = false"
                            >
                                {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-orange-100 bg-white/95 px-4 backdrop-blur">
                <button class="rounded-md p-2 lg:hidden" aria-label="Buka menu" title="Buka menu" @click="mobileOpen = true">
                    <Menu class="h-5 w-5" />
                </button>

                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold">{{ title }}</div>
                    <nav v-if="!route().current('dashboard')" class="flex min-w-0 items-center gap-1 text-xs text-slate-500" aria-label="Breadcrumb">
                        <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.label}-${index}`">
                            <span v-if="index" class="text-slate-300">/</span>
                            <Link v-if="crumb.href && !crumb.active" :href="crumb.href" class="truncate hover:text-orange-700">{{ crumb.label }}</Link>
                            <span v-else class="truncate" :class="{ 'font-semibold text-orange-700': crumb.active }">{{ crumb.label }}</span>
                        </template>
                    </nav>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <ShieldCheck class="h-5 w-5 text-orange-600" />
                    <div class="hidden text-right sm:block">
                        <div class="text-sm font-semibold">{{ user?.name }}</div>
                        <div class="text-xs capitalize text-slate-500">{{ user?.role?.replace('_', ' ') }}</div>
                    </div>
                    <button class="rounded-md border border-orange-200 p-2 text-slate-600 hover:bg-orange-50" aria-label="Logout" title="Logout" @click="logout">
                        <LogOut class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <Toast :message="$page.props.flash?.success" type="success" />
                <Toast :message="$page.props.flash?.error" type="error" />
                <slot />
            </main>
        </div>
    </div>
</template>
