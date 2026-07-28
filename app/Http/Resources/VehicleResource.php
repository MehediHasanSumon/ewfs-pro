<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_name' => $this->vehicle_name,
            'vehicle_number' => $this->vehicle_number,
            'reg_date' => $this->reg_date?->format('Y-m-d'),
            'status' => (bool) $this->status,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'proprietor_name' => $this->customer?->proprietor_name,
                'mobile' => $this->customer?->mobile,
            ]),
            'products' => $this->whenLoaded('products', fn () => $this->products
                ->values()
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'sort_order' => (int) $product->pivot->sort_order,
                ])),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
