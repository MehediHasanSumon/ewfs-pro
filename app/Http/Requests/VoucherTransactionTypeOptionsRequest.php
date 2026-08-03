<?php

namespace App\Http\Requests;

use App\Helpers\VoucherTransactionTypeHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherTransactionTypeOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('voucher_categories', 'id')->where('status', true),
            ],
            'voucher_type' => [
                'required',
                Rule::in(VoucherTransactionTypeHelper::assignableVoucherTypes()),
            ],
            'selected_id' => [
                'nullable',
                'integer',
                Rule::exists('voucher_transaction_types', 'id'),
            ],
        ];
    }
}
