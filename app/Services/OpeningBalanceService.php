<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\PartyOpeningBalance;
use App\Models\Supplier;
use Illuminate\Support\Str;

class OpeningBalanceService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers
    ) {
    }

    public function customerPreviousDue(Customer $customer, float $amount, string $date): ?PartyOpeningBalance
    {
        if ($amount <= 0) {
            return null;
        }

        $journal = $this->postCustomerBalance(
            $customer,
            'receivable',
            $amount,
            $date,
            true,
            'customer-opening-receivable:'.$customer->id
        );

        return $this->createProjection(
            $customer->id,
            null,
            'receivable',
            $amount,
            $date,
            $journal
        );
    }

    public function customerDeposit(Customer $customer, float $amount, string $date): ?PartyOpeningBalance
    {
        if ($amount <= 0) {
            return null;
        }

        $journal = $this->postCustomerBalance(
            $customer,
            'customer_deposit',
            $amount,
            $date,
            false,
            'customer-opening-customer_deposit:'.$customer->id
        );

        return $this->createProjection(
            $customer->id,
            null,
            'customer_deposit',
            $amount,
            $date,
            $journal
        );
    }

    public function setCustomerPreviousDue(
        Customer $customer,
        float $amount,
        string $date
    ): ?PartyOpeningBalance {
        return $this->syncCustomerBalance($customer, 'receivable', $amount, $date, true);
    }

    public function setCustomerDeposit(
        Customer $customer,
        float $amount,
        string $date
    ): ?PartyOpeningBalance {
        return $this->syncCustomerBalance(
            $customer,
            'customer_deposit',
            $amount,
            $date,
            false
        );
    }

    public function supplierPreviousDue(Supplier $supplier, float $amount, string $date): ?PartyOpeningBalance
    {
        if ($amount <= 0) {
            return null;
        }

        $equity = $this->systemAccounts->openingBalanceEquity();
        $journal = $this->accounting->post([
            'business_date' => $date,
            'event_type' => 'supplier_opening_balance',
            'source_type' => Supplier::class,
            'source_id' => $supplier->id,
            'reference_no' => $supplier->code,
            'description' => 'Supplier opening payable',
            'idempotency_key' => 'supplier-opening-payable:'.$supplier->id,
        ], [
            [
                'account_id' => $equity->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'supplier_id' => $supplier->id,
            ],
            [
                'account_id' => $supplier->account_id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'supplier_id' => $supplier->id,
            ],
        ]);

        return $this->createProjection(null, $supplier->id, 'payable', $amount, $date, $journal);
    }

    private function postCustomerBalance(
        Customer $customer,
        string $type,
        float $amount,
        string $date,
        bool $customerIsDebit,
        string $idempotencyKey
    ): JournalEntry {
        $equity = $this->systemAccounts->openingBalanceEquity();
        $isSecurityDeposit = $type === 'customer_deposit';
        $description = $isSecurityDeposit
            ? 'Opening Security Deposit'
            : 'Customer opening '.$type;
        $referenceNo = $isSecurityDeposit
            ? $this->numbers->next('customer_deposit', 'DEP', $date, 5)
            : $customer->code;

        return $this->accounting->post([
            'business_date' => $date,
            'event_type' => $isSecurityDeposit
                ? 'customer_security_deposit'
                : 'customer_opening_balance',
            'source_type' => Customer::class,
            'source_id' => $customer->id,
            'reference_no' => $referenceNo,
            'description' => $description,
            'idempotency_key' => $idempotencyKey,
        ], [
            [
                'account_id' => $customerIsDebit ? $customer->account_id : $equity->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'customer_id' => $customer->id,
                'description' => $description,
            ],
            [
                'account_id' => $customerIsDebit ? $equity->id : $customer->account_id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'customer_id' => $customer->id,
                'description' => $description,
            ],
        ]);
    }

    private function syncCustomerBalance(
        Customer $customer,
        string $type,
        float $amount,
        string $date,
        bool $customerIsDebit
    ): ?PartyOpeningBalance {
        $projection = PartyOpeningBalance::query()
            ->where('customer_id', $customer->id)
            ->where('balance_type', $type)
            ->with('journalEntry')
            ->first();

        if (! $projection) {
            return $amount > 0
                ? ($type === 'receivable'
                    ? $this->customerPreviousDue($customer, $amount, $date)
                    : $this->customerDeposit($customer, $amount, $date))
                : null;
        }

        if (
            $projection->status === 'posted'
            && round((float) $projection->amount, 4) === round($amount, 4)
        ) {
            return $projection;
        }

        if ($projection->journalEntry?->status === 'posted') {
            $this->accounting->reverse(
                $projection->journalEntry,
                'Customer opening '.str_replace('_', ' ', $type).' adjusted.'
            );
        }

        if ($amount <= 0) {
            $projection->update(['status' => 'reversed']);

            return $projection;
        }

        $journal = $this->postCustomerBalance(
            $customer,
            $type,
            $amount,
            $date,
            $customerIsDebit,
            'customer-opening-'.$type.':'.$customer->id.':replacement:'.Str::uuid()
        );

        $projection->update([
            'effective_date' => $date,
            'amount' => $amount,
            'journal_entry_id' => $journal->id,
            'status' => 'posted',
        ]);

        return $projection->fresh('journalEntry');
    }

    private function createProjection(
        ?int $customerId,
        ?int $supplierId,
        string $type,
        float $amount,
        string $date,
        JournalEntry $journal
    ): PartyOpeningBalance {
        return PartyOpeningBalance::query()->create([
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'balance_type' => $type,
            'effective_date' => $date,
            'amount' => $amount,
            'journal_entry_id' => $journal->id,
            'status' => 'posted',
        ]);
    }
}
