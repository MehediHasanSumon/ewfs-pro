<?php

namespace Database\Seeders;

use App\Helpers\VoucherCategoryHelper;
use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemVoucherCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (VoucherCategoryHelper::systemCategories() as $category) {
                $existing = VoucherCategory::query()
                    ->where('code', $category['code'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    VoucherCategory::query()
                        ->whereKey($existing)
                        ->update(['is_system' => true]);

                    continue;
                }

                $legacyCategory = VoucherCategory::query()
                    ->whereNull('code')
                    ->where('name', $category['name'])
                    ->lockForUpdate()
                    ->first();

                if ($legacyCategory) {
                    VoucherCategory::query()
                        ->whereKey($legacyCategory)
                        ->update([
                            'code' => $category['code'],
                            'description' => $legacyCategory->description ?? $category['description'],
                            'sort_order' => $legacyCategory->sort_order ?: $category['sort_order'],
                            'is_system' => true,
                        ]);

                    continue;
                }

                VoucherCategory::query()->create([
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => true,
                    'sort_order' => $category['sort_order'],
                    'is_system' => true,
                ]);
            }
        });
    }
}
