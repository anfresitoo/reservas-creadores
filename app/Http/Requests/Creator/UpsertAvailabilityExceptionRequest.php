<?php

namespace App\Http\Requests\Creator;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAvailabilityExceptionRequest extends FormRequest
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
            'exception_date' => ['required', 'date_format:Y-m-d'],
            'is_available' => ['required', 'boolean'],
            'start_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'reason' => ['nullable', 'string', 'max:180'],
        ];
    }
}
