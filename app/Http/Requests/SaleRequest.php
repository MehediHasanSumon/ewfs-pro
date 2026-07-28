<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $legacyProducts = $this->input('products');
        $legacyLine = is_array($legacyProducts)
            ? collect($legacyProducts)->first()
            : null;
        $items = $this->input('items');

        if (! is_array($items) && is_array($legacyProducts)) {
            $items = collect($legacyProducts)
                ->map(fn (array $line) => [
                    'product_id' => $line['product_id'] ?? null,
                    'quantity' => $line['quantity'] ?? null,
                    'discount' => $line['discount'] ?? 0,
                    'remarks' => $line['remarks'] ?? null,
                ])
                ->all();
        }

        if (! is_array($items) && $this->filled('product_id')) {
            $items = [[
                'product_id' => $this->input('product_id'),
                'quantity' => $this->input('quantity'),
                'discount' => $this->input('discount', 0),
                'remarks' => $this->input('remarks'),
            ]];
        }

        $customerMobile = $this->input(
            'customer_mobile',
            $legacyLine['mobile_number'] ?? $this->input('mobile_number')
        );

        $this->merge([
            'customer_id' => $this->input(
                'customer_id',
                $legacyLine['customer_id'] ?? null
            ),
            'customer_name' => $this->input(
                'customer_name',
                $legacyLine['customer'] ?? $this->input('customer')
            ),
            'customer_mobile' => trim((string) $customerMobile),
            'customer_address' => $this->input('customer_address'),
            'save_customer' => $this->boolean('save_customer'),
            'vehicle_id' => $this->input(
                'vehicle_id',
                $legacyLine['vehicle_id'] ?? null
            ),
            'vehicle_no' => $this->input(
                'vehicle_no',
                $legacyLine['vehicle_no'] ?? null
            ),
            'memo_no' => $this->input(
                'memo_no',
                $legacyLine['memo_no'] ?? null
            ),
            'payment_type' => $this->input(
                'payment_type',
                $legacyLine['payment_type'] ?? null
            ),
            'to_account_id' => $this->input(
                'to_account_id',
                $legacyLine['to_account_id'] ?? null
            ),
            'paid_amount' => $this->input(
                'paid_amount',
                $legacyLine['paid_amount'] ?? null
            ),
            'bank_type' => $this->input(
                'bank_type',
                $legacyLine['bank_type'] ?? null
            ),
            'bank_name' => $this->input(
                'bank_name',
                $legacyLine['bank_name'] ?? null
            ),
            'branch_name' => $this->input(
                'branch_name',
                $legacyLine['branch_name'] ?? null
            ),
            'account_no' => $this->input(
                'account_no',
                $legacyLine['account_no'] ?? null
            ),
            'cheque_no' => $this->input(
                'cheque_no',
                $legacyLine['cheque_no'] ?? null
            ),
            'cheque_date' => $this->input(
                'cheque_date',
                $legacyLine['cheque_date'] ?? null
            ),
            'mobile_bank' => $this->input(
                'mobile_bank',
                $legacyLine['mobile_bank'] ?? null
            ),
            'payment_mobile_number' => $this->input(
                'payment_mobile_number',
                $legacyLine['payment_mobile_number'] ?? null
            ),
            'remarks' => $this->input(
                'remarks',
                $legacyLine['remarks'] ?? null
            ),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        $maxItems = max(1, (int) config('erp.sales.max_items', 100));

        return [
            'sale_date' => ['required', 'date'],
            'shift_id' => [
                'required',
                'integer',
                Rule::exists('shifts', 'id')->where('status', true),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('status', true),
            ],
            'customer_name' => [
                'required_without:customer_id',
                'nullable',
                'string',
                'max:150',
            ],
            'customer_mobile' => ['required', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string', 'max:2000'],
            'save_customer' => ['boolean'],
            'vehicle_id' => [
                'nullable',
                'integer',
                Rule::exists('vehicles', 'id')->where('status', true),
            ],
            'vehicle_no' => ['nullable', 'string', 'max:50'],
            'memo_no' => ['nullable', 'string', 'max:150'],
            'payment_type' => ['required', Rule::in(['Cash', 'Bank', 'Mobile Bank'])],
            'to_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('status', true),
            ],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'bank_type' => ['nullable', 'required_if:payment_type,Bank', 'string', 'max:50'],
            'bank_name' => ['nullable', 'required_if:payment_type,Bank', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'account_no' => ['nullable', 'string', 'max:100'],
            'cheque_no' => [
                'nullable',
                'required_if:bank_type,Cheque',
                'string',
                'max:100',
            ],
            'cheque_date' => ['nullable', 'required_if:bank_type,Cheque', 'date'],
            'mobile_bank' => [
                'nullable',
                'required_if:payment_type,Mobile Bank',
                'string',
                'max:100',
            ],
            'payment_mobile_number' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:'.$maxItems],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('status', true),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required_without' => 'Customer name is required for a walk-in customer.',
            'customer_mobile.required' => 'Mobile number is required.',
            'shift_id.exists' => 'The selected shift is unavailable or inactive.',
            'items.required' => 'Add at least one product to the cart.',
            'items.min' => 'Add at least one product to the cart.',
            'items.max' => 'A sale may contain at most :max products.',
            'items.*.product_id.distinct' => 'The same product cannot be added more than once.',
            'items.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'items.*.quantity.gt' => 'Product quantity must be greater than zero.',
            'bank_type.required_if' => 'Bank transaction type is required.',
            'bank_name.required_if' => 'Bank name is required.',
            'cheque_no.required_if' => 'Cheque number is required.',
            'cheque_date.required_if' => 'Cheque date is required.',
            'mobile_bank.required_if' => 'Mobile bank name is required.',
        ];
    }
}
