<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-loan', only: ['index', 'show', 'statement']),
            new Middleware('permission:view-loan|can-loan-download', only: ['downloadStatementPdf', 'downloadLoansPdf', 'downloadPaymentsPdf']),
        ];
    }
    public function index(Request $request): Response
    {
        $query = Account::where('group_code', '400010002')
            ->with('group');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ac_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('status', $status);
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        $sortColumn = match ($sortBy) {
            'lender_name' => 'name',
            'amount' => 'total_amount',
            'due_amount' => 'due_amount',
            'paid_amount' => 'paid_amount',
            default => 'name'
        };

        $query->orderBy($sortColumn, $sortOrder);

        $perPage = $request->get('per_page', 10);
        $accounts = $query->paginate($perPage);

        $loans = $accounts->through(function ($account) {
            $totalLoan = Voucher::where('to_account_id', $account->id)
                ->where('voucher_type', 'Receipt')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount') ?? 0;

            $totalLoan += Voucher::where('from_account_id', $account->id)
                ->where('voucher_type', 'Receipt')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount') ?? 0;

            $totalPayment = Voucher::where('from_account_id', $account->id)
                ->where('voucher_type', 'Payment')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount') ?? 0;

            $totalPayment += Voucher::where('to_account_id', $account->id)
                ->where('voucher_type', 'Payment')
                ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
                ->sum('transactions.amount') ?? 0;

            return [
                'id' => $account->id,
                'lender_name' => $account->name,
                'total_loan' => $totalLoan,
                'total_payment' => $totalPayment,
                'total' => $totalLoan - $totalPayment,
                'account_number' => $account->ac_number,
                'status' => $account->status ? 'active' : 'inactive'
            ];
        });

        return Inertia::render('loans/index', [
            'loans' => $loans,
            'filters' => $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page'])
        ]);
    }

    public function show(Account $account)
    {
        $totalLoan = Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalLoan += Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment = Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment += Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $recentLoans = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->limit(5)
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Received'
                ];
            });

        $recentPayments = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->limit(5)
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Payment'
                ];
            });

        return Inertia::render('loans/details', [
            'loanAccount' => [
                'id' => $account->id,
                'name' => $account->name,
                'ac_number' => $account->ac_number,
                'status' => $account->status,
                'created_at' => $account->created_at->format('Y-m-d H:i:s')
            ],
            'totalLoan' => $totalLoan,
            'totalPayment' => $totalPayment,
            'currentBalance' => $totalLoan - $totalPayment,
            'recentLoans' => $recentLoans,
            'recentPayments' => $recentPayments
        ]);
    }

    public function statement(Request $request, Account $account)
    {
        $totalLoan = Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalLoan += Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment = Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment += Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $recentLoans = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Received'
                ];
            });

        $query = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->select('vouchers.*', 'transactions.amount');

        if ($request->start_date) {
            $query->whereDate('vouchers.date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('vouchers.date', '<=', $request->end_date);
        }

        $recentPayments = $query->orderBy('vouchers.date', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Payment'
                ];
            });

        return Inertia::render('loans/statement', [
            'loanAccount' => [
                'id' => $account->id,
                'name' => $account->name,
                'ac_number' => $account->ac_number,
                'status' => $account->status,
                'created_at' => $account->created_at->format('Y-m-d H:i:s')
            ],
            'totalLoan' => $totalLoan,
            'totalPayment' => $totalPayment,
            'currentBalance' => $totalLoan - $totalPayment,
            'recentLoans' => $recentLoans,
            'recentPayments' => $recentPayments
        ]);
    }

    public function downloadStatementPdf(Request $request, Account $account)
    {
        $totalLoan = Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalLoan += Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment = Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment += Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $recentLoans = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Received'
                ];
            });

        $recentPayments = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Payment'
                ];
            });

        $loanAccount = [
            'name' => $account->name,
            'ac_number' => $account->ac_number
        ];

        $currentBalance = $totalLoan - $totalPayment;

        $pdf = Pdf::loadView('pdf.loan-statement', compact('loanAccount', 'totalLoan', 'totalPayment', 'currentBalance', 'recentLoans', 'recentPayments'));
        
        return $pdf->stream('loan-statement-' . $account->ac_number . '.pdf');
    }

    public function downloadLoansPdf(Account $account)
    {
        $companySetting = CompanySetting::first();
        
        $totalLoan = Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalLoan += Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment = Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment += Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $recentLoans = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->orderBy('vouchers.date', 'desc')
            ->select('vouchers.*', 'transactions.amount')
            ->get()
            ->map(function($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Received'
                ];
            });

        $loanAccount = [
            'name' => $account->name,
            'ac_number' => $account->ac_number
        ];

        $currentBalance = $totalLoan - $totalPayment;

        $pdf = Pdf::loadView('pdf.loan-summary', compact('companySetting', 'loanAccount', 'totalLoan', 'currentBalance', 'recentLoans'));
        
        return $pdf->stream('loan-summary-' . $account->ac_number . '.pdf');
    }

    public function downloadPaymentsPdf(Request $request, Account $account)
    {
        $companySetting = CompanySetting::first();
        
        $totalLoan = Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalLoan += Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Receipt')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment = Voucher::where('from_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $totalPayment += Voucher::where('to_account_id', $account->id)
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->sum('transactions.amount') ?? 0;

        $query = Voucher::where(function($q) use ($account) {
                $q->where('to_account_id', $account->id)
                  ->orWhere('from_account_id', $account->id);
            })
            ->where('voucher_type', 'Payment')
            ->join('transactions', 'vouchers.transaction_id', '=', 'transactions.id')
            ->select('vouchers.*', 'transactions.amount');

        if ($request->start_date) {
            $query->whereDate('vouchers.date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('vouchers.date', '<=', $request->end_date);
        }

        $recentPayments = $query->orderBy('vouchers.date', 'desc')
            ->get()
            ->map(function($voucher) {
                return [
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'amount' => $voucher->amount,
                    'description' => $voucher->description ?? 'Loan Payment'
                ];
            });

        $loanAccount = [
            'name' => $account->name,
            'ac_number' => $account->ac_number
        ];

        $currentBalance = $totalLoan - $totalPayment;

        $pdf = Pdf::loadView('pdf.loan-payments', compact('companySetting', 'loanAccount', 'totalPayment', 'currentBalance', 'recentPayments'));
        
        return $pdf->stream('loan-payments-' . $account->ac_number . '.pdf');
    }
}