<?php

namespace App\Http\Controllers\Creator;

use App\Actions\Creator\DeleteAvailabilityExceptionAction;
use App\Actions\Creator\UpdateWeeklyScheduleAction;
use App\Actions\Creator\UpsertAvailabilityExceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Creator\UpdateWeeklyScheduleRequest;
use App\Http\Requests\Creator\UpsertAvailabilityExceptionRequest;
use App\Models\AvailabilityException;
use App\Services\CreatorResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function updateWeekly(
        UpdateWeeklyScheduleRequest $request,
        CreatorResolverService $creatorResolver,
        UpdateWeeklyScheduleAction $updateWeeklyAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $updateWeeklyAction->execute($creator, $request->validated());

        return back()->with('success', 'Horario semanal y zona horaria actualizados.');
    }

    public function storeException(
        UpsertAvailabilityExceptionRequest $request,
        CreatorResolverService $creatorResolver,
        UpsertAvailabilityExceptionAction $upsertExceptionAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $upsertExceptionAction->execute($creator, $request->validated());

        return back()->with('success', 'Excepción de fecha guardada correctamente.');
    }

    public function destroyException(
        Request $request,
        AvailabilityException $exception,
        CreatorResolverService $creatorResolver,
        DeleteAvailabilityExceptionAction $deleteExceptionAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $deleteExceptionAction->execute($creator, $exception);

        return back()->with('success', 'Excepción de fecha eliminada.');
    }
}
