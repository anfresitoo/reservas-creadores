<?php

namespace Tests\Unit;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\SessionType;
use App\Models\User;
use App\Services\TimezoneService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TimezoneServiceTest extends TestCase
{
    private TimezoneService $timezoneService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timezoneService = new TimezoneService();
    }

    public function test_converts_creator_local_window_in_santiago_to_strict_utc(): void
    {
        // En noviembre America/Santiago está en UTC-3 (horario de verano CLST)
        // 09:00:00 en Santiago equivale exactamente a 12:00:00 en UTC
        $utcStart = $this->timezoneService->localWindowToUtc(
            '2026-11-16',
            '09:00:00',
            'America/Santiago'
        );

        $this->assertSame('UTC', $utcStart->timezoneName);
        $this->assertSame('2026-11-16 12:00:00', $utcStart->format('Y-m-d H:i:s'));
    }

    public function test_converts_utc_slot_to_follower_local_timezones_across_countries(): void
    {
        // Slot almacenado en UTC: 2026-11-16 12:00:00 UTC
        $slotUtc = CarbonImmutable::createFromFormat('Y-m-d H:i:s', '2026-11-16 12:00:00', 'UTC');

        // Seguidor en Madrid (Europe/Madrid, UTC+1 en noviembre) -> 13:00
        $madridFormatted = $this->timezoneService->formatUtcForTimezone($slotUtc, 'Europe/Madrid');
        $this->assertSame('2026-11-16', $madridFormatted['local_date']);
        $this->assertSame('13:00', $madridFormatted['local_time']);
        $this->assertSame('+01:00', $madridFormatted['offset']);

        // Seguidor en Ciudad de México (America/Mexico_City, UTC-6) -> 06:00
        $mexicoFormatted = $this->timezoneService->formatUtcForTimezone($slotUtc, 'America/Mexico_City');
        $this->assertSame('2026-11-16', $mexicoFormatted['local_date']);
        $this->assertSame('06:00', $mexicoFormatted['local_time']);
        $this->assertSame('-06:00', $mexicoFormatted['offset']);

        // Seguidor en Tokio (Asia/Tokyo, UTC+9) -> 21:00
        $tokyoFormatted = $this->timezoneService->formatUtcForTimezone($slotUtc, 'Asia/Tokyo');
        $this->assertSame('2026-11-16', $tokyoFormatted['local_date']);
        $this->assertSame('21:00', $tokyoFormatted['local_time']);
        $this->assertSame('+09:00', $tokyoFormatted['offset']);
    }

    public function test_handles_international_date_line_shift_between_creator_and_follower(): void
    {
        // Creador en America/Santiago (UTC-3) tiene sesión a las 22:00 del 2026-11-16
        // En UTC eso es el día siguiente: 2026-11-17 01:00:00 UTC
        $utcStart = $this->timezoneService->localWindowToUtc(
            '2026-11-16',
            '22:00:00',
            'America/Santiago'
        );

        $this->assertSame('2026-11-17 01:00:00', $utcStart->format('Y-m-d H:i:s'));

        // Un seguidor en Madrid (UTC+1) lo ve el 2026-11-17 a las 02:00
        $madrid = $this->timezoneService->formatUtcForTimezone($utcStart, 'Europe/Madrid');
        $this->assertSame('2026-11-17', $madrid['local_date']);
        $this->assertSame('02:00', $madrid['local_time']);
    }

    public function test_validates_iana_timezones_and_rejects_invalid_strings(): void
    {
        $this->assertTrue($this->timezoneService->isValidTimezone('America/Santiago'));
        $this->assertTrue($this->timezoneService->isValidTimezone('Europe/Madrid'));
        $this->assertTrue($this->timezoneService->isValidTimezone('UTC'));
        $this->assertFalse($this->timezoneService->isValidTimezone('Invalid/Timezone'));

        $this->assertSame(
            'America/Santiago',
            $this->timezoneService->normalizeTimezone('Invalid/Timezone', 'America/Santiago')
        );
    }

    public function test_public_entities_use_uuid_as_route_key_name_and_booking_created_event_holds_booking(): void
    {
        $user = new User();
        $sessionType = new SessionType();
        $booking = new Booking(['uuid' => '019a0000-0000-7000-8000-000000000001']);

        $this->assertSame('uuid', $user->getRouteKeyName());
        $this->assertSame('uuid', $sessionType->getRouteKeyName());
        $this->assertSame('uuid', $booking->getRouteKeyName());

        $event = new BookingCreated($booking);
        $this->assertSame($booking, $event->booking);
    }
}
