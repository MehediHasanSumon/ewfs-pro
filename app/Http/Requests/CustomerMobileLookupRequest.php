<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerMobileLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => trim((string) $this->input('mobile')),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'Mobile number is required.',
            'mobile.max' => 'Mobile number may not be greater than 50 characters.',
        ];
    }
}
