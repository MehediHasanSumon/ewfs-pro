<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Customer;
use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerSettlementService
{
    public function __construct(
        private readonly VoucherPostingService $vouchers,
        private readonly PartyLedgerService $ledger
    ) {}

    public function createMany(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $created = [];

            foreach (array_values($data['vouchers'] ?? []) as $index => $line) {
                $allocatedLines = $this->allocateLine(
                    $data,
                    $line,
                    'vouchers.'.$index.'.'
                );

                $created = [
                    ...$created,
                    ...$this->vouchers->createMany(
                        VoucherTransactionTypeHelper::receiptVoucherType(),
                        [
                            'date' => $data['date'],
                            'shift_id' => $data['shift_id'] ?? null,
                            'vouchers' => $allocatedLines,
                        ]
                    ),
                ];
            }

            return $created;
        });
    }

    public function replace(Voucher $voucher, array $data): array
    {
        return DB::transaction(function () use ($voucher, $data): array {
            $this->vouchers->reverse(
                $voucher,
                'Received voucher replaced from the edit workflow.'
            );

            return $this->createMany([
                'date' => $data['date'] ?? $voucher->voucher_date?->format('Y-m-d'),
                'shift_id' => $data['shift_id'] ?? $voucher->shift_id,
                'vouchers' => $data['vouchers'] ?? [$data],
            ]);
        });
    }

    private function allocateLine(
        array $header,
        array $line,
        string $errorPrefix
    ): array {
        $transactionType = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,name')
            ->findOrFail((int) $line['voucher_transaction_type_id']);

        if (
            ! $this->isCustomerCategory($transactionType)
            || ! in_array($transactionType->code, [
                VoucherTransactionTypeHelper::customerDuePaidCode(),
                VoucherTransactionTypeHelper::customerAdvancePaymentCode(),
            ], true)
        ) {
            return [$line];
        }

        $customer = Customer::query()
            ->where('account_id', (int) $line['from_account_id'])
            ->lockForUpdate()
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                $errorPrefix.'from_account_id' => 'Customer settlement must use a customer account as the payer.',
            ]);
        }

        $amount = round((float) $line['amount'], 4);
        $due = round(
            $this->ledger->customerCurrentDue(
                $customer->id,
                $header['date'] ?? null
            ),
            4
        );
        $dueAmount = min($amount, max(0.0, $due));
        $advanceAmount = round($amount - $dueAmount, 4);

        $categoryId = (int) $transactionType->voucher_category_id;
        $dueType = $this->transactionType(
            $categoryId,
            VoucherTransactionTypeHelper::customerDuePaidCode(),
            $errorPrefix
        );
        $advanceType = $this->transactionType(
            $categoryId,
            VoucherTransactionTypeHelper::customerAdvancePaymentCode(),
            $errorPrefix
        );

        $lines = [];

        if ($dueAmount > 0) {
            $lines[] = [
                ...$line,
                'voucher_transaction_type_id' => $dueType->id,
                'amount' => $dueAmount,
            ];
        }

        if ($advanceAmount > 0) {
            $lines[] = [
                ...$line,
                'voucher_transaction_type_id' => $advanceType->id,
                'amount' => $advanceAmount,
            ];
        }

        return $lines;
    }

    private function transactionType(
        int $categoryId,
        string $code,
        string $errorPrefix
    ): VoucherTransactionType {
        $type = VoucherTransactionType::query()
            ->where('voucher_category_id', $categoryId)
            ->where('code', $code)
            ->where('voucher_type', VoucherTransactionTypeHelper::receiptVoucherType())
            ->where('status', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                $errorPrefix.'voucher_transaction_type_id' => 'The configured customer settlement transaction type is unavailable.',
            ]);
        }

        return $type;
    }

    private function isCustomerCategory(
        VoucherTransactionType $transactionType
    ): bool {
        $category = $transactionType->voucherCategory;

        return $category?->code === VoucherCategoryHelper::customerCode()
            || (
                $category?->code === null
                && $category?->name
                    === VoucherCategoryHelper::getCategoryDefaultName('customer')
            );
    }
}
