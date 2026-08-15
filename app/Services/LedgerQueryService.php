<?php

namespace App\Services;

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\ShiftClosing;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerQueryService
{
    public function activeAccounts(): Collection
    {
        return Account::query()
            ->active()
            ->with('group:id,code,name,account_class,normal_balance')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function bankAccounts(): Collection
    {
        return Account::query()
            ->active()
            ->with('group:id,code,name,account_class,normal_balance')
            ->whereHas('group', function (EloquentBuilder $query) {
                $query->where('account_class', 'asset')
                    ->where(function (EloquentBuilder $group) {
                        $group->whereIn(
                            'code',
                            AccountGroupHelper::codes([
                                'mobile_bank',
                                'bank_account',
                            ])
                        )
                            ->orWhere('name', 'like', '%bank%');
                    });
            })
            ->where(function (EloquentBuilder $query) {
                $query->whereNull('semantic_code')
                    ->orWhere('semantic_code', '<>', 'cash_on_hand');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function cashAccountIds(): Collection
    {
        return Account::query()
            ->active()
            ->where(function (EloquentBuilder $query) {
                $query->where('semantic_code', 'cash_on_hand')
                    ->orWhere('name', 'like', '%cash%')
                    ->orWhereHas('group', fn (EloquentBuilder $group) => $group
                        ->where('account_class', 'asset')
                        ->where(function (EloquentBuilder $cashGroup) {
                            $cashGroup->where(
                                'code',
                                AccountGroupHelper::code('cash_in_hand')
                            )
                                ->orWhere('name', 'like', '%cash%');
                        }));
            })
            ->pluck('id');
    }

    public function cashAccounts(): Collection
    {
        return Account::query()
            ->active()
            ->with('group:id,code,name,account_class,normal_balance')
            ->where(function (EloquentBuilder $query) {
                $query->where('semantic_code', 'cash_on_hand')
                    ->orWhere('name', 'like', '%cash%')
                    ->orWhereHas('group', fn (EloquentBuilder $group) => $group
                        ->where('account_class', 'asset')
                        ->where(function (EloquentBuilder $cashGroup) {
                            $cashGroup->where(
                                'code',
                                AccountGroupHelper::code('cash_in_hand')
                            )
                                ->orWhere('name', 'like', '%cash%');
                        }));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function accountLedger(Account $account, string $startDate, string $endDate): array
    {
        $account->loadMissing('group');
        $isCreditNormal = $account->group?->normal_balance === 'credit';
        $openingBalance = $this->signedBalanceBefore($account->id, $startDate, $isCreditNormal);

        $runningBalanceSql = $isCreditNormal
            ? '? + SUM(jl.credit_amount - jl.debit_amount) OVER (ORDER BY je.business_date, je.occurred_at, jl.id) AS balance'
            : '? + SUM(jl.debit_amount - jl.credit_amount) OVER (ORDER BY je.business_date, je.occurred_at, jl.id) AS balance';

        $transactions = $this->accountLinesQuery($account->id, $startDate, $endDate)
            ->select($this->legacySelectColumns())
            ->selectRaw($this->legacySelectExpressions())
            ->selectRaw($runningBalanceSql, [$openingBalance])
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id')
            ->get()
            ->map(fn (object $row) => $this->normalizeLegacyRow($row));

        $totals = $this->accountPeriodTotals($account->id, $startDate, $endDate);

        $closingBalance = $isCreditNormal
            ? $openingBalance + (float) $totals->total_credit - (float) $totals->total_debit
            : $openingBalance + (float) $totals->total_debit - (float) $totals->total_credit;

        return [
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'total_debit' => (float) $totals->total_debit,
            'total_credit' => (float) $totals->total_credit,
            'closing_balance' => $closingBalance,
        ];
    }

    public function paginatedAccountLedger(
        Account $account,
        string $startDate,
        string $endDate,
        int $perPage
    ): array {
        $account->loadMissing('group');
        $isCreditNormal = $account->group?->normal_balance === 'credit';
        $openingBalance = $this->signedBalanceBefore($account->id, $startDate, $isCreditNormal);

        $runningBalanceSql = $isCreditNormal
            ? '? + SUM(jl.credit_amount - jl.debit_amount) OVER (ORDER BY je.business_date, je.occurred_at, jl.id) AS balance'
            : '? + SUM(jl.debit_amount - jl.credit_amount) OVER (ORDER BY je.business_date, je.occurred_at, jl.id) AS balance';

        $query = $this->accountLinesQuery($account->id, $startDate, $endDate)
            ->select($this->legacySelectColumns())
            ->selectRaw($this->legacySelectExpressions())
            ->selectRaw($runningBalanceSql, [$openingBalance])
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id');

        $transactions = $query->paginate($perPage)->withQueryString();
        $transactions->setCollection(
            $transactions->getCollection()
                ->map(fn (object $row) => $this->normalizeLegacyRow($row))
        );
        $totals = $this->accountPeriodTotals($account->id, $startDate, $endDate);

        $closingBalance = $isCreditNormal
            ? $openingBalance + (float) $totals->total_credit - (float) $totals->total_debit
            : $openingBalance + (float) $totals->total_debit - (float) $totals->total_credit;

        return [
            'transactions' => $transactions,
            'opening_balance' => $openingBalance,
            'total_debit' => (float) $totals->total_debit,
            'total_credit' => (float) $totals->total_credit,
            'closing_balance' => $closingBalance,
        ];
    }

    public function cashTransactionsForClosing(ShiftClosing $closing): Collection
    {
        $cashAccountIds = $this->cashAccountIds();

        if ($cashAccountIds->isEmpty()) {
            return collect();
        }

        $entries = JournalEntry::query()
            ->posted()
            ->where('shift_id', $closing->shift_id)
            ->whereDate('business_date', $closing->business_date)
            ->whereHas('lines', fn (EloquentBuilder $lines) => $lines
                ->whereIn('account_id', $cashAccountIds))
            ->with([
                'lines.account:id,name',
                'shift:id,name',
            ])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $voucherIds = $entries
            ->where('source_type', Voucher::class)
            ->pluck('source_id')
            ->filter()
            ->unique();

        $vouchers = Voucher::query()
            ->whereIn('id', $voucherIds)
            ->with([
                'voucherCategory:id,name',
                'voucherTransactionType:id,name',
            ])
            ->get()
            ->keyBy('id');

        return $entries->flatMap(function (JournalEntry $entry) use ($cashAccountIds, $vouchers) {
            $voucher = $entry->source_type === Voucher::class
                ? $vouchers->get($entry->source_id)
                : null;
            $debitNames = $entry->lines
                ->where('debit_amount', '>', 0)
                ->pluck('account.name')
                ->filter()
                ->unique()
                ->implode(', ');
            $creditNames = $entry->lines
                ->where('credit_amount', '>', 0)
                ->pluck('account.name')
                ->filter()
                ->unique()
                ->implode(', ');

            return $entry->lines
                ->whereIn('account_id', $cashAccountIds)
                ->map(function ($line) use (
                    $entry,
                    $voucher,
                    $debitNames,
                    $creditNames
                ) {
                    return (object) [
                        'id' => $line->id,
                        'transaction_id' => $entry->entry_no,
                        'voucher_no' => $voucher?->voucher_no ?? $entry->reference_no,
                        'voucher_type' => $voucher?->voucher_type ?? $entry->event_type,
                        'date' => $entry->business_date->format('Y-m-d'),
                        'voucher_date' => $entry->business_date->format('Y-m-d'),
                        'transaction_date' => $entry->business_date->format('Y-m-d'),
                        'transaction_time' => $entry->occurred_at->format('H:i:s'),
                        'transaction_type' => (float) $line->debit_amount > 0 ? 'Dr' : 'Cr',
                        'amount' => (float) max(
                            (float) $line->debit_amount,
                            (float) $line->credit_amount
                        ),
                        'description' => $line->description ?? $entry->description,
                        'payment_type' => $line->payment_method,
                        'category_name' => $voucher?->voucherCategory?->name
                            ?? $voucher?->voucherTransactionType?->name
                            ?? str($entry->event_type)->headline()->toString(),
                        'from_account_name' => $creditNames ?: '-',
                        'to_account_name' => $debitNames ?: '-',
                        'shift_name' => $entry->shift?->name,
                    ];
                });
        })->values();
    }

    public function naturalBalanceForAccount(int $accountId, ?string $throughDate = null): float
    {
        $account = Account::query()
            ->with('group:id,normal_balance')
            ->findOrFail($accountId);

        $totals = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $accountId)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->when($throughDate, fn (Builder $query) => $query
                ->whereDate('je.business_date', '<=', $throughDate))
            ->selectRaw(
                'COALESCE(SUM(jl.debit_amount), 0) AS total_debit,
                 COALESCE(SUM(jl.credit_amount), 0) AS total_credit'
            )
            ->first();

        return $account->group?->normal_balance === 'credit'
            ? (float) $totals->total_credit - (float) $totals->total_debit
            : (float) $totals->total_debit - (float) $totals->total_credit;
    }

    private function accountLinesQuery(
        int $accountId,
        string $startDate,
        string $endDate
    ): Builder {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('vouchers as v', function ($join) {
                $join->on('v.id', '=', 'je.source_id')
                    ->where('je.source_type', Voucher::class);
            })
            ->leftJoin('shifts as s', 's.id', '=', 'je.shift_id')
            ->where('jl.account_id', $accountId)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereBetween('je.business_date', [$startDate, $endDate]);
    }

    private function accountPeriodTotals(
        int $accountId,
        string $startDate,
        string $endDate
    ): object {
        return $this->accountLinesQuery($accountId, $startDate, $endDate)
            ->selectRaw(
                'COALESCE(SUM(jl.debit_amount), 0) AS total_debit,
                 COALESCE(SUM(jl.credit_amount), 0) AS total_credit'
            )
            ->first();
    }

    private function signedBalanceBefore(int $accountId, string $startDate, bool $isCreditNormal = false): float
    {
        $selectExpr = $isCreditNormal
            ? 'COALESCE(SUM(jl.credit_amount - jl.debit_amount), 0) AS balance'
            : 'COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS balance';

        return (float) DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.account_id', $accountId)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereDate('je.business_date', '<', $startDate)
            ->selectRaw($selectExpr)
            ->value('balance');
    }

    private function legacySelectColumns(): array
    {
        return [
            'jl.id',
            'je.entry_no as transaction_id',
            'je.business_date as transaction_date',
            'jl.debit_amount',
            'jl.credit_amount',
            's.name as shift_name',
            'je.source_type',
            'je.source_id',
        ];
    }

    private function legacySelectExpressions(): string
    {
        return "
            TIME(je.occurred_at) AS transaction_time,
            CASE WHEN jl.debit_amount > 0 THEN 'Dr' ELSE 'Cr' END AS transaction_type,
            CASE WHEN jl.debit_amount >= jl.credit_amount THEN jl.debit_amount ELSE jl.credit_amount END AS amount,
            COALESCE(jl.description, je.description) AS description,
            jl.payment_method AS payment_type,
            COALESCE(v.voucher_date, je.business_date) AS voucher_date,
            COALESCE(v.voucher_no, je.reference_no, je.entry_no) AS voucher_no,
            COALESCE(v.voucher_type, je.event_type) AS voucher_type
        ";
    }

    private function normalizeLegacyRow(object $row): object
    {
        $row->amount = (float) $row->amount;
        $row->debit_amount = (float) $row->debit_amount;
        $row->credit_amount = (float) $row->credit_amount;
        $row->balance = (float) $row->balance;

        return $row;
    }
}
