<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    creator: {
        type: Object,
        required: true,
    },
    sessionTypes: {
        type: Array,
        default: () => [],
    },
    weeklySchedule: {
        type: Array,
        default: () => [],
    },
    exceptions: {
        type: Array,
        default: () => [],
    },
    bookings: {
        type: Array,
        default: () => [],
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const activeTab = ref('bookings'); // 'bookings' | 'sessions' | 'schedule'
const bookingFilter = ref('upcoming'); // 'upcoming' | 'all'
const copiedLink = ref(false);

const upcomingBookings = computed(() =>
    props.bookings.filter((b) => b.status === 'confirmed' && b.is_upcoming)
);

const filteredBookings = computed(() => {
    if (bookingFilter.value === 'upcoming') {
        return upcomingBookings.value;
    }
    return props.bookings;
});

const activeSessionTypesCount = computed(() =>
    props.sessionTypes.filter((s) => s.is_active).length
);

const enabledDaysCount = computed(() =>
    props.weeklySchedule.filter((d) => d.is_enabled).length
);

function copyPublicLink() {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(props.creator.public_url);
        copiedLink.value = true;
        setTimeout(() => {
            copiedLink.value = false;
        }, 2200);
    }
}

// --- CRUD Tipos de Sesión ---
const editingSessionUuid = ref(null);
const sessionForm = useForm({
    title: '',
    duration_minutes: 30,
    description: '',
    is_active: true,
});

const durationPresets = [15, 30, 45, 60, 90];

function startEditSession(session) {
    editingSessionUuid.value = session.uuid;
    sessionForm.title = session.title;
    sessionForm.duration_minutes = session.duration_minutes;
    sessionForm.description = session.description || '';
    sessionForm.is_active = session.is_active;
    activeTab.value = 'sessions';
}

function resetSessionForm() {
    editingSessionUuid.value = null;
    sessionForm.reset();
    sessionForm.clearErrors();
}

function submitSessionForm() {
    if (editingSessionUuid.value) {
        sessionForm.put(`/dashboard/session-types/${editingSessionUuid.value}`, {
            preserveScroll: true,
            onSuccess: () => resetSessionForm(),
        });
    } else {
        sessionForm.post('/dashboard/session-types', {
            preserveScroll: true,
            onSuccess: () => resetSessionForm(),
        });
    }
}

function toggleSessionActive(session) {
    router.put(
        `/dashboard/session-types/${session.uuid}`,
        {
            title: session.title,
            duration_minutes: session.duration_minutes,
            description: session.description,
            is_active: !session.is_active,
        },
        { preserveScroll: true }
    );
}

function deleteSession(session) {
    router.delete(`/dashboard/session-types/${session.uuid}`, {
        preserveScroll: true,
    });
}

// --- Horario Semanal y Zona Horaria ---
const scheduleForm = useForm({
    timezone: props.creator.timezone,
    days: props.weeklySchedule.map((day) => ({
        day_of_week: day.day_of_week,
        day_name: day.day_name,
        start_time: day.start_time,
        end_time: day.end_time,
        is_enabled: day.is_enabled,
    })),
});

function saveWeeklySchedule() {
    scheduleForm.put('/dashboard/schedule/weekly', {
        preserveScroll: true,
    });
}

// --- Excepciones por Fecha ---
const exceptionForm = useForm({
    exception_date: '',
    is_available: false,
    start_time: '10:00',
    end_time: '14:00',
    reason: '',
});

function submitExceptionForm() {
    exceptionForm.post('/dashboard/schedule/exceptions', {
        preserveScroll: true,
        onSuccess: () => {
            exceptionForm.reset('exception_date', 'reason');
        },
    });
}

function removeException(exception) {
    router.delete(`/dashboard/schedule/exceptions/${exception.uuid}`, {
        preserveScroll: true,
    });
}

