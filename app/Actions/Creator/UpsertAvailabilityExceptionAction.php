<?php

namespace App\Actions\Creator;

use App\Models\AvailabilityException;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpsertAvailabilityExceptionAction
{
    /**
     * Create or update a date-specific schedule exception in the creator's local timezone.
     *
     * @param  array{
     *     exception_date: string,
     *     is_available: bool,
     *     start_time?: string|null,
     *     end_time?: string|null,
     *     reason?: string|null
     * }  $data
     */
    public function execute(User $creator, array $data): AvailabilityException
    {
        $isAvailable = (bool) $data['is_available'];
        $startTime = null;
        $endTime = null;

        if ($isAvailable) {
            $rawStart = $data['start_time'] ?? '';
            $rawEnd = $data['end_time'] ?? '';

            if (! $rawStart || ! $rawEnd) {
                throw ValidationException::withMessages([
                    'start_time' => 'Debes indicar hora de inicio y fin cuando la fecha tiene horario especial.',
                ]);
            }

            if (substr($rawEnd, 0, 5) <= substr($rawStart, 0, 5)) {
                throw ValidationException::withMessages([
                    'end_time' => 'La hora de término debe ser posterior a la hora de inicio.',
                ]);
            }

            $startTime = strlen($rawStart) === 5 ? "{$rawStart}:00" : $rawStart;
            $endTime = strlen($rawEnd) === 5 ? "{$rawEnd}:00" : $rawEnd;
        }

        return AvailabilityException::updateOrCreate(
            [
                'user_id' => $creator->id,
                'exception_date' => $data['exception_date'],
            ],
            [
                'is_available' => $isAvailable,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => isset($data['reason']) && trim($data['reason']) !== ''
                    ? trim($data['reason'])
                    : null,
            ]
        );
    }
}

