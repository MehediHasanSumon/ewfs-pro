<?php

namespace Database\Seeders;

use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;

class VoucherCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Employee',
                'description' => 'Employee related payments and receipts',
                'status' => true
            ],
            [
                'name' => 'Supplier',
                'description' => 'Supplier related payments and receipts', 
                'status' => true
            ],
            [
                'name' => 'Customer',
                'description' => 'Customer related payments and receipts',
                'status' => true
            ],
            [
                'name' => 'Office',
                'description' => 'Office expenses and income',
                'status' => true
            ],
            [
                'name' => 'General',
                'description' => 'General account transfers',
                'status' => true
            ],
            [
                'name' => 'Liability',
                'description' => 'Liability related payments and receipts',
                'status' => true
            ]
        ];

        foreach ($categories as $category) {
            VoucherCategory::create($category);
        }
    }
}
