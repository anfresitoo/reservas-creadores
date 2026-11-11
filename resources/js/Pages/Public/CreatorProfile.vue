<script setup>
import { computed, onMounted, ref, watch } from 'vue';
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
    selectedSessionUuid: {
        type: String,
        default: null,
    },
    selectedDate: {
        type: String,
        required: true,
    },
    guestTimezone: {
        type: String,
        required: true,
    },
    hasExplicitTimezone: {
        type: Boolean,
        default: false,
    },
    upcomingDays: {
        type: Array,
        default: () => [],
    },
    availableSlots: {
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
const bookingConfirmed = computed(() => page.props.flash?.booking_confirmed);

const currentSessionUuid = ref(props.selectedSessionUuid);
const currentDate = ref(props.selectedDate);
const currentTimezone = ref(props.guestTimezone);
const browserTimezone = ref('UTC');
const selectedSlot = ref(null);

const selectedSession = computed(() =>
    props.sessionTypes.find((s) => s.uuid === currentSessionUuid.value) || props.sessionTypes[0] || null
);

// Ensure the browser's detected timezone is always in the dropdown options
const timezoneOptions = computed(() => {
    const list = [...props.timezones];
    if (
        browserTimezone.value &&
        !list.some((item) => item.value === browserTimezone.value)
    ) {
        list.unshift({
            value: browserTimezone.value,
            label: `${browserTimezone.value} (Detectada en tu navegador)`,
        });
    }
    return list;
});

const bookingForm = useForm({
    session_type_uuid: props.selectedSessionUuid || '',
    start_at_utc: '',
    guest_name: '',
    guest_email: '',
    guest_timezone: props.guestTimezone,
    notes: '',
});

watch(
    () => [props.selectedSessionUuid, props.selectedDate, props.guestTimezone],
    ([newSessionUuid, newDate, newTz]) => {
        currentSessionUuid.value = newSessionUuid;
        currentDate.value = newDate;
        currentTimezone.value = newTz;
        bookingForm.session_type_uuid = newSessionUuid || '';
        bookingForm.guest_timezone = newTz;
        selectedSlot.value = null;
        bookingForm.start_at_utc = '';
    }
);

onMounted(() => {
    try {
        const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (detected) {
            browserTimezone.value = detected;
            if (!props.hasExplicitTimezone && detected !== props.guestTimezone) {
                currentTimezone.value = detected;
                refreshAvailability({ timezone: detected });
            }
        }
    } catch {
        // Fallback to server-provided timezone
    }
});

function refreshAvailability(overrides = {}) {
    const query = {
        session_type: overrides.session_type ?? currentSessionUuid.value,
        date: overrides.date ?? currentDate.value,
        timezone: overrides.timezone ?? currentTimezone.value,
    };

    router.get(`/@${props.creator.username}`, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function selectSessionType(session) {
    currentSessionUuid.value = session.uuid;
    refreshAvailability({ session_type: session.uuid });
}

function selectDate(dateStr) {
    currentDate.value = dateStr;
    refreshAvailability({ date: dateStr });
}

function onTimezoneChange() {
    refreshAvailability({ timezone: currentTimezone.value });
}

function selectSlot(slot) {
    selectedSlot.value = slot;
    bookingForm.session_type_uuid = currentSessionUuid.value;
    bookingForm.start_at_utc = slot.start_at_utc;
    bookingForm.guest_timezone = currentTimezone.value;
}

function submitBooking() {
    if (!selectedSlot.value) return;

    bookingForm.post(`/@${props.creator.username}/bookings`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedSlot.value = null;
            bookingForm.reset('guest_name', 'guest_email', 'notes', 'start_at_utc');
        },
    });
}
</script>

<template>
    <Head :title="`Reservar sesión 1:1 con ${creator.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
        <!-- Top Header -->
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-xs">
                        1:1
                    </div>
                    <span class="font-semibold text-base tracking-tight text-slate-900">
                        eSponsor <span class="text-indigo-600">Reservas</span>
                    </span>
                </Link>

                <Link
                    href="/dashboard"
                    class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-indigo-600 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white transition"
                >
                    Ir al Panel del Creador →
                </Link>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 space-y-6">
            <!-- Confirmation Banner after Booking -->
            <div
                v-if="bookingConfirmed"
                class="rounded-2xl bg-emerald-50 border border-emerald-200 p-6 shadow-xs space-y-3"
            >
                <div class="flex items-center gap-2.5 text-emerald-800 font-bold text-base">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-600 text-white text-xs">✓</span>
                    <span>{{ flashSuccess || '¡Reserva confirmada exitosamente!' }}</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs sm:text-sm text-emerald-900 bg-white/80 rounded-xl p-4 border border-emerald-200/70">
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Sesión reservada</p>
                        <p class="font-bold text-slate-900">{{ bookingConfirmed.session_title }} ({{ bookingConfirmed.duration_minutes }} min)</p>
                        <p class="text-xs text-slate-600 mt-0.5">{{ bookingConfirmed.guest_name }} • {{ bookingConfirmed.guest_email }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Fecha y hora en tu zona horaria</p>
                        <p class="font-bold text-indigo-700">{{ bookingConfirmed.guest_date_human }}</p>
                        <p class="text-xs text-slate-700">{{ bookingConfirmed.guest_time_range }} ({{ bookingConfirmed.guest_timezone }})</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Registro validado en UTC</p>
                        <p class="font-mono text-xs text-slate-800 mt-0.5">{{ bookingConfirmed.start_at_utc }}</p>
                        <p class="font-mono text-xs text-slate-500">→ {{ bookingConfirmed.end_at_utc }}</p>
                    </div>
                </div>
            </div>

            <!-- Validation Error Banner (e.g. Race Condition / Overlap blocked) -->
            <div
                v-if="bookingForm.errors.start_at_utc || bookingForm.errors.guest_timezone"
                class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800 font-medium"
            >
                {{ bookingForm.errors.start_at_utc || bookingForm.errors.guest_timezone }}
            </div>

            <!-- Main 3-Column Booking Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden grid grid-cols-1 lg:grid-cols-12">
                <!-- Columna 1: Perfil del Creador y Selección de Tipo de Sesión -->
                <aside class="lg:col-span-4 p-6 sm:p-7 border-b lg:border-b-0 lg:border-r border-slate-200 bg-slate-50/50 space-y-6">
                    <div class="flex items-start gap-4">
                        <img
                            :src="creator.avatar_url || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(creator.name)"
                            :alt="creator.name"
                            class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shrink-0"
                        />
                        <div>
                            <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Sesiones 1 a 1</span>
                            <h1 class="text-xl font-bold text-slate-900 mt-0.5">{{ creator.name }}</h1>
                            <p class="text-xs text-slate-500">@{{ creator.username }} • Creador en {{ creator.timezone }}</p>
                        </div>
                    </div>

                    <p class="text-sm text-slate-600 leading-relaxed">
                        {{ creator.bio }}
                    </p>

                    <div class="space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            1. Elige el tipo de sesión
                        </h2>

                        <div v-if="sessionTypes.length === 0" class="text-sm text-slate-500 italic py-4">
                            Este creador no tiene sesiones activas en este momento.
                        </div>

                        <button
                            v-for="session in sessionTypes"
                            :key="session.uuid"
                            type="button"
                            @click="selectSessionType(session)"
                            :class="[
                                'w-full text-left p-4 rounded-2xl border transition cursor-pointer flex flex-col gap-1.5',
                                currentSessionUuid === session.uuid
                                    ? 'bg-indigo-50/70 border-indigo-600 ring-1 ring-indigo-600 shadow-2xs'
                                    : 'bg-white border-slate-200 hover:border-slate-300',
                            ]"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-sm text-slate-900">{{ session.title }}</span>
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded-full text-xs font-semibold shrink-0',
                                        currentSessionUuid === session.uuid
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-slate-100 text-slate-700',
                                    ]"
                                >
                                    {{ session.duration_minutes }} min
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 line-clamp-3">
                                {{ session.description || 'Sesión personalizada 1 a 1 en vivo.' }}
                            </p>
                        </button>
                    </div>
                </aside>

                <!-- Columna 2: Selección de Fecha, Zona Horaria y Bloques Disponibles -->
                <section class="lg:col-span-5 p-6 sm:p-7 border-b lg:border-b-0 lg:border-r border-slate-200 space-y-6">
                    <!-- Selector de Zona Horaria del Seguidor -->
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tu Zona Horaria Local (Seguidor)
                            </label>
                            <span class="text-[11px] font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                                Convertido desde UTC
                            </span>
                        </div>
                        <select
                            v-model="currentTimezone"
                            @change="onTimezoneChange"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-slate-800 focus:border-indigo-600 focus:outline-none"
                        >
                            <option v-for="tz in timezoneOptions" :key="tz.value" :value="tz.value">
                                {{ tz.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Selector de Fecha -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                2. Selecciona una fecha
                            </h2>
                            <input
                                v-model="currentDate"
                                @change="selectDate(currentDate)"
                                type="date"
                                class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 focus:border-indigo-600 focus:outline-none"
                            />
                        </div>

                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            <button
                                v-for="day in upcomingDays.slice(0, 7)"
                                :key="day.date"
                                type="button"
                                @click="selectDate(day.date)"
                                :class="[
                                    'py-2.5 px-2 rounded-xl border text-center transition cursor-pointer flex flex-col items-center',
                                    currentDate === day.date
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                        : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-300',
                                ]"
                            >
                                <span
                                    :class="[
                                        'text-[11px] font-medium uppercase',
                                        currentDate === day.date ? 'text-indigo-100' : 'text-slate-400',
                                    ]"
                                >
                                    {{ day.day_short }}
                                </span>
                                <span class="text-base font-bold leading-tight mt-0.5">{{ day.day_number }}</span>
                                <span
                                    :class="[
                                        'text-[10px]',
                                        currentDate === day.date ? 'text-indigo-100' : 'text-slate-400',
                                    ]"
                                >
                                    {{ day.month_short }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Bloques Horarios Disponibles -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                3. Elige un bloque disponible ({{ currentDate }})
                            </h2>
                            <span class="text-xs font-semibold text-slate-500">
                                {{ availableSlots.length }} disponibles
                            </span>
                        </div>

                        <div
                            v-if="availableSlots.length === 0"
                            class="rounded-2xl border border-dashed border-slate-300 p-8 text-center space-y-1.5 bg-slate-50/50"
                        >
                            <p class="text-sm font-semibold text-slate-700">
                                Sin bloques disponibles en esta fecha
                            </p>
                            <p class="text-xs text-slate-500">
                                Prueba seleccionando otro día de la semana en el calendario superior.
                            </p>
                        </div>

                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-80 overflow-y-auto pr-1">
                            <button
                                v-for="slot in availableSlots"
                                :key="slot.start_at_utc"
                                type="button"
                                @click="selectSlot(slot)"
                                :class="[
                                    'p-3 rounded-xl border text-center transition cursor-pointer flex flex-col items-center justify-center',
                                    selectedSlot?.start_at_utc === slot.start_at_utc
                                        ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs'
                                        : 'bg-white hover:bg-indigo-50/50 text-slate-800 border-slate-200 hover:border-indigo-300',
                                ]"
                            >
                                <span class="text-sm font-bold">
                                    {{ slot.guest_start_time }} - {{ slot.guest_end_time }}
                                </span>
                                <span
                                    :class="[
                                        'text-[10px] mt-0.5',
                                        selectedSlot?.start_at_utc === slot.start_at_utc
                                            ? 'text-indigo-100'
                                            : 'text-slate-400',
                                    ]"
                                >
                                    Creador: {{ slot.creator_start_time }}
                                </span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Columna 3: Confirmación de Reserva -->
                <section class="lg:col-span-3 p-6 sm:p-7 flex flex-col justify-between bg-white">
                    <div class="space-y-5">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            4. Completa tu reserva
                        </h2>

                        <div
                            v-if="!selectedSlot"
                            class="rounded-2xl bg-slate-50 border border-slate-200/80 p-6 text-center space-y-2"
                        >
                            <p class="text-sm font-semibold text-slate-700">
                                Selecciona un bloque horario
                            </p>
                            <p class="text-xs text-slate-500">
                                Al elegir una hora disponible a la izquierda podrás ingresar tus datos y bloquear la sesión 1 a 1.
                            </p>
                        </div>

                        <form v-else @submit.prevent="submitBooking" class="space-y-4">
                            <!-- Resumen del bloque elegido -->
                            <div class="rounded-2xl bg-indigo-50/70 border border-indigo-200/80 p-3.5 space-y-1">
                                <p class="text-xs font-bold text-indigo-900">
                                    {{ selectedSession?.title }} ({{ selectedSession?.duration_minutes }} min)
                                </p>
                                <p class="text-sm font-bold text-indigo-700">
                                    {{ selectedSlot.guest_date }} • {{ selectedSlot.guest_start_time }} - {{ selectedSlot.guest_end_time }}
                                </p>
                                <p class="text-[11px] text-slate-600">
                                    Zona horaria: <strong>{{ selectedSlot.guest_timezone }}</strong>
                                </p>
                                <p class="text-[10px] font-mono text-slate-500">
                                    UTC: {{ selectedSlot.start_utc_sql }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Tu nombre *
                                </label>
                                <input
                                    v-model="bookingForm.guest_name"
                                    type="text"
                                    required
                                    placeholder="Ej: Martín González"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                                />
                                <p v-if="bookingForm.errors.guest_name" class="text-xs text-rose-600 mt-1">
                                    {{ bookingForm.errors.guest_name }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Tu correo electrónico *
                                </label>
                                <input
                                    v-model="bookingForm.guest_email"
                                    type="email"
                                    required
                                    placeholder="martin@ejemplo.com"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                                />
                                <p v-if="bookingForm.errors.guest_email" class="text-xs text-rose-600 mt-1">
                                    {{ bookingForm.errors.guest_email }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Notas o preguntas para la sesión
                                </label>
                                <textarea
                                    v-model="bookingForm.notes"
                                    rows="3"
                                    placeholder="Cuéntale al creador sobre qué te gustaría conversar..."
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none"
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                :disabled="bookingForm.processing"
                                class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold text-sm shadow-xs transition cursor-pointer"
                            >
                                {{ bookingForm.processing ? 'Reservando horario...' : 'Confirmar Reserva 1 a 1' }}
                            </button>
                        </form>
                    </div>

                    <div class="pt-6 border-t border-slate-100 text-[11px] text-slate-400 space-y-1">
                        <p>🔒 Reserva protegida con bloqueo pesimista transaccional.</p>
                        <p>🌍 Validación y almacenamiento estricto en UTC.</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
