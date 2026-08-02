<?php

namespace Database\Seeders;

use App\Helpers\VoucherCategoryHelper;
use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SystemVoucherCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $existingCategories = DB::table('voucher_categories')
                ->lockForUpdate()
                ->get(['id', 'code', 'name']);
            $targetCodesByCategoryId = $existingCategories
                ->mapWithKeys(fn ($category): array => [
                    (int) $category->id => VoucherCategoryHelper::resolveSystemCode(
                        $category->code,
                        $category->name
                    ),
                ]);

            $usedCategoryIds = collect();

            if (Schema::hasTable('vouchers')) {
                $usedCategoryIds = $usedCategoryIds->merge(
                    DB::table('vouchers')
                        ->whereNotNull('voucher_category_id')
                        ->pluck('voucher_category_id')
                );
            }

            if (Schema::hasTable('payment_sub_types')) {
                $usedCategoryIds = $usedCategoryIds->merge(
                    DB::table('payment_sub_types')
                        ->whereNotNull('voucher_category_id')
                        ->pluck('voucher_category_id')
                );
            }

            $unmappedUsedCategories = $existingCategories
                ->whereIn('id', $usedCategoryIds->map(fn ($id): int => (int) $id))
                ->filter(fn ($category): bool => ! $targetCodesByCategoryId->get((int) $category->id));

            if ($unmappedUsedCategories->isNotEmpty()) {
                throw new RuntimeException(
                    'Used voucher categories cannot be reset because they have no system mapping: '
                    .$unmappedUsedCategories->pluck('name')->implode(', ')
                );
            }

            $this->assertPaymentSubTypesCanBeMerged($targetCodesByCategoryId);

            foreach ($existingCategories as $category) {
                DB::table('voucher_categories')
                    ->where('id', $category->id)
                    ->update([
                        'code' => null,
                        'name' => '__legacy_voucher_category_'.$category->id,
                    ]);
            }

            $newCategoryIdsByCode = collect();

            if (Schema::hasTable('document_sequences')) {
                DB::table('document_sequences')
                    ->where('document_type', 'voucher_category')
                    ->delete();
            }

            foreach (VoucherCategoryHelper::systemCategories() as $category) {
                $newCategory = VoucherCategory::query()->create([
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => true,
                    'sort_order' => $category['sort_order'],
                    'is_system' => true,
                ]);

                $newCategoryIdsByCode->put(
                    $category['code'],
                    $newCategory->getKey()
                );
            }

            foreach ($targetCodesByCategoryId as $oldCategoryId => $targetCode) {
                if (! $targetCode) {
                    continue;
                }

                $newCategoryId = $newCategoryIdsByCode->get($targetCode);

                if (Schema::hasTable('vouchers')) {
                    DB::table('vouchers')
                        ->where('voucher_category_id', $oldCategoryId)
                        ->update(['voucher_category_id' => $newCategoryId]);
                }

                if (Schema::hasTable('payment_sub_types')) {
                    DB::table('payment_sub_types')
                        ->where('voucher_category_id', $oldCategoryId)
                        ->update(['voucher_category_id' => $newCategoryId]);
                }
            }

            DB::table('voucher_categories')
                ->whereIn('id', $existingCategories->pluck('id'))
                ->delete();
        });
    }

    private function assertPaymentSubTypesCanBeMerged(
        $targetCodesByCategoryId
    ): void {
        if (! Schema::hasTable('payment_sub_types')) {
            return;
        }

        $seen = [];

        foreach (
            DB::table('payment_sub_types')
                ->get(['id', 'name', 'voucher_category_id']) as $subType
        ) {
            $targetCode = $targetCodesByCategoryId->get(
                (int) $subType->voucher_category_id
            );

            if (! $targetCode) {
                continue;
            }

            $key = $targetCode.'|'.mb_strtolower(trim($subType->name));

            if (isset($seen[$key])) {
                throw new RuntimeException(
                    "Payment sub-type [{$subType->name}] would be duplicated while merging voucher categories."
                );
            }

            $seen[$key] = $subType->id;
        }
    }
}
