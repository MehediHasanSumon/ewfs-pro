<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('products') && is_array($this->input('product_ids'))) {
            $this->merge([
                'products' => collect($this->input('product_ids'))
                    ->values()
                    ->map(fn ($productId, int $index) => [
                        'product_id' => (int) $productId,
                        'sort_order' => $index + 1,
                    ])
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'code' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'proprietor_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'nid_number' => ['nullable', 'string', 'max:100'],
            'vat_reg_no' => ['nullable', 'string', 'max:100'],
            'tin_no' => ['nullable', 'string', 'max:100'],
            'trade_license' => ['nullable', 'string', 'max:100'],
            'discount_rate' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'previous_due' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];

        if ($this->isMethod('post')) {
            $maxProducts = max(1, (int) config('erp.vehicle_products.max_assigned', 50));
            $rules += [
                'products' => ['nullable', 'array', 'max:'.$maxProducts],
                'products.*.product_id' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('products', 'id')->where('status', true),
                ],
                'products.*.sort_order' => ['required', 'integer', 'distinct', 'min:1'],
                'vehicle_type' => ['nullable', 'string', 'max:150'],
                'vehicle_name' => ['nullable', 'string', 'max:150'],
                'vehicle_number' => [
                    'nullable',
                    'required_with:vehicle_type,vehicle_name,reg_date,products',
                    'string',
                    'max:50',
                ],
                'reg_date' => ['nullable', 'date'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'proprietor_name.max' => 'Proprietor name may not be greater than 255 characters.',
            'vehicle_number.required_with' => 'Vehicle number is required when vehicle details or products are provided.',
            'products.max' => 'A vehicle may have at most :max assigned products.',
            'products.*.product_id.distinct' => 'The same product cannot be assigned more than once.',
            'products.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'products.*.sort_order.distinct' => 'Each assigned product must have a unique order.',
        ];
    }
}
