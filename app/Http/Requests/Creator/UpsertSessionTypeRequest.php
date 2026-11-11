<?php

namespace App\Http\Requests\Creator;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSessionTypeRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:240'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El nombre o tipo de la sesión es obligatorio.',
            'duration_minutes.required' => 'La duración en minutos es obligatoria.',
            'duration_minutes.min' => 'La sesión debe durar al menos 10 minutos.',
            'duration_minutes.max' => 'La sesión no puede exceder los 240 minutos.',
        ];
    }
}
