<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class CustomerLedgerDetailsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-customer', only: ['index']),
            new Middleware('permission:view-customer|can-customer-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request, $customer)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $customerModel = Customer::find($customer);

        $creditTransactions = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('accounts', 'customers.account_id', '=', 'accounts.id')
            ->join('shifts', 'credit_sales.shift_id', '=', 'shifts.id')
            ->leftJoin('transactions', 'credit_sales.transaction_id', '=', 'transactions.id')
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->where('credit_sales.customer_id', $customer)
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'accounts.ac_number',
                'credit_sales.sale_date as date',
                'shifts.name as shift',
                DB::raw('COALESCE(transactions.transaction_id, credit_sales.invoice_no) as transaction_id'),
                DB::raw('0 as debit'),
                'credit_sales.total_amount as credit',
                'credit_sales.total_amount as balance',
                DB::raw('NULL as remarks')
            );

        $debitTransactions = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('customers', 'accounts.id', '=', 'customers.account_id')
            ->join('shifts', 'vouchers.shift_id', '=', 'shifts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->where('customers.id', $customer)
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'accounts.ac_number',
                'vouchers.date as date',
                'shifts.name as shift',
                'vouchers.voucher_no as transaction_id',
                'transactions.amount as debit',
                DB::raw('0 as credit'),
                DB::raw('transactions.amount as balance'),
                'vouchers.remarks'
            );

        $allTransactions = $creditTransactions->union($debitTransactions)
            ->orderBy('customer_name')
            ->orderBy('date')
            ->get();

        $ledgers = $allTransactions->groupBy('customer_id')->map(function($items) use ($customerModel) {
            $runningBalance = 0;
            $transactions = $items->map(function($item) use (&$runningBalance) {
                $runningBalance += $item->credit - $item->debit;
                $item->due = $runningBalance;
                return $item;
            });

            return [
                'customer_name' => $items->first()->customer_name,
                'ac_number' => $items->first()->ac_number,
                'customer_mobile' => $customerModel->mobile ?? 'N/A',
                'customer_address' => $customerModel->address ?? 'N/A',
                'transactions' => $transactions->values()->toArray(),
                'total_debit' => $items->sum('debit'),
                'total_credit' => $items->sum('credit'),
                'total_due' => $runningBalance
            ];
        })->values()->toArray();

        return Inertia::render('CustomerLedgerDetails/Index', [
            'ledgers' => $ledgers,
            'filters' => $request->only(['start_date', 'end_date'])
        ]);
    }

    public function downloadPdf(Request $request, $customer)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');

        $creditTransactions = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->join('accounts', 'customers.account_id', '=', 'accounts.id')
            ->join('shifts', 'credit_sales.shift_id', '=', 'shifts.id')
            ->leftJoin('transactions', 'credit_sales.transaction_id', '=', 'transactions.id')
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->where('credit_sales.customer_id', $customer)
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'accounts.ac_number',
                'credit_sales.sale_date as date',
                'shifts.name as shift',
                DB::raw('COALESCE(transactions.transaction_id, credit_sales.invoice_no) as transaction_id'),
                DB::raw('0 as debit'),
                'credit_sales.total_amount as credit',
                'credit_sales.total_amount as balance',
                DB::raw('NULL as remarks')
            );

        $debitTransactions = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('customers', 'accounts.id', '=', 'customers.account_id')
            ->join('shifts', 'vouchers.shift_id', '=', 'shifts.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('vouchers.voucher_type', 'Receipt')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->where('customers.id', $customer)
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'accounts.ac_number',
                'vouchers.date as date',
                'shifts.name as shift',
                'vouchers.voucher_no as transaction_id',
                'transactions.amount as debit',
                DB::raw('0 as credit'),
                DB::raw('transactions.amount as balance'),
                'vouchers.remarks'
            );

        $allTransactions = $creditTransactions->union($debitTransactions)
            ->orderBy('customer_name')
            ->orderBy('date')
            ->get();

        $ledgers = $allTransactions->groupBy('customer_id')->map(function($items) {
            $runningBalance = 0;
            $transactions = $items->map(function($item) use (&$runningBalance) {
                $runningBalance += $item->credit - $item->debit;
                $item->due = $runningBalance;
                return $item;
            });

            return [
                'customer_name' => $items->first()->customer_name,
                'ac_number' => $items->first()->ac_number,
                'transactions' => $transactions->values()->toArray(),
                'total_debit' => $items->sum('debit'),
                'total_credit' => $items->sum('credit'),
                'total_due' => $runningBalance
            ];
        })->values()->toArray();

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customer-ledger-details', compact('ledgers', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('customer-ledger-details.pdf');
    }
}