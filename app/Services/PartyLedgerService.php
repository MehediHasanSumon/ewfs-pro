<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CreditSale;
use App\Models\CreditSaleCustomer;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PartyOpeningBalance;
use App\Models\Supplier;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartyLedgerService
{
    public function customerMetrics(Collection $customers): Collection
    {
        $customerIds = $customers->pluck('id');
        $accountMap = $customers->pluck('id', 'account_id');
        $activity = $this->accountActivity($accountMap->keys());

        $sales = DB::table('credit_sale_customers as csc')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->whereIn('csc.customer_id', $customerIds)
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->groupBy('csc.customer_id')
            ->selectRaw(
                'csc.customer_id, SUM(csc.grand_total) AS total_sales, COUNT(*) AS sales_count'
            )
            ->get()
            ->keyBy('customer_id');

        $payments = $this->partyPaymentTotals('customer_id', $customerIds, 'receipt');
        $deposits = PartyOpeningBalance::query()
            ->whereIn('customer_id', $customerIds)
            ->where('balance_type', 'customer_deposit')
            ->where('status', 'posted')
            ->pluck('amount', 'customer_id');

        return $customers->mapWithKeys(function (Customer $customer) use (
            $activity,
            $sales,
            $payments,
            $deposits
        ) {
            $accountActivity = $activity->get($customer->account_id);
            $sale = $sales->get($customer->id);

            return [$customer->id => [
                'total_sales' => (float) ($sale->total_sales ?? 0),
                'sales_count' => (int) ($sale->sales_count ?? 0),
                'total_paid' => (float) ($payments->get($customer->id) ?? 0),
                'current_due' => (float) ($accountActivity->total_debit ?? 0)
                    - (float) ($accountActivity->total_credit ?? 0),
                'security_deposit' => (float) ($deposits->get($customer->id) ?? 0),
            ]];
        });
    }

    public function supplierMetrics(Collection $suppliers): Collection
    {
        $supplierIds = $suppliers->pluck('id');
        $accountMap = $suppliers->pluck('id', 'account_id');
        $activity = $this->accountActivity($accountMap->keys());

        $purchases = DB::table('purchases')
            ->whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['posted', 'partially_paid', 'paid'])
            ->groupBy('supplier_id')
            ->selectRaw(
                'supplier_id, SUM(grand_total) AS total_purchases, COUNT(*) AS purchase_count'
            )
            ->get()
            ->keyBy('supplier_id');

        $payments = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('suppliers as s', function ($join) {
                $join->on('s.id', '=', 'jl.supplier_id')
                    ->on('s.account_id', '=', 'jl.account_id');
            })
            ->whereIn('s.id', $supplierIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereIn('je.event_type', [
                'purchase',
                'purchase_reversal',
                'payment_voucher',
                'payment_voucher_reversal',
            ])
            ->groupBy('s.id')
            ->selectRaw(
                "s.id AS supplier_id,
                 SUM(
                    CASE
                        WHEN je.event_type = 'purchase'
                            THEN jl.debit_amount
                        WHEN je.event_type = 'purchase_reversal'
                            THEN -jl.credit_amount
                        ELSE jl.debit_amount - jl.credit_amount
                    END
                 ) AS total_paid"
            )
            ->pluck('total_paid', 'supplier_id');

        return $suppliers->mapWithKeys(function (Supplier $supplier) use (
            $activity,
            $purchases,
            $payments
        ) {
            $accountActivity = $activity->get($supplier->account_id);
            $purchase = $purchases->get($supplier->id);

            return [$supplier->id => [
                'total_purchases' => (float) ($purchase->total_purchases ?? 0),
                'purchase_count' => (int) ($purchase->purchase_count ?? 0),
                'total_paid' => (float) ($payments->get($supplier->id) ?? 0),
                'current_due' => (float) ($accountActivity->total_credit ?? 0)
                    - (float) ($accountActivity->total_debit ?? 0),
            ]];
        });
    }

    public function statement(Account $account, string $perspective): Collection
    {
        $rows = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->leftJoin('vouchers as v', function ($join) {
                $join->on('v.id', '=', 'je.source_id')
                    ->where('je.source_type', Voucher::class);
            })
            ->where('jl.account_id', $account->id)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->orderBy('je.business_date')
            ->orderBy('je.occurred_at')
            ->orderBy('jl.id')
            ->select([
                'jl.id',
                'je.business_date as date',
                'je.event_type',
                'je.reference_no',
                'je.description as entry_description',
                'jl.description',
                'jl.debit_amount',
                'jl.credit_amount',
                'v.voucher_no',
            ])
            ->get();

        $balance = 0.0;

        return $rows->map(function (object $row) use ($perspective, &$balance) {
            $journalDebit = (float) $row->debit_amount;
            $journalCredit = (float) $row->credit_amount;
            $debit = $perspective === 'supplier' ? $journalCredit : $journalDebit;
            $credit = $perspective === 'supplier' ? $journalDebit : $journalCredit;
            $balance += $debit - $credit;

            return [
                'id' => $row->id,
                'date' => $row->date,
                'type' => str($row->event_type)->headline()->toString(),
                'description' => $row->description ?? $row->entry_description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
                'invoice_no' => $row->reference_no,
                'voucher_no' => $row->voucher_no,
            ];
        });
    }

    public function vouchers(
        string $partyColumn,
        int $partyId,
        string $voucherType,
        ?string $startDate = null,
        ?string $endDate = null
    ): Builder {
        return Voucher::query()
            ->posted()
            ->ofType($voucherType)
            ->whereHas('journalEntry', fn (Builder $entry) => $entry
                ->where('status', 'posted'))
            ->whereHas('lines', fn (Builder $lines) => $lines
                ->where($partyColumn, $partyId))
            ->with([
                'lines.account:id,name',
                'lines.paymentDetail',
                'paymentSubType:id,name,code',
                'voucherCategory:id,name',
            ])
            ->when($startDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '>=', $startDate))
            ->when($endDate, fn (Builder $query) => $query
                ->whereDate('voucher_date', '<=', $endDate));
    }

    public function voucherRows(Collection $vouchers, string $status): Collection
    {
        return $vouchers->map(fn (Voucher $voucher) => [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date' => $voucher->voucher_date->format('Y-m-d'),
            'amount' => (float) $voucher->amount,
            'payment_type' => $voucher->payment_method,
            'type' => $voucher->payment_method,
            'sub_type' => $voucher->paymentSubType?->name,
            'sub_type_name' => $voucher->paymentSubType?->name,
            'description' => $voucher->description,
            'remarks' => $voucher->remarks,
            'status' => $status,
        ]);
    }

    public function paginatedVoucherRows(
        Builder $query,
        int $perPage,
        string $status
    ): LengthAwarePaginator {
        $paginator = $query
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $this->voucherRows($paginator->getCollection(), $status)
        );

        return $paginator;
    }

    public function customerMonthlySales(int $customerId, int $year): Collection
    {
        return DB::table('credit_sale_customers as csc')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->where('csc.customer_id', $customerId)
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->whereYear('cs.sale_date', $year)
            ->groupByRaw('YEAR(cs.sale_date), MONTH(cs.sale_date)')
            ->orderByRaw('MONTH(cs.sale_date)')
            ->selectRaw(
                'YEAR(cs.sale_date) AS year,
                 MONTH(cs.sale_date) AS month,
                 SUM(csc.grand_total) AS total'
            )
            ->get()
            ->map(fn (object $sale) => [
                'month' => date('F Y', mktime(0, 0, 0, $sale->month, 1, $sale->year)),
                'total' => (float) $sale->total,
            ]);
    }

    public function customerSalesYears(int $customerId): array
    {
        return DB::table('credit_sale_customers as csc')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->where('csc.customer_id', $customerId)
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->selectRaw('DISTINCT YEAR(cs.sale_date) AS year')
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();
    }

    private function accountActivity(Collection $accountIds): Collection
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $accountIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->groupBy('jl.account_id')
            ->selectRaw(
                'jl.account_id,
                 SUM(jl.debit_amount) AS total_debit,
                 SUM(jl.credit_amount) AS total_credit'
            )
            ->get()
            ->keyBy('account_id');
    }

    private function partyPaymentTotals(
        string $partyColumn,
        Collection $partyIds,
        string $voucherType
    ): Collection {
        return DB::table('voucher_lines as vl')
            ->join('vouchers as v', 'v.id', '=', 'vl.voucher_id')
            ->join('journal_entries as je', 'je.id', '=', 'v.journal_entry_id')
            ->whereIn('vl.'.$partyColumn, $partyIds)
            ->where('v.voucher_type', $voucherType)
            ->where('v.status', 'posted')
            ->where('je.status', 'posted')
            ->groupBy('vl.'.$partyColumn)
            ->selectRaw('vl.'.$partyColumn.' AS party_id, SUM(vl.amount) AS total')
            ->pluck('total', 'party_id');
    }
}