// --- Cancelar Reserva ---
function cancelBooking(booking) {
    router.patch(`/dashboard/bookings/${booking.uuid}/cancel`, {}, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Panel del Creador — eSponsor Reservas" />

    <div class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
        <!-- Top Navigation -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex items-center gap-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-sm group-hover:bg-indigo-700 transition">
                            1:1
                        </div>
                        <div>
                            <span class="font-semibold text-base tracking-tight text-slate-900">
                                eSponsor <span class="text-indigo-600">Creator Hub</span>
                            </span>
                            <span class="hidden sm:inline-block ml-2 text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-medium">
                                {{ creator.timezone }}
                            </span>
                        </div>
                    </Link>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="copyPublicLink"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition cursor-pointer"
                    >
                        <span>{{ copiedLink ? '¡Enlace copiado!' : 'Copiar link público' }}</span>
                    </button>

                    <Link
                        :href="`/@${creator.username}`"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition"
                    >
                        <span>Ver Página Pública</span>
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 space-y-8">
            <!-- Flash Notifications -->
            <div
                v-if="flashSuccess"
                class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2 font-medium">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ flashSuccess }}</span>
                </div>
            </div>

            <div
                v-if="flashError"
                class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2 font-medium">
                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- Creator Hero Card & KPIs -->
            <section class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-2xs">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="flex items-start sm:items-center gap-4">
                        <img
                            :src="creator.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(creator.name)"
                            :alt="creator.name"
                            class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shrink-0"
                        />
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ creator.name }}</h1>
                                <span class="text-xs font-medium px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    @{{ creator.username }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 mt-1 max-w-2xl">{{ creator.bio }}</p>
                            <p class="text-xs text-slate-500 mt-1.5">
                                Zona horaria del creador: <strong class="text-slate-700">{{ creator.timezone }}</strong> • Todas las reservas se validan y persisten en <strong>UTC</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 shrink-0">
                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium">Próximas</p>
                            <p class="text-2xl font-bold text-indigo-600 mt-0.5">{{ upcomingBookings.length }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium">Tipos Activos</p>
                            <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ activeSessionTypesCount }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium">Días Hábiles</p>
                            <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ enabledDaysCount }}/7</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200/80 px-4 py-3">
                            <p class="text-xs text-slate-500 font-medium">Excepciones</p>
                            <p class="text-2xl font-bold text-slate-900 mt-0.5">{{ exceptions.length }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Navigation Tabs -->
            <div class="border-b border-slate-200">
                <nav class="flex flex-wrap gap-2 -mb-px" aria-label="Secciones del panel">
                    <button
                        type="button"
                        @click="activeTab = 'bookings'"
                        :class="[
                            'px-4 py-3 text-sm font-semibold border-b-2 transition cursor-pointer',
                            activeTab === 'bookings'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300',
                        ]"
                    >
                        Reservas Pendientes / Próximas ({{ upcomingBookings.length }})
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'sessions'"
                        :class="[
                            'px-4 py-3 text-sm font-semibold border-b-2 transition cursor-pointer',
                            activeTab === 'sessions'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300',
                        ]"
                    >
                        Tipos de Sesión 1 a 1 ({{ sessionTypes.length }})
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'schedule'"
                        :class="[
                            'px-4 py-3 text-sm font-semibold border-b-2 transition cursor-pointer',
                            activeTab === 'schedule'
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300',
                        ]"
                    >
                        Horario Semanal y Excepciones
                    </button>
                </nav>
            </div>

            <!-- TAB 1: RESERVAS PENDIENTES / PRÓXIMAS -->
            <section v-if="activeTab === 'bookings'" class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Agenda de Sesiones 1 a 1</h2>
                        <p class="text-sm text-slate-600">
                            Visualiza tus próximas reservas convertidas a tu zona horaria local ({{ creator.timezone }}) y su registro exacto en UTC.
                        </p>
                    </div>
                    <div class="inline-flex rounded-xl bg-white border border-slate-200 p-1 self-start">
                        <button
                            type="button"
                            @click="bookingFilter = 'upcoming'"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                                bookingFilter === 'upcoming'
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-slate-600 hover:text-slate-900',
                            ]"
                        >
                            Próximas confirmadas ({{ upcomingBookings.length }})
                        </button>
                        <button
                            type="button"
                            @click="bookingFilter = 'all'"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer',
                                bookingFilter === 'all'
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-slate-600 hover:text-slate-900',
                            ]"
                        >
                            Todas ({{ bookings.length }})
                        </button>
                    </div>
                </div>

                <div
                    v-if="filteredBookings.length === 0"
                    class="bg-white rounded-2xl border border-slate-200 p-10 text-center space-y-3"
                >
                    <p class="text-base font-semibold text-slate-800">No hay reservas en esta vista todavía</p>
                    <p class="text-sm text-slate-500 max-w-md mx-auto">
                        Comparte tu página pública con tus seguidores para que elijan fecha y hora en su propia zona horaria.
                    </p>
                    <div>
                        <Link
                            :href="`/@${creator.username}`"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition"
                        >
                            Probar página pública de reservas
                        </Link>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 gap-4">
                    <article
                        v-for="booking in filteredBookings"
                        :key="booking.uuid"
                        class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
                    >
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-semibold',
                                        booking.status === 'confirmed'
                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border border-slate-200 line-through',
                                    ]"
                                >
                                    {{ booking.status === 'confirmed' ? 'Confirmada' : 'Cancelada' }}
                                </span>
                                <span class="text-sm font-bold text-indigo-600">
                                    {{ booking.session_type?.title || 'Sesión 1 a 1' }}
                                </span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                                    {{ booking.session_type?.duration_minutes }} min
                                </span>
                            </div>

                            <div class="text-base font-semibold text-slate-900">
                                {{ booking.creator_date_human }} •
                                <span class="text-indigo-700">{{ booking.creator_time_range }}</span>
                                <span class="text-xs font-normal text-slate-500 ml-1">({{ booking.creator_timezone }})</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                                <span>
                                    <strong class="text-slate-800">Seguidor:</strong> {{ booking.guest_name }} ({{ booking.guest_email }})
                                </span>
                                <span>
                                    <strong class="text-slate-800">Hora del seguidor:</strong> {{ booking.guest_time_range }}
                                </span>
                                <span class="font-mono text-[11px] bg-slate-100 px-2 py-0.5 rounded text-slate-700">
                                    UTC: {{ booking.start_at_utc }} → {{ booking.end_at_utc }}
                                </span>
                            </div>

                            <p v-if="booking.notes" class="text-xs text-slate-600 bg-slate-50 border border-slate-200/70 rounded-lg px-3 py-2">
                                <strong class="text-slate-700">Notas del seguidor:</strong> {{ booking.notes }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                v-if="booking.status === 'confirmed'"
                                type="button"
                                @click="cancelBooking(booking)"
                                class="px-3.5 py-2 rounded-xl text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition cursor-pointer"
                            >
                                Cancelar y liberar bloque
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <!-- TAB 2: CRUD DE TIPOS DE SESIÓN -->
            <section v-if="activeTab === 'sessions'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Formulario Crear / Editar Tipo de Sesión -->
                <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-900">
                            {{ editingSessionUuid ? 'Editar Tipo de Sesión' : 'Nuevo Tipo de Sesión 1 a 1' }}
                        </h2>
                        <button
                            v-if="editingSessionUuid"
                            type="button"
                            @click="resetSessionForm"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 cursor-pointer"
                        >
                            Cancelar edición
                        </button>
                    </div>

                    <form @submit.prevent="submitSessionForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Nombre / Tipo de Sesión *
                            </label>
                            <input
                                v-model="sessionForm.title"
                                type="text"
                                required
                                placeholder="Ej: Revisión de Portafolio & Código"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                            />
                            <p v-if="sessionForm.errors.title" class="text-xs text-rose-600 mt-1">
                                {{ sessionForm.errors.title }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Duración (minutos) *
                            </label>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <button
                                    v-for="preset in durationPresets"
                                    :key="preset"
                                    type="button"
                                    @click="sessionForm.duration_minutes = preset"
                                    :class="[
                                        'px-3 py-1 rounded-lg text-xs font-semibold border transition cursor-pointer',
                                        Number(sessionForm.duration_minutes) === preset
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100',
                                    ]"
                                >
                                    {{ preset }} min
                                </button>
                            </div>
                            <input
                                v-model.number="sessionForm.duration_minutes"
                                type="number"
                                min="10"
                                max="240"
                                required
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                            />
                            <p v-if="sessionForm.errors.duration_minutes" class="text-xs text-rose-600 mt-1">
                                {{ sessionForm.errors.duration_minutes }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Descripción breve
                            </label>
                            <textarea
                                v-model="sessionForm.description"
                                rows="3"
                                placeholder="Describe qué se verá en la sesión y qué debe preparar el seguidor..."
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                            ></textarea>
                            <p v-if="sessionForm.errors.description" class="text-xs text-rose-600 mt-1">
                                {{ sessionForm.errors.description }}
                            </p>
                        </div>

                        <label class="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                            <input
                                v-model="sessionForm.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Sesión visible y disponible en la página pública</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="sessionForm.processing"
                            class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold text-sm shadow-xs transition cursor-pointer"
                        >
                            {{ editingSessionUuid ? 'Guardar cambios de la sesión' : 'Crear tipo de sesión' }}
                        </button>
                    </form>
                </div>

                <!-- Lista de Tipos de Sesión -->
                <div class="lg:col-span-7 space-y-4">
                    <div
                        v-for="session in sessionTypes"
                        :key="session.uuid"
                        class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                    >
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">{{ session.title }}</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ session.duration_minutes }} min
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-xs font-medium',
                                        session.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border border-slate-200',
                                    ]"
                                >
                                    {{ session.is_active ? 'Activa' : 'Oculta' }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-600">
                                {{ session.description || 'Sin descripción adicional.' }}
                            </p>
                            <p class="text-xs text-slate-400">
                                Reservas confirmadas: {{ session.confirmed_bookings_count || 0 }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="startEditSession(session)"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition cursor-pointer"
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                @click="toggleSessionActive(session)"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                            >
                                {{ session.is_active ? 'Pausar' : 'Activar' }}
                            </button>
                            <button
                                type="button"
                                @click="deleteSession(session)"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition cursor-pointer"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TAB 3: HORARIO SEMANAL Y EXCEPCIONES POR FECHA -->
            <section v-if="activeTab === 'schedule'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Configuración de Horario Semanal -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs space-y-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Horario Semanal Recurrente</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Define tu disponibilidad en tu zona horaria local. Los seguidores verán estos bloques convertidos automáticamente a su propio país.
                        </p>
                    </div>

                    <form @submit.prevent="saveWeeklySchedule" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Tu Zona Horaria Local (Creador)
                            </label>
                            <select
                                v-model="scheduleForm.timezone"
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm bg-white focus:border-indigo-600 focus:outline-none"
                            >
                                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                    {{ tz.label }}
                                </option>
                            </select>
                        </div>

                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
                            <div
                                v-for="(day, index) in scheduleForm.days"
                                :key="day.day_of_week"
                                class="p-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white hover:bg-slate-50/60 transition"
                            >
                                <label class="flex items-center gap-3 cursor-pointer min-w-[140px]">
                                    <input
                                        v-model="day.is_enabled"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-sm font-semibold text-slate-800">{{ day.day_name }}</span>
                                </label>

                                <div v-if="day.is_enabled" class="flex items-center gap-2">
                                    <input
                                        v-model="day.start_time"
                                        type="time"
                                        required
                                        class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs sm:text-sm focus:border-indigo-600 focus:outline-none"
                                    />
                                    <span class="text-xs text-slate-400">a</span>
                                    <input
                                        v-model="day.end_time"
                                        type="time"
                                        required
                                        class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs sm:text-sm focus:border-indigo-600 focus:outline-none"
                                    />
                                </div>
                                <div v-else class="text-xs font-medium text-slate-400 italic">
                                    No disponible
                                </div>
                            </div>
                        </div>

                        <button
                            type="submit"
                            :disabled="scheduleForm.processing"
                            class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold text-sm shadow-xs transition cursor-pointer"
                        >
                            Guardar horario semanal y zona horaria
                        </button>
                    </form>
                </div>

                <!-- Excepciones para Fechas Específicas -->
                <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-6 shadow-2xs space-y-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Excepciones por Fecha Específica</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Bloquea un día completo (vacaciones/feriado) o define un rango horario especial para una fecha puntual.
                        </p>
                    </div>

                    <form @submit.prevent="submitExceptionForm" class="space-y-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Fecha específica *
                            </label>
                            <input
                                v-model="exceptionForm.exception_date"
                                type="date"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                            />
                            <p v-if="exceptionForm.errors.exception_date" class="text-xs text-rose-600 mt-1">
                                {{ exceptionForm.errors.exception_date }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="exceptionForm.is_available = false"
                                :class="[
                                    'py-2 px-3 rounded-xl text-xs font-semibold border transition cursor-pointer',
                                    !exceptionForm.is_available
                                        ? 'bg-rose-600 text-white border-rose-600'
                                        : 'bg-white text-slate-700 border-slate-200',
                                ]"
                            >
                                Bloquear día completo
                            </button>
                            <button
                                type="button"
                                @click="exceptionForm.is_available = true"
                                :class="[
                                    'py-2 px-3 rounded-xl text-xs font-semibold border transition cursor-pointer',
                                    exceptionForm.is_available
                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                        : 'bg-white text-slate-700 border-slate-200',
                                ]"
                            >
                                Horario especial
                            </button>
                        </div>

                        <div v-if="exceptionForm.is_available" class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Inicio</label>
                                <input
                                    v-model="exceptionForm.start_time"
                                    type="time"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Fin</label>
                                <input
                                    v-model="exceptionForm.end_time"
                                    type="time"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Motivo / Nota (opcional)
                            </label>
                            <input
                                v-model="exceptionForm.reason"
                                type="text"
                                placeholder="Ej: Feriado local / Evento en vivo"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="exceptionForm.processing"
                            class="w-full py-2 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs sm:text-sm transition cursor-pointer"
                        >
                            Guardar excepción de fecha
                        </button>
                    </form>

                    <!-- Listado de Excepciones -->
                    <div class="space-y-2.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Excepciones configuradas ({{ exceptions.length }})
                        </h3>

                        <div v-if="exceptions.length === 0" class="text-xs text-slate-500 py-3">
                            No hay excepciones activas. Se aplicará tu horario semanal habitual.
                        </div>

                        <div
                            v-for="exc in exceptions"
                            :key="exc.uuid"
                            class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200 bg-white"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ exc.exception_date }}</span>
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[11px] font-semibold',
                                            exc.is_available
                                                ? 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                                                : 'bg-rose-50 text-rose-700 border border-rose-200',
                                        ]"
                                    >
                                        {{ exc.is_available ? `${exc.start_time} - ${exc.end_time}` : 'Día bloqueado' }}
                                    </span>
                                </div>
                                <p v-if="exc.reason" class="text-xs text-slate-500 mt-0.5">{{ exc.reason }}</p>
                            </div>

                            <button
                                type="button"
                                @click="removeException(exc)"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-800 px-2 py-1 cursor-pointer"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
