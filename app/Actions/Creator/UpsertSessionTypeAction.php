<?php

namespace App\Actions\Creator;

use App\Models\SessionType;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpsertSessionTypeAction
{
    /**
     * Create or update a 1-on-1 SessionType for the given creator.
     *
     * @param  array{
     *     title: string,
     *     duration_minutes: int,
     *     description?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function execute(User $creator, array $data, ?SessionType $sessionType = null): SessionType
    {
        if ($sessionType !== null && (int) $sessionType->user_id !== (int) $creator->id) {
            throw ValidationException::withMessages([
                'session_type' => 'No tienes permisos para editar este tipo de sesión.',
            ]);
        }

        $title = trim($data['title']);
        $duration = (int) $data['duration_minutes'];
        $slug = Str::slug($title).'-'.$duration;

        $payload = [
            'title' => $title,
            'slug' => $slug,
            'description' => isset($data['description']) && trim($data['description']) !== ''
                ? trim($data['description'])
                : null,
            'duration_minutes' => $duration,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];

        if ($sessionType !== null) {
            $sessionType->update($payload);

            return $sessionType->refresh();
        }

        return $creator->sessionTypes()->create($payload);
    }
}
