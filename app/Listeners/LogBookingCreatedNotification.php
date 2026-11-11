<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use Illuminate\Support\Facades\Log;

class LogBookingCreatedNotification
{
    /**
     * Handle the BookingCreated domain event.
     * Ready to be extended into async email/webhook microservice dispatchers.
     */
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        Log::info('BookingCreated event dispatched', [
            'booking_uuid' => $booking->uuid,
            'creator_id' => $booking->creator_id,
            'session_type_id' => $booking->session_type_id,
            'guest_email' => $booking->guest_email,
            'guest_timezone' => $booking->guest_timezone,
            'start_at_utc' => $booking->start_at_utc?->format('Y-m-d\TH:i:s\Z'),
            'end_at_utc' => $booking->end_at_utc?->format('Y-m-d\TH:i:s\Z'),
        ]);
    }
}
