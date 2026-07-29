<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $headerRules = [
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ];

        if (! $this->isMethod('post')) {
            return $headerRules + $this->lineRules();
        }

        $rules = $headerRules + [
            'vouchers' => ['required', 'array', 'min:1'],
        ];

        foreach ($this->lineRules() as $field => $fieldRules) {
            $rules['vouchers.*.'.$field] = $fieldRules;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'vouchers.required' => 'Add at least one payment voucher.',
            'vouchers.min' => 'Add at least one payment voucher.',
            'amount.gt' => 'Amount must be greater than zero.',
            'vouchers.*.amount.gt' => 'Amount must be greater than zero.',
            'from_account_id.different' => 'The source and destination accounts must be different.',
            'vouchers.*.from_account_id.different' => 'The source and destination accounts must be different.',
        ];
    }

    private function lineRules(): array
    {
        return [
            'voucher_category_id' => [
                'required',
                'integer',
                Rule::exists('voucher_categories', 'id')->where('status', true),
            ],
            'payment_sub_type_id' => [
                'required',
                'integer',
                Rule::exists('payment_sub_types', 'id')
                    ->where('status', true)
                    ->whereIn('type', ['payment', 'both']),
            ],
            'from_account_id' => [
                'required',
                'integer',
                'different:to_account_id',
                Rule::exists('accounts', 'id')->where('status', true),
            ],
            'to_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('status', true),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                Rule::in(['Cash', 'Bank', 'Mobile Bank']),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'account_no' => ['nullable', 'string', 'max:100'],
            'bank_type' => ['nullable', 'string', 'max:50'],
            'cheque_no' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'mobile_bank' => ['nullable', 'string', 'max:100'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
