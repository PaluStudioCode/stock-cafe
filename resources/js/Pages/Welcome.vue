<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});
</script>

<template>
    <Head title="CafeStock" />

    <main class="grid min-h-screen place-items-center bg-stone-50 px-4 text-slate-900">
        <section class="w-full max-w-md rounded-lg border border-orange-100 bg-white p-6 shadow-sm">
            <div class="mb-6 grid h-12 w-12 place-items-center rounded-lg bg-orange-600 text-lg font-black text-white">CS</div>
            <h1 class="text-2xl font-black">CafeStock</h1>
            <p class="mt-2 text-sm text-slate-500">Sistem inventori cafe realtime untuk memantau bahan, transaksi stok, dan laporan operasional.</p>

            <div v-if="canLogin" class="mt-6 flex flex-wrap gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700"
                >
                    Buka Dasbor
                </Link>
                <template v-else>
                    <Link :href="route('login')" class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Masuk</Link>
                    <Link v-if="canRegister" :href="route('register')" class="rounded-md border border-orange-200 px-4 py-2 text-sm font-semibold text-orange-700 hover:bg-orange-50">Daftar</Link>
                </template>
            </div>
        </section>
    </main>
</template>
