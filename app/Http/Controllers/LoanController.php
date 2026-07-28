<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CompanySetting;
use App\Services\LoanLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly LoanLedgerService $loans
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-loan', only: ['index', 'show', 'statement']),
            new Middleware('permission:view-loan|can-loan-download', only: ['downloadStatementPdf', 'downloadLoansPdf', 'downloadPaymentsPdf']),
        ];
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:all,active,inactive'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = match ($validated['status'] ?? null) {
            'active' => true,
            'inactive' => false,
            default => null,
        };
        $sortBy = $validated['sort_by'] ?? 'lender_name';
        $sortOrder = $validated['sort_order'] ?? 'asc';
        $sortColumn = match ($sortBy) {
            'amount', 'total_loan' => 'total_loan',
            'paid_amount', 'total_payment' => 'total_payment',
            'due_amount', 'total', 'current_balance' => 'current_balance',
            default => 'accounts.name',
        };

        $accounts = $this->loans
            ->accountsQuery($validated['search'] ?? null, $status)
            ->orderBy($sortColumn, $sortOrder)
            ->orderBy('accounts.id')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        $loans = $accounts->through(function (Account $account) {
            $summary = $this->loans->summary($account);

            return [
                'id' => $account->id,
                'lender_name' => $account->name,
                'total_loan' => $summary['totalLoan'],
                'total_payment' => $summary['totalPayment'],
                'total' => $summary['currentBalance'],
                'account_number' => $account->ac_number,
                'status' => $account->status ? 'active' : 'inactive',
            ];
        });

        return Inertia::render('loans/index', [
            'loans' => $loans,
            'filters' => $request->only([
                'search',
                'status',
                'sort_by',
                'sort_order',
                'per_page',
            ]),
        ]);
    }

    public function show(Account $account)
    {
        $account = $this->loans->loanAccount($account);
        $summary = $this->loans->summary($account);

        return Inertia::render('loans/details', [
            'loanAccount' => $this->loans->accountPayload($account),
            ...$summary,
            'recentLoans' => $this->recentRows($account, 'credit', 5),
            'recentPayments' => $this->recentRows($account, 'debit', 5),
        ]);
    }

    public function statement(Request $request, Account $account)
    {
        $account = $this->loans->loanAccount($account);
        $summary = $this->loans->summary($account);
        [$startDate, $endDate] = $this->dateFilters($request);

        $recentLoans = $this->loans->voucherRows(
            $this->loans->voucherQuery($account, 'credit')
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            'Loan Received'
        );
        $recentPayments = $this->loans->paginatedVoucherRows(
            $this->loans->voucherQuery(
                $account,
                'debit',
                $startDate,
                $endDate
            ),
            10,
            'Loan Payment'
        );

        return Inertia::render('loans/statement', [
            'loanAccount' => $this->loans->accountPayload($account),
            ...$summary,
            'recentLoans' => $recentLoans,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function downloadStatementPdf(Request $request, Account $account)
    {
        $account = $this->loans->loanAccount($account);
        $summary = $this->loans->summary($account);
        [$startDate, $endDate] = $this->dateFilters($request);
        $loanAccount = $this->loans->accountPayload($account);
        $totalLoan = $summary['totalLoan'];
        $totalPayment = $summary['totalPayment'];
        $currentBalance = $summary['currentBalance'];
        $recentLoans = $this->allRows(
            $account,
            'credit',
            'Loan Received',
            $startDate,
            $endDate
        );
        $recentPayments = $this->allRows(
            $account,
            'debit',
            'Loan Payment',
            $startDate,
            $endDate
        );

        return Pdf::loadView(
            'pdf.loan-statement',
            compact(
                'loanAccount',
                'totalLoan',
                'totalPayment',
                'currentBalance',
                'recentLoans',
                'recentPayments'
            )
        )->stream('loan-statement-'.$account->ac_number.'.pdf');
    }

    public function downloadLoansPdf(Account $account)
    {
        $account = $this->loans->loanAccount($account);
        $summary = $this->loans->summary($account);
        $companySetting = CompanySetting::query()->first();
        $loanAccount = $this->loans->accountPayload($account);
        $totalLoan = $summary['totalLoan'];
        $currentBalance = $summary['currentBalance'];
        $recentLoans = $this->allRows(
            $account,
            'credit',
            'Loan Received'
        );

        return Pdf::loadView(
            'pdf.loan-summary',
            compact(
                'companySetting',
                'loanAccount',
                'totalLoan',
                'currentBalance',
                'recentLoans'
            )
        )->stream('loan-summary-'.$account->ac_number.'.pdf');
    }

    public function downloadPaymentsPdf(Request $request, Account $account)
    {
        $account = $this->loans->loanAccount($account);
        $summary = $this->loans->summary($account);
        [$startDate, $endDate] = $this->dateFilters($request);
        $companySetting = CompanySetting::query()->first();
        $loanAccount = $this->loans->accountPayload($account);
        $totalPayment = $summary['totalPayment'];
        $currentBalance = $summary['currentBalance'];
        $recentPayments = $this->allRows(
            $account,
            'debit',
            'Loan Payment',
            $startDate,
            $endDate
        );

        return Pdf::loadView(
            'pdf.loan-payments',
            compact(
                'companySetting',
                'loanAccount',
                'totalPayment',
                'currentBalance',
                'recentPayments'
            )
        )->stream('loan-payments-'.$account->ac_number.'.pdf');
    }

    private function recentRows(
        Account $account,
        string $entrySide,
        int $limit
    ) {
        return $this->loans->voucherRows(
            $this->loans->voucherQuery($account, $entrySide)
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            $entrySide === 'credit' ? 'Loan Received' : 'Loan Payment'
        );
    }

    private function allRows(
        Account $account,
        string $entrySide,
        string $description,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        return $this->loans->voucherRows(
            $this->loans
                ->voucherQuery($account, $entrySide, $startDate, $endDate)
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get(),
            $description
        );
    }

    private function dateFilters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        ];
    }
}
