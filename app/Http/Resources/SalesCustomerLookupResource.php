<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesCustomerLookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'vehicles' => $this->whenLoaded('vehicles', fn () => $this->vehicles
                ->map(fn ($vehicle) => [
                    'id' => $vehicle->id,
                    'vehicle_number' => $vehicle->vehicle_number,
                    'vehicle_name' => $vehicle->vehicle_name,
                ])
                ->values()
                ->all()),
        ];
    }
}
