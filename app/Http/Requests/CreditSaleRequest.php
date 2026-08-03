<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreditSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('products'))) {
            return;
        }

        if (! $this->filled('product_id')) {
            return;
        }

        $this->merge([
            'products' => [[
                'product_id' => $this->input('product_id'),
                'customer_id' => $this->input('customer_id'),
                'vehicle_id' => $this->input('vehicle_id'),
                'memo_no' => $this->input('memo_no'),
                'quantity' => $this->input('quantity'),
                'amount' => $this->input('amount'),
                'discount' => $this->input('discount', 0),
                'remarks' => $this->input('remarks'),
            ]],
        ]);
    }

    public function rules(): array
    {
        return [
            'sale_date' => ['required', 'date'],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where('status', true),
            ],
            'memo_no' => ['nullable', 'string', 'max:150'],
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('status', true),
            ],
            'products.*.customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('status', true),
            ],
            'products.*.vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where('status', true),
            ],
            'products.*.memo_no' => ['nullable', 'string', 'max:150'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.amount' => ['required', 'numeric', 'gt:0'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'products.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('products', []) as $index => $product) {
                    $amount = round(
                        (float) ($product['amount'] ?? 0),
                        4
                    );
                    $discount = round(
                        (float) ($product['discount'] ?? 0),
                        4
                    );

                    if ($amount - $discount <= 0) {
                        $validator->errors()->add(
                            "products.{$index}.discount",
                            'Credit sale total must be greater than zero.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'shift_id.exists' => 'The selected shift is unavailable or inactive.',
            'products.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'products.*.customer_id.exists' => 'The selected customer is unavailable or inactive.',
            'products.*.vehicle_id.exists' => 'The selected vehicle is unavailable or inactive.',
            'products.*.quantity.gt' => 'Credit sale quantity must be greater than zero.',
            'products.*.amount.gt' => 'Credit sale amount must be greater than zero.',
        ];
    }
}
