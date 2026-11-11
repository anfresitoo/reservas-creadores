<?php

namespace App\Http\Controllers\Creator;

use App\Actions\Booking\CancelBookingAction;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CreatorResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreatorBookingController extends Controller
{
    public function cancel(
        Request $request,
        Booking $booking,
        CreatorResolverService $creatorResolver,
        CancelBookingAction $cancelAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $cancelAction->execute($creator, $booking);

        return back()->with('success', 'La reserva ha sido cancelada y el bloque horario liberado.');
    }
}
