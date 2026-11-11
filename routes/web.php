<?php

use App\Http\Controllers\Creator\CreatorBookingController;
use App\Http\Controllers\Creator\DashboardController;
use App\Http\Controllers\Creator\ScheduleController;
use App\Http\Controllers\Creator\SessionTypeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'creatorSlug' => 'alex-dev',
    ]);
})->name('home');

// Panel Privado del Creador (Dashboard & CRUD)
Route::prefix('dashboard')->name('creator.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // CRUD de Tipos de Sesión (UUID binding automático por HasPublicUuid)
    Route::post('/session-types', [SessionTypeController::class, 'store'])->name('session-types.store');
    Route::put('/session-types/{sessionType}', [SessionTypeController::class, 'update'])->name('session-types.update');
    Route::delete('/session-types/{sessionType}', [SessionTypeController::class, 'destroy'])->name('session-types.destroy');

    // Horario Semanal y Excepciones por Fecha
    Route::put('/schedule/weekly', [ScheduleController::class, 'updateWeekly'])->name('schedule.weekly');
    Route::post('/schedule/exceptions', [ScheduleController::class, 'storeException'])->name('schedule.exceptions.store');
    Route::delete('/schedule/exceptions/{exception}', [ScheduleController::class, 'destroyException'])->name('schedule.exceptions.destroy');

    // Gestión de Reservas
    Route::patch('/bookings/{booking}/cancel', [CreatorBookingController::class, 'cancel'])->name('bookings.cancel');
});
