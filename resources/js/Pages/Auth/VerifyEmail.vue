<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ status: String });
const form = useForm({});
const submit = () => form.post(route('verification.send'));
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <div class="mb-5">
            <h1 class="text-xl font-bold text-slate-900">Verifikasi Email</h1>
            <p class="mt-1 text-sm text-slate-500">Cek email untuk mengaktifkan akses akun CafeStock.</p>
        </div>

        <div v-if="verificationLinkSent" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            Link verifikasi baru sudah dikirim.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between gap-3">
                <button class="rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Mengirim...' : 'Kirim Ulang' }}
                </button>
                <Link :href="route('logout')" method="post" as="button" class="text-sm font-medium text-orange-700 hover:text-orange-900">Logout</Link>
            </div>
        </form>
    </GuestLayout>
</template>
