<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    creatorSlug: {
        type: String,
        default: 'alex-dev',
    },
});
</script>

<template>
    <Head title="Sistema de Reservas 1 a 1" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50/40 flex flex-col justify-between">
        <header class="border-b border-slate-200/80 bg-white/80 backdrop-blur">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-sm">
                        1:1
                    </div>
                    <span class="font-semibold text-lg tracking-tight text-slate-900">
                        eSponsor <span class="text-indigo-600">Reservas</span>
                    </span>
                </div>
                <nav class="flex items-center gap-3">
                    <Link
                        :href="`/@${creatorSlug}`"
                        class="text-sm font-medium text-slate-600 hover:text-indigo-600 px-3 py-2 rounded-lg transition"
                    >
                        Vista Pública (Seguidor)
                    </Link>
                    <Link
                        href="/dashboard"
                        class="text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-xl shadow-sm transition"
                    >
                        Panel del Creador
                    </Link>
                </nav>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 mb-6">
                MVP • Laravel 12 + Vue 3 (&lt;script setup&gt;) + Inertia.js + UTC Strict
            </span>
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900 mb-6">
                Sesiones 1 a 1 para creadores sin fricción de zonas horarias
            </h1>
            <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-10">
                El creador configura su disponibilidad en su zona horaria local, el seguidor reserva en la suya y cada bloque se valida y almacena estrictamente en UTC con bloqueo pesimista.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-4">
                <Link
                    href="/dashboard"
                    class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm transition"
                >
                    Ir al Dashboard del Creador
                </Link>
                <Link
                    :href="`/@${creatorSlug}`"
                    class="px-6 py-3 rounded-xl bg-white hover:bg-slate-50 text-slate-800 font-semibold border border-slate-200 shadow-sm transition"
                >
                    Explorar Página Pública del Seguidor
                </Link>
            </div>
        </main>

        <footer class="border-t border-slate-200/70 py-6 text-center text-xs text-slate-500">
            eSponsor Reservas • Arquitectura Modular (Actions &amp; Services) • UTC &amp; Pessimistic Locking
        </footer>
    </div>
</template>

