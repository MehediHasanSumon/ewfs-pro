<?php

namespace App\Services;

use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeSalaryPayment;
use App\Models\PayrollItem;
use App\Models\Voucher;
use App\Models\VoucherTransactionType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VoucherPostingService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers,
        private readonly CustomerSecurityDepositService $securityDeposits,
        private readonly PartyLedgerService $partyLedger
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
            $existingTransactionTypeId = $voucher
                ->voucher_transaction_type_id;
            $this->reverse($voucher, ucfirst(str_replace('_', ' ', $type)).' replaced from the edit workflow.');

            if ($type === 'office_payment') {
                return $this->createOfficePayment($data);
            }

            return $this->createDocument(
                $type,
                $data,
                $data,
                '',
                $existingTransactionTypeId
            );
        });
    }

    public function reverse(Voucher $voucher, string $reason = 'Voucher deleted from the workflow.'): void
    {
        DB::transaction(function () use ($voucher, $reason): void {
            if (
                Schema::hasTable('payroll_items')
                && PayrollItem::query()
                    ->where(fn ($query) => $query
                        ->where('payment_voucher_id', $voucher->id)
                        ->orWhere('advance_adjustment_voucher_id', $voucher->id))
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'voucher' => 'Payroll vouchers are immutable and cannot be reversed independently.',
                ]);
            }

            $voucher->loadMissing('journalEntry');

            if ($voucher->journalEntry?->status === 'posted') {
                $this->accounting->reverse($voucher->journalEntry, $reason);
            }

            if (Schema::hasTable('employee_salary_payments')) {
                EmployeeSalaryPayment::query()
                    ->where('payment_voucher_id', $voucher->id)
                    ->where('status', EmployeeSalaryPayment::STATUS_PAID)
                    ->update([
                        'status' => EmployeeSalaryPayment::STATUS_REVERSED,
                        'updated_at' => now(),
                    ]);
            }
        });
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
        string $errorPrefix = '',
        ?int $allowedInactiveTransactionTypeId = null
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
            ->find($this->transactionTypeId($lineData));

        if (! $transactionType) {
            throw ValidationException::withMessages([
                $errorPrefix.'voucher_transaction_type_id' => 'The selected transaction type is not valid for this voucher.',
            ]);
        }

        if (
            (int) $transactionType->voucher_category_id
                !== (int) $lineData['voucher_category_id']
        ) {
            throw ValidationException::withMessages([
                $errorPrefix.'voucher_transaction_type_id' => 'The selected transaction type does not belong to the selected voucher category.',
            ]);
        }

        if (
            $transactionType->voucher_type !== $type
            || (
                ! $transactionType->status
                && $transactionType->id
                    !== $allowedInactiveTransactionTypeId
            )
        ) {
            throw ValidationException::withMessages([
                $errorPrefix.'voucher_transaction_type_id' => 'The selected transaction type is not valid for this voucher.',
            ]);
        }

        $taggedEmployee = null;

        if (isset($lineData['employee_id'])) {
            if (
                $transactionType->voucherCategory?->code
                    !== VoucherCategoryHelper::employeeCode()
            ) {
                throw ValidationException::withMessages([
                    $errorPrefix.'employee_id' => 'Employee attribution is only available for Employee vouchers.',
                ]);
            }

            $taggedEmployee = Employee::query()
                ->whereKey((int) $lineData['employee_id'])
                ->where('status', true)
                ->first();

            if (! $taggedEmployee) {
                throw ValidationException::withMessages([
                    $errorPrefix.'employee_id' => 'The selected employee is unavailable.',
                ]);
            }

            $accountEmployeeIds = collect([
                $fromAccount->employee?->id,
                $toAccount->employee?->id,
            ])->filter()->unique();

            if (
                $accountEmployeeIds->isNotEmpty()
                && ! $accountEmployeeIds->contains($taggedEmployee->id)
            ) {
                throw ValidationException::withMessages([
                    $errorPrefix.'employee_id' => 'The selected employee does not match the voucher accounts.',
                ]);
            }
        }

        $amount = (float) $lineData['amount'];
        $paymentMethod = $this->normalizePaymentMethod($lineData);
        $isSecurityDepositRefund = $type
                === VoucherTransactionTypeHelper::paymentVoucherType()
            && $this->securityDeposits->isRefundSubType($transactionType);
        $isSecurityDepositReceipt = $type
                === VoucherTransactionTypeHelper::receiptVoucherType()
            && $this->securityDeposits->isDepositSubType($transactionType);
        $isCustomerAdvanceReturn = $type
                === VoucherTransactionTypeHelper::paymentVoucherType()
            && $transactionType->code
                === VoucherTransactionTypeHelper::customerAdvanceReturnCode()
            && (
                $transactionType->voucherCategory?->code
                    === VoucherCategoryHelper::customerCode()
                || (
                    $transactionType->voucherCategory?->code === null
                    && $transactionType->voucherCategory?->name
                        === VoucherCategoryHelper::getCategoryDefaultName('customer')
                )
            );
        $isEmployeeAdvanceReturn = $type
                === VoucherTransactionTypeHelper::receiptVoucherType()
            && $transactionType->code
                === VoucherTransactionTypeHelper::employeeAdvanceReturnCode()
            && $transactionType->voucherCategory?->code
                === VoucherCategoryHelper::employeeCode();
        $isEmployeeLoanRecovery = $type
                === VoucherTransactionTypeHelper::receiptVoucherType()
            && $transactionType->code
                === VoucherTransactionTypeHelper::employeeLoanRecoveryCode()
            && $transactionType->voucherCategory?->code
                === VoucherCategoryHelper::employeeCode();

        if ($isSecurityDepositRefund) {
            $this->securityDeposits->assertRefundAllowed(
                $toAccount,
                $amount,
                $errorPrefix.'amount'
            );
        }

        if ($isCustomerAdvanceReturn) {
            $customer = $toAccount->customer;

            if (! $customer) {
                throw ValidationException::withMessages([
                    $errorPrefix.'to_account_id' => 'Advance Return must be posted to a customer account.',
                ]);
            }

            $availableAdvance = $this->partyLedger
                ->customerCurrentAdvance($customer->id, $businessDate);

            if (round($amount, 4) > round($availableAdvance, 4)) {
                throw ValidationException::withMessages([
                    $errorPrefix.'amount' => 'Return amount cannot exceed the customer\'s available advance.',
                ]);
            }
        }

        if ($isEmployeeAdvanceReturn || $isEmployeeLoanRecovery) {
            $employee = $fromAccount->employee;

            if (! $employee) {
                throw ValidationException::withMessages([
                    $errorPrefix.'from_account_id' => 'Employee recovery must be posted from an employee account.',
                ]);
            }

            $metric = $this->partyLedger->employeeFinancialMetric(
                $employee,
                $businessDate
            );
            $available = $isEmployeeAdvanceReturn
                ? (float) ($metric['net_advance'] ?? 0)
                : (float) ($metric['loan_balance'] ?? 0);

            if (round($amount, 4) > round($available, 4)) {
                throw ValidationException::withMessages([
                    $errorPrefix.'amount' => $isEmployeeAdvanceReturn
                        ? 'Advance return cannot exceed the employee net advance.'
                        : 'Loan recovery cannot exceed the employee loan balance.',
                ]);
            }
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

        $debitAccount = $toAccount;
        $creditAccount = $fromAccount;
        $debitEmployeeId = $debitAccount->employee?->id
            ?? $taggedEmployee?->id;
        $creditEmployeeId = $creditAccount->employee?->id;

        $debitLine = $voucher->lines()->create([
            'line_no' => 1,
            'account_id' => $debitAccount->id,
            'entry_side' => 'debit',
            'amount' => $amount,
            'customer_id' => $debitAccount->customer?->id,
            'supplier_id' => $debitAccount->supplier?->id,
            'employee_id' => $debitEmployeeId,
            'description' => $description,
        ]);

        $voucher->lines()->create([
            'line_no' => 2,
            'account_id' => $creditAccount->id,
            'entry_side' => 'credit',
            'amount' => $amount,
            'customer_id' => $creditAccount->customer?->id,
            'supplier_id' => $creditAccount->supplier?->id,
            'employee_id' => $creditEmployeeId,
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
                : ($isSecurityDepositReceipt
                    ? 'customer_security_deposit'
                    : $type.'_voucher'),
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
                'employee_id' => $debitEmployeeId,
                'payment_method' => $paymentMethod,
                'description' => $description,
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'customer_id' => $creditAccount->customer?->id,
                'supplier_id' => $creditAccount->supplier?->id,
                'employee_id' => $creditEmployeeId,
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

        return match (strtolower($method)) {
            'journal' => 'journal',
            'online' => 'online',
            default => 'cash',
        };
    }

    private function transactionTypeId(array $data): ?int
    {
        $id = $data['voucher_transaction_type_id']
            ?? $data['payment_sub_type_id']
            ?? null;

        return $id === null ? null : (int) $id;
    }
}
