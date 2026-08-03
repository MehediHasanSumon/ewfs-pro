<?php

namespace App\Services;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherTransactionTypeService
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {}

    public function create(array $data): VoucherTransactionType
    {
        return DB::transaction(function () use ($data): VoucherTransactionType {
            $highestCode = VoucherTransactionType::query()
                ->lockForUpdate()
                ->pluck('code')
                ->filter(fn (?string $code): bool => ctype_digit((string) $code))
                ->map(fn (string $code): int => (int) $code)
                ->max() ?? 0;

            $code = $this->numbers->nextGlobal(
                'voucher_transaction_type',
                '',
                VoucherTransactionTypeHelper::codePadding(),
                $highestCode + 1
            );

            return VoucherTransactionType::query()->create([
                ...Arr::only($data, [
                    'voucher_category_id',
                    'name',
                    'voucher_type',
                    'description',
                    'sort_order',
                    'status',
                ]),
                'code' => $code,
                'is_system' => false,
            ]);
        }, 3);
    }

    public function update(
        VoucherTransactionType $transactionType,
        array $data
    ): VoucherTransactionType {
        $transactionType->update(
            Arr::only($data, ['name', 'description', 'sort_order', 'status'])
        );

        return $transactionType->fresh('voucherCategory');
    }

    public function options(
        int $voucherCategoryId,
        string $voucherType,
        ?int $selectedId = null
    ): Collection {
        return VoucherTransactionType::query()
            ->with('voucherCategory:id,code,name')
            ->forCategory($voucherCategoryId)
            ->forVoucherType($voucherType)
            ->where(function ($query) use ($selectedId): void {
                $query->where('status', true);

                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function delete(VoucherTransactionType $transactionType): void
    {
        $this->deleteMany([$transactionType->getKey()]);
    }

    public function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return DB::transaction(function () use ($ids): int {
            $types = VoucherTransactionType::query()
                ->with('voucherCategory:id,code')
                ->whereKey($ids)
                ->lockForUpdate()
                ->get();

            if ($types->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => 'One or more voucher transaction types are no longer available.',
                ]);
            }

            foreach ($types as $type) {
                if ($type->isSystemType()) {
                    throw ValidationException::withMessages([
                        'ids' => 'System voucher transaction types cannot be deleted.',
                    ]);
                }

                if ($type->vouchers()->exists()) {
                    throw ValidationException::withMessages([
                        'ids' => 'The selected voucher transaction type is already used and cannot be deleted.',
                    ]);
                }
            }

            foreach ($types as $type) {
                $type->delete();
            }

            return $types->count();
        }, 3);
    }
}
