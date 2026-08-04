<?php

namespace App\Http\Requests;

use App\Models\ShiftClosing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where('status', true),
            ],
            'salary_month' => ['required', 'integer', 'between:1,12'],
            'salary_year' => ['required', 'integer', 'between:2000,2100'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('employees', 'id')->where('status', true),
            ],
            'remarks' => ['nullable', 'array'],
            'remarks.*' => ['nullable', 'string', 'max:2000'],
            'amount' => ['prohibited'],
            'amounts' => ['prohibited'],
            'payment_method' => ['prohibited'],
            'payment_methods' => ['prohibited'],
            'account_id' => ['prohibited'],
            'account_ids' => ['prohibited'],
            'voucher_category_id' => ['prohibited'],
            'voucher_transaction_type_id' => ['prohibited'],
            'vouchers' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $validator->errors()->hasAny(['date', 'shift_id'])
                    || ! $this->filled('date')
                    || ! $this->filled('shift_id')
                ) {
                    return;
                }

                $closed = ShiftClosing::query()
                    ->posted()
                    ->whereDate('business_date', $this->date('date'))
                    ->where('shift_id', $this->integer('shift_id'))
                    ->exists();

                if ($closed) {
                    $validator->errors()->add(
                        'shift_id',
                        'The selected shift is already closed for this date.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Select at least one employee.',
            'employee_ids.min' => 'Select at least one employee.',
            'employee_ids.*.exists' => 'One or more selected employees are unavailable.',
            'shift_id.required' => 'Select a shift.',
            'amount.prohibited' => 'Salary amount is loaded from the employee salary structure.',
            'amounts.prohibited' => 'Salary amounts are loaded from employee salary structures.',
            'payment_method.prohibited' => 'Payment method is loaded from the employee payment account.',
            'payment_methods.prohibited' => 'Payment methods are loaded from employee payment accounts.',
            'account_id.prohibited' => 'Payment account is loaded from the employee profile.',
            'account_ids.prohibited' => 'Payment accounts are loaded from employee profiles.',
        ];
    }
}
