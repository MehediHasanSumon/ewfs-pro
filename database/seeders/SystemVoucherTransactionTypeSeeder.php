<?php

namespace Database\Seeders;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\VoucherCategory;
use App\Models\VoucherTransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SystemVoucherTransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $definitions = collect(
                VoucherTransactionTypeHelper::flattenedSystemTypes()
            );
            $categories = VoucherCategory::query()
                ->whereIn(
                    'code',
                    $definitions->pluck('category_code')->unique()
                )
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            foreach ($definitions as $definition) {
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

            $this->removeRetiredSystemTypes($definitions, $categories);
        }, 3);
    }

    private function removeRetiredSystemTypes(
        Collection $definitions,
        Collection $categories
    ): void {
        $configuredIdentities = $definitions
            ->map(function (array $definition) use ($categories): ?string {
                $categoryId = $categories->get(
                    $definition['category_code']
                )?->id;

                return $categoryId
                    ? "{$categoryId}:{$definition['code']}"
                    : null;
            })
            ->filter()
            ->flip();

        $retiredTypes = VoucherTransactionType::query()
            ->select('voucher_transaction_types.*')
            ->where('is_system', true)
            ->when(
                Schema::hasTable('vouchers'),
                fn ($query) => $query->withCount('vouchers'),
                fn ($query) => $query->selectRaw('0 as vouchers_count')
            )
            ->lockForUpdate()
            ->get()
            ->reject(
                fn (VoucherTransactionType $type): bool => $configuredIdentities
                    ->has("{$type->voucher_category_id}:{$type->code}")
            );

        foreach ($retiredTypes as $retiredType) {
            if ($retiredType->vouchers_count > 0) {
                DB::table('voucher_transaction_types')
                    ->where('id', $retiredType->id)
                    ->update([
                        'status' => false,
                        'is_system' => false,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('voucher_transaction_types')
                ->where('id', $retiredType->id)
                ->delete();
        }
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
