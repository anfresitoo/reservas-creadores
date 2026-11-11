<?php

namespace App\Http\Controllers\Creator;

use App\Actions\Creator\DeleteSessionTypeAction;
use App\Actions\Creator\UpsertSessionTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Creator\UpsertSessionTypeRequest;
use App\Models\SessionType;
use App\Services\CreatorResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionTypeController extends Controller
{
    public function store(
        UpsertSessionTypeRequest $request,
        CreatorResolverService $creatorResolver,
        UpsertSessionTypeAction $upsertAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $upsertAction->execute($creator, $request->validated());

        return back()->with('success', 'Tipo de sesión creado correctamente.');
    }

    public function update(
        UpsertSessionTypeRequest $request,
        SessionType $sessionType,
        CreatorResolverService $creatorResolver,
        UpsertSessionTypeAction $upsertAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $upsertAction->execute($creator, $request->validated(), $sessionType);

        return back()->with('success', 'Tipo de sesión actualizado correctamente.');
    }

    public function destroy(
        Request $request,
        SessionType $sessionType,
        CreatorResolverService $creatorResolver,
        DeleteSessionTypeAction $deleteAction
    ): RedirectResponse {
        $creator = $creatorResolver->resolveActiveCreator($request);
        $deleteAction->execute($creator, $sessionType);

        return back()->with('success', 'Tipo de sesión eliminado.');
    }
}

