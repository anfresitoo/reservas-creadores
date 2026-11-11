# eSponsor Reservas — Sistema de Reservas 1 a 1 para Creadores (MVP)

Sistema de reservas de sesiones 1 a 1 para creadores construido bajo una arquitectura de **Monolito Modular (*Microservice-ready*)** utilizando **Laravel 12, Vue 3 (`<script setup>`), Inertia.js, Tailwind CSS y MySQL**.

---

## Tabla de Contenidos
1. [Requisitos Previos (Desde Cero)](#1-requisitos-previos-desde-cero)
2. [Guía de Instalación Paso a Paso](#2-guía-de-instalación-paso-a-paso)
3. [Cómo Levantar y Probar el Proyecto](#3-cómo-levantar-y-probar-el-proyecto)
4. [Ejecución de Pruebas Automatizadas](#4-ejecución-de-pruebas-automatizadas)
5. [Arquitectura del Proyecto (Monolito Modular)](#5-arquitectura-del-proyecto-monolito-modular)
6. [Reglas Críticas de Negocio (UTC y Bloqueo Pesimista)](#6-reglas-críticas-de-negocio-utc-y-bloqueo-pesimista)

---

## 1. Requisitos Previos (Desde Cero)

Antes de clonar el proyecto, asegúrate de tener instaladas las siguientes herramientas en tu equipo (Linux / macOS / Windows con WSL2 o Laragon/Herd):

| Herramienta | Versión Mínima | Comando para verificar | Dónde descargar si no la tienes |
| :--- | :--- | :--- | :--- |
| **Git** | 2.x+ | `git --version` | https://git-scm.com/downloads |
| **PHP** | **8.2 / 8.3 / 8.4+** *(con extensiones `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`)* | `php -v` y `php -m` | https://www.php.net/downloads (o Laravel Herd / Laragon / `apt`) |
| **Composer** | 2.x+ | `composer --version` | https://getcomposer.org/download/ |
| **Node.js & npm** | Node **20+** / npm **10+** | `node -v` y `npm -v` | https://nodejs.org/ |
| **MySQL** | **8.0+** | `mysql --version` | https://dev.mysql.com/downloads/mysql/ |

> **En Ubuntu / Debian / WSL2**, si no tienes alguno instalado, puedes instalarlos ejecutando:
> ```bash
> sudo apt update
> sudo apt install -y git curl unzip php php-cli php-mysql php-xml php-mbstring php-curl php-zip composer nodejs npm mysql-server
> ```

---

## 2. Guía de Instalación Paso a Paso

Sigue estos pasos en orden desde tu terminal para dejar el proyecto funcionando desde cero:

### Paso 1: Clonar el repositorio e ingresar a la carpeta
```bash
# Vía SSH
git clone git@github.com:anfresitoo/reservas-creadores.git
cd reservas-creadores

# (Alternativa vía HTTPS si no tienes llave SSH configurada)
# git clone https://github.com/anfresitoo/reservas-creadores.git
# cd reservas-creadores
```

### Paso 2: Instalar las dependencias de Backend (PHP / Laravel)
```bash
composer install
```

### Paso 3: Instalar las dependencias de Frontend (Vue 3 / Inertia / Tailwind / Vite)
```bash
npm install
```

### Paso 4: Crear el archivo de variables de entorno (`.env`) y generar la llave de la aplicación
```bash
cp .env.example .env
php artisan key:generate
```

### Paso 5: Crear la base de datos en MySQL
Asegúrate de que tu servidor MySQL esté encendido y crea la base de datos `esponsor_reservas`:

```bash
# Inicia sesión en MySQL con tu usuario (por defecto root)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS esponsor_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
*(Si usas un cliente visual como TablePlus, DBeaver, phpMyAdmin o HeidiSQL, simplemente crea una nueva base de datos llamada `esponsor_reservas`).*

### Paso 6: Configurar tus credenciales de MySQL en el archivo `.env`
Abre el archivo `.env` en la raíz del proyecto y verifica que la sección de base de datos coincida con tu usuario y contraseña local de MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=esponsor_reservas
DB_USERNAME=root
DB_PASSWORD=tu_password_de_mysql
```
*(Si tu usuario `root` local no tiene contraseña, deja `DB_PASSWORD=` vacío).*

### Paso 7: Ejecutar las migraciones y cargar los datos iniciales de prueba (Seeders)
Este comando creará todas las tablas (`users`, `session_types`, `availabilities`, `availability_exceptions`, `bookings`) y cargará automáticamente un perfil de Creador de demostración (**Alex Rivera**, `@alex-dev`, zona horaria `America/Santiago`), 3 tipos de sesiones 1 a 1, su horario semanal, una excepción de calendario y una reserva de ejemplo en UTC:

```bash
php artisan migrate:fresh --seed
```

### Paso 8: Compilar los recursos de Frontend (Producción)
```bash
npm run build
```

---

## 3. Cómo Levantar y Probar el Proyecto

### Opción A: Modo simple (con assets ya compilados en el Paso 8)
```bash
php artisan serve
```

### Opción B: Modo desarrollo con Hot-Reload (2 terminales o 1 comando)
Si vas a editar componentes de Vue, puedes levantar el servidor de Laravel y Vite simultáneamente:
```bash
# Terminal 1: Backend Laravel
php artisan serve

# Terminal 2: Servidor de desarrollo Vite (HMR)
npm run dev
```

### Rutas Principales de la Aplicación
Una vez iniciado `php artisan serve`, abre tu navegador en:

1. **Página de Inicio**:
   - `http://127.0.0.1:8000/`
2. **Vista 1 — Panel Privado del Creador (Dashboard)**:
   - `http://127.0.0.1:8000/dashboard`
   - Aquí puedes:
     - Ver tus **reservas próximas/pendientes** (mostrando la hora local del creador, la hora local del seguidor y el rango exacto en `UTC`) y cancelar/liberar bloques.
     - Hacer el **CRUD completo de Tipos de Sesión 1 a 1** (crear, editar nombre/tipo, duración en minutos, descripción breve, pausar/activar y eliminar).
     - Configurar tu **horario semanal recurrente** en tu `timezone` local y añadir **excepciones para fechas específicas** (bloquear días completos o asignar un horario especial).
3. **Vista 2 — Página Pública del Seguidor (Sin necesidad de cuenta)**:
   - `http://127.0.0.1:8000/@alex-dev`
   - Aquí cualquier seguidor puede:
     - Elegir el tipo de sesión 1 a 1.
     - Ver los bloques disponibles convertidos automáticamente desde UTC a su **propia zona horaria local** (detectada automáticamente del navegador o seleccionable desde el desplegable para simular seguidores en España, México, EE.UU., Japón, Chile, etc.).
     - Seleccionar fecha y hora y confirmar su reserva (quedando el bloque inmediatamente bloqueado para los demás seguidores).

---

## 4. Ejecución de Pruebas Automatizadas

Para ejecutar la suite completa de pruebas (Unitarias + Feature):
```bash
php artisan test
```

Para ejecutar únicamente las pruebas unitarias de conversión de zonas horarias y dominio (no requieren conexión activa a base de datos):
```bash
./vendor/bin/phpunit --testsuite=Unit
```

---

## 5. Arquitectura del Proyecto (Monolito Modular)

El proyecto desacopla toda la lógica de negocio de los controladores HTTP para mantener el código limpio, testeable y preparado para escalar:

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
    │   ├── Creator/                             # Controladores delegados del Dashboard privado
    │   └── PublicBookingController.php          # Controlador delegado de la vista pública del seguidor
    └── Requests/                                # FormRequests de validación desacoplada
```

---

## 6. Reglas Críticas de Negocio (UTC y Bloqueo Pesimista)

1. **Zonas Horarias en UTC (`start_at_utc`, `end_at_utc`)**:
   - El creador define sus reglas de horario en su `timezone` local (ej. `America/Santiago`).
   - `AvailabilityService` transforma cada ventana del creador a `UTC`, descuenta excepciones y reservas confirmadas en `UTC`, y entrega los bloques convertidos al `timezone` local del seguidor.
   - Todas las reservas en la tabla `bookings` se validan y guardan estrictamente en **UTC** (`start_at_utc` y `end_at_utc`).
2. **Bloqueo Pesimista contra *Race Conditions***:
   - `CreateBookingAction` ejecuta la creación dentro de `DB::transaction()`, bloquea con `lockForUpdate()` el registro del creador y las reservas activas del rango en `UTC`, verifica que no exista colisión (`start_at_utc < $endAtUtc AND end_at_utc > $startAtUtc`), guarda la reserva y dispara el evento `BookingCreated` en `DB::afterCommit()`.
