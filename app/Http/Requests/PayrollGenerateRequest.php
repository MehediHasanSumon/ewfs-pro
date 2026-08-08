<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employees' => ['required', 'array', 'min:1', 'max:500'],
            'employees.*.employee_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id')->where('status', true),
            ],
            'employees.*.deductions' => [
                'nullable',
                'array',
                'max:50',
            ],
            'employees.*.deductions.*.amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999',
            ],
            'employees.*.deductions.*.reason' => [
                'required',
                'string',
                'max:500',
            ],
            'employees.*.extras' => [
                'nullable',
                'array',
                'max:50',
            ],
            'employees.*.extras.*.voucher_transaction_type_id' => [
                'required',
                'integer',
                Rule::exists('voucher_transaction_types', 'id'),
            ],
            'employees.*.extras.*.amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999',
            ],
            'employees.*.extras.*.remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employees.required' => 'Select at least one employee.',
            'employees.min' => 'Select at least one employee.',
            'employees.*.employee_id.distinct' => 'An employee can be included only once in a payroll.',
            'employees.*.deductions.*.reason.required' => 'Enter a reason for every salary deduction.',
        ];
    }
}
