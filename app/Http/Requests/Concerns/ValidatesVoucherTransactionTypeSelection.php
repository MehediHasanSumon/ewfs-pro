<?php

namespace App\Http\Requests\Concerns;

use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

trait ValidatesVoucherTransactionTypeSelection
{
    protected function validateVoucherTransactionTypeSelections(
        Validator $validator,
        string $voucherType
    ): void {
        $lines = $this->isMethod('post')
            ? collect($this->input('vouchers', []))->values()
            : collect([$this->all()]);
        $transactionTypes = $this->transactionTypesFor($lines);
        $existingVoucher = $this->route('voucher');

        foreach ($lines as $index => $line) {
            $line = is_array($line) ? $line : [];
            $transactionTypeId = (int) (
                $line['voucher_transaction_type_id'] ?? 0
            );
            $voucherCategoryId = (int) (
                $line['voucher_category_id'] ?? 0
            );

            if (! $transactionTypeId || ! $voucherCategoryId) {
                continue;
            }

            $transactionType = $transactionTypes->get($transactionTypeId);
            $errorKey = $this->isMethod('post')
                ? "vouchers.{$index}.voucher_transaction_type_id"
                : 'voucher_transaction_type_id';
            $isExistingSelection = $existingVoucher instanceof Voucher
                && (int) $existingVoucher->voucher_transaction_type_id
                    === $transactionTypeId;

            if (! $transactionType) {
                $validator->errors()->add(
                    $errorKey,
                    'The selected transaction type is not valid for this voucher.'
                );

                continue;
            }

            if ((int) $transactionType->voucher_category_id !== $voucherCategoryId) {
                $validator->errors()->add(
                    $errorKey,
                    'The selected transaction type does not belong to the selected voucher category.'
                );

                continue;
            }

            if (
                $transactionType->voucher_type !== $voucherType
                || (! $transactionType->status && ! $isExistingSelection)
            ) {
                $validator->errors()->add(
                    $errorKey,
                    'The selected transaction type is not valid for this voucher.'
                );
            }
        }
    }

    private function transactionTypesFor(Collection $lines): Collection
    {
        $ids = $lines
            ->pluck('voucher_transaction_type_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return VoucherTransactionType::query()
            ->whereKey($ids)
            ->get([
                'id',
                'voucher_category_id',
                'voucher_type',
                'status',
            ])
            ->keyBy('id');
    }
}
