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
            $usedByVouchers = Schema::hasTable('vouchers')
                && DB::table('vouchers')
                    ->whereNotNull('voucher_category_id')
                    ->exists();
            $usedByPaymentSubTypes = Schema::hasTable('payment_sub_types')
                && DB::table('payment_sub_types')
                    ->whereNotNull('voucher_category_id')
                    ->exists();

            if ($usedByVouchers || $usedByPaymentSubTypes) {
                throw new RuntimeException(
                    'Voucher categories cannot be reset because existing vouchers or payment sub-types reference them.'
                );
            }

            DB::table('voucher_categories')->delete();

            if (Schema::hasTable('document_sequences')) {
                DB::table('document_sequences')
                    ->where('document_type', 'voucher_category')
                    ->delete();
            }

            foreach (VoucherCategoryHelper::systemCategories() as $category) {
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
