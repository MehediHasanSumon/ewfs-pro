<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class VehicleSalesContextService
{
    public function resolve(Vehicle $vehicle): array
    {
        if (! $vehicle->status) {
            throw ValidationException::withMessages([
                'vehicle' => 'The selected vehicle is unavailable.',
            ]);
        }

        $vehicle->load([
            'customer:id,name,status',
            'products' => fn ($query) => $query
                ->where('products.status', true)
                ->select('products.id', 'products.product_name'),
        ]);

        if (! $vehicle->customer || ! $vehicle->customer->status) {
            throw ValidationException::withMessages([
                'vehicle' => 'This vehicle is not assigned to any active customer.',
            ]);
        }

        return [
            'customer' => [
                'id' => $vehicle->customer->id,
                'name' => $vehicle->customer->name,
            ],
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->vehicle_name ?: $vehicle->vehicle_number,
                'number' => $vehicle->vehicle_number,
            ],
            'products' => $vehicle->products
                ->values()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'sort_order' => (int) $product->pivot->sort_order,
                ])
                ->all(),
        ];
    }
}
