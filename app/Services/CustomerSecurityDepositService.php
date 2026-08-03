<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Customer;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerSecurityDepositService
{
    public const REFUND_EVENT_TYPE = 'customer_security_deposit_refund';

    public function isRefundSubType(VoucherTransactionType $transactionType): bool
    {
        $transactionType->loadMissing('voucherCategory:id,code,name');

        return $transactionType->code
                === VoucherTransactionTypeHelper::customerSecurityDepositRefundCode()
            && $transactionType->voucher_type
                === VoucherTransactionTypeHelper::paymentVoucherType()
            && (
                $transactionType->voucherCategory?->code === VoucherCategoryHelper::customerCode()
                || (
                    $transactionType->voucherCategory?->code === null
                    && $transactionType->voucherCategory?->name
                        === VoucherCategoryHelper::getCategoryDefaultName('customer')
                )
            );
    }

    public function balancesByAccountIds(
        Collection $accountIds,
        ?string $asOfDate = null
    ): Collection {
        $ids = $accountIds
            ->filter()
            ->map(fn ($accountId) => (int) $accountId)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $ids)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('je.business_date', '<=', $asOfDate))
            ->groupBy('jl.account_id')
            ->selectRaw(
                "jl.account_id,
                 SUM(
                    CASE
                        WHEN je.event_type LIKE 'customer_security_deposit%'
                            OR (
                                je.event_type LIKE 'customer_opening_balance%'
                                AND (
                                    LOWER(COALESCE(je.description, ''))
                                        LIKE '%customer_deposit%'
                                    OR LOWER(COALESCE(je.description, ''))
                                        LIKE '%customer deposit%'
                                )
                            )
                            THEN jl.credit_amount - jl.debit_amount
                        ELSE 0
                    END
                 ) AS available_deposit"
            )
            ->pluck('available_deposit', 'account_id')
            ->map(fn ($balance) => max(0.0, (float) $balance));
    }

    public function availableForAccount(
        int $accountId,
        ?string $asOfDate = null
    ): float {
        return (float) $this->balancesByAccountIds(
            collect([$accountId]),
            $asOfDate
        )->get($accountId, 0);
    }

    public function assertRefundAllowed(
        Account $destinationAccount,
        float $amount,
        string $errorKey = 'amount'
    ): Customer {
        $customer = Customer::query()
            ->where('account_id', $destinationAccount->id)
            ->lockForUpdate()
            ->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                str_replace('amount', 'to_account_id', $errorKey) => 'Security Deposit Refund must be posted to a customer account.',
            ]);
        }

        $available = $this->availableForAccount($destinationAccount->id);

        if (round($amount, 4) > round($available, 4)) {
            throw ValidationException::withMessages([
                $errorKey => 'Refund amount cannot exceed the customer\'s available Security Deposit.',
            ]);
        }

        return $customer;
    }
}
