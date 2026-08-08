<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => [
                'required',
                'integer',
                'between:1,12',
                Rule::unique('payroll_periods', 'month')
                    ->where(fn ($query) => $query->where('year', $this->integer('year'))),
            ],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'payable_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.unique' => 'A payroll period already exists for this month and year.',
        ];
    }
}
