<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();

        $diesel = Product::where('product_name', 'Diesel')
            ->orWhere('product_code', 'PC04')
            ->first();

        $octane = Product::where('product_name', 'Octane')
            ->orWhere('product_code', 'PC03')
            ->first();

        $fuelProductIds = array_filter([$diesel?->id, $octane?->id]);

        if (empty($fuelProductIds)) {
            $fuelProductIds = Product::pluck('id')->take(2)->toArray();
        }

        $vehicleTypes = ['Truck', 'Covered Van', 'Pickup', 'Microbus', 'Bus', 'Car'];
        $vehicleNames = [
            'Truck' => ['Tata 407', 'Ashok Leyland 1616', 'Isuzu Forward', 'Eicher Pro 2049'],
            'Covered Van' => ['Tata LPT 709', 'Mahindra Bolero Maxi Truck', 'Ashok Leyland Dost', 'Isuzu NPR'],
            'Pickup' => ['Toyota Hilux', 'Mahindra Bolero Pickup', 'Tata Ace', 'Mitsubishi L200'],
            'Microbus' => ['Toyota Hiace', 'Toyota Noah', 'Nissan Urvan', 'Hyundai H1'],
            'Bus' => ['Hino 1J', 'Ashok Leyland Bus', 'Tata 1109 Bus', 'Isuzu Bus'],
            'Car' => ['Toyota Corolla', 'Toyota Allion', 'Honda Civic', 'Nissan Sunny'],
        ];

        $vehicleCounter = 1;

        foreach ($customers as $customer) {
            // Exactly 4 vehicles per customer/company
            for ($i = 1; $i <= 4; $i++) {
                $type = $vehicleTypes[($vehicleCounter - 1) % count($vehicleTypes)];
                $namePool = $vehicleNames[$type];
                $name = $namePool[($i - 1) % count($namePool)];

                // Vehicle Number in 00-0000 style (e.g. 11-1001, 11-1002...)
                $prefix = 11 + (int) floor(($vehicleCounter - 1) / 9000);
                $number = 1000 + (($vehicleCounter - 1) % 9000);
                $vehicleNumber = sprintf('%02d-%04d', $prefix, $number);

                $vehicle = Vehicle::create([
                    'customer_id' => $customer->id,
                    'vehicle_type' => $type,
                    'vehicle_name' => $name,
                    'vehicle_number' => $vehicleNumber,
                    'reg_date' => now()->subDays(rand(30, 800)),
                    'status' => true,
                ]);

                // Assign either Diesel or Octane to each vehicle
                $selectedProductId = $fuelProductIds[($vehicleCounter - 1) % count($fuelProductIds)];

                $vehicle->products()->attach([
                    $selectedProductId => ['sort_order' => 1],
                ]);

                $vehicleCounter++;
            }
        }
    }
}
