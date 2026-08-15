<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FundTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'transfer_date' => ['nullable', 'date'],
            'from_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'to_account_id' => [
                'required',
                'integer',
                'exists:accounts,id',
                'different:from_account_id',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_fee' => ['nullable', 'numeric', 'min:0'],
            'fee_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'reference_no' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_account_id.different' => 'The destination (To) account must be different from the source (From) account.',
            'amount.gt' => 'The transfer amount must be greater than zero.',
            'transfer_fee.min' => 'The transfer fee cannot be negative.',
        ];
    }
}
