<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FundTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundTransferService
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly PaymentAccountService $paymentAccounts,
        private readonly SystemAccountService $systemAccounts,
        private readonly DocumentNumberService $numbers
    ) {}

    public function create(array $data): FundTransfer
    {
        return DB::transaction(function () use ($data): FundTransfer {
            $transferDate = now()->parse($data['transfer_date'] ?? $data['date'] ?? now())->toDateString();
            $fromAccountId = (int) ($data['from_account_id'] ?? 0);
            $toAccountId = (int) ($data['to_account_id'] ?? 0);
            $amount = round((float) ($data['amount'] ?? 0), 4);
            $transferFee = round((float) ($data['transfer_fee'] ?? 0), 4);
            $feeAccountId = ! empty($data['fee_account_id']) ? (int) $data['fee_account_id'] : null;
            $referenceNo = ! empty($data['reference_no']) ? trim((string) $data['reference_no']) : null;
            $remarks = ! empty($data['remarks']) ? trim((string) $data['remarks']) : null;

            if ($fromAccountId <= 0) {
                throw ValidationException::withMessages([
                    'from_account_id' => 'Please select a valid From Account.',
                ]);
            }

            if ($toAccountId <= 0) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'Please select a valid To Account.',
                ]);
            }

            if ($fromAccountId === $toAccountId) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'From Account and To Account must be different accounts.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transfer amount must be greater than zero.',
                ]);
            }

            if ($transferFee < 0) {
                throw ValidationException::withMessages([
                    'transfer_fee' => 'Transfer fee cannot be negative.',
                ]);
            }

            $fromAccount = Account::query()
                ->with('group')
                ->where('status', true)
                ->find($fromAccountId);

            if (! $fromAccount) {
                throw ValidationException::withMessages([
                    'from_account_id' => 'The selected From Account is inactive or does not exist.',
                ]);
            }

            $fromMethod = $this->paymentAccounts->methodFor($fromAccount);
            if (! $fromMethod) {
                throw ValidationException::withMessages([
                    'from_account_id' => 'The selected From Account is not an eligible company fund account.',
                ]);
            }

            $toAccount = Account::query()
                ->with('group')
                ->where('status', true)
                ->find($toAccountId);

            if (! $toAccount) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'The selected To Account is inactive or does not exist.',
                ]);
            }

            $toMethod = $this->paymentAccounts->methodFor($toAccount);
            if (! $toMethod) {
                throw ValidationException::withMessages([
                    'to_account_id' => 'The selected To Account is not an eligible company fund account.',
                ]);
            }

            $feeAccount = null;
            if ($transferFee > 0) {
                if ($feeAccountId) {
                    $feeAccount = Account::query()
                        ->with('group')
                        ->where('status', true)
                        ->find($feeAccountId);

                    if (! $feeAccount || $feeAccount->group?->account_class !== 'expense') {
                        throw ValidationException::withMessages([
                            'fee_account_id' => 'The selected Transfer Fee Account must be an active expense account.',
                        ]);
                    }
                } else {
                    $feeAccount = $this->systemAccounts->bankChargeExpense();
                    $feeAccountId = $feeAccount->id;
                }
            }

            $transferNo = $this->numbers->next('fund_transfer', 'TRF-', $transferDate, 6);

            $rawRemarks = isset($data['remarks']) ? trim((string) $data['remarks']) : '';
            $remarks = $rawRemarks !== ''
                ? $rawRemarks
                : $this->generateRemark($fromAccount, $toAccount, $transferFee);

            $transfer = FundTransfer::query()->create([
                'transfer_no' => $transferNo,
                'transfer_date' => $transferDate,
                'from_account_id' => $fromAccount->id,
                'to_account_id' => $toAccount->id,
                'amount' => $amount,
                'transfer_fee' => $transferFee,
                'fee_account_id' => $transferFee > 0 ? $feeAccountId : null,
                'reference_no' => $referenceNo,
                'remarks' => $remarks,
                'status' => FundTransfer::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            $lines = [];

            // Line 1: Debit To Account (receiving destination)
            $lines[] = [
                'account_id' => $toAccount->id,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'payment_method' => $this->normalizePaymentMethod($toMethod),
                'description' => "Internal fund transfer in from {$fromAccount->name}".($referenceNo ? " (Ref: {$referenceNo})" : ''),
            ];

            // Line 2 (optional): Debit Fee Expense Account
            if ($transferFee > 0 && $feeAccount) {
                $lines[] = [
                    'account_id' => $feeAccount->id,
                    'debit_amount' => $transferFee,
                    'credit_amount' => 0,
                    'payment_method' => 'journal',
                    'description' => "Transfer fee for {$transferNo}".($referenceNo ? " (Ref: {$referenceNo})" : ''),
                ];
            }

            // Line 3: Credit From Account (source account deductions = amount + fee)
            $totalSourceDeduction = round($amount + $transferFee, 4);
            $lines[] = [
                'account_id' => $fromAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $totalSourceDeduction,
                'payment_method' => $this->normalizePaymentMethod($fromMethod),
                'description' => "Internal fund transfer out to {$toAccount->name}".($referenceNo ? " (Ref: {$referenceNo})" : ''),
            ];

            $journal = $this->accounting->post([
                'shift_id' => $data['shift_id'] ?? null,
                'business_date' => $transferDate,
                'occurred_at' => now(),
                'event_type' => 'fund_transfer',
                'source_type' => FundTransfer::class,
                'source_id' => $transfer->id,
                'reference_no' => $transferNo,
                'description' => $remarks ?: "Internal fund transfer: {$fromAccount->name} -> {$toAccount->name}",
                'idempotency_key' => 'fund-transfer:'.$transfer->id,
                'posted_by' => auth()->id(),
            ], $lines);

            $transfer->update([
                'journal_entry_id' => $journal->id,
                'status' => FundTransfer::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $transfer->fresh([
                'fromAccount.group',
                'toAccount.group',
                'feeAccount',
                'journalEntry.lines',
            ]);
        });
    }

    public function cancel(FundTransfer $transfer, string $reason = 'Fund transfer cancelled.'): void
    {
        DB::transaction(function () use ($transfer, $reason): void {
            if ($transfer->status === FundTransfer::STATUS_CANCELLED) {
                return;
            }

            $transfer->loadMissing('journalEntry');

            if ($transfer->journalEntry && $transfer->journalEntry->status === 'posted') {
                $this->accounting->reverse($transfer->journalEntry, $reason);
            }

            $transfer->update([
                'status' => FundTransfer::STATUS_CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
        });
    }

    public function replace(FundTransfer $transfer, array $data): FundTransfer
    {
        return DB::transaction(function () use ($transfer, $data): FundTransfer {
            $this->cancel($transfer, 'Fund transfer replaced from the edit workflow.');

            return $this->create($data);
        });
    }

    public function generateRemark(Account $fromAccount, Account $toAccount, float $transferFee = 0): string
    {
        if ($transferFee > 0) {
            $formattedFee = (float) $transferFee == (int) $transferFee
                ? number_format($transferFee, 0)
                : rtrim(rtrim(number_format($transferFee, 2, '.', ''), '0'), '.');

            return "Fund transfer from {$fromAccount->name} to {$toAccount->name} with transfer fee of {$formattedFee}.";
        }

        return "Fund transfer from {$fromAccount->name} to {$toAccount->name}.";
    }

    private function normalizePaymentMethod(string $method): string
    {
        return match (strtolower($method)) {
            'bank' => 'bank',
            'mobile bank', 'mobile_bank' => 'mobile_bank',
            default => 'cash',
        };
    }
}
