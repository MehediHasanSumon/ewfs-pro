<?php

namespace App\Services;

use App\Helpers\AccountGroupHelper;
use App\Helpers\VoucherCategoryHelper;
use App\Helpers\VoucherTransactionTypeHelper;
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

        $topSheet = $this->topSheetData($startDate, $endDate);

        return [
            ...$position,
            'purchase_data' => $purchaseData,
            'sales_data' => $salesData,
            'credit_sales_data' => $creditSalesData,
            'stock_data' => $stockData,
            'admin_expenses' => $adminExpenses,
            'top_sheet_data' => $topSheet,
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

    public function topSheetData(string $startDate, string $endDate): array
    {
        $year = date('Y', strtotime($startDate));
        $prevYear = (int)$year - 1;

        $monthFinanceReceipts = $this->monthlyFinanceReceipts((int) $year);
        $monthGrossProfits = $this->monthlyGrossProfits((int) $year);
        $monthExpenses = $this->monthlyOfficeExpenses((int) $year);
        $monthOwnerPayments = $this->monthlyOwnerPayments((int) $year);

        $priorStatus = $this->priorNetBalance((int) $year);
        $hasStarted = $priorStatus['has_started'];
        $runningNetBalance = $priorStatus['net_balance'];

        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $monthlySheets = [];
        $totalProfit = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $monthName = $monthNames[$m];
            $currentFinanceReceipts = (float) ($monthFinanceReceipts->get($m)?->total_receipts ?? 0.0);
            $monthData = $monthGrossProfits->get($m);
            $grossProfit = (float) ($monthData?->gross_profit ?? 0.0);
            $officeExpense = (float) ($monthExpenses->get($m)?->total_expense ?? 0.0);
            $ownerPayment = (float) ($monthOwnerPayments->get($m)?->total_payment ?? 0.0);

            $hasActivityInMonth = (
                $currentFinanceReceipts > 0
                || abs($grossProfit) > 0.000001
                || $officeExpense > 0
                || $ownerPayment > 0
            );

            if (! $hasStarted) {
                if ($hasActivityInMonth) {
                    $hasStarted = true;
                    $openingBalance = $currentFinanceReceipts;
                    $netBalance = $openingBalance + $grossProfit - $officeExpense - $ownerPayment;
                    $runningNetBalance = $netBalance;
                } else {
                    $openingBalance = 0.00;
                    $netBalance = 0.00;
                }
            } else {
                $openingBalance = $runningNetBalance + $currentFinanceReceipts;
                $netBalance = $openingBalance + $grossProfit - $officeExpense - $ownerPayment;
                $runningNetBalance = $netBalance;
            }

            if ($hasStarted) {
                $monthlyOperationalProfit = $grossProfit - $officeExpense - $ownerPayment;
                $totalProfit += $monthlyOperationalProfit;
            }

            $monthlySheets[] = [
                'month' => $monthName,
                'opening_balance' => $openingBalance,
                'gross_profit' => $grossProfit,
                'office_expense' => $officeExpense,
                'cash_payment_md' => $ownerPayment,
                'net_balance' => $netBalance,
            ];
        }

        $totalNetBalance = $hasStarted ? $runningNetBalance : 0.0;

        $cashHistory = [
            'items' => [
                ['particular' => 'bank', 'qty' => null, 'amount' => 700000.00],
                ['particular' => 'Pay order', 'qty' => null, 'amount' => null],
                ['particular' => 'cash', 'qty' => null, 'amount' => 2646750.00],
                ['particular' => 'Diesel', 'qty' => 20630, 'amount' => 2372450.00],
                ['particular' => 'Octane', 'qty' => 14216, 'amount' => 2061320.00],
                ['particular' => 'LPG', 'qty' => 6500, 'amount' => 405600.00],
            ],
            'subtotal' => 8186120.00,
            'extra_items' => [
                ['particular' => 'Kuddu', 'qty' => null, 'amount' => 997695.00],
                ['particular' => 'Cash', 'qty' => null, 'amount' => 7188425.00],
            ],
        ];

        $capitalBalance = $this->capitalBalance($endDate);
        $loanBalance = $this->loanBalance($endDate);
        $hasVouchers = DB::table('vouchers as v')
            ->join('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->where('v.status', 'posted')
            ->whereIn('vtt.code', ['1072', '1073', '1074', '1075', '1076'])
            ->exists();

        $capitalAmount = $hasVouchers ? $capitalBalance : 12174977.00;
        $loanAmount = $hasVouchers ? $loanBalance : 0.00;
        $totalBalanceAmount = $capitalAmount + $loanAmount;

        $investAmount = $totalBalanceAmount;
        $totalInvestProfit = $investAmount + $totalProfit;
        $totalAmount = $totalInvestProfit;
        $recentDue = 11551984.00;
        $cash = $totalAmount - $recentDue;

        return [
            'close_month_year' => [
                'label' => "Total Balance -- 31-12- {$prevYear}",
                'capital_balance' => $capitalAmount,
                'loan_balance' => $loanAmount,
                'total_balance' => $totalBalanceAmount,
                'amount' => $totalBalanceAmount,
            ],
            'top_sheet' => [
                'title' => 'Top Sheet',
                'subtitle' => 'Month wise balance sheet',
                'months' => $monthlySheets,
                'total_net_balance' => $totalNetBalance,
                'total_profit' => $totalProfit,
            ],
            'cash_history' => $cashHistory,
            'bottom_summary' => [
                'invest_amount' => $investAmount,
                'profit' => $totalProfit,
                'total_invest_profit' => $totalInvestProfit,
                'total_amount' => $totalAmount,
                'recent_due' => $recentDue,
                'cash' => $cash,
                'extra' => 192721.01,
            ],
        ];
    }

    public function capitalBalance(?string $endDate = null): float
    {
        $receiptQuery = DB::table('vouchers as v')
            ->join('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'receipt')
            ->where(function ($q) {
                $q->whereIn('vtt.code', ['1072', '1073'])
                    ->orWhereIn('vtt.name', ['Opening Balance', 'Investment']);
            });

        if ($endDate) {
            $receiptQuery->whereDate('v.voucher_date', '<=', $endDate);
        }

        $receiptAmount = (float) ($receiptQuery->sum('vl.amount') ?? 0);

        $withdrawQuery = DB::table('vouchers as v')
            ->join('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'payment')
            ->where(function ($q) {
                $q->whereIn('vtt.code', ['1074'])
                    ->orWhereIn('vtt.name', ['Capital Withdraw']);
            });

        if ($endDate) {
            $withdrawQuery->whereDate('v.voucher_date', '<=', $endDate);
        }

        $withdrawAmount = (float) ($withdrawQuery->sum('vl.amount') ?? 0);

        return $receiptAmount - $withdrawAmount;
    }

    public function loanBalance(?string $endDate = null): float
    {
        $receivedQuery = DB::table('vouchers as v')
            ->join('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'receipt')
            ->where(function ($q) {
                $q->whereIn('vtt.code', ['1075'])
                    ->orWhereIn('vtt.name', ['Loan Received']);
            });

        if ($endDate) {
            $receivedQuery->whereDate('v.voucher_date', '<=', $endDate);
        }

        $receivedAmount = (float) ($receivedQuery->sum('vl.amount') ?? 0);

        $paymentQuery = DB::table('vouchers as v')
            ->join('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'payment')
            ->where(function ($q) {
                $q->whereIn('vtt.code', ['1076'])
                    ->orWhereIn('vtt.name', ['Loan Payment']);
            });

        if ($endDate) {
            $paymentQuery->whereDate('v.voucher_date', '<=', $endDate);
        }

        $paymentAmount = (float) ($paymentQuery->sum('vl.amount') ?? 0);

        return $receivedAmount - $paymentAmount;
    }

    public function financeOpeningBalance(?string $endDate = null): float
    {
        return $this->capitalBalance($endDate) + $this->loanBalance($endDate);
    }

    public function monthlyFinanceReceipts(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $financeCategoryCode = VoucherCategoryHelper::financeCode();
        $openingBalanceCode = VoucherTransactionTypeHelper::getCode('finance', 'opening_balance');
        $investmentCode = VoucherTransactionTypeHelper::getCode('finance', 'investment');
        $loanReceivedCode = VoucherTransactionTypeHelper::getCode('finance', 'loan_received');

        $qualifyingCodes = [$openingBalanceCode, $investmentCode, $loanReceivedCode];

        return DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'receipt')
            ->whereBetween('v.voucher_date', [$yearStart, $yearEnd])
            ->where(function ($q) use ($qualifyingCodes, $financeCategoryCode) {
                $q->whereIn('vtt.code', $qualifyingCodes)
                    ->orWhere(function ($sub) use ($financeCategoryCode) {
                        $sub->where(function ($c) use ($financeCategoryCode) {
                            $c->where('vc.code', $financeCategoryCode)
                                ->orWhere('vc.name', 'Finance');
                        })
                        ->whereIn('vtt.name', ['Opening Balance', 'Investment', 'Loan Received']);
                    });
            })
            ->groupByRaw('MONTH(v.voucher_date)')
            ->selectRaw('MONTH(v.voucher_date) as month_num, SUM(vl.amount) as total_receipts')
            ->get()
            ->keyBy('month_num');
    }

    public function priorNetBalance(int $year): array
    {
        $yearStart = sprintf('%04d-01-01', $year);

        $financeCategoryCode = VoucherCategoryHelper::financeCode();
        $openingBalanceCode = VoucherTransactionTypeHelper::getCode('finance', 'opening_balance');
        $investmentCode = VoucherTransactionTypeHelper::getCode('finance', 'investment');
        $loanReceivedCode = VoucherTransactionTypeHelper::getCode('finance', 'loan_received');

        $qualifyingCodes = [$openingBalanceCode, $investmentCode, $loanReceivedCode];

        $priorFinanceReceipts = (float) DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'receipt')
            ->whereDate('v.voucher_date', '<', $yearStart)
            ->where(function ($q) use ($qualifyingCodes, $financeCategoryCode) {
                $q->whereIn('vtt.code', $qualifyingCodes)
                    ->orWhere(function ($sub) use ($financeCategoryCode) {
                        $sub->where(function ($c) use ($financeCategoryCode) {
                            $c->where('vc.code', $financeCategoryCode)
                                ->orWhere('vc.name', 'Finance');
                        })
                        ->whereIn('vtt.name', ['Opening Balance', 'Investment', 'Loan Received']);
                    });
            })
            ->sum('vl.amount');

        // Prior Regular Sales GP
        $priorRegularGP = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('s.status', ['posted', 'partially_paid', 'paid'])
            ->whereDate('s.sale_date', '<', $yearStart)
            ->selectRaw('COALESCE(SUM(si.line_total - (si.quantity * si.unit_cost)), 0) as gp')
            ->value('gp');

        // Prior Credit Sales GP
        $priorCreditGP = (float) DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->whereDate('cs.sale_date', '<', $yearStart)
            ->selectRaw('COALESCE(SUM(csi.line_total - (csi.quantity * csi.unit_cost)), 0) as gp')
            ->value('gp');

        // Prior Office Expenses
        $operatingCategoryCode = VoucherCategoryHelper::operatingCode();
        $employeeCategoryCode = VoucherCategoryHelper::employeeCode();
        $salaryCode = VoucherTransactionTypeHelper::getCode('employee', 'monthly_salary');
        $bonusCode = VoucherTransactionTypeHelper::getCode('employee', 'employee_bonus');

        $priorExpenses = (float) DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->whereIn('v.voucher_type', ['payment', 'office_payment'])
            ->whereDate('v.voucher_date', '<', $yearStart)
            ->where(function ($query) use ($operatingCategoryCode, $employeeCategoryCode, $salaryCode, $bonusCode) {
                $query->where(function ($q) use ($operatingCategoryCode) {
                    $q->where(function ($cat) use ($operatingCategoryCode) {
                        $cat->where('vc.code', $operatingCategoryCode)
                            ->orWhere('vc.name', 'Operating');
                    })
                    ->where(function ($vType) {
                        $vType->where('v.voucher_type', 'payment')
                            ->orWhere('v.voucher_type', 'office_payment');
                    });
                })
                ->orWhere(function ($q) use ($employeeCategoryCode, $salaryCode, $bonusCode) {
                    $q->where(function ($cat) use ($employeeCategoryCode) {
                        $cat->where('vc.code', $employeeCategoryCode)
                            ->orWhere('vc.name', 'Employee');
                    })
                    ->where('v.voucher_type', 'payment')
                    ->where(function ($type) use ($salaryCode, $bonusCode) {
                        $type->whereIn('vtt.code', [$salaryCode, $bonusCode])
                            ->orWhereIn('vtt.name', ['Monthly Salary', 'Employee Bonus', 'Salary Payment', 'Bonus']);
                    });
                });
            })
            ->sum('vl.amount');

        // Prior Owner Payments
        $ownerWithdrawalCode = VoucherTransactionTypeHelper::getCode('finance', 'owner_withdrawal');
        $priorOwnerPayments = (float) DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'payment')
            ->whereDate('v.voucher_date', '<', $yearStart)
            ->where(function ($q) use ($financeCategoryCode, $ownerWithdrawalCode) {
                $q->where('vtt.code', $ownerWithdrawalCode)
                    ->orWhere(function ($sub) use ($financeCategoryCode) {
                        $sub->where(function ($c) use ($financeCategoryCode) {
                            $c->where('vc.code', $financeCategoryCode)
                                ->orWhere('vc.name', 'Finance');
                        })
                        ->where('vtt.name', 'Owner Withdrawal');
                    });
            })
            ->sum('vl.amount');

        $hasStarted = (
            $priorFinanceReceipts > 0
            || abs($priorRegularGP) > 0.000001
            || abs($priorCreditGP) > 0.000001
            || $priorExpenses > 0
            || $priorOwnerPayments > 0
        );

        $netBalance = $hasStarted
            ? ($priorFinanceReceipts + $priorRegularGP + $priorCreditGP - $priorExpenses - $priorOwnerPayments)
            : 0.0;

        return [
            'has_started' => $hasStarted,
            'net_balance' => $netBalance,
        ];
    }

    public function monthlyGrossProfits(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        // 1. Regular Sales & Product COGS (Cash, Bank, Mobile Bank, etc.)
        $regularSales = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 's.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('s.status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('s.sale_date', [$yearStart, $yearEnd])
            ->groupByRaw('MONTH(s.sale_date)')
            ->selectRaw('
                MONTH(s.sale_date) as month_num,
                SUM(si.line_total) as total_sales,
                SUM(si.quantity * si.unit_cost) as total_cogs,
                SUM(si.line_total - (si.quantity * si.unit_cost)) as gross_profit
            ')
            ->get()
            ->keyBy('month_num');

        // 2. Credit Sales & Product COGS
        $creditSales = DB::table('credit_sale_items as csi')
            ->join('credit_sale_customers as csc', 'csc.id', '=', 'csi.credit_sale_customer_id')
            ->join('credit_sales as cs', 'cs.id', '=', 'csc.credit_sale_id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'csc.journal_entry_id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('cs.status', ['posted', 'partially_paid', 'paid'])
            ->whereBetween('cs.sale_date', [$yearStart, $yearEnd])
            ->groupByRaw('MONTH(cs.sale_date)')
            ->selectRaw('
                MONTH(cs.sale_date) as month_num,
                SUM(csi.line_total) as total_sales,
                SUM(csi.quantity * csi.unit_cost) as total_cogs,
                SUM(csi.line_total - (csi.quantity * csi.unit_cost)) as gross_profit
            ')
            ->get()
            ->keyBy('month_num');

        $monthly = collect();
        for ($m = 1; $m <= 12; $m++) {
            $regSale = (float) ($regularSales->get($m)?->total_sales ?? 0);
            $regCogs = (float) ($regularSales->get($m)?->total_cogs ?? 0);
            $regProfit = (float) ($regularSales->get($m)?->gross_profit ?? 0);

            $credSale = (float) ($creditSales->get($m)?->total_sales ?? 0);
            $credCogs = (float) ($creditSales->get($m)?->total_cogs ?? 0);
            $credProfit = (float) ($creditSales->get($m)?->gross_profit ?? 0);

            $monthly->put($m, (object) [
                'month_num' => $m,
                'total_sales' => $regSale + $credSale,
                'total_cogs' => $regCogs + $credCogs,
                'gross_profit' => $regProfit + $credProfit,
            ]);
        }

        return $monthly;
    }

    public function monthlyOfficeExpenses(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $operatingCategoryCode = VoucherCategoryHelper::operatingCode();
        $employeeCategoryCode = VoucherCategoryHelper::employeeCode();
        $salaryCode = VoucherTransactionTypeHelper::getCode('employee', 'monthly_salary');
        $bonusCode = VoucherTransactionTypeHelper::getCode('employee', 'employee_bonus');

        return DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->whereIn('v.voucher_type', ['payment', 'office_payment'])
            ->whereBetween('v.voucher_date', [$yearStart, $yearEnd])
            ->where(function ($query) use ($operatingCategoryCode, $employeeCategoryCode, $salaryCode, $bonusCode) {
                // 1. Operating category: ALL payment transaction types
                $query->where(function ($q) use ($operatingCategoryCode) {
                    $q->where(function ($cat) use ($operatingCategoryCode) {
                        $cat->where('vc.code', $operatingCategoryCode)
                            ->orWhere('vc.name', 'Operating');
                    })
                    ->where(function ($vType) {
                        $vType->where('v.voucher_type', 'payment')
                            ->orWhere('v.voucher_type', 'office_payment');
                    });
                })
                // 2. Employee category: ONLY Monthly Salary (1001) and Employee Bonus (1004) payments
                ->orWhere(function ($q) use ($employeeCategoryCode, $salaryCode, $bonusCode) {
                    $q->where(function ($cat) use ($employeeCategoryCode) {
                        $cat->where('vc.code', $employeeCategoryCode)
                            ->orWhere('vc.name', 'Employee');
                    })
                    ->where('v.voucher_type', 'payment')
                    ->where(function ($type) use ($salaryCode, $bonusCode) {
                        $type->whereIn('vtt.code', [$salaryCode, $bonusCode])
                            ->orWhereIn('vtt.name', ['Monthly Salary', 'Employee Bonus', 'Salary Payment', 'Bonus']);
                    });
                });
            })
            ->groupByRaw('MONTH(v.voucher_date)')
            ->selectRaw('MONTH(v.voucher_date) as month_num, SUM(vl.amount) as total_expense')
            ->get()
            ->keyBy('month_num');
    }

    public function monthlyOwnerPayments(int $year): Collection
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $financeCategoryCode = VoucherCategoryHelper::financeCode();
        $ownerWithdrawalCode = VoucherTransactionTypeHelper::getCode('finance', 'owner_withdrawal');

        return DB::table('vouchers as v')
            ->leftJoin('voucher_transaction_types as vtt', 'vtt.id', '=', 'v.voucher_transaction_type_id')
            ->leftJoin('voucher_categories as vc', 'vc.id', '=', DB::raw('COALESCE(v.voucher_category_id, vtt.voucher_category_id)'))
            ->join('voucher_lines as vl', function ($join) {
                $join->on('vl.voucher_id', '=', 'v.id')
                    ->where('vl.entry_side', 'debit');
            })
            ->where('v.status', 'posted')
            ->where('v.voucher_type', 'payment')
            ->whereBetween('v.voucher_date', [$yearStart, $yearEnd])
            ->where(function ($q) use ($financeCategoryCode, $ownerWithdrawalCode) {
                $q->where('vtt.code', $ownerWithdrawalCode)
                    ->orWhere(function ($sub) use ($financeCategoryCode) {
                        $sub->where(function ($c) use ($financeCategoryCode) {
                            $c->where('vc.code', $financeCategoryCode)
                                ->orWhere('vc.name', 'Finance');
                        })
                        ->where('vtt.name', 'Owner Withdrawal');
                    });
            })
            ->groupByRaw('MONTH(v.voucher_date)')
            ->selectRaw('MONTH(v.voucher_date) as month_num, SUM(vl.amount) as total_payment')
            ->get()
            ->keyBy('month_num');
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
