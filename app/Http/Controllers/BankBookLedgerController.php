<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Services\LedgerQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class BankBookLedgerController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LedgerQueryService $ledger
    ) {
    }

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

        $bankAccounts = $this->ledger->bankAccounts();

        $ledgers = [];

        foreach ($bankAccounts as $account) {
            $result = $this->ledger->accountLedger($account, $startDate, $endDate);

            $ledgers[] = [
                'account' => $account,
                'transactions' => $result['transactions'],
                'total_debit' => $result['total_debit'],
                'total_credit' => $result['total_credit'],
                'closing_balance' => $result['closing_balance'],
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

        $bankAccounts = $this->ledger->bankAccounts();

        $ledgers = [];

        foreach ($bankAccounts as $account) {
            $result = $this->ledger->accountLedger($account, $startDate, $endDate);

            $ledgers[] = [
                'account' => $account,
                'transactions' => $result['transactions'],
                'total_debit' => $result['total_debit'],
                'total_credit' => $result['total_credit'],
                'closing_balance' => $result['closing_balance'],
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
            ->whereKey($this->ledger->bankAccounts()->pluck('id'))
            ->firstOrFail();

        $result = $this->ledger->paginatedAccountLedger(
            $account,
            $startDate,
            $endDate,
            max(1, min((int) $perPage, 100))
        );

        return Inertia::render('BankBookLedger/Show', [
            'account' => $account,
            'transactions' => $result['transactions'],
            'total_debit' => $result['total_debit'],
            'total_credit' => $result['total_credit'],
            'closing_balance' => $result['closing_balance'],
            'filters' => $request->only(['start_date', 'end_date', 'per_page']),
        ]);
    }

    public function downloadAccountPdf(Request $request, $ac_number)
    {
        $startDate = $request->start_date ?? date('Y-m-01');
        $endDate = $request->end_date ?? date('Y-m-d');

        $account = Account::with('group')
            ->where('ac_number', $ac_number)
            ->whereKey($this->ledger->bankAccounts()->pluck('id'))
            ->firstOrFail();

        $result = $this->ledger->accountLedger($account, $startDate, $endDate);
        $transactions = $result['transactions'];
        $totalDebit = $result['total_debit'];
        $totalCredit = $result['total_credit'];
        $closingBalance = $result['closing_balance'];

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
