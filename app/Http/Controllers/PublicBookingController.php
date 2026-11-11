<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CreateBookingAction;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Models\SessionType;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\CreatorResolverService;
use App\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingController extends Controller
{
    public function show(
        Request $request,
        string $username,
        CreatorResolverService $creatorResolver,
        AvailabilityService $availabilityService,
        TimezoneService $timezoneService
    ): Response {
        $creator = User::query()->where('username', $username)->first();

        if (! $creator && $username === 'alex-dev') {
            $creator = $creatorResolver->resolveActiveCreator($request);
        }

        abort_if(! $creator, 404, 'Creador no encontrado.');

        $creatorTz = $timezoneService->normalizeTimezone($creator->timezone, 'America/Santiago');

        $requestedTz = $request->query('timezone');
        $guestTz = $timezoneService->normalizeTimezone(
            is_string($requestedTz) ? $requestedTz : null,
            $creatorTz
        );

        $sessionTypes = $creator->sessionTypes()
            ->where('is_active', true)
            ->orderBy('duration_minutes')
            ->get();

        $requestedSessionUuid = $request->query('session_type');
        /** @var SessionType|null $selectedSession */
        $selectedSession = $sessionTypes->firstWhere('uuid', $requestedSessionUuid)
            ?? $sessionTypes->first();

        // Default date: next weekday in the follower's timezone if no date query param is given
        $requestedDate = $request->query('date');
        if (is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            $selectedDate = $requestedDate;
        } else {
            $candidate = CarbonImmutable::now($guestTz)->addDay()->startOfDay();
            while ($candidate->isWeekend()) {
                $candidate = $candidate->addDay();
            }
            $selectedDate = $candidate->format('Y-m-d');
        }

        // Build upcoming 14 days in follower's timezone for quick calendar strip
        $upcomingDays = [];
        $dayCursor = CarbonImmutable::now($guestTz)->startOfDay();
        for ($i = 0; $i < 14; $i++) {
            $d = $dayCursor->addDays($i);
            $upcomingDays[] = [
                'date' => $d->format('Y-m-d'),
                'day_short' => ucfirst($d->locale('es')->isoFormat('ddd')),
                'day_number' => $d->format('d'),
                'month_short' => ucfirst($d->locale('es')->isoFormat('MMM')),
                'is_today' => $i === 0,
            ];
        }

        $availableSlots = [];
        if ($selectedSession) {
            $availableSlots = $availabilityService->getAvailableSlotsForGuestDate(
                $creator,
                $selectedSession,
                $selectedDate,
                $guestTz
            );
        }

        return Inertia::render('Public/CreatorProfile', [
            'creator' => [
                'uuid' => $creator->uuid,
                'name' => $creator->name,
                'username' => $creator->username,
                'bio' => $creator->bio,
                'avatar_url' => $creator->avatar_url,
                'timezone' => $creatorTz,
            ],
            'sessionTypes' => $sessionTypes->map(fn (SessionType $type) => [
                'uuid' => $type->uuid,
                'title' => $type->title,
                'slug' => $type->slug,
                'description' => $type->description,
                'duration_minutes' => $type->duration_minutes,
            ])->values(),
            'selectedSessionUuid' => $selectedSession?->uuid,
            'selectedDate' => $selectedDate,
            'guestTimezone' => $guestTz,
            'hasExplicitTimezone' => is_string($requestedTz) && $timezoneService->isValidTimezone($requestedTz),
            'upcomingDays' => $upcomingDays,
            'availableSlots' => $availableSlots,
            'timezones' => $timezoneService->getCuratedTimezones(),
        ]);
    }

    public function store(
        StoreBookingRequest $request,
        string $username,
        CreateBookingAction $createBookingAction,
        TimezoneService $timezoneService
    ): RedirectResponse {
        $creator = User::query()->where('username', $username)->firstOrFail();

        $validated = $request->validated();

        /** @var SessionType $sessionType */
        $sessionType = $creator->sessionTypes()
            ->where('uuid', $validated['session_type_uuid'])
            ->where('is_active', true)
            ->firstOrFail();

        $booking = $createBookingAction->execute($creator, $sessionType, $validated);

        $startUtc = CarbonImmutable::parse($booking->getRawOriginal('start_at_utc'), 'UTC');
        $endUtc = CarbonImmutable::parse($booking->getRawOriginal('end_at_utc'), 'UTC');

        $guestStart = $timezoneService->formatUtcForTimezone($startUtc, $booking->guest_timezone);
        $guestEnd = $timezoneService->formatUtcForTimezone($endUtc, $booking->guest_timezone);

        return back()
            ->with('success', '¡Tu sesión 1 a 1 ha sido confirmada y el horario ha quedado reservado!')
            ->with('booking_confirmed', [
                'uuid' => $booking->uuid,
                'session_title' => $sessionType->title,
                'duration_minutes' => $sessionType->duration_minutes,
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'guest_timezone' => $booking->guest_timezone,
                'guest_date_human' => $guestStart['local_human'],
                'guest_time_range' => "{$guestStart['local_time']} - {$guestEnd['local_time']}",
                'start_at_utc' => $startUtc->format('Y-m-d H:i:s').' UTC',
                'end_at_utc' => $endUtc->format('Y-m-d H:i:s').' UTC',
            ]);
    }
}
