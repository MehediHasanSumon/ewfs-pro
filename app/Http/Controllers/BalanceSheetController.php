<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Voucher;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\CreditSale;
use Illuminate\Http\Request;
use App\Models\OfficePayment;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class BalanceSheetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addDays(1)->format('Y-m-d'));

        // Calculate Purchase Data by Product with Date Ranges
        $purchaseData = Purchase::join('products', 'purchases.product_id', '=', 'products.id')
            ->whereDate('purchases.purchase_date', '>=', $startDate)
            ->whereDate('purchases.purchase_date', '<=', $endDate)
            ->selectRaw('
                products.product_name as product_name,
                purchases.unit_price as avg_price,
                SUM(purchases.quantity) as total_quantity,
                SUM(purchases.net_total_amount) as total_amount
            ')
            ->groupBy('products.product_name', 'purchases.unit_price')
            ->get();

        // Calculate Sales Data by Product with Date Ranges (Merged Sales + Credit Sales)
        $salesData = DB::table(DB::raw('(
            SELECT 
                sales.product_id,
                sales.sale_date,
                sales.quantity,
                sales.total_amount
            FROM sales
            WHERE DATE(sales.sale_date) >= "' . $startDate . '"
            AND DATE(sales.sale_date) <= "' . $endDate . '"
            UNION ALL
            SELECT 
                credit_sales.product_id,
                credit_sales.sale_date,
                credit_sales.quantity,
                credit_sales.total_amount
            FROM credit_sales
            WHERE DATE(credit_sales.sale_date) >= "' . $startDate . '"
            AND DATE(credit_sales.sale_date) <= "' . $endDate . '"
        ) as all_sales'))
        ->join('products', 'all_sales.product_id', '=', 'products.id')
        ->join('product_rates', function($join) {
            $join->on('all_sales.product_id', '=', 'product_rates.product_id')
                 ->whereColumn('product_rates.effective_date', '<=', 'all_sales.sale_date')
                 ->where('product_rates.status', true)
                 ->whereRaw('product_rates.effective_date = (
                     SELECT MAX(pr2.effective_date)
                     FROM product_rates pr2
                     WHERE pr2.product_id = all_sales.product_id
                     AND pr2.effective_date <= all_sales.sale_date
                     AND pr2.status = true
                 )');
        })
        ->selectRaw('
            products.product_name as product_name,
            product_rates.purchase_price as purchase_price,
            product_rates.sales_price as sale_price,
            product_rates.effective_date as effective_date,
            SUM(all_sales.quantity) as total_quantity,
            SUM(all_sales.total_amount) as total_amount
        ')
        ->groupBy('products.product_name', 'product_rates.purchase_price', 'product_rates.sales_price', 'product_rates.effective_date')
        ->get();

        $creditSalesData = collect([]);

        // Calculate Stock Value
        $stockData = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->join('product_rates', 'stocks.product_id', '=', 'product_rates.product_id')
            ->where('product_rates.status', true)
            ->selectRaw('
                products.product_name as product_name,
                stocks.current_stock as quantity,
                product_rates.purchase_price,
                (stocks.current_stock * product_rates.purchase_price) as stock_value
            ')
            ->get();

        // Calculate General Admin Expenses
        $adminExpenses = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->whereDate('vouchers.date', '>=', $startDate)
            ->whereDate('vouchers.date', '<=', $endDate)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.type', ['payment'])
            ->selectRaw('
                payment_sub_types.name as expense_type,
                SUM(transactions.amount) as total_amount
            ')
            ->groupBy('payment_sub_types.id', 'payment_sub_types.name')
            ->get();

        // Calculate Purchase Due
        $totalPurchases = Purchase::whereDate('purchase_date', '<=', $date)->sum('net_total_amount');
        $totalSupplierPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('suppliers', 'vouchers.to_account_id', '=', 'suppliers.account_id')
            ->where('vouchers.voucher_type', 'Payment')
            ->whereDate('vouchers.date', '<=', $date)
            ->sum('transactions.amount');
        $purchaseDue = max(0, $totalPurchases - $totalSupplierPayments);

        // Calculate Customer Advance
        $totalCreditSales = CreditSale::whereDate('sale_date', '<=', $date)->sum('total_amount');
        $totalCustomerPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('customers', 'vouchers.from_account_id', '=', 'customers.account_id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereDate('vouchers.date', '<=', $date)
            ->sum('transactions.amount');
        $customerAdvance = max(0, $totalCustomerPayments - $totalCreditSales);

        // Calculate Customer Security
        $customerSecurity = Customer::sum('security_deposit');

        // Calculate Bank Loan
        $bankLoanAccounts = Account::where('group_code', '400010002')->pluck('id');
        $totalLoanReceived = Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->whereDate('date', '<=', $date)
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanReceived += Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->whereDate('date', '<=', $date)
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments = Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->whereDate('date', '<=', $date)
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments += Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->whereDate('date', '<=', $date)
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $bankLoan = max(0, $totalLoanReceived - $totalLoanPayments);

        // Calculate Office Cash
        $officeCash = OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'cash')
            ->whereDate('office_payments.date', '<=', $date)
            ->sum('transactions.amount');

        // Calculate Bank Deposit
        $bankDeposit = OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'bank')
            ->orWhereIn('transactions.payment_type', ['bank', 'mobile bank'])
            ->whereDate('office_payments.date', '<=', $date)
            ->sum('transactions.amount');

        // Calculate Customer Due
        $customerDue = max(0, $totalCreditSales - $totalCustomerPayments);

        // Calculate Stock Value
        $stockValue = Stock::join('product_rates', 'stocks.product_id', '=', 'product_rates.product_id')
            ->where('product_rates.status', true)
            ->selectRaw('SUM(stocks.current_stock * product_rates.purchase_price) as total_value')
            ->value('total_value') ?? 0;

        // Calculate Profit
        $totalSalesAmount = $salesData->sum('total_amount') + $creditSalesData->sum('total_amount');
        $totalPurchaseAmount = $purchaseData->sum('total_amount');
        $grossProfit = $totalSalesAmount - $totalPurchaseAmount;
        $totalAdminExpenses = $adminExpenses->sum('total_amount');
        $netProfit = $grossProfit - $totalAdminExpenses;

        $data = [
            'purchase_data' => $purchaseData,
            'sales_data' => $salesData,
            'credit_sales_data' => $creditSalesData,
            'stock_data' => $stockData,
            'admin_expenses' => $adminExpenses,
            'totals' => [
                'total_purchases' => $totalPurchaseAmount,
                'total_sales' => $totalSalesAmount,
                'total_stock_value' => $stockData->sum('stock_value'),
                'total_admin_expenses' => $totalAdminExpenses,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
            ],
        ];

        return inertia('BalanceSheet/Index', [
            'data' => $data,
            'filters' => [
                'date' => $date,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addDays(1)->format('Y-m-d'));

        // Same calculation logic as index method with date filter
        $purchaseData = Purchase::join('products', 'purchases.product_id', '=', 'products.id')
            ->whereDate('purchases.purchase_date', '>=', $startDate)
            ->whereDate('purchases.purchase_date', '<=', $endDate)
            ->selectRaw('
                products.product_name as product_name,
                purchases.unit_price as avg_price,
                SUM(purchases.quantity) as total_quantity,
                SUM(purchases.net_total_amount) as total_amount
            ')
            ->groupBy('products.product_name', 'purchases.unit_price')
            ->get();

        $salesData = Sale::join('products', 'sales.product_id', '=', 'products.id')
            ->join('product_rates', function($join) {
                $join->on('sales.product_id', '=', 'product_rates.product_id')
                     ->whereColumn('product_rates.effective_date', '<=', 'sales.sale_date')
                     ->where('product_rates.status', true)
                     ->whereRaw('product_rates.effective_date = (
                         SELECT MAX(pr2.effective_date)
                         FROM product_rates pr2
                         WHERE pr2.product_id = sales.product_id
                         AND pr2.effective_date <= sales.sale_date
                         AND pr2.status = true
                     )');
            })
            ->whereDate('sales.sale_date', '>=', $startDate)
            ->whereDate('sales.sale_date', '<=', $endDate)
            ->selectRaw('
                products.product_name as product_name,
                product_rates.purchase_price as purchase_price,
                product_rates.sales_price as sale_price,
                product_rates.effective_date as effective_date,
                SUM(sales.quantity) as total_quantity,
                SUM(sales.total_amount) as total_amount
            ')
            ->groupBy('products.product_name', 'product_rates.purchase_price', 'product_rates.sales_price', 'product_rates.effective_date')
            ->get();

        $creditSalesData = CreditSale::join('products', 'credit_sales.product_id', '=', 'products.id')
            ->join('product_rates', function($join) {
                $join->on('credit_sales.product_id', '=', 'product_rates.product_id')
                     ->whereColumn('product_rates.effective_date', '<=', 'credit_sales.sale_date')
                     ->where('product_rates.status', true)
                     ->whereRaw('product_rates.effective_date = (
                         SELECT MAX(pr2.effective_date)
                         FROM product_rates pr2
                         WHERE pr2.product_id = credit_sales.product_id
                         AND pr2.effective_date <= credit_sales.sale_date
                         AND pr2.status = true
                     )');
            })
            ->whereDate('credit_sales.sale_date', '>=', $startDate)
            ->whereDate('credit_sales.sale_date', '<=', $endDate)
            ->selectRaw('
                products.product_name as product_name,
                product_rates.purchase_price as purchase_price,
                product_rates.sales_price as sale_price,
                product_rates.effective_date as effective_date,
                SUM(credit_sales.quantity) as total_quantity,
                SUM(credit_sales.total_amount) as total_amount
            ')
            ->groupBy('products.product_name', 'product_rates.purchase_price', 'product_rates.sales_price', 'product_rates.effective_date')
            ->get();

        $stockData = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->join('product_rates', 'stocks.product_id', '=', 'product_rates.product_id')
            ->where('product_rates.status', true)
            ->selectRaw('
                products.product_name as product_name,
                stocks.current_stock as quantity,
                product_rates.purchase_price,
                (stocks.current_stock * product_rates.purchase_price) as stock_value
            ')
            ->get();

        $adminExpenses = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('payment_sub_types', 'vouchers.payment_sub_type_id', '=', 'payment_sub_types.id')
            ->whereDate('vouchers.date', '>=', $startDate)
            ->whereDate('vouchers.date', '<=', $endDate)
            ->where('vouchers.voucher_type', 'Payment')
            ->whereIn('payment_sub_types.type', ['payment'])
            ->selectRaw('
                payment_sub_types.name as expense_type,
                SUM(transactions.amount) as total_amount
            ')
            ->groupBy('payment_sub_types.id', 'payment_sub_types.name')
            ->get();

        $totalSalesAmount = $salesData->sum('total_amount') + $creditSalesData->sum('total_amount');
        $totalPurchaseAmount = $purchaseData->sum('total_amount');
        $totalAdminExpenses = $adminExpenses->sum('total_amount');

        $data = [
            'purchase_data' => $purchaseData,
            'sales_data' => $salesData,
            'credit_sales_data' => $creditSalesData,
            'stock_data' => $stockData,
            'admin_expenses' => $adminExpenses,
            'totals' => [
                'total_purchases' => $totalPurchaseAmount,
                'total_sales' => $totalSalesAmount,
                'total_admin_expenses' => $totalAdminExpenses,
            ],
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $pdf = PDF::loadView('pdf.balance-sheet', compact('data'));
        return $pdf->stream('balance-sheet-' . $startDate . '-to-' . $endDate . '.pdf');
    }
}
