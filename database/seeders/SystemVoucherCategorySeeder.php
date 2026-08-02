<?php

namespace Database\Seeders;

use App\Helpers\VoucherCategoryHelper;
use App\Models\VoucherCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemVoucherCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $existingCategories = DB::table('voucher_categories')
                ->lockForUpdate()
                ->get([
                    'id',
                    'code',
                    'name',
                    'description',
                    'status',
                    'sort_order',
                ]);
            $usedCategoryIds = $this->usedCategoryIds();
            $sourceIdsBySystemCode = $this->systemSourceIds(
                $existingCategories
            );

            foreach ($existingCategories as $category) {
                DB::table('voucher_categories')
                    ->where('id', $category->id)
                    ->update([
                        'code' => null,
                        'name' => '__legacy_voucher_category_'.$category->id,
                        'is_system' => false,
                    ]);
            }

            $systemSourceIds = $sourceIdsBySystemCode
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values();

            foreach (VoucherCategoryHelper::systemCategories() as $category) {
                $sourceId = $sourceIdsBySystemCode->get($category['code']);
                $values = [
                    'code' => $category['code'],
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => true,
                    'sort_order' => $category['sort_order'],
                    'is_system' => true,
                    'updated_at' => now(),
                ];

                if ($sourceId) {
                    DB::table('voucher_categories')
                        ->where('id', $sourceId)
                        ->update($values);

                    continue;
                }

                VoucherCategory::query()->create($values);
            }

            $remainingCategories = $existingCategories
                ->reject(
                    fn ($category): bool => $systemSourceIds->contains(
                        (int) $category->id
                    )
                )
                ->sortBy('id')
                ->values();
            $usedCodes = collect(
                VoucherCategoryHelper::getSystemCategoryCodes()
            )->flip();
            $nextSequence = VoucherCategoryHelper::minimumCustomSequence();

            foreach ($remainingCategories as $category) {
                $categoryId = (int) $category->id;

                if (! $usedCategoryIds->contains($categoryId)) {
                    DB::table('voucher_categories')
                        ->where('id', $categoryId)
                        ->delete();

                    continue;
                }

                $code = $this->availableCustomCode(
                    $category->code,
                    $usedCodes,
                    $nextSequence
                );
                $usedCodes->put($code, true);
                $nextSequence = max(
                    $nextSequence,
                    VoucherCategoryHelper::sequenceNumber($code) + 1
                );

                DB::table('voucher_categories')
                    ->where('id', $categoryId)
                    ->update([
                        'code' => $code,
                        'name' => $category->name,
                        'description' => $category->description,
                        'status' => (bool) $category->status,
                        'sort_order' => max(
                            (int) $category->sort_order,
                            $nextSequence - 1
                        ),
                        'is_system' => false,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('document_sequences')) {
                DB::table('document_sequences')
                    ->where('document_type', 'voucher_category')
                    ->delete();
            }
        });
    }

    private function usedCategoryIds(): Collection
    {
        $ids = collect();

        if (Schema::hasTable('vouchers')) {
            $ids = $ids->merge(
                DB::table('vouchers')
                    ->whereNotNull('voucher_category_id')
                    ->pluck('voucher_category_id')
            );
        }

        if (Schema::hasTable('voucher_transaction_types')) {
            $ids = $ids->merge(
                DB::table('voucher_transaction_types')
                    ->whereNotNull('voucher_category_id')
                    ->pluck('voucher_category_id')
            );
        } elseif (Schema::hasTable('payment_sub_types')) {
            $ids = $ids->merge(
                DB::table('payment_sub_types')
                    ->whereNotNull('voucher_category_id')
                    ->pluck('voucher_category_id')
            );
        }

        return $ids
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function systemSourceIds(Collection $categories): Collection
    {
        return collect(VoucherCategoryHelper::systemCategories())
            ->mapWithKeys(function (array $systemCategory) use ($categories): array {
                $source = $categories->first(
                    fn ($category): bool => $category->code === $systemCategory['code']
                );

                $source ??= $categories->first(
                    fn ($category): bool => mb_strtolower(trim($category->name))
                        === mb_strtolower($systemCategory['name'])
                );

                $source ??= $categories->first(
                    fn ($category): bool => VoucherCategoryHelper::resolveSystemCode(
                        $category->code,
                        $category->name
                    ) === $systemCategory['code']
                );

                return [$systemCategory['code'] => $source?->id];
            });
    }

    private function availableCustomCode(
        ?string $preferredCode,
        Collection $usedCodes,
        int $nextSequence
    ): string {
        if (
            $preferredCode
            && ! VoucherCategoryHelper::isSystemCode($preferredCode)
            && VoucherCategoryHelper::sequenceNumber($preferredCode) > 0
            && ! $usedCodes->has($preferredCode)
        ) {
            return $preferredCode;
        }

        do {
            $code = VoucherCategoryHelper::prefix().str_pad(
                (string) $nextSequence,
                VoucherCategoryHelper::codePadding(),
                '0',
                STR_PAD_LEFT
            );
            $nextSequence++;
        } while ($usedCodes->has($code));

        return $code;
    }
}
