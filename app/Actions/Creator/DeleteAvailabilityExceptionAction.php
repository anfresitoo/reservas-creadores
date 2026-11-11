<?php

namespace App\Actions\Creator;

use App\Models\AvailabilityException;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteAvailabilityExceptionAction
{
    public function execute(User $creator, AvailabilityException $exception): void
    {
        if ((int) $exception->user_id !== (int) $creator->id) {
            throw ValidationException::withMessages([
                'exception' => 'No tienes permisos para eliminar esta excepción.',
            ]);
        }

        $exception->delete();
    }
}
