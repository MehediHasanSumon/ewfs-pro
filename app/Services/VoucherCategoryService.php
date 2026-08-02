<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Models\VoucherCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherCategoryService
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {}

    public function create(array $data): VoucherCategory
    {
        return DB::transaction(function () use ($data): VoucherCategory {
            $highestExistingSequence = VoucherCategory::query()
                ->where('code', 'like', VoucherCategoryHelper::prefix().'%')
                ->lockForUpdate()
                ->pluck('code')
                ->map(fn (?string $code): int => VoucherCategoryHelper::sequenceNumber($code))
                ->max() ?? 0;

            $code = $this->numbers->nextGlobal(
                'voucher_category',
                VoucherCategoryHelper::prefix(),
                VoucherCategoryHelper::codePadding(),
                max(
                    VoucherCategoryHelper::minimumCustomSequence(),
                    $highestExistingSequence + 1
                )
            );

            return VoucherCategory::query()->create([
                ...Arr::only($data, ['name', 'description', 'status', 'sort_order']),
                'code' => $code,
                'is_system' => false,
            ]);
        }, 3);
    }

    public function update(VoucherCategory $voucherCategory, array $data): VoucherCategory
    {
        $voucherCategory->update(
            Arr::only($data, ['name', 'description', 'status', 'sort_order'])
        );

        return $voucherCategory->fresh();
    }

    public function delete(VoucherCategory $voucherCategory): void
    {
        $this->deleteMany([$voucherCategory->getKey()]);
    }

    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($ids): int {
            $categories = VoucherCategory::query()
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($categories->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'One or more voucher categories are no longer available.',
                ]);
            }

            foreach ($categories as $category) {
                if ($category->isSystemCategory()) {
                    throw ValidationException::withMessages([
                        'ids' => 'System voucher categories cannot be deleted.',
                    ]);
                }

                if (
                    $category->vouchers()->exists()
                    || $category->paymentSubTypes()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'ids' => 'The selected voucher category is already used and cannot be deleted.',
                    ]);
                }
            }

            foreach ($categories as $category) {
                $category->delete();
            }

            return $categories->count();
        }, 3);
    }
}
