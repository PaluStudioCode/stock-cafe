<template>
    <div class="grid min-h-screen bg-stone-50 lg:grid-cols-[1fr_480px]">
        <section class="hidden bg-[url('https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=1600&q=80')] bg-cover bg-center lg:block">
            <div class="flex h-full flex-col justify-end bg-slate-950/45 p-12 text-white">
                <h1 class="text-5xl font-black">CafeStock</h1>
                <p class="mt-4 max-w-lg text-lg text-orange-50">Pantau bahan baku, cegah kehabisan stok, dan jaga operasional cafe tetap rapi.</p>
            </div>
        </section>
        <section class="flex items-center justify-center px-6 py-10">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <div class="grid h-12 w-12 place-items-center rounded-lg bg-orange-600 text-xl font-black text-white">CS</div>
                    <h2 class="mt-5 text-2xl font-bold text-slate-900">Masuk ke CafeStock</h2>
                    <p class="mt-1 text-sm text-slate-500">Gunakan akun demo dari seed data.</p>
                </div>
                <div class="rounded-lg border border-orange-100 bg-white p-6 shadow-sm">
                    <slot />
                </div>
            </div>
        </section>
    </div>
</template>
