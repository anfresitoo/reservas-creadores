<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\SessionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function __construct(
        private readonly TimezoneService $timezoneService
    ) {}

    /**
     * Compute available 1-on-1 slots for a specific date in the follower's local timezone.
     *
     * All internal slot generation and collision checks against bookings happen strictly in UTC.
     *
     * @return array<int, array{
     *     start_at_utc: string,
     *     end_at_utc: string,
     *     start_utc_sql: string,
     *     end_utc_sql: string,
     *     guest_date: string,
     *     guest_start_time: string,
     *     guest_end_time: string,
     *     guest_timezone: string,
     *     guest_offset: string,
     *     creator_start_time: string,
     *     creator_end_time: string,
     *     creator_timezone: string
     * }>
     */
    public function getAvailableSlotsForGuestDate(
        User $creator,
        SessionType $sessionType,
        string $guestDateYmd,
        string $guestTimezone,
        ?CarbonImmutable $referenceNowUtc = null
    ): array {
        $guestTz = $this->timezoneService->normalizeTimezone($guestTimezone, 'UTC');
        $creatorTz = $this->timezoneService->normalizeTimezone($creator->timezone, 'America/Santiago');
        $nowUtc = ($referenceNowUtc ?? CarbonImmutable::now('UTC'))->utc();

        // Start and end of the requested day in the follower's local timezone, converted to UTC
        $guestDayStartLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$guestDateYmd} 00:00:00", $guestTz);
        $guestDayEndLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$guestDateYmd} 23:59:59", $guestTz);

        $rangeStartUtc = $guestDayStartLocal->utc();
        $rangeEndUtc = $guestDayEndLocal->utc();

        // Candidate dates in the creator's timezone that overlap the follower's local day
        $creatorStartLocalDate = $rangeStartUtc->setTimezone($creatorTz)->subDay()->startOfDay();
        $creatorEndLocalDate = $rangeEndUtc->setTimezone($creatorTz)->addDay()->startOfDay();

        // Preload creator schedule, exceptions and confirmed bookings in UTC range
        $weeklyMap = $creator->availabilities()
            ->get()
            ->keyBy('day_of_week');

        $candidateDateStrings = [];
        $cursor = $creatorStartLocalDate;
        while ($cursor->lessThanOrEqualTo($creatorEndLocalDate)) {
            $candidateDateStrings[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        $exceptionsMap = $creator->availabilityExceptions()
            ->whereIn('exception_date', $candidateDateStrings)
            ->get()
            ->keyBy(fn (AvailabilityException $exc) => $exc->exception_date->format('Y-m-d'));

        $confirmedBookings = $creator->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('start_at_utc', '<', $rangeEndUtc->addHours(4)->format('Y-m-d H:i:s'))
            ->where('end_at_utc', '>', $rangeStartUtc->subHours(4)->format('Y-m-d H:i:s'))
            ->get(['start_at_utc', 'end_at_utc']);

        $slots = [];
        $duration = max(5, (int) $sessionType->duration_minutes);

        foreach ($candidateDateStrings as $creatorDateYmd) {
            $windowUtc = $this->resolveCreatorDailyWindowUtc(
                $creatorDateYmd,
                $creatorTz,
                $weeklyMap,
                $exceptionsMap
            );

            if ($windowUtc === null) {
                continue;
            }

            [$windowStartUtc, $windowEndUtc] = $windowUtc;

            $slotStartUtc = $windowStartUtc;
            while ($slotStartUtc->addMinutes($duration)->lessThanOrEqualTo($windowEndUtc)) {
                $slotEndUtc = $slotStartUtc->addMinutes($duration);

                // Must be in the future relative to UTC now
                if ($slotStartUtc->greaterThan($nowUtc)) {
                    $slotInGuestTz = $slotStartUtc->setTimezone($guestTz);
                    $slotEndInGuestTz = $slotEndUtc->setTimezone($guestTz);

                    // Only include slots whose start time falls on the follower's selected local date
                    if ($slotInGuestTz->format('Y-m-d') === $guestDateYmd) {
                        if (! $this->overlapsAnyBooking($slotStartUtc, $slotEndUtc, $confirmedBookings)) {
                            $slotInCreatorTz = $slotStartUtc->setTimezone($creatorTz);
                            $slotEndInCreatorTz = $slotEndUtc->setTimezone($creatorTz);

                            $slots[] = [
                                'start_at_utc' => $slotStartUtc->format('Y-m-d\TH:i:s\Z'),
                                'end_at_utc' => $slotEndUtc->format('Y-m-d\TH:i:s\Z'),
                                'start_utc_sql' => $slotStartUtc->format('Y-m-d H:i:s'),
                                'end_utc_sql' => $slotEndUtc->format('Y-m-d H:i:s'),
                                'guest_date' => $slotInGuestTz->format('Y-m-d'),
                                'guest_start_time' => $slotInGuestTz->format('H:i'),
                                'guest_end_time' => $slotEndInGuestTz->format('H:i'),
                                'guest_timezone' => $guestTz,
                                'guest_offset' => $slotInGuestTz->format('P'),
                                'creator_start_time' => $slotInCreatorTz->format('H:i'),
                                'creator_end_time' => $slotEndInCreatorTz->format('H:i'),
                                'creator_timezone' => $creatorTz,
                            ];
                        }
                    }
                }

                $slotStartUtc = $slotEndUtc;
            }
        }

        usort($slots, fn (array $a, array $b) => strcmp($a['start_at_utc'], $b['start_at_utc']));

        return $slots;
    }

    /**
     * Validate strictly in UTC whether a proposed [startAtUtc, endAtUtc] slot belongs
     * to a valid, open window of the creator's schedule (weekly + date exceptions).
     */
    public function isSlotWithinCreatorSchedule(
        User $creator,
        SessionType $sessionType,
        CarbonImmutable $startAtUtc,
        CarbonImmutable $endAtUtc,
        ?CarbonImmutable $referenceNowUtc = null
    ): bool {
        if (! $sessionType->is_active || (int) $sessionType->user_id !== (int) $creator->id) {
            return false;
        }

        $nowUtc = ($referenceNowUtc ?? CarbonImmutable::now('UTC'))->utc();
        $startUtc = $startAtUtc->utc();
        $endUtc = $endAtUtc->utc();

        if ($startUtc->lessThanOrEqualTo($nowUtc)) {
            return false;
        }

        $duration = (int) $sessionType->duration_minutes;
        if ((int) $startUtc->diffInMinutes($endUtc, false) !== $duration) {
            return false;
        }

        $creatorTz = $this->timezoneService->normalizeTimezone($creator->timezone, 'America/Santiago');
        $creatorLocalDate = $startUtc->setTimezone($creatorTz)->format('Y-m-d');

        $weeklyMap = $creator->availabilities()->get()->keyBy('day_of_week');
        $exceptionsMap = $creator->availabilityExceptions()
            ->whereDate('exception_date', $creatorLocalDate)
            ->get()
            ->keyBy(fn (AvailabilityException $exc) => $exc->exception_date->format('Y-m-d'));

        $windowUtc = $this->resolveCreatorDailyWindowUtc(
            $creatorLocalDate,
            $creatorTz,
            $weeklyMap,
            $exceptionsMap
        );

        if ($windowUtc === null) {
            return false;
        }

        [$windowStartUtc, $windowEndUtc] = $windowUtc;

        if ($startUtc->lessThan($windowStartUtc) || $endUtc->greaterThan($windowEndUtc)) {
            return false;
        }

        // Verify slot aligns with the start of the creator's availability window in multiples of duration
        $offsetMinutes = (int) $windowStartUtc->diffInMinutes($startUtc, false);

        return $offsetMinutes >= 0 && ($offsetMinutes % $duration === 0);
    }

    /**
     * Resolve the creator's working window in UTC for a specific local date (Y-m-d in creator's timezone).
     *
     * @param  Collection<int, Availability>  $weeklyMap
     * @param  Collection<string, AvailabilityException>  $exceptionsMap
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function resolveCreatorDailyWindowUtc(
        string $creatorDateYmd,
        string $creatorTz,
        Collection $weeklyMap,
        Collection $exceptionsMap
    ): ?array {
        if ($exceptionsMap->has($creatorDateYmd)) {
            /** @var AvailabilityException $exception */
            $exception = $exceptionsMap->get($creatorDateYmd);

            if (! $exception->is_available || ! $exception->start_time || ! $exception->end_time) {
                return null;
            }

            $startUtc = $this->timezoneService->localWindowToUtc($creatorDateYmd, $exception->start_time, $creatorTz);
            $endUtc = $this->timezoneService->localWindowToUtc($creatorDateYmd, $exception->end_time, $creatorTz);

            return $endUtc->greaterThan($startUtc) ? [$startUtc, $endUtc] : null;
        }

        $localDay = CarbonImmutable::createFromFormat('Y-m-d', $creatorDateYmd, $creatorTz);
        $dayOfWeek = $localDay->dayOfWeek;

        if (! $weeklyMap->has($dayOfWeek)) {
            return null;
        }

        /** @var Availability $weeklyRule */
        $weeklyRule = $weeklyMap->get($dayOfWeek);

        if (! $weeklyRule->is_enabled) {
            return null;
        }

        $startUtc = $this->timezoneService->localWindowToUtc($creatorDateYmd, $weeklyRule->start_time, $creatorTz);
        $endUtc = $this->timezoneService->localWindowToUtc($creatorDateYmd, $weeklyRule->end_time, $creatorTz);

        return $endUtc->greaterThan($startUtc) ? [$startUtc, $endUtc] : null;
    }

    /**
     * Check if a candidate UTC slot overlaps any confirmed booking in UTC.
     *
     * @param  Collection<int, Booking>  $confirmedBookings
     */
    private function overlapsAnyBooking(
        CarbonImmutable $slotStartUtc,
        CarbonImmutable $slotEndUtc,
        Collection $confirmedBookings
    ): bool {
        foreach ($confirmedBookings as $booking) {
            $bookingStartUtc = CarbonImmutable::parse($booking->getRawOriginal('start_at_utc'), 'UTC');
            $bookingEndUtc = CarbonImmutable::parse($booking->getRawOriginal('end_at_utc'), 'UTC');

            if ($slotStartUtc->lessThan($bookingEndUtc) && $slotEndUtc->greaterThan($bookingStartUtc)) {
                return true;
            }
        }

        return false;
    }
}
