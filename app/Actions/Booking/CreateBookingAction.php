<?php

namespace App\Actions\Booking;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\SessionType;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBookingAction
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly TimezoneService $timezoneService
    ) {}

    /**
     * Execute the 1-on-1 booking creation atomically with pessimistic locking.
     *
     * @param  array{
     *     start_at_utc: string,
     *     guest_name: string,
     *     guest_email: string,
     *     guest_timezone: string,
     *     notes?: string|null
     * }  $data
     *
     * @throws ValidationException
     */
    public function execute(
        User $creator,
        SessionType $sessionType,
        array $data,
        ?CarbonImmutable $referenceNowUtc = null
    ): Booking {
        if (! $this->timezoneService->isValidTimezone($data['guest_timezone'])) {
            throw ValidationException::withMessages([
                'guest_timezone' => 'La zona horaria seleccionada no es válida.',
            ]);
        }

        try {
            $startAtUtc = CarbonImmutable::parse($data['start_at_utc'], 'UTC')->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'start_at_utc' => 'El formato de fecha/hora en UTC no es válido.',
            ]);
        }

        $endAtUtc = $startAtUtc->addMinutes((int) $sessionType->duration_minutes);

        return DB::transaction(function () use ($creator, $sessionType, $data, $startAtUtc, $endAtUtc, $referenceNowUtc) {
            // 1. Pessimistic lock on the creator record to serialize concurrent booking attempts
            /** @var User $lockedCreator */
            $lockedCreator = User::query()
                ->whereKey($creator->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validate that the slot belongs to a valid availability window in UTC
            if (! $this->availabilityService->isSlotWithinCreatorSchedule(
                $lockedCreator,
                $sessionType,
                $startAtUtc,
                $endAtUtc,
                $referenceNowUtc
            )) {
                throw ValidationException::withMessages([
                    'start_at_utc' => 'El bloque seleccionado no está dentro del horario disponible del creador.',
                ]);
            }

            // 3. Pessimistic lock on any potentially overlapping confirmed bookings in UTC
            $hasOverlap = Booking::query()
                ->where('creator_id', $lockedCreator->id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->where('start_at_utc', '<', $endAtUtc->format('Y-m-d H:i:s'))
                ->where('end_at_utc', '>', $startAtUtc->format('Y-m-d H:i:s'))
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw ValidationException::withMessages([
                    'start_at_utc' => 'Este horario ya fue reservado por otro seguidor. Por favor selecciona otro bloque disponible.',
                ]);
            }

            // 4. Persist booking strictly in UTC
            $booking = Booking::create([
                'creator_id' => $lockedCreator->id,
                'session_type_id' => $sessionType->id,
                'guest_name' => trim($data['guest_name']),
                'guest_email' => strtolower(trim($data['guest_email'])),
                'guest_timezone' => $data['guest_timezone'],
                'notes' => isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null,
                'start_at_utc' => $startAtUtc->format('Y-m-d H:i:s'),
                'end_at_utc' => $endAtUtc->format('Y-m-d H:i:s'),
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            // 5. Dispatch BookingCreated domain event once transaction commits
            DB::afterCommit(function () use ($booking) {
                BookingCreated::dispatch($booking);
            });

            return $booking;
        });
    }
}

