<?php

namespace App\Services;

use App\Helpers\AccountGroupHelper;
use App\Models\Account;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function __construct(
        private readonly PartyLedgerService $partyLedger
    ) {}

    public function balanceSheet(string $asOfDate, string $startDate, string $endDate): array
    {
        $purchaseData = $this->purchaseData($startDate, $endDate);
        $salesData = $this->saleData($startDate, $endDate);
        $creditSalesData = $this->creditSaleData($startDate, $endDate);
        $stockData = $this->stockData($asOfDate);
        $adminExpenses = $this->adminExpenses($startDate, $endDate);
        $position = $this->positionSummary($asOfDate);

        $totalPurchases = (float) $purchaseData->sum('total_amount');
        $totalSales = (float) $salesData->sum('total_amount')
            + (float) $creditSalesData->sum('total_amount');
        $totalStockValue = (float) $stockData->sum('stock_value');
        $totalAdminExpenses = (float) $adminExpenses->sum('total_amount');
        $costOfSales = (float) $salesData->sum('total_cost')
            + (float) $creditSalesData->sum('total_cost');
        $grossProfit = $totalSales - $costOfSales;

        return [
            ...$position,
            'purchase_data' => $purchaseData,
            'sales_data' => $salesData,
            'credit_sales_data' => $creditSalesData,
            'stock_data' => $stockData,
            'admin_expenses' => $adminExpenses,
            'totals' => [
                'total_purchases' => $totalPurchases,
                'total_sales' => $totalSales,
                'total_stock_value' => $totalStockValue,
                'total_admin_expenses' => $totalAdminExpenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $grossProfit - $totalAdminExpenses,
            ],
        ];
    }

    public function positionSummary(string $asOfDate): array
    {
        $cash = $this->classifiedPositiveAccountBalance('cash', $asOfDate);
        $bank = $this->classifiedPositiveAccountBalance('bank', $asOfDate);
        $customerPosition = $this->customerPosition($asOfDate);
        $purchaseDue = $this->supplierDue($asOfDate);
        $bankLoan = $this->classifiedPositiveAccountBalance('loan', $asOfDate);
        $stockValue = (float) $this->stockData($asOfDate)->sum('stock_value');

        $assets = [
            'office_cash' => max(0, $cash),
            'bank_deposit' => max(0, $bank),
            'customer_due' => $customerPosition['due'],
            'stock_value' => max(0, $stockValue),
        ];
        $assets['total_assets'] = array_sum($assets);

        $liabilities = [
            'purchase_due' => $purchaseDue,
            'customer_advance' => $customerPosition['advance'],
            'customer_security' => $customerPosition['security'],
            'bank_loan' => max(0, $bankLoan),
        ];
        $liabilities['total_liabilities'] = array_sum($liabilities);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'net_worth' => $assets['total_assets'] - $liabilities['total_liabilities'],
        ];
    }

    public function accountClassItems(string $accountClass, ?string $asOfDate = null): Collection
    {
        $balanceExpression = "
            CASE
                WHEN g.normal_balance = 'credit'
                    THEN COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit_amount - jl.debit_amount ELSE 0 END), 0)
                ELSE COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.debit_amount - jl.credit_amount ELSE 0 END), 0)
            END
        ";

        return DB::table('accounts as a')
            ->join('groups as g', 'g.id', '=', 'a.group_id')
            ->leftJoin('journal_lines as jl', 'jl.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as je', function ($join) use ($asOfDate) {
                $join->on('je.id', '=', 'jl.journal_entry_id')
                    ->whereIn('je.status', ['posted', 'reversed']);

                if ($asOfDate) {
                    $join->whereDate('je.business_date', '<=', $asOfDate);
                }
            })
            ->where('g.account_class', $accountClass)
            ->groupBy(
                'a.id',
                'a.name',
                'a.ac_number',
                'g.name',
                'g.normal_balance'
            )
            ->selectRaw(
                "a.id,
                 a.name,
                 a.ac_number,
                 g.name AS group_name,
                 {$balanceExpression} AS balance"
            )
            ->havingRaw("ABS({$balanceExpression}) > 0.0001")
            ->orderBy('g.name')
            ->orderBy('a.name')
            ->get()
            ->map(function (object $row) use ($accountClass) {
                $row->balance = (float) $row->balance;
                $row->type = ucfirst($accountClass);

                return $row;
            });
    }

    public function monthlySales(int $months = 6, ?int $productId = null): Collection
    {
        $from = now()->startOfMonth()->subMonths($months - 1)->toDateString();
        $rows = DB::query()
            ->fromSub(
                DB::table('sale_items as si')
                    ->join('sales as s', 's.id', '=', 'si.sale_id')
                    ->join('journal_entries as je', function ($join) {
                        $join->on('je.id', '=', 's.journal_entry_id')
                            ->where('je.status', 'posted');
                    })
                    ->whereIn('s.status', ['posted', 'partially_paid', 'paid'])
                    ->whereDate('s.sale_date', '>=', $from)
                    ->when($productId, fn ($query) => $query
                        ->where('si.product_id', $productId))
                    ->selectRaw(
                        's.sale_date AS business_date, si.line_total AS amount'
                    )
                    ->unionAll(
                        DB::table('credit_sale_items as csi')
                            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
                            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
                            ->join('journal_entries as je', function ($join) {
                                $join->on('je.id', '=', 'csc.journal_entry_id')
                                    ->where('je.status', 'posted');
                            })
                            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
                            ->whereDate('cs.sale_date', '>=', $from)
                            ->when($productId, fn ($query) => $query
                                ->where('csi.product_id', $productId))
                            ->selectRaw(
                                'cs.sale_date AS business_date, csi.line_total AS amount'
                            )
                    ),
                'combined_sales'
            )
            ->groupByRaw('YEAR(business_date), MONTH(business_date)')
            ->selectRaw(
                'YEAR(business_date) AS year,
                 MONTH(business_date) AS month,
                 SUM(amount) AS total'
            )
            ->get()
            ->keyBy(fn (object $row) => $row->year.'-'.$row->month);

        return $this->monthSeries($rows, $months);
    }

    public function monthlyPurchases(int $months = 6, ?int $productId = null): Collection
    {
        $from = now()->startOfMonth()->subMonths($months - 1)->toDateString();
        $rows = DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'p.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('p.status', ['posted', 'partially_paid', 'paid'])
            ->whereDate('p.purchase_date', '>=', $from)
            ->when($productId, fn ($query) => $query->where('pi.product_id', $productId))
            ->groupByRaw('YEAR(p.purchase_date), MONTH(p.purchase_date)')
            ->selectRaw(
                'YEAR(p.purchase_date) AS year,
                 MONTH(p.purchase_date) AS month,
                 SUM(pi.line_total) AS total'
            )
            ->get()
            ->keyBy(fn (object $row) => $row->year.'-'.$row->month);

        return $this->monthSeries($rows, $months);
    }

    public function cashBalance(?string $asOfDate = null): float
    {
        return max(0, $this->classifiedAccountBalance('cash', $asOfDate));
    }

    public function officeExpenses(string $date): float
    {
        return (float) DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('groups as g', 'g.id', '=', 'a.group_id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereDate('je.business_date', $date)
            ->where('g.account_class', 'expense')
            ->selectRaw(
                'COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total'
            )
            ->value('total');
    }

    public function cashSales(string $date): float
    {
        $cashIds = $this->classifiedAccountIds('cash');

        return (float) DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('jl.account_id', $cashIds)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereDate('je.business_date', $date)
            ->whereIn('je.event_type', [
                'regular_sale',
                'white_sale',
                'shift_closing',
                'regular_sale_reversal',
                'white_sale_reversal',
                'shift_closing_reversal',
            ])
            ->selectRaw(
                'COALESCE(SUM(jl.debit_amount - jl.credit_amount), 0) AS total'
            )
            ->value('total');
    }

    public function outstandingCustomers(int $limit = 5, ?string $asOfDate = null): Collection
    {
        $customers = Customer::query()
            ->get(['id', 'account_id', 'name', 'mobile']);
        $metrics = $this->partyLedger->customerMetrics($customers, $asOfDate);

        return $customers
            ->map(function (Customer $customer) use ($metrics) {
                $metric = $metrics->get($customer->id);

                return (object) [
                    'id' => $customer->id,
                    'customer' => $customer->name,
                    'mobile_number' => $customer->mobile,
                    'balance' => (float) ($metric['current_due'] ?? 0),
                ];
            })
            ->filter(fn (object $row) => $row->balance > 0.0001)
            ->sortByDesc('balance')
            ->take($limit)
            ->values();
    }

    public function totalOutstanding(?string $asOfDate = null): float
    {
        return (float) $this->outstandingCustomers(PHP_INT_MAX, $asOfDate)
            ->sum('balance');
    }

    public function stockData(?string $asOfDate = null): Collection
    {
        return DB::table('inventory_movements as im')
            ->join('products as p', 'p.id', '=', 'im.product_id')
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('im.business_date', '<=', $asOfDate))
            ->groupBy('p.id', 'p.product_name')
            ->selectRaw(
                'p.id AS product_id,
                 p.product_name,
                 SUM(im.quantity_in - im.quantity_out) AS quantity,
                 SUM(CASE WHEN im.quantity_in > 0 THEN im.total_cost ELSE -im.total_cost END) AS stock_value'
            )
            ->get()
            ->map(function (object $row) {
                $row->quantity = (float) $row->quantity;
                $row->stock_value = (float) $row->stock_value;
                $row->purchase_price = $row->quantity > 0
                    ? max(0, $row->stock_value / $row->quantity)
                    : 0;
                $row->current_stock = $row->quantity;

                return $row;
            })
            ->filter(fn (object $row) => abs($row->quantity) > 0.000001)
            ->values();
    }

    private function purchaseData(string $startDate, string $endDate): Collection
    {
        return DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'p.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->join('products as product', 'product.id', '=', 'pi.product_id')
            ->whereIn('p.status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('p.purchase_date', [$startDate, $endDate])
            ->groupBy('product.id', 'product.product_name')
            ->selectRaw(
                'product.product_name,
                 AVG(pi.unit_cost) AS avg_price,
                 SUM(pi.quantity) AS total_quantity,
                 SUM(pi.line_total) AS total_amount'
            )
            ->get()
            ->map(fn (object $row) => $this->castAmounts($row, [
                'avg_price',
                'total_quantity',
                'total_amount',
            ]));
    }

    private function saleData(string $startDate, string $endDate): Collection
    {
        return DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->join('products as product', 'product.id', '=', 'si.product_id')
            ->whereIn('s.status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('s.sale_date', [$startDate, $endDate])
            ->groupBy('product.id', 'product.product_name')
            ->selectRaw(
                'product.product_name,
                 AVG(si.unit_cost) AS purchase_price,
                 AVG(si.unit_price) AS sale_price,
                 MAX(s.sale_date) AS effective_date,
                 SUM(si.quantity) AS total_quantity,
                 SUM(si.line_total) AS total_amount,
                 SUM(si.quantity * si.unit_cost) AS total_cost'
            )
            ->get()
            ->map(fn (object $row) => $this->castAmounts($row, [
                'purchase_price',
                'sale_price',
                'total_quantity',
                'total_amount',
                'total_cost',
            ]));
    }

    private function creditSaleData(string $startDate, string $endDate): Collection
    {
        return DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->join('products as product', 'product.id', '=', 'csi.product_id')
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('cs.sale_date', [$startDate, $endDate])
            ->groupBy('product.id', 'product.product_name')
            ->selectRaw(
                'product.product_name,
                 AVG(csi.unit_cost) AS purchase_price,
                 AVG(csi.unit_price) AS sale_price,
                 MAX(cs.sale_date) AS effective_date,
                 SUM(csi.quantity) AS total_quantity,
                 SUM(csi.line_total) AS total_amount,
                 SUM(csi.quantity * csi.unit_cost) AS total_cost'
            )
            ->get()
            ->map(fn (object $row) => $this->castAmounts($row, [
                'purchase_price',
                'sale_price',
                'total_quantity',
                'total_amount',
                'total_cost',
            ]));
    }

    private function adminExpenses(string $startDate, string $endDate): Collection
    {
        return DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('groups as g', 'g.id', '=', 'a.group_id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->whereBetween('je.business_date', [$startDate, $endDate])
            ->where('g.account_class', 'expense')
            ->groupBy('a.id', 'a.name')
            ->selectRaw(
                'a.name AS expense_type,
                 SUM(jl.debit_amount - jl.credit_amount) AS total_amount'
            )
            ->get()
            ->map(fn (object $row) => $this->castAmounts($row, ['total_amount']))
            ->filter(fn (object $row) => $row->total_amount > 0)
            ->values();
    }

    private function customerPosition(string $asOfDate): array
    {
        $position = $this->partyLedger->customerPosition($asOfDate);

        return [
            'due' => (float) $position['due'],
            'advance' => (float) $position['advance'],
            'security' => (float) $position['security'],
        ];
    }

    private function supplierDue(string $asOfDate): float
    {
        $supplierBalances = DB::table('suppliers as s')
            ->join('accounts as a', 'a.id', '=', 's.account_id')
            ->leftJoin('journal_lines as jl', 'jl.account_id', '=', 'a.id')
            ->leftJoin('journal_entries as je', function ($join) use ($asOfDate) {
                $join->on('je.id', '=', 'jl.journal_entry_id')
                    ->whereIn('je.status', ['posted', 'reversed'])
                    ->whereDate('je.business_date', '<=', $asOfDate);
            })
            ->groupBy('s.id')
            ->selectRaw(
                's.id,
                 COALESCE(SUM(CASE WHEN je.id IS NOT NULL THEN jl.credit_amount - jl.debit_amount ELSE 0 END), 0) AS balance'
            );

        return (float) DB::query()
            ->fromSub($supplierBalances, 'supplier_balances')
            ->selectRaw('COALESCE(SUM(GREATEST(balance, 0)), 0) AS total')
            ->value('total');
    }

    private function classifiedAccountBalance(string $type, ?string $asOfDate): float
    {
        $ids = $this->classifiedAccountIds($type);

        if ($ids->isEmpty()) {
            return 0;
        }

        return (float) DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('groups as g', 'g.id', '=', 'a.group_id')
            ->whereIn('jl.account_id', $ids)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('je.business_date', '<=', $asOfDate))
            ->selectRaw(
                "COALESCE(SUM(
                    CASE WHEN g.normal_balance = 'credit'
                        THEN jl.credit_amount - jl.debit_amount
                        ELSE jl.debit_amount - jl.credit_amount
                    END
                ), 0) AS balance"
            )
            ->value('balance');
    }

    private function classifiedPositiveAccountBalance(
        string $type,
        ?string $asOfDate
    ): float {
        $ids = $this->classifiedAccountIds($type);

        if ($ids->isEmpty()) {
            return 0;
        }

        $balances = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->join('groups as g', 'g.id', '=', 'a.group_id')
            ->whereIn('jl.account_id', $ids)
            ->whereIn('je.status', ['posted', 'reversed'])
            ->when($asOfDate, fn ($query) => $query
                ->whereDate('je.business_date', '<=', $asOfDate))
            ->groupBy('jl.account_id', 'g.normal_balance')
            ->selectRaw(
                "jl.account_id,
                 CASE WHEN g.normal_balance = 'credit'
                    THEN SUM(jl.credit_amount - jl.debit_amount)
                    ELSE SUM(jl.debit_amount - jl.credit_amount)
                 END AS balance"
            );

        return (float) DB::query()
            ->fromSub($balances, 'account_balances')
            ->selectRaw('COALESCE(SUM(GREATEST(balance, 0)), 0) AS total')
            ->value('total');
    }

    private function classifiedAccountIds(string $type): Collection
    {
        return Account::query()
            ->where(function ($account) use ($type) {
                $account->whereHas('group', function ($query) use ($type) {
                    $query->where(function ($group) use ($type) {
                        if ($type === 'cash') {
                            $group->where(
                                'code',
                                AccountGroupHelper::code('cash_in_hand')
                            )
                                ->orWhere('name', 'like', '%cash%');
                        } elseif ($type === 'bank') {
                            $group->whereIn(
                                'code',
                                AccountGroupHelper::codes([
                                    'mobile_bank',
                                    'bank_account',
                                ])
                            )
                                ->orWhere('name', 'like', '%bank%');
                        } else {
                            $group->where(
                                'code',
                                AccountGroupHelper::code('bank_loan')
                            )
                                ->orWhere('name', 'like', '%loan%');
                        }
                    });
                });

                if ($type === 'cash') {
                    $account->orWhere('semantic_code', 'cash_on_hand');
                }
            })
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function monthSeries(Collection $rows, int $months): Collection
    {
        return collect(range($months - 1, 0))
            ->map(function (int $offset) use ($rows) {
                $date = now()->startOfMonth()->subMonths($offset);
                $row = $rows->get($date->year.'-'.$date->month);

                return [
                    'month' => $date->format('M'),
                    'total' => (float) ($row->total ?? 0),
                ];
            });
    }

    private function castAmounts(object $row, array $columns): object
    {
        foreach ($columns as $column) {
            $row->{$column} = (float) $row->{$column};
        }

        return $row;
    }
}
