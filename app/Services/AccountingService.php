<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(
        private readonly DocumentNumberService $numbers
    ) {
    }

    public function post(array $entryData, array $lines): JournalEntry
    {
        $this->assertBalanced($lines);

        return DB::transaction(function () use ($entryData, $lines) {
            $businessDate = now()->parse($entryData['business_date'])->toDateString();
            $periodId = AccountingPeriod::query()
                ->where('status', 'open')
                ->whereDate('starts_on', '<=', $businessDate)
                ->whereDate('ends_on', '>=', $businessDate)
                ->value('id');

            $entry = JournalEntry::query()->create([
                'accounting_period_id' => $periodId,
                'shift_id' => $entryData['shift_id'] ?? null,
                'entry_no' => $this->numbers->next('journal_entry', 'JRN', $businessDate),
                'business_date' => $businessDate,
                'occurred_at' => $entryData['occurred_at'] ?? now(),
                'event_type' => $entryData['event_type'],
                'source_type' => $entryData['source_type'] ?? 'manual_journal',
                'source_id' => $entryData['source_id'] ?? null,
                'reference_no' => $entryData['reference_no'] ?? null,
                'description' => $entryData['description'] ?? null,
                'status' => 'draft',
                'reversal_of_id' => $entryData['reversal_of_id'] ?? null,
                'idempotency_key' => $entryData['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid(),
                'posted_by' => $entryData['posted_by'] ?? auth()->id(),
            ]);

            foreach (array_values($lines) as $index => $line) {
                $entry->lines()->create([
                    'line_no' => $index + 1,
                    'account_id' => $line['account_id'],
                    'debit_amount' => $line['debit_amount'] ?? 0,
                    'credit_amount' => $line['credit_amount'] ?? 0,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'employee_id' => $line['employee_id'] ?? null,
                    'product_id' => $line['product_id'] ?? null,
                    'payment_method' => $line['payment_method'] ?? null,
                    'description' => $line['description'] ?? null,
                ]);
            }

            $entry->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            return $entry->fresh(['lines.account']);
        });
    }

    public function reverse(JournalEntry $entry, string $reason): JournalEntry
    {
        if ($entry->status === 'reversed') {
            return $entry->reversals()->latest('id')->firstOrFail();
        }

        if ($entry->status !== 'posted') {
            throw ValidationException::withMessages([
                'record' => 'Only posted journal entries can be reversed.',
            ]);
        }

        return DB::transaction(function () use ($entry, $reason) {
            $entry->loadMissing('lines');

            $reversal = $this->post([
                'shift_id' => $entry->shift_id,
                'business_date' => now()->toDateString(),
                'occurred_at' => now(),
                'event_type' => $entry->event_type.'_reversal',
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reference_no' => $entry->reference_no,
                'description' => $reason,
                'reversal_of_id' => $entry->id,
                'idempotency_key' => 'journal-reversal:'.$entry->id,
            ], $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit_amount' => $line->credit_amount,
                'credit_amount' => $line->debit_amount,
                'customer_id' => $line->customer_id,
                'supplier_id' => $line->supplier_id,
                'employee_id' => $line->employee_id,
                'product_id' => $line->product_id,
                'payment_method' => $line->payment_method,
                'description' => $reason,
            ])->all());

            $entry->update([
                'status' => 'reversed',
                'reversed_by' => auth()->id(),
                'reversed_at' => now(),
            ]);

            return $reversal;
        });
    }

    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'journal' => 'A journal entry requires at least two lines.',
            ]);
        }

        $debit = round(array_sum(array_map(
            fn (array $line) => (float) ($line['debit_amount'] ?? 0),
            $lines
        )), 4);
        $credit = round(array_sum(array_map(
            fn (array $line) => (float) ($line['credit_amount'] ?? 0),
            $lines
        )), 4);

        if ($debit <= 0 || $debit !== $credit) {
            throw ValidationException::withMessages([
                'journal' => 'Debit and credit totals must be equal and greater than zero.',
            ]);
        }
    }
}
