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

class CustomerLedgerSummaryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-customer', only: ['index']),
            new Middleware('permission:view-customer|can-customer-download', only: ['downloadPdf']),
        ];
    }
    public function index(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $customerId = $request->customer_id;

        $creditQuery = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.mobile as customer_mobile',
                'customers.address as customer_address',
                DB::raw('SUM(credit_sales.total_amount) as credit')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.mobile', 'customers.address');

        if ($customerId) {
            $creditQuery->where('credit_sales.customer_id', $customerId);
        }

        $debitQuery = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('customers', 'accounts.id', '=', 'customers.account_id')
            ->join('voucher_categories', 'vouchers.voucher_category_id', '=', 'voucher_categories.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('voucher_categories.name', 'Customer')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->select(
                'customers.id as customer_id',
                DB::raw('SUM(transactions.amount) as debit')
            )
            ->groupBy('customers.id');

        if ($customerId) {
            $debitQuery->where('customers.id', $customerId);
        }

        $credits = $creditQuery->get()->keyBy('customer_id');
        $debits = $debitQuery->get()->keyBy('customer_id');

        $customerIds = $credits->keys()->merge($debits->keys())->unique();

        $ledgers = $customerIds->map(function($id) use ($credits, $debits) {
            $credit = $credits->get($id);
            $debit = $debits->get($id);

            return [
                'customer_id' => $id,
                'customer_name' => $credit->customer_name ?? Customer::find($id)->name,
                'customer_mobile' => $credit->customer_mobile ?? Customer::find($id)->mobile,
                'customer_address' => $credit->customer_address ?? Customer::find($id)->address,
                'debit' => $debit->debit ?? 0,
                'credit' => $credit->credit ?? 0,
                'due' => ($credit->credit ?? 0) - ($debit->debit ?? 0)
            ];
        })->sortBy('customer_name')->values();

        return Inertia::render('CustomerLedgerSummary/Index', [
            'ledgers' => $ledgers,
            'customers' => Customer::select('id', 'name')->get(),
            'filters' => $request->only(['customer_id', 'start_date', 'end_date'])
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $customerId = $request->customer_id;

        $creditQuery = DB::table('credit_sales')
            ->join('customers', 'credit_sales.customer_id', '=', 'customers.id')
            ->whereBetween('credit_sales.sale_date', [$startDate, $endDate])
            ->select(
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.mobile as customer_mobile',
                'customers.address as customer_address',
                DB::raw('SUM(credit_sales.total_amount) as credit')
            )
            ->groupBy('customers.id', 'customers.name', 'customers.mobile', 'customers.address');

        if ($customerId) {
            $creditQuery->where('credit_sales.customer_id', $customerId);
        }

        $debitQuery = DB::table('vouchers')
            ->join('accounts', 'vouchers.from_account_id', '=', 'accounts.id')
            ->join('customers', 'accounts.id', '=', 'customers.account_id')
            ->join('voucher_categories', 'vouchers.voucher_category_id', '=', 'voucher_categories.id')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('voucher_categories.name', 'Customer')
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->select(
                'customers.id as customer_id',
                DB::raw('SUM(transactions.amount) as debit')
            )
            ->groupBy('customers.id');

        if ($customerId) {
            $debitQuery->where('customers.id', $customerId);
        }

        $credits = $creditQuery->get()->keyBy('customer_id');
        $debits = $debitQuery->get()->keyBy('customer_id');

        $customerIds = $credits->keys()->merge($debits->keys())->unique();

        $ledgers = $customerIds->map(function($id) use ($credits, $debits) {
            $credit = $credits->get($id);
            $debit = $debits->get($id);

            return (object)[
                'customer_id' => $id,
                'customer_name' => $credit->customer_name ?? Customer::find($id)->name,
                'customer_mobile' => $credit->customer_mobile ?? Customer::find($id)->mobile,
                'customer_address' => $credit->customer_address ?? Customer::find($id)->address,
                'debit' => $debit->debit ?? 0,
                'credit' => $credit->credit ?? 0,
                'due' => ($credit->credit ?? 0) - ($debit->debit ?? 0)
            ];
        })->sortBy('customer_name')->values();

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.customer-ledger-summary', compact('ledgers', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('customer-ledger-summary.pdf');
    }
}