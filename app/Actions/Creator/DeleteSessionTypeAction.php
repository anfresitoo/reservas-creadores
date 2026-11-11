<?php

namespace App\Actions\Creator;

use App\Models\SessionType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteSessionTypeAction
{
    public function execute(User $creator, SessionType $sessionType): void
    {
        if ((int) $sessionType->user_id !== (int) $creator->id) {
            throw ValidationException::withMessages([
                'session_type' => 'No tienes permisos para eliminar este tipo de sesión.',
            ]);
        }

        $sessionType->delete();
    }
}

