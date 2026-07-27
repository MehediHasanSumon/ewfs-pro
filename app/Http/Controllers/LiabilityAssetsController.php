<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Voucher;
use App\Models\Account;
use App\Models\CompanySetting;
use App\Models\CreditSale;
use App\Models\Customer;
use App\Models\OfficePayment;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class LiabilityAssetsController extends Controller implements HasMiddleware
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
        // Calculate Purchase Due (total purchases - total supplier payments)
        $totalPurchases = Purchase::sum('net_total_amount');

        // Calculate total payments to suppliers through vouchers
        $totalSupplierPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('suppliers', 'vouchers.to_account_id', '=', 'suppliers.account_id')
            ->where('vouchers.voucher_type', 'Payment')
            ->sum('transactions.amount');

        $purchaseDue = $totalPurchases - $totalSupplierPayments;
        $purchaseDue = $purchaseDue > 0 ? $purchaseDue : 0;

        // Calculate Customer Advance (total customer payments - total credit sales)
        $totalCreditSales = CreditSale::sum('total_amount');
        $totalCustomerPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('customers', 'vouchers.from_account_id', '=', 'customers.account_id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->sum('transactions.amount');
        $customerAdvance = $totalCustomerPayments - $totalCreditSales;
        $customerAdvance = $customerAdvance > 0 ? $customerAdvance : 0;

        // Calculate Customer Security
        $customerSecurity = Customer::sum('security_deposit');

        // Calculate Bank Loan (total loan received - total loan payments)
        $bankLoanAccounts = Account::where('group_code', '400010002')->pluck('id');
        $totalLoanReceived = Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanReceived += Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments = Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments += Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $bankLoan = $totalLoanReceived - $totalLoanPayments;
        $bankLoan = $bankLoan > 0 ? $bankLoan : 0;

        $liabilities = collect([
            [
                'name' => 'Purchase Due',
                'group_name' => 'Accounts Payable',
                'balance' => $purchaseDue,
                'type' => 'Liability'
            ],
            [
                'name' => 'Customer Advance',
                'group_name' => 'Current Liabilities',
                'balance' => $customerAdvance,
                'type' => 'Liability'
            ],
            [
                'name' => 'Customer Security',
                'group_name' => 'Current Liabilities',
                'balance' => $customerSecurity,
                'type' => 'Liability'
            ],
            [
                'name' => 'Bank Loan',
                'group_name' => 'Long Term Liabilities',
                'balance' => $bankLoan,
                'type' => 'Liability'
            ]
        ]);

        $assets = collect([]);

        // Calculate In Stock Product (current_stock * purchase_price)
        $inStockValue = Stock::join('product_rates', 'stocks.product_id', '=', 'product_rates.product_id')
            ->where('product_rates.status', true)
            ->selectRaw('SUM(stocks.current_stock * product_rates.purchase_price) as total_value')
            ->value('total_value') ?? 0;

        // Calculate Customer Due (total credit sales - total customer payments)
        $totalCreditSales = CreditSale::sum('total_amount');
        $totalCustomerPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('customers', 'vouchers.from_account_id', '=', 'customers.account_id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->sum('transactions.amount');
        $customerDue = $totalCreditSales - $totalCustomerPayments;
        $customerDue = $customerDue > 0 ? $customerDue : 0;

        // Calculate Bank Deposit from office_payments with bank and mobile bank types
        $bankDeposit = OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'bank')
            ->orWhereIn('transactions.payment_type', ['bank', 'mobile bank'])
            ->sum('transactions.amount');

        // Calculate Office Cash from office_payments with cash type only
        $officeCash = OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'cash')
            ->sum('transactions.amount');

        $assets = collect([
            [
                'name' => 'In Stock Product',
                'group_name' => 'Current Assets',
                'balance' => $inStockValue,
                'type' => 'Asset'
            ],
            [
                'name' => 'Customer Due',
                'group_name' => 'Account Receivable',
                'balance' => $customerDue,
                'type' => 'Asset'
            ],
            [
                'name' => 'Bank Deposit',
                'group_name' => 'Current Assets',
                'balance' => $bankDeposit,
                'type' => 'Asset'
            ],
            [
                'name' => 'Office Cash',
                'group_name' => 'Current Assets',
                'balance' => $officeCash,
                'type' => 'Asset'
            ]
        ]);

        return Inertia::render('LiabilityAssets/Index', [
            'liabilities' => $liabilities,
            'assets' => $assets,
            'totalLiabilities' => $liabilities->sum('balance'),
            'totalAssets' => $assets->sum('balance')
        ]);
    }

    public function downloadPdf(Request $request)
    {
        // Calculate liability amounts from voucher transactions
        $totalPurchases = Purchase::sum('net_total_amount');
        $totalSupplierPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('suppliers', 'vouchers.to_account_id', '=', 'suppliers.account_id')
            ->where('vouchers.voucher_type', 'Payment')
            ->sum('transactions.amount');
        $purchaseDue = $totalPurchases - $totalSupplierPayments;
        $purchaseDue = $purchaseDue > 0 ? $purchaseDue : 0;

        // Calculate Customer Security
        $customerSecurity = Customer::sum('security_deposit');

        // Calculate Bank Loan
        $bankLoanAccounts = Account::where('group_code', '400010002')->pluck('id');
        $totalLoanReceived = Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanReceived += Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments = Voucher::whereIn('from_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $totalLoanPayments += Voucher::whereIn('to_account_id', $bankLoanAccounts)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount');
        $bankLoan = $totalLoanReceived - $totalLoanPayments;
        $bankLoan = $bankLoan > 0 ? $bankLoan : 0;

        $liabilities = collect([
            [
                'name' => 'Purchase Due',
                'group_name' => 'Accounts Payable',
                'balance' => $purchaseDue
            ],
            [
                'name' => 'Customer Security',
                'group_name' => 'Current Liabilities',
                'balance' => $customerSecurity
            ],
            [
                'name' => 'Bank Loan',
                'group_name' => 'Long Term Liabilities',
                'balance' => $bankLoan
            ]
        ]);

        // Get asset data
        $inStockValue = \App\Models\Stock::join('product_rates', 'stocks.product_id', '=', 'product_rates.product_id')
            ->where('product_rates.status', true)
            ->selectRaw('SUM(stocks.current_stock * product_rates.purchase_price) as total_value')
            ->value('total_value') ?? 0;

        $totalCreditSales = \App\Models\CreditSale::sum('total_amount');
        $totalCustomerPayments = Voucher::join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('customers', 'vouchers.from_account_id', '=', 'customers.account_id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->sum('transactions.amount');
        $customerDue = $totalCreditSales - $totalCustomerPayments;
        $customerDue = $customerDue > 0 ? $customerDue : 0;

        $bankDeposit = \App\Models\OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'bank')
            ->orWhereIn('transactions.payment_type', ['bank', 'mobile bank'])
            ->sum('transactions.amount');

        // Calculate Office Cash for PDF from office_payments with cash type only
        $officeCash = \App\Models\OfficePayment::join('transactions', 'office_payments.transaction_id', '=', 'transactions.id')
            ->where('office_payments.type', 'cash')
            ->sum('transactions.amount');

        $assets = collect([
            [
                'name' => 'In Stock Product',
                'group_name' => 'Current Assets',
                'balance' => $inStockValue
            ],
            [
                'name' => 'Customer Due',
                'group_name' => 'Account Receivable',
                'balance' => $customerDue
            ],
            [
                'name' => 'Bank Deposit',
                'group_name' => 'Current Assets',
                'balance' => $bankDeposit
            ],
            [
                'name' => 'Office Cash',
                'group_name' => 'Current Assets',
                'balance' => $officeCash
            ]
        ]);

        $companySetting = CompanySetting::first();
        $totalLiabilities = $liabilities->sum('balance');
        $totalAssets = $assets->sum('balance');

        $pdf = Pdf::loadView('pdf.liability-assets', compact('liabilities', 'assets', 'totalLiabilities', 'totalAssets', 'companySetting'));
        return $pdf->stream('liability-assets.pdf');
    }
}
