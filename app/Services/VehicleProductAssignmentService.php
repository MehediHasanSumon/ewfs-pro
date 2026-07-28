<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleProductAssignmentService
{
    public function sync(Vehicle $vehicle, array $products): void
    {
        $vehicle->products()->sync($this->normalize($products));
        $vehicle->unsetRelation('products');
    }

    public function normalize(array $products): array
    {
        return collect($products)
            ->sortBy([
                ['sort_order', 'asc'],
                ['product_id', 'asc'],
            ])
            ->values()
            ->mapWithKeys(fn (array $product, int $index) => [
                (int) $product['product_id'] => ['sort_order' => $index + 1],
            ])
            ->all();
    }

    public function assertAssigned(
        Vehicle $vehicle,
        int $productId,
        string $attribute = 'product_id'
    ): void {
        $assigned = $vehicle->relationLoaded('products')
            ? $vehicle->products->contains('id', $productId)
            : $vehicle->products()->whereKey($productId)->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                $attribute => 'The selected product is not assigned to the selected vehicle.',
            ]);
        }
    }

    public function assertBelongsToCustomer(
        Vehicle $vehicle,
        Customer $customer,
        string $attribute = 'vehicle_id'
    ): void {
        if ((int) $vehicle->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                $attribute => 'The selected vehicle does not belong to the selected customer.',
            ]);
        }
    }
}
