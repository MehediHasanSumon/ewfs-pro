<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s', 'after:start_time'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.required' => 'Start Time is required.',
            'start_time.date_format' => 'Start Time must use the hh:mm AM/PM format.',
            'end_time.required' => 'End Time is required.',
            'end_time.date_format' => 'End Time must use the hh:mm AM/PM format.',
            'end_time.after' => 'End Time must be after Start Time.',
        ];
    }
}
