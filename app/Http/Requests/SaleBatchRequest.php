<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rows' => collect($this->input('rows', []))
                ->map(fn ($row) => is_array($row)
                    ? [
                        ...$row,
                        'customer_mobile' => trim(
                            (string) ($row['customer_mobile'] ?? '')
                        ),
                    ]
                    : $row)
                ->all(),
        ]);
    }

    public function rules(): array
    {
        $maxRows = max(1, (int) config('erp.sales.max_items', 100));

        return [
            ...SaleRequest::headerRules(),
            'rows' => ['required', 'array', 'min:1', 'max:'.$maxRows],
            ...SaleRequest::transactionRules('rows.*.'),
            'rows.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('status', true),
            ],
            'rows.*.quantity' => ['required', 'numeric', 'gt:0'],
            'rows.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'Add at least one sale row to the cart.',
            'rows.min' => 'Add at least one sale row to the cart.',
            'rows.max' => 'A sales session may contain at most :max rows.',
            'rows.*.customer_name.required_without' => 'Customer name is required for a walk-in customer.',
            'rows.*.customer_mobile.required' => 'Mobile number is required.',
            'rows.*.product_id.required' => 'Select a product.',
            'rows.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'rows.*.quantity.gt' => 'Quantity must be greater than zero.',
            'rows.*.bank_type.required_if' => 'Bank transaction type is required.',
            'rows.*.bank_name.required_if' => 'Bank name is required.',
            'rows.*.cheque_no.required_if' => 'Cheque number is required.',
            'rows.*.cheque_date.required_if' => 'Cheque date is required.',
            'rows.*.mobile_bank.required_if' => 'Mobile bank name is required.',
        ];
    }
}
