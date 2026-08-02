<?php

namespace App\Services;

use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherPostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers,
        private readonly CustomerSecurityDepositService $securityDeposits
    ) {}

    public function createMany(string $type, array $data): array
    {
        return DB::transaction(function () use ($type, $data) {
            $created = [];

            foreach (array_values($data['vouchers']) as $index => $voucher) {
                $created[] = $this->createOne(
                    $type,
                    $data,
                    $voucher,
                    'vouchers.'.$index.'.'
                );
            }

            return $created;
        });
    }

    public function createOfficePayment(array $data): Voucher
    {
        return DB::transaction(function () use ($data) {
            $fundingAccount = Account::query()->findOrFail($data['to_account_id']);
            $expenseAccount = $this->systemAccounts->officeExpense();

            return $this->createDocument('office_payment', $data, [
                'voucher_category_id' => $data['voucher_category_id'] ?? null,
                'voucher_transaction_type_id' => $this->transactionTypeId($data),
                'from_account_id' => $fundingAccount->id,
                'to_account_id' => $expenseAccount->id,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_type'] ?? 'Cash',
                'description' => 'Office payment',
                'remarks' => $data['remarks'] ?? null,
            ]);
        });
    }

    public function replace(Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(function () use ($voucher, $data) {
            $type = $voucher->voucher_type;
            $this->reverse($voucher, ucfirst(str_replace('_', ' ', $type)).' replaced from the edit workflow.');

            if ($type === 'office_payment') {
                return $this->createOfficePayment($data);
            }

            return $this->createDocument($type, $data, $data);
        });
    }

    public function reverse(Voucher $voucher, string $reason = 'Voucher deleted from the workflow.'): void
    {
        $voucher->loadMissing('journalEntry');

        if ($voucher->journalEntry?->status === 'posted') {
            $this->accounting->reverse($voucher->journalEntry, $reason);
        }
    }

    private function createOne(
        string $type,
        array $headerData,
        array $lineData,
        string $errorPrefix
    ): Voucher {
        return $this->createDocument(
            $type,
            $headerData,
            $lineData,
            $errorPrefix
        );
    }

    private function createDocument(
        string $type,
        array $headerData,
        array $lineData,
        string $errorPrefix = ''
    ): Voucher {
        $businessDate = $headerData['date'] ?? $headerData['voucher_date'];
        $accounts = Account::query()
            ->with(['customer', 'supplier', 'employee'])
            ->whereIn('id', [
                $lineData['from_account_id'],
                $lineData['to_account_id'],
            ])
            ->get()
            ->keyBy('id');
        $fromAccount = $accounts->get((int) $lineData['from_account_id']);
        $toAccount = $accounts->get((int) $lineData['to_account_id']);

        if (! $fromAccount || ! $toAccount) {
            throw ValidationException::withMessages([
                $errorPrefix.'to_account_id' => 'The selected account is unavailable.',
            ]);
        }

        $transactionType = VoucherTransactionType::query()
            ->with('voucherCategory:id,code,name')
            ->findOrFail($this->transactionTypeId($lineData));

        if (
            (int) $transactionType->voucher_category_id
                !== (int) $lineData['voucher_category_id']
            || ! in_array($transactionType->voucher_type, [
                $type,
                VoucherTransactionTypeHelper::bothVoucherType(),
            ], true)
        ) {
            throw ValidationException::withMessages([
                $errorPrefix.'voucher_transaction_type_id' => 'The selected voucher transaction type does not belong to this category.',
            ]);
        }

        $amount = (float) $lineData['amount'];
        $paymentMethod = $this->normalizePaymentMethod($lineData);
        $isSecurityDepositRefund = $type
                === VoucherTransactionTypeHelper::paymentVoucherType()
            && $this->securityDeposits->isRefundSubType($transactionType);

        if ($isSecurityDepositRefund) {
            $this->securityDeposits->assertRefundAllowed(
                $toAccount,
                $amount,
                $errorPrefix.'amount'
            );
        }

        $description = $lineData['description']
            ?? ($isSecurityDepositRefund
                ? 'Security Deposit Refund'
                : null);

        $voucher = Voucher::query()->create([
            'voucher_no' => $this->numbers->next('voucher', 'V', $businessDate, 4),
            'voucher_type' => $type,
            'voucher_date' => $businessDate,
            'voucher_time' => now()->format('H:i:s'),
            'shift_id' => $headerData['shift_id'] ?? null,
            'voucher_category_id' => $lineData['voucher_category_id'] ?? null,
            'voucher_transaction_type_id' => $this->transactionTypeId($lineData),
            'status' => 'draft',
            'description' => $description,
            'remarks' => $lineData['remarks'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $debitAccount = $type === 'receipt' ? $toAccount : $toAccount;
        $creditAccount = $type === 'receipt' ? $fromAccount : $fromAccount;

        $debitLine = $voucher->lines()->create([
            'line_no' => 1,
            'account_id' => $debitAccount->id,
            'entry_side' => 'debit',
            'amount' => $amount,
            'customer_id' => $debitAccount->customer?->id,
            'supplier_id' => $debitAccount->supplier?->id,
            'employee_id' => $debitAccount->employee?->id,
            'description' => $description,
        ]);

        $voucher->lines()->create([
            'line_no' => 2,
            'account_id' => $creditAccount->id,
            'entry_side' => 'credit',
            'amount' => $amount,
            'customer_id' => $creditAccount->customer?->id,
            'supplier_id' => $creditAccount->supplier?->id,
            'employee_id' => $creditAccount->employee?->id,
            'description' => $description,
        ]);

        $debitLine->paymentDetail()->create([
            'payment_method' => $paymentMethod,
            'bank_type' => $lineData['bank_type'] ?? null,
            'bank_name' => $lineData['bank_name'] ?? null,
            'branch_name' => $lineData['branch_name'] ?? null,
            'account_number' => $lineData['account_no'] ?? null,
            'cheque_number' => $lineData['cheque_no'] ?? null,
            'cheque_date' => $lineData['cheque_date'] ?? null,
            'mobile_bank_name' => $lineData['mobile_bank'] ?? null,
            'mobile_number' => $lineData['mobile_number'] ?? null,
            'transaction_reference' => $lineData['transaction_reference'] ?? null,
        ]);

        $journal = $this->accounting->post([
            'shift_id' => $voucher->shift_id,
            'business_date' => $voucher->voucher_date,
            'event_type' => $isSecurityDepositRefund
                ? CustomerSecurityDepositService::REFUND_EVENT_TYPE
                : $type.'_voucher',
            'source_type' => Voucher::class,
            'source_id' => $voucher->id,
            'reference_no' => $voucher->voucher_no,
            'description' => $description,
            'idempotency_key' => 'voucher:'.$voucher->id,
        ], [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'customer_id' => $debitAccount->customer?->id,
                'supplier_id' => $debitAccount->supplier?->id,
                'employee_id' => $debitAccount->employee?->id,
                'payment_method' => $paymentMethod,
                'description' => $description,
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'customer_id' => $creditAccount->customer?->id,
                'supplier_id' => $creditAccount->supplier?->id,
                'employee_id' => $creditAccount->employee?->id,
                'payment_method' => $paymentMethod,
                'description' => $description,
            ],
        ]);

        $voucher->update([
            'journal_entry_id' => $journal->id,
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $voucher->fresh([
            'lines.account',
            'lines.paymentDetail',
            'journalEntry.lines',
        ]);
    }

    private function normalizePaymentMethod(array $data): string
    {
        $method = (string) ($data['payment_method'] ?? $data['payment_type'] ?? 'Cash');

        if (str_contains(strtolower($method), 'bank')
            && ($data['bank_type'] ?? null) === 'Cheque') {
            return 'cheque';
        }

        if (str_contains(strtolower($method), 'mobile')) {
            return 'mobile_bank';
        }

        if (str_contains(strtolower($method), 'bank')) {
            return 'bank';
        }

        return strtolower($method) === 'online' ? 'online' : 'cash';
    }

    private function transactionTypeId(array $data): ?int
    {
        $id = $data['voucher_transaction_type_id']
            ?? $data['payment_sub_type_id']
            ?? null;

        return $id === null ? null : (int) $id;
    }
}
