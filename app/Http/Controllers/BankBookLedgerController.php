<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class BankBookLedgerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-account', only: ['index', 'show']),
            new Middleware('permission:view-account|can-account-download', only: ['downloadPdf', 'downloadAccountPdf']),
        ];
    }
    public function index(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');

        // Get bank accounts (including mobile bank accounts)
        $bankAccounts = Account::with('group')
            ->where('status', true)
            ->whereIn('group_code', ['100020003', '100020004'])
            ->get();

        $ledgers = [];

        foreach ($bankAccounts as $account) {
            // Get all transactions for this bank account
            $transactions = DB::table('transactions')
                ->where('ac_number', $account->ac_number)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate running balance
            $runningBalance = 0;
            $processedTransactions = $transactions->map(function ($transaction) use (&$runningBalance) {
                if ($transaction->transaction_type === 'Dr') {
                    $runningBalance -= $transaction->amount;
                } else {
                    $runningBalance += $transaction->amount;
                }
                $transaction->balance = $runningBalance;
                return $transaction;
            });

            $ledgers[] = [
                'account' => $account,
                'transactions' => $processedTransactions,
                'total_debit' => $processedTransactions->where('transaction_type', 'Dr')->sum('amount'),
                'total_credit' => $processedTransactions->where('transaction_type', 'Cr')->sum('amount'),
                'closing_balance' => $runningBalance
            ];
        }

        // Calculate summary data
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($ledgers as $ledger) {
            $totalDebit += $ledger['total_debit'];
            $totalCredit += $ledger['total_credit'];
        }
        $summary = [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'net_balance' => $totalCredit - $totalDebit,
        ];

        return Inertia::render('BankBookLedger/Index', [
            'ledgers' => $ledgers,
            'filters' => $request->only(['start_date', 'end_date']),
            'summary' => $summary,
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');

        $bankAccounts = Account::with('group')
            ->where('status', true)
            ->whereIn('group_code', ['100020003', '100020004'])
            ->get();

        $ledgers = [];

        foreach ($bankAccounts as $account) {
            $transactions = DB::table('transactions')
                ->where('ac_number', $account->ac_number)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $runningBalance = 0;
            $processedTransactions = $transactions->map(function ($transaction) use (&$runningBalance) {
                if ($transaction->transaction_type === 'Dr') {
                    $runningBalance -= $transaction->amount;
                } else {
                    $runningBalance += $transaction->amount;
                }
                $transaction->balance = $runningBalance;
                return $transaction;
            });

            $ledgers[] = [
                'account' => $account,
                'transactions' => $processedTransactions,
                'total_debit' => $processedTransactions->where('transaction_type', 'Dr')->sum('amount'),
                'total_credit' => $processedTransactions->where('transaction_type', 'Cr')->sum('amount'),
                'closing_balance' => $runningBalance
            ];
        }

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.bank-book-ledger', compact('ledgers', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('bank-book-ledger.pdf');
    }

    public function show(Request $request, $ac_number)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');
        $perPage = $request->per_page ?? 10;

        $account = Account::with('group')
            ->where('ac_number', $ac_number)
            ->where('status', true)
            ->whereIn('group_code', ['100020003', '100020004'])
            ->firstOrFail();

        $query = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('shifts', 'vouchers.shift_id', '=', 'shifts.id')
            ->where('transactions.ac_number', $account->ac_number)
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->select(
                'transactions.*',
                'vouchers.voucher_no',
                'vouchers.voucher_type',
                'vouchers.date as voucher_date',
                'shifts.name as shift_name'
            )
            ->orderBy('vouchers.date', 'asc')
            ->orderBy('vouchers.created_at', 'asc');

        $transactions = $query->paginate($perPage)->withQueryString();

        // Calculate totals from all records (not just current page)
        $allTransactions = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->where('transactions.ac_number', $account->ac_number)
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->select('transactions.transaction_type', 'transactions.amount')
            ->get();

        $totalDebit = $allTransactions->where('transaction_type', 'Dr')->sum('amount');
        $totalCredit = $allTransactions->where('transaction_type', 'Cr')->sum('amount');

        // Calculate running balance for current page
        $runningBalance = 0;
        $processedTransactions = $transactions->getCollection()->map(function ($transaction) use (&$runningBalance) {
            if ($transaction->transaction_type === 'Dr') {
                $runningBalance -= $transaction->amount;
            } else {
                $runningBalance += $transaction->amount;
            }
            $transaction->balance = $runningBalance;
            return $transaction;
        });

        $transactions->setCollection($processedTransactions);

        return Inertia::render('BankBookLedger/Show', [
            'account' => $account,
            'transactions' => $transactions,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'closing_balance' => $totalCredit - $totalDebit,
            'filters' => $request->only(['start_date', 'end_date', 'per_page']),
        ]);
    }

    public function downloadAccountPdf(Request $request, $ac_number)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $account = Account::with('group')
            ->where('ac_number', $ac_number)
            ->where('status', true)
            ->whereIn('group_code', ['100020003', '100020004'])
            ->firstOrFail();

        $transactions = DB::table('vouchers')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->join('shifts', 'vouchers.shift_id', '=', 'shifts.id')
            ->where('transactions.ac_number', $account->ac_number)
            ->whereBetween('vouchers.date', [$startDate, $endDate])
            ->select(
                'transactions.*',
                'vouchers.voucher_no',
                'vouchers.voucher_type',
                'vouchers.date as voucher_date',
                'shifts.name as shift_name'
            )
            ->orderBy('vouchers.date', 'asc')
            ->orderBy('vouchers.created_at', 'asc')
            ->get();

        $totalDebit = $transactions->where('transaction_type', 'Dr')->sum('amount');
        $totalCredit = $transactions->where('transaction_type', 'Cr')->sum('amount');
        $closingBalance = $totalCredit - $totalDebit;

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.bank-book-ledger-account', compact(
            'account',
            'transactions',
            'totalDebit',
            'totalCredit',
            'closingBalance',
            'companySetting',
            'startDate',
            'endDate'
        ));
        
        return $pdf->stream('bank-book-ledger-' . $account->name . '.pdf');
    }
}