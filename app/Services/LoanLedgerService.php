<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanLedgerService
{
    public function accountsQuery(
        ?string $search = null,
        ?bool $status = null
    ): Builder {
        return Account::query()
            ->select('accounts.*')
            ->selectSub($this->journalTotal('credit_amount'), 'total_loan')
            ->selectSub($this->journalTotal('debit_amount'), 'total_payment')
            ->selectSub($this->journalBalance(), 'current_balance')
            ->with('group:id,code,name,account_class,normal_balance')
            ->whereHas('group', fn (Builder $group) => $group
                ->where('account_class', 'liability')
                ->where(function (Builder $loanGroup) {
                    $loanGroup->where('code', '400010002')
                        ->orWhere('name', 'like', '%loan%');
                }))
            ->when($search, fn (Builder $query) => $query
                ->where(function (Builder $account) use ($search) {
                    $account->where('name', 'like', '%'.$search.'%')
                        ->orWhere('ac_number', 'like', '%'.$search.'%');
                }))
            ->when($status !== null, fn (Builder $query) => $query
                ->where('status', $status));
    }

    public function loanAccount(Account $account): Account
    {
        return $this->accountsQuery()
            ->whereKey($account->id)
            ->firstOrFail();
    }

    public function summary(Account $account): array
    {
        $totalLoan = (float) ($account->total_loan ?? 0);
        $totalPayment = (float) ($account->total_payment ?? 0);

        return [
            'totalLoan' => $totalLoan,
            'totalPayment' => $totalPayment,
            'currentBalance' => (float) ($account->current_balance
                ?? $totalLoan - $totalPayment),
        ];
    }

    public function voucherQuery(
        Account $account,
        string $entrySide,
        ?string $startDate = null,
        ?string $endDate = null
    ): Builder {
        return Voucher::query()
            ->posted()
            ->whereHas('journalEntry', fn (Builder $entry) => $entry
                ->where('status', 'posted'))
            ->whereHas('lines', fn (Builder $lines) => $lines
                ->where('account_id', $account->id)
                ->where('entry_side', $entrySide))
            ->with([
                'lines' => fn ($lines) => $lines
                    ->where('account_id', $account->id)
                    ->where('entry_side', $entrySide)
                    ->orderBy('line_no'),
            ])
            ->when($startDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '<=', $endDate));
    }

    public function voucherRows(
        Collection $vouchers,
        string $defaultDescription
    ): Collection {
        return $vouchers->map(fn (Voucher $voucher) => [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date' => $voucher->voucher_date->format('Y-m-d'),
            'amount' => (float) $voucher->lines->sum('amount'),
            'description' => $voucher->description ?? $defaultDescription,
        ]);
    }

    public function paginatedVoucherRows(
        Builder $query,
        int $perPage,
        string $defaultDescription
    ): LengthAwarePaginator {
        $paginator = $query
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $this->voucherRows(
                $paginator->getCollection(),
                $defaultDescription
            )
        );

        return $paginator;
    }

    public function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'ac_number' => $account->ac_number,
            'status' => (bool) $account->status,
            'created_at' => $account->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function journalTotal(string $column): QueryBuilder
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereColumn('jl.account_id', 'accounts.id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw('COALESCE(SUM(jl.'.$column.'), 0)');
    }

    private function journalBalance(): QueryBuilder
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereColumn('jl.account_id', 'accounts.id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw(
                'COALESCE(SUM(jl.credit_amount - jl.debit_amount), 0)'
            );
    }
}
