<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\SessionType;
use App\Services\CreatorResolverService;
use App\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DAY_NAMES = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    public function __invoke(
        Request $request,
        CreatorResolverService $creatorResolver,
        TimezoneService $timezoneService
    ): Response {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $creatorTz = $timezoneService->normalizeTimezone($creator->timezone, 'America/Santiago');
        $nowUtc = CarbonImmutable::now('UTC');

        $sessionTypes = $creator->sessionTypes()
            ->withCount([
                'bookings as confirmed_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_CONFIRMED),
            ])
            ->orderBy('duration_minutes')
            ->get()
            ->map(fn (SessionType $type) => [
                'uuid' => $type->uuid,
                'title' => $type->title,
                'slug' => $type->slug,
                'description' => $type->description,
                'duration_minutes' => $type->duration_minutes,
                'is_active' => $type->is_active,
                'confirmed_bookings_count' => $type->confirmed_bookings_count,
            ]);

        $existingAvailabilities = $creator->availabilities()
            ->get()
            ->keyBy('day_of_week');

        // Present Monday (1) through Saturday (6), then Sunday (0)
        $orderedDays = [1, 2, 3, 4, 5, 6, 0];
        $weeklySchedule = [];
        foreach ($orderedDays as $dayIndex) {
            /** @var Availability|null $rule */
            $rule = $existingAvailabilities->get($dayIndex);
            $weeklySchedule[] = [
                'day_of_week' => $dayIndex,
                'day_name' => self::DAY_NAMES[$dayIndex],
                'start_time' => $rule ? substr($rule->start_time, 0, 5) : '09:00',
                'end_time' => $rule ? substr($rule->end_time, 0, 5) : '17:00',
                'is_enabled' => $rule ? (bool) $rule->is_enabled : ($dayIndex >= 1 && $dayIndex <= 5),
            ];
        }

        $exceptions = $creator->availabilityExceptions()
            ->orderBy('exception_date')
            ->get()
            ->map(fn (AvailabilityException $exc) => [
                'uuid' => $exc->uuid,
                'exception_date' => $exc->exception_date->format('Y-m-d'),
                'is_available' => (bool) $exc->is_available,
                'start_time' => $exc->start_time ? substr($exc->start_time, 0, 5) : null,
                'end_time' => $exc->end_time ? substr($exc->end_time, 0, 5) : null,
                'reason' => $exc->reason,
            ]);

        $bookings = $creator->bookings()
            ->with('sessionType')
            ->orderBy('start_at_utc')
            ->get()
            ->map(function (Booking $booking) use ($creatorTz, $timezoneService, $nowUtc) {
                $startUtc = CarbonImmutable::parse($booking->getRawOriginal('start_at_utc'), 'UTC');
                $endUtc = CarbonImmutable::parse($booking->getRawOriginal('end_at_utc'), 'UTC');

                $creatorStart = $timezoneService->formatUtcForTimezone($startUtc, $creatorTz);
                $creatorEnd = $timezoneService->formatUtcForTimezone($endUtc, $creatorTz);

                $guestStart = $timezoneService->formatUtcForTimezone($startUtc, $booking->guest_timezone);
                $guestEnd = $timezoneService->formatUtcForTimezone($endUtc, $booking->guest_timezone);

                return [
                    'uuid' => $booking->uuid,
                    'status' => $booking->status,
                    'is_upcoming' => $booking->status === Booking::STATUS_CONFIRMED && $endUtc->greaterThanOrEqualTo($nowUtc),
                    'guest_name' => $booking->guest_name,
                    'guest_email' => $booking->guest_email,
                    'guest_timezone' => $booking->guest_timezone,
                    'notes' => $booking->notes,
                    'session_type' => $booking->sessionType ? [
                        'uuid' => $booking->sessionType->uuid,
                        'title' => $booking->sessionType->title,
                        'duration_minutes' => $booking->sessionType->duration_minutes,
                    ] : null,
                    'start_at_utc' => $startUtc->format('Y-m-d H:i:s').' UTC',
                    'end_at_utc' => $endUtc->format('Y-m-d H:i:s').' UTC',
                    'creator_date_human' => $creatorStart['local_human'],
                    'creator_time_range' => "{$creatorStart['local_time']} - {$creatorEnd['local_time']}",
                    'creator_timezone' => $creatorTz,
                    'guest_time_range' => "{$guestStart['local_time']} - {$guestEnd['local_time']} ({$booking->guest_timezone})",
                ];
            });

        return Inertia::render('Creator/Dashboard', [
            'creator' => [
                'uuid' => $creator->uuid,
                'name' => $creator->name,
                'username' => $creator->username,
                'email' => $creator->email,
                'bio' => $creator->bio,
                'avatar_url' => $creator->avatar_url,
                'timezone' => $creatorTz,
                'public_url' => url("/@{$creator->username}"),
            ],
            'sessionTypes' => $sessionTypes,
            'weeklySchedule' => $weeklySchedule,
            'exceptions' => $exceptions,
            'bookings' => $bookings,
            'timezones' => $timezoneService->getCuratedTimezones(),
        ]);
    }
}

