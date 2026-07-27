<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\Category;
use App\Models\Unit;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create Categories
        $oilCategory = Category::create([
            'name' => 'Oil',
            'code' => '1001',
            'status' => true
        ]);

        $othersCategory = Category::create([
            'name' => 'Others',
            'code' => '1002',
            'status' => true
        ]);

        // Create Units
        $units = [
            ['name' => 'litter', 'value' => '1', 'status' => true],
            ['name' => 'ml', 'value' => '1', 'status' => true],
            ['name' => 'pcs', 'value' => '1', 'status' => true],
        ];

        $createdUnits = [];
        foreach ($units as $unitData) {
            $createdUnits[] = Unit::create($unitData);
        }

        $literUnit = $createdUnits[0]; // litter
        $pcsUnit = $createdUnits[2]; // pcs

        $products = [
            [
                'category_id' => $oilCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC01',
                'product_name' => 'Petrol',
                'status' => 1,
                'purchase_price' => 70.00,
                'sales_price' => 75.00,
            ],
            [
                'category_id' => $oilCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC02',
                'product_name' => 'CNG',
                'status' => 1,
                'purchase_price' => 60.00,
                'sales_price' => 65.00,
            ],
            [
                'category_id' => $oilCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC03',
                'product_name' => 'Octane',
                'status' => 1,
                'purchase_price' => 118.02,
                'sales_price' => 122.00,
            ],
            [
                'category_id' => $oilCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC04',
                'product_name' => 'Diesel',
                'status' => 1,
                'purchase_price' => 101.25,
                'sales_price' => 102.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC05',
                'product_name' => 'Engine Oil',
                'status' => 1,
                'purchase_price' => 450.00,
                'sales_price' => 500.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC06',
                'product_name' => 'Brake Oil',
                'status' => 1,
                'purchase_price' => 180.00,
                'sales_price' => 200.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC07',
                'product_name' => 'Gear Oil',
                'status' => 1,
                'purchase_price' => 220.00,
                'sales_price' => 250.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $literUnit->id,
                'product_code' => 'PC08',
                'product_name' => 'Coolant',
                'status' => 1,
                'purchase_price' => 150.00,
                'sales_price' => 170.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $pcsUnit->id,
                'product_code' => 'PC09',
                'product_name' => 'Air Filter',
                'status' => 1,
                'purchase_price' => 350.00,
                'sales_price' => 400.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $pcsUnit->id,
                'product_code' => 'PC10',
                'product_name' => 'Oil Filter',
                'status' => 1,
                'purchase_price' => 280.00,
                'sales_price' => 320.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $pcsUnit->id,
                'product_code' => 'PC11',
                'product_name' => 'Spark Plug',
                'status' => 1,
                'purchase_price' => 120.00,
                'sales_price' => 150.00,
            ],
            [
                'category_id' => $othersCategory->id,
                'unit_id' => $pcsUnit->id,
                'product_code' => 'PC12',
                'product_name' => 'Battery',
                'status' => 1,
                'purchase_price' => 4500.00,
                'sales_price' => 5000.00,
            ]
        ];

        foreach ($products as $productData) {
            $purchasePrice = $productData['purchase_price'];
            $salesPrice = $productData['sales_price'];
            unset($productData['purchase_price'], $productData['sales_price']);

            $product = Product::create($productData);

            ProductRate::create([
                'product_id' => $product->id,
                'purchase_price' => $purchasePrice,
                'sales_price' => $salesPrice,
                'effective_date' => now(),
                'status' => true
            ]);
        }
    }
}
