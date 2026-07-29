<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->route('customer');

        if ($customer instanceof Customer) {
            $this->merge(['customer_id' => $customer->id]);
        }

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
        $vehicle = $this->route('vehicle');
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->id : $vehicle;
        $maxProducts = max(1, (int) config('erp.vehicle_products.max_assigned', 50));

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
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
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'vehicle_number')
                    ->where(fn ($query) => $query->where('customer_id', $this->input('customer_id')))
                    ->ignore($vehicleId),
            ],
            'reg_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_number.required' => 'Vehicle number is required.',
            'vehicle_number.unique' => 'This vehicle number is already registered for the selected customer.',
            'products.max' => 'A vehicle may have at most :max assigned products.',
            'products.*.product_id.distinct' => 'The same product cannot be assigned more than once.',
            'products.*.product_id.exists' => 'The selected product is unavailable or inactive.',
            'products.*.sort_order.distinct' => 'Each assigned product must have a unique order.',
        ];
    }
}
