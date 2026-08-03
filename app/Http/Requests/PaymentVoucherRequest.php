<?php

namespace App\Http\Requests;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Http\Requests\Concerns\ValidatesVoucherTransactionTypeSelection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentVoucherRequest extends FormRequest
{
    use ValidatesVoucherTransactionTypeSelection;

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
            'voucher_transaction_type_id.exists' => 'The selected transaction type is not valid for this voucher.',
            'vouchers.*.voucher_transaction_type_id.exists' => 'The selected transaction type is not valid for this voucher.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateVoucherTransactionTypeSelections(
                    $validator,
                    VoucherTransactionTypeHelper::paymentVoucherType()
                );
            },
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
            'voucher_transaction_type_id' => [
                'required',
                'integer',
                Rule::exists('voucher_transaction_types', 'id'),
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

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_sub_type_id') && ! $this->has('voucher_transaction_type_id')) {
            $this->merge([
                'voucher_transaction_type_id' => $this->input('payment_sub_type_id'),
            ]);
        }

        if (! is_array($this->input('vouchers'))) {
            return;
        }

        $vouchers = collect($this->input('vouchers'))
            ->map(function ($voucher): array {
                $voucher = is_array($voucher) ? $voucher : [];
                $voucher['voucher_transaction_type_id'] ??=
                    $voucher['payment_sub_type_id'] ?? null;

                return $voucher;
            })
            ->all();

        $this->merge(['vouchers' => $vouchers]);
    }
}
