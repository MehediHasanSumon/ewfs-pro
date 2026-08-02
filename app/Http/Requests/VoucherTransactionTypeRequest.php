<?php

namespace App\Http\Requests;

use App\Helpers\VoucherTransactionTypeHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherTransactionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $transactionType = $this->route('voucherTransactionType');

        $editableRules = [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('voucher_transaction_types', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'voucher_category_id',
                            $transactionType?->voucher_category_id
                                ?? $this->integer('voucher_category_id')
                        )
                    )
                    ->ignore($transactionType),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', 'boolean'],
        ];

        if ($transactionType) {
            return $editableRules;
        }

        return [
            'voucher_category_id' => [
                'required',
                'integer',
                Rule::exists('voucher_categories', 'id')->where('status', true),
            ],
            'voucher_type' => [
                'required',
                Rule::in(VoucherTransactionTypeHelper::voucherTypes()),
            ],
            ...$editableRules,
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }

        if (! $this->has('voucher_type') && $this->has('type')) {
            $this->merge(['voucher_type' => $this->input('type')]);
        }
    }
}
