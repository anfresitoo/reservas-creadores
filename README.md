# eSponsor Reservas — Sistema de Reservas 1 a 1 para Creadores (MVP)

Sistema de reservas de sesiones 1 a 1 para creadores construido como **Monolito Modular (*Microservice-ready*)** con **Laravel 12, Vue 3 (`<script setup>`), Inertia.js, Tailwind CSS y MySQL**.

---

## Stack Tecnológico

- **Backend**: PHP 8.3+ / Laravel 12
- **Frontend**: Vue 3 (`<script setup>`) + Inertia.js + Vite
- **Estilos / UI**: Tailwind CSS v4 (100% responsivo)
- **Base de Datos**: MySQL 8+ (con bloqueo pesimista `SELECT ... FOR UPDATE`)
- **Control de Versiones**: Git & GitHub (`git@github.com:anfresitoo/reservas-creadores.git`)

---

## Arquitectura del Proyecto (Monolito Modular)

El proyecto desacopla completamente la capa HTTP de las reglas de negocio para facilitar su evolución y eventual extracción a microservicios:

```text
app/
├── Actions/
│   ├── Booking/
│   │   ├── CreateBookingAction.php              # Transacción atómica + lockForUpdate() + evento BookingCreated
│   │   └── CancelBookingAction.php              # Cancelación y liberación inmediata del bloque en UTC
│   └── Creator/
│       ├── UpsertSessionTypeAction.php          # Crear/editar tipos de sesión 1 a 1
│       ├── DeleteSessionTypeAction.php          # Eliminar tipo de sesión
│       ├── UpdateWeeklyScheduleAction.php       # Actualizar horario semanal + timezone local
│       ├── UpsertAvailabilityExceptionAction.php# Crear/editar excepción por fecha específica
│       └── DeleteAvailabilityExceptionAction.php# Eliminar excepción de fecha
├── Events/
│   └── BookingCreated.php                       # Evento de dominio disparado tras confirmar una reserva
├── Listeners/
│   └── LogBookingCreatedNotification.php        # Listener desacoplado (listo para emails/webhooks/colas)
├── Services/
│   ├── TimezoneService.php                      # Conversión bidireccional Creador TZ <-> UTC <-> Seguidor TZ
│   ├── AvailabilityService.php                  # Cálculo de bloques libres en UTC y validación de solapamientos
│   └── CreatorResolverService.php               # Resolución del perfil del creador activo
├── Models/
│   ├── Concerns/HasPublicUuid.php               # UUIDv7 automático y route binding público por uuid
│   ├── User.php
│   ├── SessionType.php
│   ├── Availability.php
│   ├── AvailabilityException.php
│   └── Booking.php
└── Http/
    ├── Controllers/
    │   ├── Creator/                             # Controladores delgados del Dashboard privado
    │   └── PublicBookingController.php          # Controlador delgado de la vista pública del seguidor
    └── Requests/                                # FormRequests de validación desacoplada
```

---

## Reglas Críticas Implementadas

### 1. Zonas Horarias en UTC (`start_at_utc`, `end_at_utc`)
- **Creador**: Configura su horario semanal (`availabilities`) y excepciones (`availability_exceptions`) en su propia zona horaria local (ej. `America/Santiago`).
- **Seguidor**: Al abrir `/@{username}`, el sistema detecta automáticamente la zona horaria de su navegador (`Intl.DateTimeFormat().resolvedOptions().timeZone`) o le permite elegir cualquier zona horaria IANA (ej. `Europe/Madrid`, `America/Mexico_City`, `Asia/Tokyo`), mostrando todos los bloques convertidos automáticamente desde UTC.
- **Persistencia y Validación**: Todas las reservas en la tabla `bookings` se almacenan y validan estrictamente en **UTC** (`start_at_utc`, `end_at_utc`).

### 2. Prevención de *Race Conditions* con Bloqueo Pesimista
En `App\Actions\Booking\CreateBookingAction`:
1. Se abre una transacción `DB::transaction()`.
2. Se bloquea de forma pesimista el registro del creador (`User::whereKey($creator->id)->lockForUpdate()->firstOrFail()`) para serializar intentos concurrentes sobre su agenda.
3. Se valida que el rango `[start_at_utc, end_at_utc]` pertenezca a una ventana válida de disponibilidad del creador (`AvailabilityService::isSlotWithinCreatorSchedule`).
4. Se ejecuta una consulta con `lockForUpdate()` sobre `bookings` verificando que no exista ninguna reserva confirmada donde `start_at_utc < $endAtUtc` y `end_at_utc > $startAtUtc`.
5. Se persiste la reserva en UTC y se despacha `BookingCreated` dentro de `DB::afterCommit()`.

### 3. Vistas Implementadas
1. **Panel Privado del Creador (`/dashboard`)**:
   - Resumen de KPIs y enlace directo a la página pública.
   - **Reservas Pendientes / Próximas**: detalle con hora local del creador, hora local del seguidor, marca exacta en UTC y botón para cancelar/liberar el bloque.
   - **CRUD de Tipos de Sesión 1 a 1**: creación, edición (nombre/tipo, duración en minutos, descripción breve, estado activo/pausado) y eliminación.
   - **Horario Semanal y Excepciones**: configuración de Lunes a Domingo en el timezone del creador y bloqueos o rangos especiales para fechas específicas.
2. **Página Pública del Seguidor (`/@{username}`, ej. `/@alex-dev`)**:
   - Accesible sin cuenta.
   - Selección de tipo de sesión, selector de zona horaria local, calendario interactivo y confirmación instantánea.

---

## Instalación y Uso Local

1. **Instalar dependencias**:
   ```bash
   composer install
   npm install
   ```

2. **Configurar variables de entorno**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Configura las credenciales de tu base de datos MySQL en `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=esponsor_reservas
   DB_USERNAME=root
   DB_PASSWORD=tu_password
   ```

3. **Ejecutar migraciones y seeders**:
   ```bash
   php artisan migrate --seed
   ```

4. **Compilar assets e iniciar el servidor**:
   ```bash
   npm run build
   php artisan serve
   ```
   - **Dashboard del Creador**: `http://localhost:8000/dashboard`
   - **Página Pública del Seguidor**: `http://localhost:8000/@alex-dev`

5. **Ejecutar pruebas automatizadas**:
   ```bash
   php artisan test
   ```
