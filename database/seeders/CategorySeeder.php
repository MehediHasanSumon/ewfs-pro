<?php

namespace Database\Seeders;

use App\Helpers\ErpHelper;
use App\Models\Category;
use Illuminate\Database\Seeder;
use RuntimeException;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (ErpHelper::getCategoryDefaults() as $code => $defaultName) {
            if (Category::query()->where('code', $code)->exists()) {
                continue;
            }

            if (Category::query()->where('name', $defaultName)->exists()) {
                throw new RuntimeException(
                    "Cannot seed ERP category {$code}: the name [{$defaultName}] "
                    .'is already assigned to another category.'
                );
            }

            Category::query()->create([
                'code' => $code,
                'name' => $defaultName,
                'status' => true,
            ]);
        }
    }
}
