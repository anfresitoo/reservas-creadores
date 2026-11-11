<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelBookingAction
{
    public function execute(User $creator, Booking $booking): Booking
    {
        if ((int) $booking->creator_id !== (int) $creator->id) {
            throw ValidationException::withMessages([
                'booking' => 'No tienes permisos para modificar esta reserva.',
            ]);
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
        ]);

        return $booking->refresh();
    }
}
