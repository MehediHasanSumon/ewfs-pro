<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vehicle;
use App\Models\Customer;
use App\Models\Product;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $oilProducts = Product::whereHas('category', function($query) {
            $query->where('code', '1001');
        })->pluck('id')->toArray();
        
        $otherProducts = Product::whereHas('category', function($query) {
            $query->where('code', '1002');
        })->pluck('id')->toArray();

        $vehicleTypes = ['Car', 'Truck', 'Bus', 'Motorcycle', 'Pickup', 'Van', 'SUV', 'Microbus'];
        $vehicleNames = [
            'Car' => ['Toyota Corolla', 'Honda Civic', 'Nissan Sunny', 'Mitsubishi Lancer'],
            'Truck' => ['Tata 407', 'Ashok Leyland', 'Mahindra Bolero Pickup', 'Isuzu NPR'],
            'Bus' => ['Ashok Leyland Bus', 'Tata Bus', 'Volvo Bus', 'Scania Bus'],
            'Motorcycle' => ['Honda CBR', 'Yamaha R15', 'Suzuki Gixxer', 'Bajaj Pulsar'],
            'Pickup' => ['Toyota Hilux', 'Ford Ranger', 'Isuzu D-Max', 'Mitsubishi L200'],
            'Van' => ['Toyota Hiace', 'Nissan Urvan', 'Hyundai H1', 'Ford Transit'],
            'SUV' => ['Toyota Prado', 'Honda CR-V', 'Nissan X-Trail', 'Mitsubishi Pajero'],
            'Microbus' => ['Toyota Coaster', 'Nissan Civilian', 'Hyundai County', 'Isuzu Elf']
        ];

        $vehicleCounter = 1;

        foreach ($customers as $customer) {
            // Each customer gets 5-8 vehicles
            $vehicleCount = rand(5, 8);
            
            for ($i = 0; $i < $vehicleCount; $i++) {
                $vehicleType = $vehicleTypes[array_rand($vehicleTypes)];
                $vehicleName = $vehicleNames[$vehicleType][array_rand($vehicleNames[$vehicleType])];
                
                $vehicle = Vehicle::create([
                    'customer_id' => $customer->id,
                    'vehicle_type' => $vehicleType,
                    'vehicle_name' => $vehicleName,
                    'vehicle_number' => 'DHK-' . str_pad($vehicleCounter, 4, '0', STR_PAD_LEFT),
                    'reg_date' => now()->subDays(rand(30, 1000)),
                    'status' => true
                ]);

                // Each vehicle gets 2-4 products (mix of oil and other products)
                $productCount = rand(2, 4);
                $selectedProducts = [];
                
                // Always include at least 1 oil product
                $selectedProducts[] = $oilProducts[array_rand($oilProducts)];
                
                // Add remaining products (mix of oil and others)
                for ($j = 1; $j < $productCount; $j++) {
                    $useOilProduct = rand(0, 1); // 50% chance
                    if ($useOilProduct && count($oilProducts) > 0) {
                        $productId = $oilProducts[array_rand($oilProducts)];
                    } else if (count($otherProducts) > 0) {
                        $productId = $otherProducts[array_rand($otherProducts)];
                    } else {
                        continue;
                    }
                    
                    if (!in_array($productId, $selectedProducts)) {
                        $selectedProducts[] = $productId;
                    }
                }

                $vehicle->products()->attach($selectedProducts);
                $vehicleCounter++;
            }
        }
    }
}