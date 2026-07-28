<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VehicleSalesContextService
{
    public function forSalesSelection(): Collection
    {
        return Vehicle::query()
            ->where('vehicles.status', true)
            ->with([
                'customer:id,name,status',
                'products' => fn ($query) => $query
                    ->where('products.status', true)
                    ->select('products.id', 'products.product_name'),
            ])
            ->get([
                'vehicles.id',
                'vehicles.vehicle_number',
                'vehicles.customer_id',
            ])
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'vehicle_number' => $vehicle->vehicle_number,
                'customer_id' => $vehicle->customer_id,
                'customer' => $vehicle->customer?->status
                    ? [
                        'id' => $vehicle->customer->id,
                        'name' => $vehicle->customer->name,
                    ]
                    : null,
                'products' => $vehicle->products
                    ->values()
                    ->map(fn ($product) => [
                        'id' => $product->id,
                        'product_name' => $product->product_name,
                        'sort_order' => (int) $product->pivot->sort_order,
                    ])
                    ->all(),
            ]);
    }

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
