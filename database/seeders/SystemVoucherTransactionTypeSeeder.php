<?php

namespace Database\Seeders;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SystemVoucherTransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $categories = VoucherCategory::query()
                ->whereIn(
                    'code',
                    collect(VoucherTransactionTypeHelper::flattenedSystemTypes())
                        ->pluck('category_code')
                        ->unique()
                )
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            foreach (VoucherTransactionTypeHelper::flattenedSystemTypes() as $definition) {
                $category = $categories->get($definition['category_code']);

                if (! $category) {
                    throw new RuntimeException(
                        "Voucher category [{$definition['category_code']}] must be configured before transaction types."
                    );
                }

                $transactionType = VoucherTransactionType::query()
                    ->where('voucher_category_id', $category->id)
                    ->where('code', $definition['code'])
                    ->lockForUpdate()
                    ->first();

                if (! $transactionType) {
                    $this->releaseDefaultName(
                        $category->id,
                        $definition['name'],
                        $definition['code']
                    );

                    VoucherTransactionType::query()->create([
                        'voucher_category_id' => $category->id,
                        'code' => $definition['code'],
                        'name' => $definition['name'],
                        'voucher_type' => $definition['voucher_type'],
                        'description' => $definition['description'],
                        'sort_order' => $definition['sort_order'],
                        'status' => true,
                        'is_system' => true,
                    ]);

                    continue;
                }

                $updates = [
                    'voucher_type' => $definition['voucher_type'],
                    'is_system' => true,
                ];

                if (! $transactionType->is_system) {
                    $this->releaseDefaultName(
                        $category->id,
                        $definition['name'],
                        $definition['code']
                    );
                    $updates['name'] = $definition['name'];
                    $updates['description'] = $definition['description'];
                    $updates['sort_order'] = $definition['sort_order'];
                    $updates['status'] = true;
                }

                DB::table('voucher_transaction_types')
                    ->where('id', $transactionType->id)
                    ->update([...$updates, 'updated_at' => now()]);
            }
        }, 3);
    }

    private function releaseDefaultName(
        int $categoryId,
        string $defaultName,
        string $targetCode
    ): void {
        $conflict = VoucherTransactionType::query()
            ->where('voucher_category_id', $categoryId)
            ->where('name', $defaultName)
            ->where('code', '!=', $targetCode)
            ->lockForUpdate()
            ->first();

        if (! $conflict) {
            return;
        }

        DB::table('voucher_transaction_types')
            ->where('id', $conflict->id)
            ->update([
                'name' => "{$conflict->name} (Legacy {$conflict->code})",
                'updated_at' => now(),
            ]);
    }
}
