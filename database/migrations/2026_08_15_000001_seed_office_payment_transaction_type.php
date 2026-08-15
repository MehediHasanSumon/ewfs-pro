<?php

use App\Helpers\VoucherCategoryHelper;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('voucher_transaction_types')) {
            return;
        }

        Schema::table('voucher_transaction_types', function (Blueprint $table) {
            $table->string('voucher_type', 20)->change();
        });

        $operatingCode = VoucherCategoryHelper::operatingCode();
        $category = VoucherCategory::query()
            ->where('code', $operatingCode)
            ->first();

        if (! $category) {
            return;
        }

        $exists = VoucherTransactionType::query()
            ->where('voucher_category_id', $category->id)
            ->where('voucher_type', 'office_payment')
            ->exists();

        if ($exists) {
            return;
        }

        $maxCode = (int) VoucherTransactionType::query()
            ->where('voucher_category_id', $category->id)
            ->max('code');

        VoucherTransactionType::unguard();

        VoucherTransactionType::query()->create([
            'voucher_category_id' => $category->id,
            'code' => (string) max($maxCode + 1, 2001),
            'name' => 'Office Payment',
            'voucher_type' => 'office_payment',
            'description' => 'System transaction type for office expense payments.',
            'sort_order' => 100,
            'status' => true,
            'is_system' => true,
        ]);

        VoucherTransactionType::reguard();
    }

    public function down(): void
    {
        if (! Schema::hasTable('voucher_transaction_types')) {
            return;
        }

        $operatingCode = VoucherCategoryHelper::operatingCode();
        $category = VoucherCategory::query()
            ->where('code', $operatingCode)
            ->first();

        if (! $category) {
            return;
        }

        VoucherTransactionType::query()
            ->where('voucher_category_id', $category->id)
            ->where('voucher_type', 'office_payment')
            ->where('is_system', true)
            ->where('name', 'Office Payment')
            ->delete();
    }
};
