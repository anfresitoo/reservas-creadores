<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_type_uuid' => ['required', 'uuid'],
            'start_at_utc' => ['required', 'string'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:160'],
            'guest_timezone' => ['required', 'string', 'timezone:all'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_type_uuid.required' => 'Debes seleccionar un tipo de sesión.',
            'start_at_utc.required' => 'Debes seleccionar un bloque horario disponible.',
            'guest_name.required' => 'Por favor ingresa tu nombre completo.',
            'guest_email.required' => 'Por favor ingresa tu correo electrónico.',
            'guest_email.email' => 'Ingresa un correo electrónico válido.',
            'guest_timezone.required' => 'La zona horaria es obligatoria.',
            'guest_timezone.timezone' => 'La zona horaria seleccionada no es válida.',
        ];
    }
}

