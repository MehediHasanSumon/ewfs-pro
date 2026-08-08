<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routePeriod = $this->route('period');
        $periodId = $routePeriod?->id ?? $this->integer('payroll_id');
        $payrollIdRules = [
            'integer',
            Rule::exists('payroll_periods', 'id')
                ->where('status', 'generated'),
        ];

        array_unshift(
            $payrollIdRules,
            $routePeriod ? 'nullable' : 'required'
        );

        return [
            'payroll_id' => $payrollIdRules,
            'date' => ['required', 'date'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('payroll_items', 'employee_id')
                    ->where('payroll_period_id', $periodId)
                    ->where('status', 'pending'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.*.exists' => 'One or more selected payroll employees are unavailable or already paid.',
        ];
    }
}
