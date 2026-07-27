<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class GeneralLedgerController extends Controller implements HasMiddleware
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
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $accountId = $request->account_id;

        $accounts = Account::with('group')->where('status', true)->get();

        if ($accountId) {
            $account = Account::with('group')->find($accountId);
            
            // Get all transactions for this account
            $transactions = DB::table('transactions')
                ->where('ac_number', $account->ac_number)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate running balance
            $runningBalance = 0;
            $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
                if ($transaction->transaction_type === 'Dr') {
                    $runningBalance -= $transaction->amount;
                } else {
                    $runningBalance += $transaction->amount;
                }
                $transaction->balance = $runningBalance;
                return $transaction;
            });

            return Inertia::render('GeneralLedger/Index', [
                'accounts' => $accounts,
                'selectedAccount' => $account,
                'transactions' => $transactions,
                'currentBalance' => $runningBalance,
                'filters' => $request->only(['account_id', 'start_date', 'end_date'])
            ]);
        }

        return Inertia::render('GeneralLedger/Index', [
            'accounts' => $accounts,
            'selectedAccount' => null,
            'transactions' => collect([]),
            'currentBalance' => 0,
            'filters' => $request->only(['account_id', 'start_date', 'end_date'])
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');
        $accountId = $request->account_id;

        if (!$accountId) {
            return redirect()->back()->with('error', 'Please select an account.');
        }

        $account = Account::with('group')->find($accountId);
        
        $transactions = DB::table('transactions')
            ->where('ac_number', $account->ac_number)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $runningBalance = 0;
        $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
            if ($transaction->transaction_type === 'Dr') {
                $runningBalance -= $transaction->amount;
            } else {
                $runningBalance += $transaction->amount;
            }
            $transaction->balance = $runningBalance;
            return $transaction;
        });

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.general-ledger', compact('account', 'transactions', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('general-ledger.pdf');
    }
}