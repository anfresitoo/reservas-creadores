<?php

namespace App\Actions\Creator;

use App\Models\Availability;
use App\Models\User;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWeeklyScheduleAction
{
    public function __construct(
        private readonly TimezoneService $timezoneService
    ) {}

    /**
     * Update the creator's local timezone and weekly availability schedule.
     *
     * @param  array{
     *     timezone: string,
     *     days: array<int, array{
     *         day_of_week: int,
     *         start_time: string,
     *         end_time: string,
     *         is_enabled: bool
     *     }>
     * }  $data
     */
    public function execute(User $creator, array $data): void
    {
        if (! $this->timezoneService->isValidTimezone($data['timezone'])) {
            throw ValidationException::withMessages([
                'timezone' => 'La zona horaria del creador no es válida.',
            ]);
        }

        foreach ($data['days'] as $index => $dayRule) {
            $start = substr($dayRule['start_time'], 0, 5);
            $end = substr($dayRule['end_time'], 0, 5);

            if ((bool) $dayRule['is_enabled'] && $end <= $start) {
                throw ValidationException::withMessages([
                    "days.{$index}.end_time" => 'La hora de término debe ser posterior a la hora de inicio.',
                ]);
            }
        }

        DB::transaction(function () use ($creator, $data) {
            $creator->update([
                'timezone' => $data['timezone'],
            ]);

            foreach ($data['days'] as $dayRule) {
                $startTime = strlen($dayRule['start_time']) === 5
                    ? $dayRule['start_time'].':00'
                    : $dayRule['start_time'];
                $endTime = strlen($dayRule['end_time']) === 5
                    ? $dayRule['end_time'].':00'
                    : $dayRule['end_time'];

                Availability::updateOrCreate(
                    [
                        'user_id' => $creator->id,
                        'day_of_week' => (int) $dayRule['day_of_week'],
                    ],
                    [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_enabled' => (bool) $dayRule['is_enabled'],
                    ]
                );
            }
        });
    }
}

