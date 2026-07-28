<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Services\LedgerQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class GeneralLedgerController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LedgerQueryService $ledger
    ) {
    }

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

        $accounts = $this->ledger->activeAccounts();

        if ($accountId) {
            $account = Account::with('group')->findOrFail($accountId);
            $result = $this->ledger->accountLedger($account, $startDate, $endDate);

            return Inertia::render('GeneralLedger/Index', [
                'accounts' => $accounts,
                'selectedAccount' => $account,
                'transactions' => $result['transactions'],
                'currentBalance' => $result['closing_balance'],
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

        $account = Account::with('group')->findOrFail($accountId);
        $transactions = $this->ledger
            ->accountLedger($account, $startDate, $endDate)['transactions'];

        $companySetting = CompanySetting::first();

        $pdf = Pdf::loadView('pdf.general-ledger', compact('account', 'transactions', 'companySetting', 'startDate', 'endDate'));
        return $pdf->stream('general-ledger.pdf');
    }
}
