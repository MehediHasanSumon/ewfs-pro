<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseRequest extends FormRequest
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
                'supplier_id' => $this->input('supplier_id'),
                'unit_price' => $this->input('unit_price'),
                'quantity' => $this->input('quantity'),
                'discount' => $this->input('discount', 0),
                'payment_type' => $this->input('payment_type', 'Cash'),
                'from_account_id' => $this->input('from_account_id'),
                'paid_amount' => $this->input('paid_amount', 0),
                'bank_name' => $this->input('bank_name'),
                'branch_name' => $this->input('branch_name'),
                'account_no' => $this->input('account_no'),
                'bank_type' => $this->input('bank_type'),
                'cheque_no' => $this->input('cheque_no'),
                'cheque_date' => $this->input('cheque_date'),
                'mobile_bank' => $this->input('mobile_bank'),
                'mobile_number' => $this->input('mobile_number'),
            ]],
        ]);
    }

    public function rules(): array
    {
        return [
            'purchase_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:150'],
            'memo_no' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('status', true),
            ],
            'products.*.supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('status', true),
            ],
            'products.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'products.*.quantity' => ['required', 'numeric', 'gt:0'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'products.*.payment_type' => [
                'required',
                Rule::in(['Cash', 'Bank', 'Mobile Bank']),
            ],
            'products.*.from_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('status', true),
            ],
            'products.*.paid_amount' => ['required', 'numeric', 'min:0'],
            'products.*.bank_name' => ['nullable', 'string', 'max:150'],
            'products.*.branch_name' => ['nullable', 'string', 'max:150'],
            'products.*.account_no' => ['nullable', 'string', 'max:100'],
            'products.*.bank_type' => ['nullable', 'string', 'max:50'],
            'products.*.cheque_no' => ['nullable', 'string', 'max:100'],
            'products.*.cheque_date' => ['nullable', 'date'],
            'products.*.mobile_bank' => ['nullable', 'string', 'max:100'],
            'products.*.mobile_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('products', []) as $index => $product) {
                    $subtotal = round(
                        (float) ($product['unit_price'] ?? 0)
                        * (float) ($product['quantity'] ?? 0),
                        4
                    );
                    $discount = round(
                        (float) ($product['discount'] ?? 0),
                        4
                    );
                    $total = round($subtotal - $discount, 4);
                    $paid = round(
                        (float) ($product['paid_amount'] ?? 0),
                        4
                    );

                    if ($total <= 0) {
                        $validator->errors()->add(
                            "products.{$index}.discount",
                            'Purchase total must be greater than zero.'
                        );
                    }

                    if ($paid > max(0, $total)) {
                        $validator->errors()->add(
                            "products.{$index}.paid_amount",
                            'Paid amount cannot exceed the purchase total.'
                        );
                    }

                    if (
                        $paid > 0
                        && empty($product['from_account_id'])
                    ) {
                        $validator->errors()->add(
                            "products.{$index}.from_account_id",
                            'A payment account is required when a paid amount is entered.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'products.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'products.*.supplier_id.exists' => 'The selected supplier is unavailable or inactive.',
            'products.*.unit_price.gt' => 'Purchase unit price must be greater than zero.',
            'products.*.quantity.gt' => 'Purchase quantity must be greater than zero.',
        ];
    }
}
