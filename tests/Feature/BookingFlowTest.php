<?php

namespace Tests\Feature;

use App\Actions\Booking\CreateBookingAction;
use App\Events\BookingCreated;
use App\Models\Availability;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\SessionType;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_follower_books_slot_in_own_timezone_and_persists_strictly_in_utc_dispatching_event(): void
    {
        Event::fake([BookingCreated::class]);

        // Creador en America/Santiago (UTC-3 en noviembre)
        $creator = User::factory()->create([
            'username' => 'alex-dev',
            'timezone' => 'America/Santiago',
        ]);

        $sessionType = SessionType::create([
            'user_id' => $creator->id,
            'title' => 'Mentoría Arquitectura 30m',
            'slug' => 'mentoria-arquitectura-30',
            'description' => 'Sesión 1 a 1 de arquitectura modular',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        // Lunes (day_of_week = 1) de 09:00 a 10:00 hora de Santiago (12:00 a 13:00 UTC)
        Availability::create([
            'user_id' => $creator->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_enabled' => true,
        ]);

        // Fecha de prueba: Lunes 2026-11-16
        $referenceNowUtc = CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC');
        $availabilityService = app(AvailabilityService::class);

        // El seguidor está en Europe/Madrid (UTC+1 -> verá 13:00-13:30 y 13:30-14:00)
        $slots = $availabilityService->getAvailableSlotsForGuestDate(
            $creator,
            $sessionType,
            '2026-11-16',
            'Europe/Madrid',
            $referenceNowUtc
        );

        $this->assertCount(2, $slots);
        $this->assertSame('13:00', $slots[0]['guest_start_time']);
        $this->assertSame('13:30', $slots[0]['guest_end_time']);
        $this->assertSame('09:00', $slots[0]['creator_start_time']);
        $this->assertSame('2026-11-16T12:00:00Z', $slots[0]['start_at_utc']);

        // Confirmar la reserva del primer bloque
        $createBookingAction = app(CreateBookingAction::class);
        $booking = $createBookingAction->execute(
            $creator,
            $sessionType,
            [
                'start_at_utc' => $slots[0]['start_at_utc'],
                'guest_name' => 'Carlos Ruiz',
                'guest_email' => 'carlos@example.com',
                'guest_timezone' => 'Europe/Madrid',
                'notes' => 'Revisión de arquitectura hexagonal',
            ],
            $referenceNowUtc
        );

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'creator_id' => $creator->id,
            'session_type_id' => $sessionType->id,
            'guest_email' => 'carlos@example.com',
            'guest_timezone' => 'Europe/Madrid',
            'start_at_utc' => '2026-11-16 12:00:00',
            'end_at_utc' => '2026-11-16 12:30:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Event::assertDispatched(BookingCreated::class, function (BookingCreated $event) use ($booking) {
            return $event->booking->uuid === $booking->uuid;
        });

        // Ahora el bloque de las 12:00 UTC queda bloqueado y solo queda 1 slot disponible
        $remainingSlots = $availabilityService->getAvailableSlotsForGuestDate(
            $creator,
            $sessionType,
            '2026-11-16',
            'Europe/Madrid',
            $referenceNowUtc
        );

        $this->assertCount(1, $remainingSlots);
        $this->assertSame('2026-11-16T12:30:00Z', $remainingSlots[0]['start_at_utc']);
    }

    public function test_prevents_double_booking_race_condition_on_same_utc_slot(): void
    {
        $creator = User::factory()->create([
            'username' => 'alex-dev',
            'timezone' => 'America/Santiago',
        ]);

        $sessionType = SessionType::create([
            'user_id' => $creator->id,
            'title' => 'Sesión 30m',
            'slug' => 'sesion-30m',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        Availability::create([
            'user_id' => $creator->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_enabled' => true,
        ]);

        $referenceNowUtc = CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC');
        $action = app(CreateBookingAction::class);

        // Primera reserva exitosa
        $action->execute(
            $creator,
            $sessionType,
            [
                'start_at_utc' => '2026-11-16T12:00:00Z',
                'guest_name' => 'Seguidor Uno',
                'guest_email' => 'uno@example.com',
                'guest_timezone' => 'America/Mexico_City',
            ],
            $referenceNowUtc
        );

        // Segundo intento sobre el mismo bloque UTC debe fallar con ValidationException
        $this->expectException(ValidationException::class);

        $action->execute(
            $creator,
            $sessionType,
            [
                'start_at_utc' => '2026-11-16T12:00:00Z',
                'guest_name' => 'Seguidor Dos',
                'guest_email' => 'dos@example.com',
                'guest_timezone' => 'America/Bogota',
            ],
            $referenceNowUtc
        );
    }

    public function test_date_exception_blocks_or_overrides_creator_weekly_schedule(): void
    {
        $creator = User::factory()->create([
            'username' => 'alex-dev',
            'timezone' => 'America/Santiago',
        ]);

        $sessionType = SessionType::create([
            'user_id' => $creator->id,
            'title' => 'Sesión 60m',
            'slug' => 'sesion-60m',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        Availability::create([
            'user_id' => $creator->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_enabled' => true,
        ]);

        // Bloquear día completo Lunes 2026-11-16
        AvailabilityException::create([
            'user_id' => $creator->id,
            'exception_date' => '2026-11-16',
            'is_available' => false,
            'reason' => 'Feriado',
        ]);

        $referenceNowUtc = CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC');
        $availabilityService = app(AvailabilityService::class);

        $blockedSlots = $availabilityService->getAvailableSlotsForGuestDate(
            $creator,
            $sessionType,
            '2026-11-16',
            'America/Santiago',
            $referenceNowUtc
        );

        $this->assertCount(0, $blockedSlots);
    }
}
