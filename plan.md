# Plan de Ejecución por Fases — MVP Sistema de Reservas 1 a 1 (`esponsor-reservas`)

## Reglas Arquitectónicas y de Ingeniería
1. **Stack Obligatorio**: Laravel 12, Vue 3 (`<script setup>`), Inertia.js, Tailwind CSS, MySQL y GitHub (`git@github.com:anfresitoo/reservas-creadores.git`).
2. **Arquitectura**: Monolito Modular (*Microservice-ready*):
   - Lógica de negocio desacoplada en `app/Services` y `app/Actions`.
   - Identificadores públicos `uuid` en todas las entidades expuestas.
   - Controladores delgados (*thin controllers*) que delegan validación a `FormRequest` y ejecución a `Actions`/`Services`.
   - Disparo del evento de dominio `App\Events\BookingCreated` al confirmar una reserva.
3. **Zonas Horarias en UTC (Crítico)**:
   - El creador define su horario semanal y excepciones en su `timezone` local (ej. `America/Santiago`).
   - El seguidor visualiza y reserva bloques en su propio `timezone` local, convertido automáticamente desde UTC.
   - Todas las reservas (`bookings`) se guardan y validan estrictamente en UTC (`start_at_utc`, `end_at_utc`).
4. **Concurrencia y Race Conditions**:
   - Bloqueo pesimista (`lockForUpdate()` dentro de `DB::transaction()`) sobre el creador y el rango de reservas activas antes de insertar la reserva.
5. **UI/UX**:
   - 2 vistas principales completamente responsivas y modernas con Tailwind CSS:
     1. **Dashboard Privado del Creador**: CRUD de tipos de sesión (tipo/título, duración, descripción breve), visualización de reservas pendientes/próximas, configuración de horario semanal y excepciones para fechas específicas.
     2. **Página Pública del Seguidor**: Acceso sin cuenta al perfil del creador, selección de sesión, fecha y bloque horario en el timezone del seguidor con bloqueo inmediato para los demás.

---

## Fases de Ejecución

### Fase 1: Inicialización del Proyecto, Configuración del Stack y Repositorio Git
- [x] Crear proyecto Laravel 12 en `/home/andre/workspace/esponsor-reservas`.
- [x] Configurar Inertia.js + Vue 3 (`<script setup>`) + Tailwind CSS + Vite.
- [x] Configurar variables de entorno base para MySQL (`DB_CONNECTION=mysql`) y verificar que `.env` esté excluido en `.gitignore`.
- [x] Guardar `plan.md` en la raíz del repositorio.
- [x] Commit semántico (`chore: initialize laravel 12 inertia vue3 tailwind stack and execution plan`) y `git push` a `git@github.com:anfresitoo/reservas-creadores.git`.

### Fase 2: Modelado de Base de Datos, Migraciones, Modelos con UUID y Seeders
- [x] Crear migraciones con índices optimizados y soporte de `uuid`:
  - `users` (`uuid`, `username`, `bio`, `avatar_url`, `timezone`).
  - `session_types` (`uuid`, `user_id`, `title`, `slug`, `description`, `duration_minutes`, `is_active`).
  - `availabilities` (`uuid`, `user_id`, `day_of_week`, `start_time`, `end_time`, `is_enabled`).
  - `availability_exceptions` (`uuid`, `user_id`, `exception_date`, `is_available`, `start_time`, `end_time`, `reason`).
  - `bookings` (`uuid`, `creator_id`, `session_type_id`, `guest_name`, `guest_email`, `guest_timezone`, `notes`, `start_at_utc`, `end_at_utc`, `status`).
- [x] Implementar Modelos Eloquent con `uuid` y relaciones tipadas.
- [x] Crear `DatabaseSeeder` con datos reales de prueba para el creador (`America/Santiago`), sesiones 1-a-1 y disponibilidad.
- [x] Commit semántico (`feat(database): add migrations, uuid models and seeders for creator bookings`) y `git push`.

### Fase 3: Capa de Dominio Modular (`Services`, `Actions`, `Events`, `UTC` & Bloqueo Pesimista)
- [ ] Implementar `App\Services\TimezoneService` para conversión bidireccional entre el timezone del creador, UTC y el timezone del seguidor.
- [ ] Implementar `App\Services\AvailabilityService` para cálculo de slots disponibles en UTC descontando excepciones y reservas confirmadas.
- [ ] Implementar `App\Actions\Booking\CreateBookingAction` con `DB::transaction()` y `lockForUpdate()` (bloqueo pesimista) validando `start_at_utc` y `end_at_utc`.
- [ ] Implementar evento `App\Events\BookingCreated` y listener `App\Listeners\LogBookingCreatedNotification`.
- [ ] Implementar Actions del Creador (`UpsertSessionTypeAction`, `DeleteSessionTypeAction`, `UpdateWeeklyScheduleAction`, `UpsertAvailabilityExceptionAction`, `DeleteAvailabilityExceptionAction`, `CancelBookingAction`).
- [ ] Commit semántico (`feat(domain): implement UTC availability service, pessimistic booking action and BookingCreated event`) y `git push`.

### Fase 4: Controladores Delegados y Panel Privado del Creador (Vista 1)
- [ ] Implementar `FormRequests` y controladores delegados para el Dashboard del creador.
- [ ] Construir `resources/js/Pages/Creator/Dashboard.vue` con Vue 3 (`<script setup>`) y Tailwind CSS:
  - CRUD de tipos de sesión (tipo/título, tiempo/duración, descripción breve, estado).
  - Listado de reservas pendientes/próximas con hora local del creador + UTC + opción de cancelar.
  - Configuración de horario semanal en el timezone del creador y excepciones por fecha específica.
- [ ] Commit semántico (`feat(creator-dashboard): build creator dashboard UI and delegated CRUD controllers`) y `git push`.

### Fase 5: Vista Pública del Seguidor y Flujo de Reserva en Tiempo Real (Vista 2)
- [ ] Implementar `PublicBookingController` delegado a `AvailabilityService` y `CreateBookingAction`.
- [ ] Construir `resources/js/Pages/Public/CreatorProfile.vue` con Vue 3 (`<script setup>`) y Tailwind CSS:
  - Visualización sin cuenta de los tipos de sesión del creador.
  - Detección automática y selector de `timezone` del seguidor con conversión automática desde UTC.
  - Selección de fecha y hora disponible, formulario de reserva y bloqueo inmediato del horario para otros seguidores.
- [ ] Commit semántico (`feat(public-booking): implement public follower booking view with local timezone conversion`) y `git push`.

### Fase 6: Pruebas Automatizadas (Feature & Unit Tests) y Documentación Final
- [ ] Implementar suite de pruebas en `tests/Feature/BookingFlowTest.php` verificando:
  - Conversión entre zonas horarias (`America/Santiago` ↔ `UTC` ↔ `Europe/Madrid` / `America/Mexico_City`).
  - Prevención de colisiones y bloqueo de slots reservados (`start_at_utc`, `end_at_utc`).
  - Disparo del evento `BookingCreated`.
  - Excepciones de calendario y CRUD de tipos de sesión.
- [ ] Documentar el proyecto y su arquitectura en `README.md`.
- [ ] Commit semántico (`test(bookings): add UTC timezone, overlap prevention and event feature tests with docs`) y `git push`.

